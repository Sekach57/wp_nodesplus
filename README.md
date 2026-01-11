# NodesPlus Site

This repository tracks the custom WordPress code and local dev setup for NodesPlus.

Tracked code:
- Custom theme: `wordpress/html/wp-content/themes/new_incrypted/`
- Custom plugin: `wordpress/html/wp-content/plugins/incrypted-site/`
- Telegram bot service: `telegram-bot/`
- Local dev compose: `docker-compose.yml`

## Local development (WSL)
1) From WSL:
   ```bash
   cd /home/tauren/projects/NodesPlus/Site
   ```
2) Create `.env` in the project root with your local DB values:
   ```env
   DB_NAME=
   DB_USER=
   DB_PASSWORD=
   DB_ROOT_PASSWORD=
   ```
3) Start services:
   ```bash
   docker compose up -d
   ```
4) Open http://localhost:8080

## Environment config
- Copy `.env.example` to `.env` and fill values for your environment.
- Add matching `define()` calls in `wordpress/html/wp-config.php` using `getenv()` (do not commit `wp-config.php`).

## Telegram bot (local)
1) Configure bot env:
   ```bash
   cd telegram-bot
   cp .env.example .env
   ```
2) Run:
   ```bash
   python bot.py
   ```

> Note: Do not commit `.env` or other secrets.
