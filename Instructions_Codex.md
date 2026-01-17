# Instructions_Codex.md

This document captures a system-level understanding of the NodesPlus WordPress/WooCommerce codebase.
All observations are marked as FACT or HYPOTHESIS, and include file paths for verification.

---

## Part A - Site Mechanics and Inventory

### A1) Main site mechanics (user flows / business functions)

1) Nodes catalog + node cards + node details modal
- UI: Nodes section with product cards; clicking a card opens a modal with details, price, and social links.
- Implementation: Theme (cards and modal markup) + Theme JS + Plugin (AJAX endpoint + meta box).
- Entry points: shortcode `custom_nodes_list`, modal markup in theme, JS click handler, AJAX action `np_node_details`.
- FACT: `wordpress/html/wp-content/themes/incrypted/WOO-customization/nodes-functionality.php` (shortcode + modal markup).
- FACT: `wordpress/html/wp-content/themes/incrypted/js/app.js` (modal JS).
- FACT: `wordpress/html/wp-content/plugins/incrypted-site/includes/incr-node-details.php` (AJAX + meta box + pills).

2) WooCommerce checkout + cart customization
- UI: Cart/checkout flows; cart quantity display changes for renewal items; checkout fields removed.
- Implementation: Theme customizations via filters.
- Entry points: Woo filters in `nodes-functionality.php`.
- FACT: `wordpress/html/wp-content/themes/incrypted/WOO-customization/nodes-functionality.php` (checkout fields, cart item name, remove address/download endpoints).

3) Node renewals (prolongation)
- UI: Renewal form with nodes and "Add for prolongation"; cart shows "Prolongation".
- Implementation: Theme PHP + AJAX action.
- Entry points: `ProductRenewalSystem::displayNodesRenewalForm`, AJAX `add_renewal_products_to_cart`.
- FACT: `wordpress/html/wp-content/themes/incrypted/WOO-customization/nodes-functionality.php`.

4) Custom add-to-cart and cart count
- UI: Add-to-cart actions for node cards; cart count updates.
- Implementation: Theme PHP AJAX handlers; JS in theme custom-cart.js (not reviewed here).
- Entry points: AJAX actions `add_to_cart_custom`, `get_cart_count`.
- FACT: `wordpress/html/wp-content/themes/incrypted/WOO-customization/nodes-functionality.php`.
- HYPOTHESIS: `wordpress/html/wp-content/themes/incrypted/js/custom-cart.js` drives frontend behavior (verify file contents).

5) User nodes sync and REST endpoint
- UI: Not directly visible; supports account nodes and external sync.
- Implementation: Theme PHP, REST route `custom/v1/user/(?P<user_id>\d+)`.
- Entry points: `rest_api_init`, `get_user_data_endpoint`.
- FACT: `wordpress/html/wp-content/themes/incrypted/WOO-customization/nodes-functionality.php`.
- FACT: External API base from `INCR_API_BASE` (wp-config.php).

6) Account "Nodes" endpoint
- UI: My Account menu includes "Nodes" with renewal form.
- Implementation: Theme PHP adds endpoint, menu item, and content.
- Entry points: `init` endpoint add, menu filter, account content action.
- FACT: `wordpress/html/wp-content/themes/incrypted/WOO-customization/nodes-functionality.php`.

7) Discord connect callback
- UI: Likely "connect Discord" flow; callback to `/discord-callback`.
- Implementation: Theme `functions.php` rewrite rule + template redirect.
- Entry points: `init` rewrite rule, `template_redirect`.
- FACT: `wordpress/html/wp-content/themes/incrypted/functions.php` (partial shown).
- HYPOTHESIS: Additional Discord handling in theme or plugin beyond snippet (verify remainder of functions.php).

8) Telegram integration and wallet/notifications
- UI: Likely account connect, wallet, notifications.
- Implementation: Custom plugin classes.
- Entry points: class init hooks in plugin bootstrap.
- FACT: `wordpress/html/wp-content/plugins/incrypted-site/incrypted-site.php` requires telegram classes and initializes them.
- HYPOTHESIS: Exact routes, admin UI, and frontend wiring inside class files (verify class files).

