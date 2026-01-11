# Staging Setup (safe, isolated)

Goal: create a staging environment that is isolated from production and uses its own DB, config, and secrets.
This document provides steps and config snippets only. No prod changes.

## 1) Domain + DNS
- Create a subdomain like `staging.yourdomain.com`.
- Point the DNS A/AAAA record to the staging server.
- Ensure this domain is not used by production.

## 2) Server directory layout
```
/var/www/nodesplus-staging/
  current -> releases/<timestamp>
  releases/<timestamp>/
  shared/
    uploads/
    wp-config.php
    .env
```

Example commands:
```bash
sudo mkdir -p /var/www/nodesplus-staging/{releases,shared/uploads}
sudo ln -s /var/www/nodesplus-staging/releases/20260111_120000 /var/www/nodesplus-staging/current
```

## 3) Database (MySQL) - separate staging DB + user
Replace placeholders before executing. Do not reuse prod credentials.
```sql
CREATE DATABASE nodesplus_staging DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'nodesplus_staging'@'localhost' IDENTIFIED BY 'REPLACE_WITH_STRONG_PASSWORD';
GRANT ALL PRIVILEGES ON nodesplus_staging.* TO 'nodesplus_staging'@'localhost';
FLUSH PRIVILEGES;
```

## 4) Staging environment variables
Create `/var/www/nodesplus-staging/shared/.env` (not committed).
```
NP_ENV=staging

# NodesPlus API (use dev/staging endpoints)
INCR_API_BASE=
INCR_IMPORT_URL=
INCR_IMPORT_URL_DEV=
INCR_AUTH_TOKEN=

# Telegram bot (staging bot)
INCR_TELEGRAM_BOT_USERNAME=
INCR_TELEGRAM_BOT_TOKEN=
NP_BOT_SECRET=

# Feature flags
INCR_USE_MOCK_NODES=false
INCR_DRY_RUN_IMPORT=1
```

## 5) wp-config.php for staging
Place config outside webroot to avoid exposure:
`/var/www/nodesplus-staging/shared/wp-config.php`

Use `getenv()` for all secrets. Example pattern:
```php
define( 'NP_ENV', getenv( 'NP_ENV' ) ?: 'staging' );
define( 'INCR_API_BASE', getenv( 'INCR_API_BASE' ) ?: '' );
define( 'INCR_IMPORT_URL', getenv( 'INCR_IMPORT_URL' ) ?: '' );
define( 'INCR_IMPORT_URL_DEV', getenv( 'INCR_IMPORT_URL_DEV' ) ?: '' );
define( 'INCR_AUTH_TOKEN', getenv( 'INCR_AUTH_TOKEN' ) ?: '' );
define( 'INCR_TELEGRAM_BOT_USERNAME', getenv( 'INCR_TELEGRAM_BOT_USERNAME' ) ?: '' );
define( 'INCR_TELEGRAM_BOT_TOKEN', getenv( 'INCR_TELEGRAM_BOT_TOKEN' ) ?: '' );
define( 'INCR_TELEGRAM_BOT_SECRET', getenv( 'NP_BOT_SECRET' ) ?: '' );
```

Then symlink into release:
```bash
ln -s /var/www/nodesplus-staging/shared/wp-config.php /var/www/nodesplus-staging/current/wordpress/html/wp-config.php
```

## 6) Ensure env vars reach PHP
You must load `/var/www/nodesplus-staging/shared/.env` for PHP.
Pick one approach:
- systemd + php-fpm: use `EnvironmentFile=` for the php-fpm service.
- nginx + php-fpm: set `env[KEY]=value` in pool config.
- docker-compose: add `env_file: /var/www/nodesplus-staging/shared/.env`.

Example php-fpm pool snippet:
```
env[NP_ENV] = staging
env[INCR_API_BASE] = ...
env[INCR_IMPORT_URL] = ...
env[INCR_AUTH_TOKEN] = ...
env[INCR_TELEGRAM_BOT_TOKEN] = ...
env[NP_BOT_SECRET] = ...
```

## 7) Web server (Nginx) snippet
Update domain, SSL paths, and php-fpm socket.
```
server {
    listen 80;
    server_name staging.yourdomain.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name staging.yourdomain.com;

    root /var/www/nodesplus-staging/current/wordpress/html;
    index index.php index.html;

    ssl_certificate     /etc/letsencrypt/live/staging.yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/staging.yourdomain.com/privkey.pem;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header X-Robots-Tag "noindex, nofollow, noarchive, nosnippet" always;

    location = /robots.txt {
        add_header Content-Type text/plain;
        return 200 "User-agent: *\nDisallow: /\n";
    }

    location / {
        try_files $uri $uri/ /index.php?$args;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }
}
```

Optional: add HTTP basic auth for staging.

## 8) Data bootstrap (Option A - recommended)
1) Fresh WordPress install on staging.
2) Import only minimal products + test users needed for QA.
3) Keep `INCR_DRY_RUN_IMPORT=1` to prevent real API import.

## 9) Verification checklist
- WP loads and login works
- Nodes dashboard loads
- API sync works (read-only)
- Orders can be placed without real import (dry-run enforced)
- Telegram connect uses staging bot token/secret (or endpoints disabled)
