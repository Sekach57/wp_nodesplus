#!/usr/bin/env python3
import json
import os
import sys
import time
from datetime import datetime, timezone
from http.server import BaseHTTPRequestHandler, HTTPServer
from urllib.parse import urlencode
from urllib.request import Request, urlopen
from urllib.error import URLError, HTTPError


def load_env(path):
    if not os.path.exists(path):
        return
    with open(path, "r", encoding="utf-8") as f:
        for line in f:
            line = line.strip()
            if not line or line.startswith("#") or "=" not in line:
                continue
            key, value = line.split("=", 1)
            os.environ.setdefault(key.strip(), value.strip())


def ensure_dir(file_path):
    directory = os.path.dirname(os.path.abspath(file_path))
    if directory and not os.path.exists(directory):
        os.makedirs(directory, exist_ok=True)


def read_json(file_path):
    if not os.path.exists(file_path):
        return []
    try:
        with open(file_path, "r", encoding="utf-8") as f:
            return json.load(f)
    except (json.JSONDecodeError, OSError):
        return []


def write_json(file_path, data):
    ensure_dir(file_path)
    with open(file_path, "w", encoding="utf-8") as f:
        json.dump(data, f, indent=2)


def upsert_user(file_path, record):
    data = read_json(file_path)
    updated = False
    for i, item in enumerate(data):
        if item.get("telegram_chat_id") == record.get("telegram_chat_id"):
            data[i] = record
            updated = True
            break
    if not updated:
        data.append(record)
    write_json(file_path, data)


def send_message(bot_token, chat_id, text):
    url = "https://api.telegram.org/bot{0}/sendMessage".format(bot_token)
    payload = json.dumps({"chat_id": chat_id, "text": text}).encode("utf-8")
    req = Request(url, data=payload, headers={"Content-Type": "application/json"})
    try:
        with urlopen(req, timeout=10) as resp:
            resp.read()
    except HTTPError as exc:
        try:
            body = exc.read().decode("utf-8")
        except Exception:
            body = str(exc)
        print("Telegram send error:", body, file=sys.stderr)
        return False
    except URLError as exc:
        print("Telegram send error:", str(exc), file=sys.stderr)
        return False
    return True


def link_with_wp(token, chat_id, user_id, username, first_name):
    if not WP_BASE_URL or not BOT_SECRET:
        return False, "Missing WP_BASE_URL or BOT_SECRET"

    url = WP_BASE_URL.rstrip("/") + "/wp-json/nodesplus/v1/telegram/link"
    payload = json.dumps({
        "token": token,
        "telegram_chat_id": chat_id,
        "telegram_user_id": user_id,
        "username": username,
        "first_name": first_name,
    }).encode("utf-8")
    req = Request(
        url,
        data=payload,
        headers={
            "Content-Type": "application/json",
            "X-NP-BOT-SECRET": BOT_SECRET,
        },
    )
    try:
        with urlopen(req, timeout=10) as resp:
            body = resp.read().decode("utf-8")
            if 200 <= resp.status < 300:
                return True, body
            return False, body
    except (HTTPError, URLError) as exc:
        return False, str(exc)


def fetch_nodes_for_chat(chat_id, status=None):
    if not WP_BASE_URL or not BOT_SECRET:
        return False, "Missing WP_BASE_URL or BOT_SECRET"

    url = WP_BASE_URL.rstrip("/") + "/wp-json/nodesplus/v1/telegram/nodes?chat_id={0}".format(chat_id)
    if status:
        url += "&status={0}".format(status)
    req = Request(
        url,
        headers={
            "Content-Type": "application/json",
            "X-NP-BOT-SECRET": BOT_SECRET,
        },
    )
    try:
        with urlopen(req, timeout=10) as resp:
            body = resp.read().decode("utf-8")
            if 200 <= resp.status < 300:
                return True, body
            return False, body
    except (HTTPError, URLError) as exc:
        return False, str(exc)


class WebhookHandler(BaseHTTPRequestHandler):
    def do_POST(self):
        if self.path != WEBHOOK_PATH:
            self.send_response(404)
            self.end_headers()
            return

        content_length = int(self.headers.get("Content-Length", "0"))
        raw_body = self.rfile.read(content_length) if content_length > 0 else b""
        try:
            update = json.loads(raw_body.decode("utf-8"))
        except json.JSONDecodeError:
            self.send_response(400)
            self.end_headers()
            return

        handle_update(update)

        self.send_response(200)
        self.end_headers()
        self.wfile.write(b"ok")

    def log_message(self, format, *args):
        return


def parse_start_payload(text):
    if not text or not text.startswith("/start"):
        return None
    parts = text.split(" ", 1)
    if len(parts) == 2:
        return parts[1].strip() or None
    return None