9) Custom nodes storage (custom table)
- UI: Admin or internal storage.
- Implementation: Custom plugin `INCR_Nodes_Store`.
- Entry points: activation hook creates tables; methods for upsert and queries.
- FACT: `wordpress/html/wp-content/plugins/incrypted-site/includes/class-incr-nodes-store.php`.

10) Localization and multi-language
- UI: Language switcher, translated labels.
- Implementation: Polylang; theme helper.
- Entry points: `custom_language_switcher`, `pll_get_post_types` filter.
- FACT: `wordpress/html/wp-content/themes/incrypted/functions.php`.
- FACT: Polylang and Polylang for WooCommerce plugins are present in `wp-content/plugins/`.
- HYPOTHESIS: Polylang is active (installed != active).

11) ACF blocks
- UI: ACF block components for sections (e.g., nodes/hero).
- Implementation: Theme `acf-blocks.php`, `blocks/` templates.
- Entry points: `acf_register_block_type` in `acf-blocks.php`.
- FACT: `wordpress/html/wp-content/themes/incrypted/acf-blocks.php` and `blocks/`.

12) Authentication / login / account
- UI: WooCommerce My Account.
- Implementation: WooCommerce.
- FACT: WooCommerce plugin present.
- HYPOTHESIS: WooCommerce is active and used for auth (verify in wp-admin or `active_plugins` option).

13) Payments / gateways
- UI: Payment gateways at checkout.
- Implementation: Monopay, Whitepay plugins (installed).
- FACT: `wp-content/plugins/monopay`, `wp-content/plugins/whitepay-for-woocommerce` exist.
- HYPOTHESIS: Which gateway is active (verify in wp-admin settings).

14) Email/SMS / SMTP
- UI: System emails.
- Implementation: WP Mail SMTP plugin (installed).
- FACT: `wp-content/plugins/wp-mail-smtp`.
- HYPOTHESIS: Plugin active and configured.

15) Wallet / credits
- UI: Woo Wallet.
- Implementation: `woo-wallet` plugin (installed).
- FACT: `wp-content/plugins/woo-wallet`.
- HYPOTHESIS: Plugin active, used in checkout/account.

16) Performance / caching
- UI: Not explicit.
- Implementation: `wp-content/cache` directory exists.
- FACT: `wp-content/cache` directory present.
- HYPOTHESIS: Specific caching plugin in use (not identified in plugin list).

---

### A2) Project structure map

Active theme and child theme
- FACT: Theme directory `wordpress/html/wp-content/themes/incrypted`.
- HYPOTHESIS: No child theme present (only one theme directory).

Key theme files
- FACT: `wordpress/html/wp-content/themes/incrypted/functions.php`.
- FACT: `wordpress/html/wp-content/themes/incrypted/WOO-customization/nodes-functionality.php`.
- FACT: `wordpress/html/wp-content/themes/incrypted/js/app.js`.
- FACT: `wordpress/html/wp-content/themes/incrypted/css/nodes-cards.css`.
- FACT: `wordpress/html/wp-content/themes/incrypted/acf-blocks.php`.
- FACT: `wordpress/html/wp-content/themes/incrypted/blocks/` (ACF block templates).
- FACT: `wordpress/html/wp-content/themes/incrypted/woocommerce/` (Woo templates overrides).

Plugins that affect frontend/checkout/user flows (installed)
- FACT: `woocommerce`
- FACT: `advanced-custom-fields-pro`
- FACT: `polylang` and `polylang-wc`
- FACT: `woo-wallet`
- FACT: `monopay`
- FACT: `whitepay-for-woocommerce`
- FACT: `nextend-facebook-connect` (social login)
- FACT: `user-role-editor`
- FACT: `wp-mail-smtp`
- FACT: `loco-translate`
- FACT: `kama-thumbnail`
- FACT: `incrypted-site` (custom plugin)
- HYPOTHESIS: Which ones are active (verify in wp-admin).

MU-plugins
- FACT: `wp-content/mu-plugins` does not exist.

