# NodesPlus Telegram Bot (Stub)

Minimal webhook-based Telegram Bot API handler for NodesPlus.

## Setup

1) Copy env file and set your token:

```bash
cp .env.example .env
```

2) Run the server:

```bash
python3 bot.py
```

The server listens on `http://0.0.0.0:8081/webhook` by default.

## Local dev without webhook

If you don't have a public URL for webhooks, enable polling:

```
USE_POLLING=1
```

## WordPress linking

Set these in `.env` so the bot can link `/start <token>` to a WP user:

```
WP_BASE_URL=https://your-wordpress-site
BOT_SECRET=your_shared_secret
```

## Bot commands

- `/nodes` - Fetch active nodes and due dates from the site.

## Set webhook (curl)

Replace `YOUR_BOT_TOKEN` and `YOUR_PUBLIC_URL`.

```bash
curl -X POST "https://api.telegram.org/botYOUR_BOT_TOKEN/setWebhook" \
  -H "Content-Type: application/json" \
  -d '{"url":"YOUR_PUBLIC_URL/webhook"}'
```

## Data storage

Users are stored in a local JSON file at `./data/users.json`.

## Notes

- Only handles `/start` and `/start <payload>`.
- Replies: "✅ Telegram connected. You can return to the website."
- No WordPress integration in this step.
