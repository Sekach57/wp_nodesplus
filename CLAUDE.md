# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

NodesPlus (Incrypted) is a WordPress/WooCommerce e-commerce platform for node/blockchain service management. Users purchase node products and manage renewals through a custom dashboard with Discord and Telegram integrations.

## Repository

- **GitHub**: git@github.com:Sekach57/wp_nodesplus.git
- **Branch**: main
- **Tracked code only** (WordPress core is ignored):
  - Custom theme: `wordpress/html/wp-content/themes/incrypted/`
  - Custom plugin: `wordpress/html/wp-content/plugins/incrypted-site/`
  - Telegram bot: `telegram-bot/`

## Development Commands

### Local Environment
```bash
cp .env.example .env  # Fill in DB credentials
docker compose up -d  # WordPress at http://localhost:8080
```

### Theme Development
```bash
cd wordpress/html/wp-content/themes/incrypted
npm install
npm run watch          # Watch and compile SCSS
npm run compile:css    # Compile SCSS to CSS
npm run lint:scss      # Lint SCSS files
npm run lint:js        # Lint JavaScript files
```

### Telegram Bot
```bash
cd telegram-bot
cp .env.example .env   # Set bot token
USE_POLLING=1 python3 bot.py  # Local dev without webhook
```

## Deployment

```
Local (WSL) → git push → Staging → Production
```

### Server (VPS 85.17.40.131)
- **Staging**: staging.nodesplus.io → `/containers/wordpress-staging/html`
- **Production**: nodesplus.io → `/containers/wordpress/html`
- **Repo**: `/opt/nodesplus-deploy/repo`

### Deploy to Staging
```bash
# On server
cd /opt/nodesplus-deploy/repo
git pull origin main
cp -r wordpress/html/wp-content/themes/incrypted /containers/wordpress-staging/html/wp-content/themes/
cp -r wordpress/html/wp-content/plugins/incrypted-site /containers/wordpress-staging/html/wp-content/plugins/
```

## Architecture

### Key Files
| File | Purpose |
|------|---------|
| `themes/incrypted/functions.php` | Theme setup and hooks |
| `themes/incrypted/WOO-customization/nodes-functionality.php` | Node system core logic |
| `themes/incrypted/woocommerce/myaccount/dashboard.php` | User dashboard |
| `themes/incrypted/woocommerce/myaccount/partials/node-card.php` | Node card component |
| `themes/incrypted/js/custom-cart.js` | Cart and renewal interactions |
| `plugins/incrypted-site/incrypted-site.php` | Custom hooks, Discord/Telegram OAuth |
| `plugins/incrypted-site/includes/incr-node-details.php` | Node details AJAX handler |

### Tech Stack
- WordPress 6.x, WooCommerce, PHP 8.2, MariaDB
- ACF Pro, Polylang (UK/EN)
- Payments: MonoPay, Whitepay
- SCSS via node-sass

## Plugins (Not in Git)

**IMPORTANT**: The `incrypted-site` plugin MUST be activated for the site to work. It contains:
- Node details AJAX handler and modal functionality
- Tier and categories feature for products
- Telegram/Discord integrations

Plugins are installed on-demand. If a feature requires a plugin, either:
1. Install fresh via WordPress admin or composer
2. Copy from backup: `C:\All\work\projects\NodesPlus\Site_OLD\wordpress\html\wp-content\plugins\`

### Available plugins in Site_OLD backup:
- `woocommerce` - E-commerce core
- `advanced-custom-fields-pro` - Custom fields for node products
- `polylang`, `polylang-wc` - Multilingual (UK/EN)
- `monopay`, `whitepay-for-woocommerce` - Payment gateways
- `nextend-facebook-connect` - Social login
- `loco-translate` - Translation interface
- `duplicator` - Site migration
- `user-role-editor` - Role management
- `wp-mail-smtp`, `wp-mail-logging` - Email
- `woo-wallet` - Wallet functionality
- `kama-thumbnail` - Image handling

### Database backup location:
```
C:\All\work\projects\NodesPlus\Site_OLD\db-import\dup-installer\dup-database__6323f11-09110120.sql
```

## Environment Variables

Set in `.env` and reference via `getenv()` in `wp-config.php`:
```
NP_ENV=local|staging|production
INCR_API_BASE=
INCR_AUTH_TOKEN=
INCR_TELEGRAM_BOT_TOKEN=
NP_BOT_SECRET=
DISCORD_CLIENT_ID=
DISCORD_CLIENT_SECRET=
INCR_USE_MOCK_NODES=1      # Local only
```

## Product Features

### Tier & Categories (Pills)
Products can have a tier and up to 3 categories displayed as colored pills on node cards and in the modal.

**Tiers** (single select): Tier TOP, Tier 1, Tier 2, Tier 3
**Categories** (multi-select, max 3): DePIN, RPC, Validator, AI, Gaming, DeFi, Infrastructure, Storage

Edit in: WP Admin → Products → Edit → "Node details (for popup)" metabox

**Code locations**:
- Backend/metabox: `plugins/incrypted-site/includes/incr-node-details.php`
- Frontend cards: `themes/incrypted/WOO-customization/nodes-functionality.php` (calls `incr_render_product_pills()`)
- Modal JS: `themes/incrypted/js/app.js` (buildPillsHtml function)
- Styles: `themes/incrypted/css/nodes-cards.css` (.np-pill classes)

**Post meta keys**:
- `np_project_tier` - tier slug (e.g., 'tier-1')
- `np_project_categories` - array of category slugs

## Notes

- **Mock nodes**: Local-only testing via `incr_mock_nodes_data` user meta
- **Uploads**: Not in git, sync manually between environments
- See `STAGING_SETUP.md` for full staging server configuration