Build tools / asset pipeline
- FACT: `wordpress/html/wp-content/themes/incrypted/package.json`.
- FACT: `wordpress/html/wp-content/themes/incrypted/composer.json`.
- HYPOTHESIS: Actual build command usage (verify package.json scripts).

Config files affecting behavior
- FACT: `wordpress/html/wp-config.php` defines `WP_HOME`, `WP_SITEURL`, API tokens, `INCR_API_BASE`, `INCR_AUTH_TOKEN`, `INCR_USE_MOCK_NODES`, `NP_ENV`.
- FACT: `wordpress/html/wp-content/debug.log` and `api-requests.log` exist.

---

## Part B - Dependency and Trigger Analysis (Dependency Map)

### Mechanic: Nodes list + cards + modal
-> TRIGGERS
- Shortcode `[custom_nodes_list]` renders cards.
- JS click on `.node_item` or `.node-card`.
- AJAX `np_node_details`.
-> FILES
- `wordpress/html/wp-content/themes/incrypted/WOO-customization/nodes-functionality.php`
- `wordpress/html/wp-content/themes/incrypted/js/app.js`
- `wordpress/html/wp-content/plugins/incrypted-site/includes/incr-node-details.php`
-> DATA SOURCES
- WooCommerce products (`wp_posts`, `wp_postmeta`)
- Post meta: `_np_node_details`, `np_project_tier`, `np_project_categories`, social URLs
- Polylang translations (meta fallback uses translations)
-> OUTPUT / UI
- Cards with title/description/price.
- Modal with details, price, pills, social links, add-to-cart link.

### Mechanic: Node details meta box (admin)
-> TRIGGERS
- `add_meta_boxes` hook for `product`.
- `save_post` updates meta.
-> FILES
- `wordpress/html/wp-content/plugins/incrypted-site/includes/incr-node-details.php`
-> DATA SOURCES
- Post meta: `_np_node_details`, `np_project_tier`, `np_project_categories`, social URLs.
-> OUTPUT / UI
- Metabox in Product edit screen; saved values drive pills/modal content.

### Mechanic: Node renewal form
-> TRIGGERS
- `display_nodes_renewal_form` called from account nodes page.
- AJAX `add_renewal_products_to_cart`.
-> FILES
- `wordpress/html/wp-content/themes/incrypted/WOO-customization/nodes-functionality.php`
-> DATA SOURCES
- WooCommerce products, user nodes data.
- Cart item meta for prolongation (`is_prolongation`, `node_id`).
-> OUTPUT / UI
- Renewal form, cart items marked as Prolongation.

### Mechanic: User nodes sync + REST endpoint
-> TRIGGERS
- REST route `custom/v1/user/(?P<user_id>\d+)`.
- `getNodesByUserID` uses external API.
-> FILES
- `wordpress/html/wp-content/themes/incrypted/WOO-customization/nodes-functionality.php`
- `wordpress/html/wp-config.php` (INCR_API_BASE, INCR_AUTH_TOKEN).
-> DATA SOURCES
- External API (INCR_API_BASE) with Bearer token.
- Transients `Incr_user_nodes_{user_id}`.
-> OUTPUT / UI
- REST response with user id and latest node expiration timestamp.

### Mechanic: Custom add-to-cart + cart count
-> TRIGGERS
- AJAX actions `add_to_cart_custom`, `get_cart_count`.
-> FILES
- `wordpress/html/wp-content/themes/incrypted/WOO-customization/nodes-functionality.php`
-> DATA SOURCES
- WooCommerce cart/session.
-> OUTPUT / UI
- JSON response for cart count/total.

### Mechanic: WooCommerce checkout customization
-> TRIGGERS
- Woo filters for fields, endpoints, cart item name.
-> FILES
- `wordpress/html/wp-content/themes/incrypted/WOO-customization/nodes-functionality.php`
-> DATA SOURCES
- WooCommerce checkout fields.
-> OUTPUT / UI
- Simplified checkout fields, modified account menu, disabled endpoints.

### Mechanic: Discord callback
-> TRIGGERS
- Rewrite rule and `template_redirect` when query var `discord` is present.
-> FILES
- `wordpress/html/wp-content/themes/incrypted/functions.php`
-> DATA SOURCES
- Query var + user session.
-> OUTPUT / UI
- Redirect into account page (details in rest of file).