def handle_update(update):
    callback = update.get("callback_query")
    if callback:
        return

    message = update.get("message") or update.get("edited_message")
    if not message:
        return

    text = message.get("text", "")
    chat = message.get("chat", {})
    chat_id = chat.get("id")
    if text:
        print("Incoming:", text, "chat_id=", chat_id, file=sys.stderr)
    if text.startswith("/nodes"):
        if chat_id is None:
            return
        ok, body = fetch_nodes_for_chat(chat_id, "active")
        if not ok:
            print("WP nodes failed:", body, file=sys.stderr)
            send_message(BOT_TOKEN, chat_id, "❌ Unable to load nodes. Please try again.")
            return
        try:
            data = json.loads(body.lstrip("\ufeff"))
        except json.JSONDecodeError:
            send_message(BOT_TOKEN, chat_id, "❌ Unexpected response from server.")
            return

        nodes = data.get("nodes", [])
        if not nodes:
            ok, body = fetch_nodes_for_chat(chat_id, "overdue")
            if not ok:
                print("WP nodes failed:", body, file=sys.stderr)
                send_message(BOT_TOKEN, chat_id, "❌ Unable to load nodes. Please try again.")
                return
            try:
                data = json.loads(body.lstrip("\ufeff"))
            except json.JSONDecodeError:
                send_message(BOT_TOKEN, chat_id, "❌ Unexpected response from server.")
                return
            nodes = data.get("nodes", [])
            if not nodes:
                if not send_message(BOT_TOKEN, chat_id, "No active or overdue nodes found."):
                    print("Telegram send failed for /nodes empty response", file=sys.stderr)
                return
            header = "⚠️ Overdue nodes"
        else:
            header = "⏰ Active nodes"

        lines = [header]
        for node in nodes:
            node_type = node.get("node_type") or node.get("node_id") or "Node"
            due_date = node.get("due_date") or "unknown"
            status = node.get("derived_status") or "unknown"
            if status == "overdue":
                lines.append("- {0} — OVERDUE since {1}".format(node_type, due_date[:10]))
            else:
                lines.append("- {0} — due {1}".format(node_type, due_date[:10]))

        if not send_message(BOT_TOKEN, chat_id, "\n".join(lines)):
            print("Telegram send failed for /nodes response", file=sys.stderr)
        return

    if text.startswith("/overdue"):
        if chat_id is None:
            return
        ok, body = fetch_nodes_for_chat(chat_id, "overdue")
        if not ok:
            print("WP nodes failed:", body, file=sys.stderr)
            send_message(BOT_TOKEN, chat_id, "❌ Unable to load nodes. Please try again.")
            return
        try:
            data = json.loads(body.lstrip("\ufeff"))
        except json.JSONDecodeError:
            send_message(BOT_TOKEN, chat_id, "❌ Unexpected response from server.")
            return

        nodes = data.get("nodes", [])
        if not nodes:
            if not send_message(BOT_TOKEN, chat_id, "No overdue nodes found."):
                print("Telegram send failed for /overdue empty response", file=sys.stderr)
            return

        lines = ["⚠️ Overdue nodes"]
        for node in nodes:
            node_type = node.get("node_type") or node.get("node_id") or "Node"
            due_date = node.get("due_date") or "unknown"
            lines.append("- {0} — OVERDUE since {1}".format(node_type, due_date[:10]))

        if not send_message(BOT_TOKEN, chat_id, "\n".join(lines)):
            print("Telegram send failed for /overdue response", file=sys.stderr)
        return

    if not text.startswith("/start"):
        return

    user = message.get("from", {})
    if chat_id is None:
        return

    payload = parse_start_payload(text)
    record = {
        "telegram_chat_id": chat_id,
        "telegram_user_id": user.get("id"),
        "username": user.get("username"),
        "first_name": user.get("first_name"),
        "started_at": datetime.now(timezone.utc).isoformat(),
        "start_payload": payload,
    }
    upsert_user(DATA_FILE, record)

    if payload:
        ok, info = link_with_wp(payload, chat_id, user.get("id"), user.get("username"), user.get("first_name"))
        if not ok:
            print("WP link failed:", info, file=sys.stderr)
            if not send_message(BOT_TOKEN, chat_id, "❌ Unable to link Telegram. Please try again."):
                print("Telegram send failed for link error", file=sys.stderr)
            return

    if not send_message(BOT_TOKEN, chat_id, "✅ Telegram connected. You can return to the website."):
        print("Telegram send failed for start response", file=sys.stderr)


def poll_updates():
    offset = None
    while True:
        try:
            params = {"timeout": 20}
            if offset is not None:
                params["offset"] = offset
            url = "https://api.telegram.org/bot{0}/getUpdates?{1}".format(BOT_TOKEN, urlencode(params))
            req = Request(url)
            with urlopen(req, timeout=25) as resp:
                data = json.loads(resp.read().decode("utf-8"))
            if not data.get("ok"):
                time.sleep(2)
                continue
            for update in data.get("result", []):
                handle_update(update)
                offset = update.get("update_id", 0) + 1
        except Exception as exc:
            print("Polling error:", exc, file=sys.stderr)
            time.sleep(3)


def main():
    if not BOT_TOKEN:
        print("Missing BOT_TOKEN. Set it in .env or environment.")
        sys.exit(1)

    if USE_POLLING:
        print("Polling Telegram updates...")
        poll_updates()
        return

    server = HTTPServer(("0.0.0.0", PORT), WebhookHandler)
    print("Listening on http://0.0.0.0:{0}{1}".format(PORT, WEBHOOK_PATH))
    server.serve_forever()


if __name__ == "__main__":
    load_env(os.path.join(os.path.dirname(__file__), ".env"))
    BOT_TOKEN = os.environ.get("BOT_TOKEN", "").strip()
    WEBHOOK_PATH = os.environ.get("WEBHOOK_PATH", "/webhook").strip() or "/webhook"
    PORT = int(os.environ.get("PORT", "8081"))
    DATA_FILE = os.environ.get("DATA_FILE", "./data/users.json")
    WP_BASE_URL = os.environ.get("WP_BASE_URL", "").strip()
    BOT_SECRET = os.environ.get("BOT_SECRET", "").strip()
    USE_POLLING = os.environ.get("USE_POLLING", "").strip() == "1"

    main()