### Mechanic: Telegram + wallet + notifications (custom plugin)
-> TRIGGERS
- Plugin init hooks, scheduled events.
-> FILES
- `wordpress/html/wp-content/plugins/incrypted-site/includes/class-incr-telegram-*.php`
-> DATA SOURCES
- Custom tables created at activation (tokens, links, notifications, wallets).
-> OUTPUT / UI
- HYPOTHESIS: Admin pages or REST routes for Telegram flows.

### Mechanic: Custom nodes storage
-> TRIGGERS
- Plugin activation creates custom table.
- Plugin methods called from other code (not traced here).
-> FILES
- `wordpress/html/wp-content/plugins/incrypted-site/includes/class-incr-nodes-store.php`
-> DATA SOURCES
- Custom table `wp_incr_nodes`.
-> OUTPUT / UI
- Internal storage for node data.

---

## Part C - Causal System Map (critical nodes)

### Node: Nodes list (shortcode)
PURPOSE: Render node product cards on the front page.
ENTRY POINTS: Shortcode `custom_nodes_list`.
FILES: `wordpress/html/wp-content/themes/incrypted/WOO-customization/nodes-functionality.php`
CONDITIONS: Shortcode placement; optional `category` or `ids` attributes.
DEPENDENCIES: WooCommerce products; Polylang language.
SIDE EFFECTS: None.
FACT/HYPOTHESIS: FACT (implemented in file).

### Node: Node details modal (frontend)
PURPOSE: Show details for a node product.
ENTRY POINTS: JS click handler on `.node_item`/`.node-card`.
FILES: `wordpress/html/wp-content/themes/incrypted/js/app.js`, modal markup in `nodes-functionality.php`.
CONDITIONS: Modal exists in DOM, `npNodeDetails` JS localized.
DEPENDENCIES: AJAX endpoint `np_node_details`.
SIDE EFFECTS: Body gets class `np-modal-open`.
FACT/HYPOTHESIS: FACT.

### Node: Node details AJAX
PURPOSE: Return modal data (title, details, price, tier, categories, links).
ENTRY POINTS: AJAX `np_node_details`.
FILES: `wordpress/html/wp-content/plugins/incrypted-site/includes/incr-node-details.php`
CONDITIONS: Valid `product_id`.
DEPENDENCIES: Product meta; translation fallback via Polylang.
SIDE EFFECTS: None.
FACT/HYPOTHESIS: FACT.

### Node: Node details meta box
PURPOSE: Author custom detail content and classification.
ENTRY POINTS: `add_meta_boxes`, `save_post`.
FILES: `wordpress/html/wp-content/plugins/incrypted-site/includes/incr-node-details.php`
CONDITIONS: Post type = product, user can edit.
DEPENDENCIES: Post meta; WordPress editor.
SIDE EFFECTS: Updates product meta.
FACT/HYPOTHESIS: FACT.

### Node: Node renewal form
PURPOSE: Allow users to renew nodes and add to cart.
ENTRY POINTS: Account "Nodes" endpoint, AJAX `add_renewal_products_to_cart`.
FILES: `wordpress/html/wp-content/themes/incrypted/WOO-customization/nodes-functionality.php`
CONDITIONS: User nodes data available.
DEPENDENCIES: WooCommerce cart; user nodes API.
SIDE EFFECTS: Adds cart items with `is_prolongation` metadata.
FACT/HYPOTHESIS: FACT.

### Node: User nodes fetch + API logging
PURPOSE: Fetch nodes from external API, cache via transient.
ENTRY POINTS: `getNodesByUserID`, REST `custom/v1/user/{id}`.
FILES: `wordpress/html/wp-content/themes/incrypted/WOO-customization/nodes-functionality.php`
CONDITIONS: INCR_API_BASE defined; token available.
DEPENDENCIES: `INCR_API_BASE`, `INCR_AUTH_TOKEN`, `wp-content/api-requests.log`.
SIDE EFFECTS: Writes `api-requests.log`, sets transients.
FACT/HYPOTHESIS: FACT.

### Node: Discord connect callback
PURPOSE: Handle Discord auth callback.
ENTRY POINTS: Rewrite rule `discord-callback`, `template_redirect`.
FILES: `wordpress/html/wp-content/themes/incrypted/functions.php`
CONDITIONS: Query var `discord` present.
DEPENDENCIES: User session.
SIDE EFFECTS: Redirect to account page.
FACT/HYPOTHESIS: FACT (callback exists), HYPOTHESIS (full behavior requires rest of functions.php).

### Node: Telegram connect/notifications/wallet
PURPOSE: Telegram auth, links, notifications, wallet operations.
ENTRY POINTS: Plugin init hooks, possibly REST or cron.
FILES: `wordpress/html/wp-content/plugins/incrypted-site/includes/class-incr-telegram-*.php`
CONDITIONS: Plugin active; tables created on activation.
DEPENDENCIES: Custom DB tables.
SIDE EFFECTS: Schedules cron (notifications/wallet).
FACT/HYPOTHESIS: FACT for tables/initialization; HYPOTHESIS for UI routes.

### Node: WooCommerce customization layer
PURPOSE: Simplify checkout and account UI.
ENTRY POINTS: Woo filters, account menu filters.
FILES: `wordpress/html/wp-content/themes/incrypted/WOO-customization/nodes-functionality.php`
CONDITIONS: WooCommerce pages.
DEPENDENCIES: WooCommerce plugin.
SIDE EFFECTS: Removes fields and menu endpoints; changes cart item display.
FACT/HYPOTHESIS: FACT.

---

## Part D - Task Execution Protocol

For any future task:
1) Respond with an execution plan:
   - Mechanics involved
   - System map nodes affected
   - Files likely to change
   - Risks and side effects
   - What must be verified before coding
2) Wait for confirmation before code changes.
3) If info is missing, request specific paths, URLs, logs, or screenshots.
4) All edits must be proposed as patches/diffs or scoped edits.
5) Never delete/overwrite code unless explicitly told.

---

## Environment Behavior (Local/Staging/Prod)

FACT: Local environment is explicitly configured in `wordpress/html/wp-config.php`.
- `NP_ENV` is set to `local`.
- `INCR_USE_MOCK_NODES` is set to `true`.
- API endpoints (`INCR_API_BASE`, `INCR_IMPORT_URL`, `INCR_IMPORT_URL_DEV`) are hard-coded to `scripts.unihost.com` URLs.

FACT: Environment resolution logic is in `wordpress/html/wp-content/plugins/incrypted-site/includes/incr-helpers.php`.
- `np_env()` reads `NP_ENV` constant or environment variable; normalizes to `local`, `staging`, or `prod`.
- `incr_is_local_env()` returns true when `np_env()` is `local`.
- `incr_get_config()` pulls config from constants or env vars.

FACT: Staging setup guidance exists in `STAGING_SETUP.md`.
- It expects `NP_ENV=staging`, `INCR_USE_MOCK_NODES=false`, and env-based secrets.
- It recommends separate DB, separate host, and `INCR_DRY_RUN_IMPORT=1` to prevent real imports.

HYPOTHESIS: Staging and production read secrets from environment variables (via `incr_get_config()`), not hard-coded values.
- Verification: check staging/prod `wp-config.php` and `.env` on those servers.

HYPOTHESIS: In staging/prod, `INCR_USE_MOCK_NODES` is false and real API calls are used.
- Verification: confirm the constant/env on staging/prod.

HYPOTHESIS: `INCR_IMPORT_URL_DEV` is only used when explicitly switched in code (currently commented in theme).
- Verification: search for `INCR_IMPORT_URL_DEV` usage and check any staging overrides.

### Staging/Prod Verification Checklist
- Check `NP_ENV` value in `wp-config.php` or environment variables.
- Confirm `INCR_USE_MOCK_NODES` is `false` in staging/prod.
- Verify `INCR_API_BASE`, `INCR_IMPORT_URL`, and `INCR_AUTH_TOKEN` are set via env vars.
- Confirm `INCR_DRY_RUN_IMPORT` is enabled on staging (if required).
- Validate Telegram bot secrets are staging/prod specific.
