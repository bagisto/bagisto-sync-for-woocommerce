# Bagisto Sync for WooCommerce

[![CI](https://github.com/bagisto/bagisto-sync-for-woocommerce/actions/workflows/ci.yml/badge.svg)](https://github.com/bagisto/bagisto-sync-for-woocommerce/actions/workflows/ci.yml)
[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](LICENSE)
[![WordPress](https://img.shields.io/badge/WordPress-6.5%2B-21759b.svg)](https://wordpress.org/)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-9.0%2B-96588a.svg)](https://woocommerce.com/)
[![PHP](https://img.shields.io/badge/PHP-7.4%20–%208.4-777bb4.svg)](https://www.php.net/)
[![Docs](https://img.shields.io/badge/docs-wpdoc.webkul.com-0a7cff.svg)](https://wpdoc.webkul.com/bagisto-connector/)

> Module that connects WooCommerce products, users and orders with Bagisto.

📖 **[User documentation](https://wpdoc.webkul.com/bagisto-connector/)** — illustrated setup, configuration and usage guide, with screenshots for every setting.

A WordPress/WooCommerce plugin by [Webkul](https://profiles.wordpress.org/webkul/) that pushes WooCommerce store data — users, customers, categories, attributes, products, tax classes and rates, coupons and orders — into a [Bagisto](https://bagisto.com/) store through Bagisto's Admin REST API.

Synchronisation runs one way (**WooCommerce → Bagisto**), either automatically when data changes in WooCommerce or on demand from the plugin's Bulk Sync screen and from the native WordPress/WooCommerce list tables. All work is handed to a background queue so large catalogues and order histories do not block the admin request, and every API exchange is written to a per-entity log file you can view, download or purge from the admin.

---

## Table of contents

- [Key features](#key-features)
- [How the integration works](#how-the-integration-works)
- [Requirements and compatibility](#requirements-and-compatibility)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage and example workflows](#usage-and-example-workflows)
- [Reference: hooks, AJAX actions, cron and stored data](#reference-hooks-ajax-actions-cron-and-stored-data)
- [Development setup and build commands](#development-setup-and-build-commands)
- [Directory structure](#directory-structure)
- [Troubleshooting](#troubleshooting)
- [FAQ](#faq)
- [Security](#security)
- [Contributing](#contributing)
- [Support](#support)
- [Releases and upgrading](#releases-and-upgrading)
- [License](#license)
- [Credits and acknowledgements](#credits-and-acknowledgements)
- [Project status and known limitations](#project-status-and-known-limitations)

Further reading: [CONTRIBUTING](CONTRIBUTING.md) · [CHANGELOG](CHANGELOG.md) · [SECURITY](SECURITY.md) · [RELEASING](RELEASING.md) · [THIRD-PARTY NOTICES](THIRD-PARTY-NOTICES.md) · [CODE OF CONDUCT](CODE_OF_CONDUCT.md)

---

## Key features

- **Token-based connection.** Connect to Bagisto with a Personal Access Token (PAT); the connection is verified live against `GET /api/admin/get` and can be re-tested at any time from the Connection screen.
- **Seven entity syncs.** Users (non-customer roles), customers, categories, attributes, products, taxes and orders each get their own queue, settings and log stream.
- **Automatic sync on change.** Optional per-entity toggles for "sync on create" and "sync on update", wired to WooCommerce/WordPress hooks (`woocommerce_new_product`, `created_product_cat`, `profile_update`, `woocommerce_new_order`, `woocommerce_order_status_changed`, and others).
- **Bulk sync screen.** Run a full sync per entity with per-entity options (exclude specific users/customers/categories/products, exclude order statuses, and "update existing records" toggles).
- **Selective sync from list tables.** A "Sync to Bagisto" bulk action is added to the Users, Product categories, Products and Orders list tables; the WooCommerce Attributes screen gets an equivalent JS-rendered bulk toolbar.
- **Sync-state columns.** Extra columns on the Users, Product categories, Products and Orders list tables show the mapped Bagisto ID, plus an order metabox showing the Bagisto Order ID.
- **Category and tax mapping.** One-to-one mapping tables pair WooCommerce product categories with Bagisto categories, and WooCommerce tax classes with Bagisto tax categories.
- **Order status mapping.** Map each WooCommerce order status to a Bagisto transition action — *Pending*, *Processing (create invoice)*, *Complete (create invoice & shipment)*, *Cancel order* or *Closed (refund)*.
- **Coupon sync during order sync.** WooCommerce coupons attached to an order are created or updated as Bagisto cart rules and applied to the draft cart.
- **Background queue with retries.** Items are processed in the background; a failed item is retried up to 3 times before being recorded as permanently failed. A health-check cron per entity runs every 5 minutes.
- **Live progress polling.** The admin polls sync status and renders a progress bar and toast notifications while a queue drains.
- **Log viewer and retention.** Per-source log files with an embedded viewer, raw download, manual delete, 5 MB rotation, a configurable retention policy (1 / 15 / 30 days, or never) and a daily cleanup cron.
- **HPOS compatible.** Declares compatibility with WooCommerce High-Performance Order Storage (`custom_order_tables`) and supports both the legacy `shop_order` and the HPOS `wc-orders` admin screens.

---

## How the integration works

### Connection

The plugin talks to Bagisto's **Admin REST API** using a Personal Access Token. You supply the Bagisto store URL and the token on the Connection screen; the plugin calls `GET {store_url}/api/admin/get` with an `Authorization: Bearer <token>` header. On success it stores the store URL, the token and the returned admin e-mail address in WordPress options and marks the site as connected.

Every subsequent call goes through a single request helper that adds the bearer token, JSON-encodes the body (or builds a `multipart/form-data` body when images are attached), tunnels `PUT` as `POST` with `_method=PUT`, and routes the request/response into the correct log stream based on the endpoint path. A `401` response sets a `wkwctobag_token_expired` flag which surfaces a dismissible admin notice.

All requests use WordPress's HTTP API with **TLS certificate verification enabled**. See [TLS and self-signed certificates](#tls-and-self-signed-certificates).

### Sync pipeline

```
WooCommerce event                Admin action
(new/updated product,            (Bulk Sync screen, list-table
 order, user, term, tax rate)     bulk action, attributes toolbar)
        |                                   |
        v                                   v
 wp_schedule_single_event            push_to_queue() -> save()
 (wkwctobag_delayed_*_sync)                  |
        |                                   v
        +-------------> Wkwctobag_Background_Process (per entity type)
                                            |
                                            v
                            Wkwctobag_Sync_Data::sync_data( $item )
                                            |
              +--------+--------+-----------+-----------+--------+--------+
              v        v        v           v           v        v        v
           Product Category  Order       Customer      User  Attribute   Tax
              \________________________ Wkwctobag_Helper::bagisto_curl_request _______/
                                            |
                                            v
                                   Bagisto Admin REST API
                                            |
                                            v
                    Bagisto ID stored back as WP post/term/user/order meta
```

Each entity type gets its own `WP_Background_Process` instance (action `wkwctobag_sync_<type>`), so a long product sync does not block an order sync. Progress (`total` / `done` / `failed`) and state (`running`, `completed`, `partial_failure`, `failed`, `token_error`) are tracked per type in options and polled by the admin UI.

### ID mapping

The plugin never re-reads Bagisto to find a record it already created; it stores the remote ID locally:

| WooCommerce object | Where the Bagisto ID is stored |
| --- | --- |
| User (admin roles) | user meta `__wkwctobag_bagisto_user_id` |
| Customer | user meta `__wkwctobag_bagisto_customer_id` |
| Customer address | user meta `__wkwctobag_bagisto_customer_address_id` |
| Product category | term meta `__wkwctobag_bagisto_category_id` |
| Product | post meta `__wkwctobag_bagisto_product_id` |
| Order | order meta `__wkwctobag_bagisto_order_id` |
| Product attribute | option `wkwctobag_attribute_mappings` (taxonomy → Bagisto attribute ID) |
| Tax class | option `wkwctobag_tax_category_mappings` |
| Tax rate | option `wkwctobag_tax_rate_mappings` |

### Order sync in detail

Bagisto has no "import order" endpoint, so an order is replayed through the admin cart API:

1. **Resolve the customer** — use the mapped Bagisto customer, or the configured guest customer, or create a customer on the fly.
2. **Create a draft cart** for that customer.
3. **Add line items** — mapped products are resolved by Bagisto product ID, then by variant, then by SKU. Shipping fees, order fees and price-adjusted lines that have no Bagisto counterpart are represented by temporary virtual products which are deleted again once the order is placed.
4. **Save billing and shipping addresses.**
5. **Select a shipping method**, then **a payment method**.
6. **Apply coupons** — each WooCommerce coupon is created/updated as a Bagisto cart rule and applied to the cart.
7. **Place the order** and store the returned Bagisto order ID on the WooCommerce order.
8. **Transition the status** according to your order status mapping — creating invoices, shipments, cancellations or refunds in Bagisto.

---

## Requirements and compatibility

Taken from the plugin header and `readme.txt`:

| Requirement | Version |
| --- | --- |
| WordPress | 6.5 or newer (tested up to 7.0) |
| WooCommerce | 9.0 or newer (tested up to 10.9) |
| PHP | 7.4 or newer (tested up to 8.4) |
| WooCommerce HPOS | Compatible (`custom_order_tables` declared) |
| Bagisto | A reachable Bagisto store exposing the Admin REST API (`/api/admin/...`) with a Personal Access Token |

Additional notes:

- WooCommerce is a hard dependency, declared with the `Requires Plugins: woocommerce` header. WordPress 6.5+ will refuse to activate the plugin without it.
- The background queue extends `WP_Background_Process`, which is **not bundled here** — it is provided by the copy WooCommerce ships in `woocommerce/includes/libraries/wp-background-process.php`. This is an implicit dependency on a WooCommerce internal library.
- WP-Cron must be able to run for background syncs and log cleanup to progress. On sites with `DISABLE_WP_CRON` set, configure a real system cron that hits `wp-cron.php`.
- The Bagisto store must present a **valid TLS certificate** that the WordPress server trusts. See below.
- For local development: **Node.js** and **npm** (a `package-lock.json` with `lockfileVersion` 3 is committed, so npm 7+ / Node 16+), plus **PHP CLI** for the `.pot` merge script and **Bash** for the shell helpers in `bin/`.
- The plugin has **no PHP runtime dependencies** and ships no `vendor/` directory. `composer.json` exists only for the linting toolchain.

### TLS and self-signed certificates

Every request to the Bagisto API carries a Bagisto **administrator** bearer token, so the plugin relies on WordPress's default behaviour of verifying TLS certificates. If your Bagisto host presents a self-signed or otherwise untrusted certificate, connection and sync will fail with a certificate error.

**The correct fix is to install a valid certificate on the Bagisto host.** If you must work around this in a throwaway local environment, use WordPress core's own filter in a `mu-plugin` — never edit the plugin:

```php
// Local development ONLY. Never do this on a production site:
// it disables certificate verification for the whole site and exposes the
// Bagisto administrator token to anyone on the network path.
add_filter( 'https_ssl_verify', '__return_false' );
```

---

## Installation

### From a release archive

1. Copy the plugin into your site as `wp-content/plugins/bagisto-sync-for-woocommerce/`.
   The plugin's text domain, `readme.txt`, `package.json` and `composer.json` all use the slug **`bagisto-sync-for-woocommerce`**, which matches the repository name, so a plain `git clone` already produces a correctly named directory.
2. Activate **Bagisto Sync for WooCommerce** from **Plugins** in the WordPress admin. WooCommerce must be installed and active.
3. Open the new **WC to Bagisto** menu in the admin sidebar.

### From this repository

```bash
git clone https://github.com/bagisto/bagisto-sync-for-woocommerce.git bagisto-sync-for-woocommerce
cd bagisto-sync-for-woocommerce
npm install
npm run buildr        # produces assets/js/admin.js and assets/css/admin.css
```

Built assets (`assets/js/admin.js`, `assets/css/admin.css`) are committed to the repository, so a clone is runnable without a build step; you only need to build after changing anything under `src/`.

---

## Configuration

All screens live under the **WC to Bagisto** top-level admin menu and require the `manage_options` capability by default (see [`wkwctobag_modify_capabilities`](#filters)).

### 1. Connection

**WC to Bagisto → Connection**

| Field | Description |
| --- | --- |
| Bagisto Store URL | Base URL of your Bagisto store, e.g. `https://your-bagisto-store.example/` |
| Bagisto API Token | A Personal Access Token generated in the Bagisto admin under **My Account → Personal Access Tokens** |

Use **Test Connection** to verify credentials without saving, then **Connect Store** to save them. Once connected the screen shows the store URL, the connected Bagisto admin e-mail and a live status indicator, plus **Update Connection** and **Disconnect** actions.

### 2. Settings

**WC to Bagisto → Settings** has five tabs. Options are stored one WordPress option per tab.

| Tab | Option | Keys |
| --- | --- | --- |
| User | `wkwctobag_user_settings` | `auto_sync_user`, `update_user`, `wc_bagisto_user_role`, `wc_bagisto_user_status` |
| Customer | `wkwctobag_customer_settings` | `auto_sync_customer`, `update_customer`, `wc_bagisto_customer_group`, `wc_bagisto_customer_default_gender`, `wc_bagisto_customer_status` |
| Product | `wkwctobag_product_settings` | `auto_sync_products`, `update_product`, `auto_sync_categories`, `update_categories`, `auto_sync_attributes`, `update_attributes`, plus the WooCommerce → Bagisto category mapping |
| Tax | `wkwctobag_tax_settings` | `auto_sync_taxes`, plus the WooCommerce tax class → Bagisto tax category mapping |
| Order | `wkwctobag_order_settings` | `auto_sync_order`, `update_order`, `allowed_order_status`, `order_status_mappings`, `wc_bagisto_guest_customer_id` |

Before the first bulk sync you must set:

- a **default Bagisto user role** (User tab) — required for user sync;
- a **default Bagisto customer group** (Customer tab) — required for customer sync.

Both are validated against Bagisto when a bulk sync starts, and the sync is refused with an explanatory message if the selected role/group no longer exists.

### 3. Logs

**WC to Bagisto → Logs** lists one entry per log source (`category`, `product`, `user`, `customer`, `order`, `api`, `attribute`, `tax`, `background-process`) with size and last-modified time, and lets you view the tail of a log, download the raw file, delete it, set the retention policy (`1`, `15`, `30` days or `never`) and purge expired logs immediately.

Logs are written to `wp-content/uploads/bagisto-sync-for-woocommerce-logs/wkwctobag-<source>.log`. The directory is created with an `index.php` stub and an `.htaccess` that denies direct access using both Apache 2.2 (`Deny from all`) and Apache 2.4 (`Require all denied`) syntax. Files rotate at 5 MB and a daily cron trims entries older than the retention window.

> `.htaccess` is ignored by nginx. On nginx, deny the directory in your server configuration — see [SECURITY.md](SECURITY.md#hardening-notes-for-site-operators). Log files can contain customer personal data.

---

## Usage and example workflows

### Recommended first-run order

Because products reference categories and attributes, and orders reference products and customers, sync in dependency order:

```
Users  →  Customers  →  Categories  →  Attributes  →  Taxes  →  Products  →  Orders
```

Run each step from **WC to Bagisto → Bulk Sync**, wait for its progress bar to complete, then move to the next.

### Example: initial catalogue migration

1. Connect the store (**Connection**).
2. In **Settings → Product**, map any WooCommerce categories that already exist in Bagisto so the sync updates them instead of creating duplicates.
3. In **Settings → Tax**, map WooCommerce tax classes to Bagisto tax categories.
4. Open **Bulk Sync**:
   - *Category Sync* → optionally exclude categories → **Sync Category Now**.
   - *Attribute Sync* → choose whether to overwrite existing attributes → **Sync Attribute Now**.
   - *Taxes Sync* → **Sync Taxes Now**.
   - *Product Sync* → optionally exclude products, choose "Update Existing Products" → **Sync Product Now**.
5. Watch the progress bar; check **Logs → Product Sync Log** for any failures.

### Example: syncing a subset from a list table

1. Go to **Products → All Products** (or **Users**, **Products → Categories**, **WooCommerce → Orders**).
2. Tick the rows you want.
3. Choose **Sync to Bagisto** from the *Bulk actions* dropdown and click **Apply**.
4. A modal asks whether to update records that already exist in Bagisto; confirm to queue the selection.
5. The **Bagisto ID** column fills in as items complete.

### Example: keeping a live store in sync

1. In **Settings**, enable *Auto Sync* and *Auto Update* for the entities you care about.
2. In **Settings → Order**, choose the *Allowed Order Status* values that should sync, map each WooCommerce status to a Bagisto action, and pick a *Bagisto Customer for Guest Orders* if you take guest checkouts.
3. From then on, new and updated records are queued automatically; the queue drains in the background and via the 5-minute health-check cron.

---

## Reference: hooks, AJAX actions, cron and stored data

### Filters

| Filter | Arguments | Purpose |
| --- | --- | --- |
| `wkwctobag_modify_capabilities` | `string $capability` (default `manage_options`) | Change the capability required to see and use the plugin's admin menus. |
| `wp_wkwctobag_sync_<type>_memory_exceeded` | `bool $exceeded` | Inherited from `WP_Background_Process`; override the 80 %-of-memory guard for a queue. `<type>` is one of `product`, `category`, `order`, `customer`, `user`, `attribute`, `tax`. |

Other `WP_Background_Process` filters (`..._queue_lock_time`, `..._default_time_limit`, `..._time_exceeded`, `..._cron_interval`) are also available under the same `wp_wkwctobag_sync_<type>` prefix.

### Actions

| Action | Purpose |
| --- | --- |
| `wkwctobag_settings_tab_<tab>` | Fired to render a settings tab body. `<tab>` is `user`, `customer`, `product`, `tax` or `order`. Use it to append markup to an existing tab. |

### Internal scheduled single events

These are dispatched with `wp_schedule_single_event()` to move work off the originating request. They are implementation details rather than a public API, but are useful when debugging:

`wkwctobag_delayed_customer_sync`, `wkwctobag_delayed_order_sync`, `wkwctobag_delayed_order_create_sync`, `wkwctobag_delayed_order_status_sync`, `wkwctobag_delayed_category_create_sync`, `wkwctobag_delayed_category_update_sync`, `wkwctobag_delayed_product_create_sync`, `wkwctobag_delayed_product_update_sync`.

### Cron jobs

| Event | Schedule | Purpose |
| --- | --- | --- |
| `wp_wkwctobag_sync_<type>_cron` | `wp_wkwctobag_sync_<type>_cron_interval` — every 300 s | Background-queue health check; restarts a stalled queue for that entity type. Registered for all seven types and unscheduled automatically when the queue empties. |
| `wkwctobag_daily_log_cleanup_event` | `daily` | Applies the log retention policy. Cleared on plugin deactivation. |

### AJAX actions

All are handled on `admin-ajax.php`.

**Search (feeds the Select2 pickers)**

`wkwctobag_get_searched_users`, `wkwctobag_get_searched_products`, `wkwctobag_get_searched_categories`, `wkwctobag_get_searched_bagisto_customers`

**Bulk sync (Bulk Sync screen)**

`wkwctobag_bulk_user_sync`, `wkwctobag_bulk_category_sync`, `wkwctobag_bulk_product_sync`, `wkwctobag_bulk_attribute_sync`, `wkwctobag_bulk_tax_sync`, `wkwctobag_bulk_order_sync`

**Selection sync (list-table bulk actions)**

`wkwctobag_selected_users_sync`, `wkwctobag_selected_categories_sync`, `wkwctobag_selected_products_sync`, `wkwctobag_selected_attributes_sync`, `wkwctobag_selected_orders_sync`

**Status and diagnostics**

`wkwctobag_check_sync_status`, `wkwctobag_test_connection`

`WP_Background_Process` additionally registers `wp_wkwctobag_sync_<type>` handlers used for asynchronous queue dispatch.

### WP-CLI

The plugin does not currently register any WP-CLI commands.

### Bagisto endpoints used

| Area | Endpoints |
| --- | --- |
| Auth / identity | `GET /api/admin/get` |
| Catalogue | `/api/admin/catalog/products`, `/api/admin/catalog/products/{id}`, `/api/admin/catalog/products/{id}/inventories`, `/api/admin/catalog/attributes`, `/api/admin/catalog/families/{id}` |
| Categories | `/api/admin/catalog` (categories) |
| Customers | `/api/admin/customers`, `/api/admin/customers/{id}`, `/api/admin/customers/groups`, `/api/admin/customers/{id}/draft-carts` |
| Carts / orders | `/api/admin/carts/{id}/items`, `/addresses`, `/shipping-methods`, `/payment-methods`, `/coupon`, `/api/admin/orders/place/{cart}`, `/api/admin/orders/{id}`, `/invoices`, `/shipments`, `/refunds`, `/cancel` |
| Settings | `/api/admin/settings`, `/api/admin/settings/channels`, `/api/admin/settings/inventory-sources`, `/api/admin/settings/tax-categories`, `/api/admin/settings/tax-rates` |
| Marketing | `/api/admin/marketing/cart-rules` |

### Options written by the plugin

`wkwctobag_bagisto_site_url`, `wkwctobag_bagisto_admin_token`, `wkwctobag_bagisto_admin_email`, `wkwctobag_token_expired`, `wkwctobag_user_settings`, `wkwctobag_customer_settings`, `wkwctobag_product_settings`, `wkwctobag_tax_settings`, `wkwctobag_order_settings`, `wkwctobag_attribute_mappings`, `wkwctobag_tax_category_mappings`, `wkwctobag_tax_rate_mappings`, `wkwctobag_fallback_tax_rate_id`, `wkwctobag_log_retention`, `wkwctobag_active_sync_type`, `wkwctobag_active_sync_totals`, `wkwctobag_sync_progress_<type>`, `wkwctobag_sync_state_<type>`, `wkwctobag_sync_failed_<type>`.

Transients: `wkwctobag_bagisto_roles_cache`, `wkwctobag_bagisto_groups_cache`, `wkwctobag_bagisto_categories_cache` (10 minutes each) and `wkwctobag_sync_just_completed` (60 s).

---

## Development setup and build commands

```bash
npm install       # front-end build toolchain
composer install  # PHP lint + coding standards (dev only)
```

| Command | What it does |
| --- | --- |
| `npm run buildr` | Production build (`NODE_ENV=production webpack`) → `assets/js/admin.js`, `assets/css/admin.css`. |
| `npm run build` | Runs `bin/build.sh`, an interactive release helper that confirms before calling `npm run buildr`. Triggers `postbuild` → `npm run i18n`. |
| `npm run watch` / `npm start` | Webpack in watch mode for development. |
| `npm run build-old` | Plain `webpack` (development mode). |
| `npm run i18n` | Full i18n chain: extract JS strings → verify text domain → regenerate `.pot` → merge → rewrite JS source references. |
| `npm run i18n:check` | `grunt checktextdomain` — verifies every gettext call uses the `bagisto-sync-for-woocommerce` text domain. |
| `npm run i18n:pot` | `grunt makepot` — regenerates `languages/bagisto-sync-for-woocommerce.pot`. |
| `npm run i18n:build` | `php bin/combine-pot-files.php` — merges the Babel-extracted `.po` into the `.pot`. |
| `npm run i18n:json` | `bin/make-i18n-json.sh` — rewrites `src/` references in the `.pot` to build paths. |
| `npm run zip` | `wp-scripts plugin-zip` — packages the files listed under `files` in `package.json`. |
| `composer lint` | PHP syntax check across every plugin file. |
| `composer phpcs` | WordPress + WooCommerce coding standards (`phpcs.xml.dist`). |
| `composer phpcbf` | Auto-fix the safely fixable standards violations. |
| `composer check` | `lint` + `phpcs`. The codebase is clean: 0 errors, 0 warnings. |

Build entry point: `src/js/admin/index.js` + `src/scss/admin.scss` → `assets/js/admin.js` + `assets/css/admin.css` (Webpack 5, Babel with the WordPress preset, `mini-css-extract-plugin`, `sass-loader`).

### Dependency security

Only two libraries are actually compiled into the distributed bundle: **jQuery** and **Select2** (plus Babel's `regenerator-runtime`). Everything else in `package.json` is build-time only and never reaches a user's site. See [THIRD-PARTY-NOTICES.md](THIRD-PARTY-NOTICES.md) for the full inventory and licences.

```bash
npm audit --omit=dev   # what actually ships — this is the one that matters
npm audit              # the whole build toolchain, informational
```

`npm audit --omit=dev` currently reports **0 vulnerabilities**. The full tree still reports advisories reached through `@wordpress/scripts` (Puppeteer, Lighthouse, `adm-zip`) and `grunt`; those affect only a developer's machine during a build and never reach a user's site.

`package.json` pins `lodash` to `^4.18.1` through an `overrides` entry. `lodash` is a transitive build-time dependency of `@wordpress/i18n` and is not present in the built bundle, but the older line carried two high-severity advisories.

Dependabot keeps the toolchain current and deliberately does **not** auto-bump `jquery` or `select2`, because those two are compiled into distributed code and must be reviewed by hand.

Coding conventions follow WordPress PHP standards: one class per file, `class-*.php` filenames, `WKWCBAGCONNECTOR\...` namespaces resolved by the custom autoloader in `includes/autoload.php`, `wkwctobag_` / `WKWCTOBAG_` prefixes for globals, and every user-facing string wrapped in a gettext call with the `bagisto-sync-for-woocommerce` text domain. The full contributor guide, including the mandatory security checks for new endpoints, is in [CONTRIBUTING.md](CONTRIBUTING.md).

> `assets/js/admin.js` and `assets/css/admin.css` are generated by webpack but are **intentionally committed**, because WordPress plugins are distributed as ready-to-run directories. Rebuild and commit them whenever you change `src/`; never edit them by hand.

---

## Directory structure

```
.
├── bagisto-sync-for-woocommerce.php   # Plugin header, constants, cron registration, bootstrap
├── includes/
│   ├── autoload.php                   # PSR-ish autoloader for the WKWCBAGCONNECTOR namespace
│   ├── class-wkwctobag-file-handler.php  # Singleton bootstrapper
│   ├── admin/                         # Admin hook wiring + admin AJAX/callback implementations
│   └── front/                         # Front-end hook wiring + storefront auto-sync handlers
├── helper/
│   ├── class-wkwctobag-helper.php     # Connection state, API request helper, ID mapping, settings I/O
│   ├── class-wkwctobag-logger.php     # Per-source file logger, rotation, retention
│   ├── background-process/            # WP_Background_Process subclass (one instance per entity type)
│   └── sync/                          # Per-entity sync implementations
│       ├── class-wkwctobag-sync-data.php    # Queue item → sync class dispatcher
│       ├── class-wkwctobag-sync-status.php  # Progress/state tracking
│       ├── attribute/  category/  customer/  order/  product/  tax/  user/
├── templates/
│   └── admin/
│       ├── class-wkwctobag-connection.php   # Connection screen
│       ├── class-wkwctobag-bulk-sync.php    # Bulk Sync screen
│       ├── class-wkwctobag-logs.php         # Logs screen
│       └── settings/                        # Settings screen + one class per tab
├── src/                               # Un-built front-end sources
│   ├── js/actions/index.js            # fetch + Select2 helpers
│   ├── js/admin/index.js              # Admin behaviour (polling, modals, toasts, bulk actions)
│   └── scss/admin.scss
├── assets/                            # Built, distributed assets (generated, but committed)
│   ├── css/admin.css
│   ├── js/admin.js (+ admin.js.LICENSE.txt)
│   └── images/
├── languages/bagisto-sync-for-woocommerce.pot
├── bin/                               # build.sh, make-i18n-json.sh, combine-pot-files.php
├── .github/                           # Issue/PR templates, Dependabot, CI workflow
├── Gruntfile.js  babel.config.js  webpack.config.js  package.json
├── composer.json  phpcs.xml.dist      # PHP linting / coding standards (dev only)
├── .gitignore  .gitattributes  .editorconfig  .distignore
├── readme.txt                         # WordPress.org readme
├── CHANGELOG.md  CONTRIBUTING.md  CODE_OF_CONDUCT.md
├── SECURITY.md  RELEASING.md  THIRD-PARTY-NOTICES.md
├── LICENSE  license.txt               # GNU GPL v3
└── README.md
```

Every directory also contains an `index.php` "Silence is golden" stub to prevent directory listing.

---

## Troubleshooting

**"Failed to connect. Please verify the Store URL and Token."**
The store URL must be the Bagisto root (the plugin appends `/api/admin/get` itself). Confirm the token is a Personal Access Token created in the Bagisto admin and that `/api/admin/get` is reachable from the WordPress server — not just from your browser.

**A red "token expired" notice keeps appearing.**
Bagisto returned `401`. Regenerate the PAT in Bagisto, then **Disconnect** and reconnect on the Connection screen. The notice clears when a request succeeds again.

**Bulk sync starts but the progress bar never moves.**
The queue is driven by WP-Cron and the async dispatch request. Check that WP-Cron runs (`wp cron event list`), that nothing blocks loopback requests to `admin-ajax.php`, and look at **Logs → Background Process Log**.

**"Please set default Bagisto user role / customer group in settings before bulk sync."**
User and customer syncs require a default Bagisto role and customer group. Set them in **Settings → User** and **Settings → Customer**. If the selected role/group was deleted in Bagisto, pick a valid one — the plugin validates the selection against Bagisto before queueing.

**Products sync but variations or options are missing.**
Attributes must exist in Bagisto first. Run *Attribute Sync* before *Product Sync*, and check **Logs → Attribute Sync Log**.

**Order sync fails with "Billing address … is missing".**
Bagisto requires address line 1, city, country and phone. Orders missing any of these are rejected before a draft cart is created.

**Orders sync but no invoice/shipment is created in Bagisto.**
Check **Settings → Order → Order Status Mappings**. A status mapped to *Pending* performs no transition.

**Nothing is written to the log files.**
The logger needs a writable `wp-content/uploads/bagisto-sync-for-woocommerce-logs/`. If the directory cannot be created the logger silently disables itself; check upload directory permissions.

**Duplicate records appear in Bagisto.**
Turn on the relevant "Update Existing …" option before re-running a sync, and map pre-existing Bagisto categories/tax categories in **Settings** so the plugin updates instead of creating.

---

## FAQ

**Does data flow back from Bagisto to WooCommerce?**
No. Synchronisation is one-way: WooCommerce → Bagisto.

**How do I connect WooCommerce with Bagisto?**
Go to **WC to Bagisto → Connection**, enter the Bagisto store URL and Admin API Token, and click **Connect Store**.

**Where do I generate the Bagisto API token?**
In the Bagisto admin panel under **My Account → Personal Access Tokens**.

**Which entities can be synced?**
Users (non-customer roles), customers, product categories, product attributes, products, tax classes/rates, coupons (as part of order sync) and orders.

**Can I sync only a few records instead of everything?**
Yes — select rows on the Users, Product categories, Products, Orders or Attributes screens and use the **Sync to Bagisto** bulk action, or use the exclusion pickers on the Bulk Sync screen.

**Will syncing again create duplicates?**
No, provided the record was synced by this plugin: the Bagisto ID is stored as WordPress meta and re-used. For records that already existed in Bagisto before you installed the plugin, use the category/tax mapping tables and the "Update Existing …" options.

**Which user roles are synced as "users" and which as "customers"?**
The Bulk Sync screen separates them: *User Sync* covers non-customer WordPress roles (mapped to a Bagisto admin role); *Customer Sync* covers the customer role (mapped to a Bagisto customer group).

**How are guest orders handled?**
You can nominate an existing Bagisto customer for guest orders in **Settings → Order**; otherwise the plugin creates a Bagisto customer from the order's billing details, falling back to a generated `@example.com` address when the order has no billing e-mail.

**How long are logs kept?**
By default 30 days. Change it in **WC to Bagisto → Logs**, or set *Never* to keep them indefinitely. Files also rotate at 5 MB.

**Is it compatible with High-Performance Order Storage?**
Yes — the plugin declares `custom_order_tables` compatibility and registers columns, bulk actions and the order metabox for both the legacy and HPOS order screens.

**Are there WP-CLI commands?**
Not at present.

**What happens to my data if I uninstall the plugin?**
The plugin ships no uninstall routine, so its settings, the WooCommerce↔Bagisto ID mappings (post, term, user and order meta) and the log directory are left in place when you delete it. That means you can reinstall without re-syncing everything. For a clean removal, delete the `wkwctobag_*` options, the `__wkwctobag_bagisto_*` meta and `wp-content/uploads/bagisto-sync-for-woocommerce-logs/` manually.

**Why does the repository contain built files in `assets/`?**
WordPress plugins are distributed as ready-to-run directories, so `assets/js/admin.js` and `assets/css/admin.css` must be present in a clone, a source archive and the WordPress.org package. They are generated from `src/` — rebuild with `npm run buildr` rather than editing them.

---

## Security

**Do not report security vulnerabilities through public GitHub issues.** The full policy, including private reporting channels, scope and response targets, is in **[SECURITY.md](SECURITY.md)**.

Operational notes for anyone running this plugin:

- The Bagisto Personal Access Token is stored **unencrypted** in the `wkwctobag_bagisto_admin_token` WordPress option — the way WordPress stores integration credentials generally. Anyone with database access, or with a capability that lets them read arbitrary options, can read it. Scope the token as tightly as Bagisto allows and rotate it if the site is compromised.
- Sync log files contain API request/response detail, which can include customer names, e-mail addresses and order contents. They live under `wp-content/uploads/bagisto-sync-for-woocommerce-logs/` and are protected by an `index.php` stub and an `.htaccess` that denies access using both Apache 2.2 and Apache 2.4 syntax. **`.htaccess` has no effect on nginx** — add a server-level `deny all` rule there (the exact snippet is in [SECURITY.md](SECURITY.md#hardening-notes-for-site-operators)), or set a short retention period.
- The plugin's admin menus are gated on `manage_options` by default (filterable via `wkwctobag_modify_capabilities`); every AJAX endpoint requires a valid nonce **and** the `manage_woocommerce` capability. Review both before granting either capability to a role you do not fully trust.
- Outbound Bagisto API calls verify TLS certificates. See [TLS and self-signed certificates](#tls-and-self-signed-certificates).

---

## Contributing

Contributions are welcome. Please read **[CONTRIBUTING.md](CONTRIBUTING.md)** for the development setup, coding standards, the security requirements for new endpoints, the built-asset policy and the pull-request checklist.

In short:

1. Open an issue describing the bug or proposal before starting substantial work.
2. Branch from `main` and keep pull requests focused on a single change.
3. Match the existing conventions: WordPress Coding Standards for PHP, one class per `class-*.php` file, `wkwctobag_` / `WKWCTOBAG_` prefixes, and gettext-wrapped user-facing strings using the `bagisto-sync-for-woocommerce` text domain.
4. If you change anything in `src/`, run `npm run buildr` and commit the regenerated `assets/js/admin.js` and `assets/css/admin.css` — built assets are tracked in this repository.
5. If you add or change translatable strings, run `npm run i18n` and commit the updated `.pot`.
6. Run `composer check` and `npm run i18n:check` before opening the PR, add a `CHANGELOG.md` entry under `## [Unreleased]`, and describe how you tested (WordPress, WooCommerce, PHP and Bagisto versions).

All participants are expected to follow the [Code of Conduct](CODE_OF_CONDUCT.md). Release and versioning procedure: [RELEASING.md](RELEASING.md).

---

## Support

### Where to go

| I want to… | Go to |
| --- | --- |
| Report a reproducible bug in this plugin | [Open an issue](https://github.com/bagisto/bagisto-sync-for-woocommerce/issues/new/choose) using the bug report template |
| Request a feature | [Open an issue](https://github.com/bagisto/bagisto-sync-for-woocommerce/issues/new/choose) using the feature request template |
| Report a security vulnerability | **Not** a public issue — follow [SECURITY.md](SECURITY.md) |
| Get help with my specific store, or commission custom work | [Webkul support desk](https://webkul.com/ticket/) |
| Follow the illustrated setup and usage guide | [User documentation](https://wpdoc.webkul.com/bagisto-connector/) |
| Ask a general Bagisto question | [Bagisto community](https://bagisto.com/en/) |
| Ask a general WooCommerce question | [WooCommerce support](https://woocommerce.com/support/) |

### Support boundaries

This is an open-source project maintained on a best-effort basis. To set expectations honestly:

**In scope**

- Reproducible defects in this plugin's own code, on a supported version matrix (see [Requirements](#requirements-and-compatibility)).
- Documentation that is wrong, missing or misleading.
- Security vulnerabilities in this plugin, through the private channel.

**Out of scope for GitHub issues**

- Store-specific debugging, migrations, and "it doesn't work on my site" reports without a reproduction — these belong on the [support desk](https://webkul.com/ticket/), which is a commercial channel.
- Custom development, paid feature work and integration consulting.
- Bugs in WordPress, WooCommerce, Bagisto, or third-party plugins and themes — report those to their maintainers.
- Conflicts caused by another plugin or a customised theme, unless you can show this plugin is at fault.
- Versions outside the supported matrix, including end-of-life PHP.
- Anything requiring access to your site, database or Bagisto credentials. **Maintainers will never ask for credentials, and you should never share them in an issue.**

There is **no service-level agreement** on GitHub issues. If you need guaranteed response times, use the commercial support desk.

If you find the plugin useful and would like to support further development, see the [Webkul payment page](https://marketplace.webkul.com/knowledgebase/make-payment-customization/).

---

## Releases and upgrading

### Versioning

This project follows [Semantic Versioning](https://semver.org/). Given `MAJOR.MINOR.PATCH`:

| Bump | Means |
| --- | --- |
| **MAJOR** | A breaking change — an option name, meta key, hook name or filter signature changed or was removed, or the minimum WordPress/WooCommerce/PHP/Bagisto version was raised in a way that drops support. |
| **MINOR** | Backward-compatible new functionality — a new sync entity, setting or filter. |
| **PATCH** | Backward-compatible bug fixes, security fixes, documentation and packaging changes. |

The following are treated as a **public contract** and only change in a MAJOR release: the `wkwctobag_*` option names, the `__wkwctobag_bagisto_*` meta keys, the `wkwctobag_modify_capabilities` filter, the `wkwctobag_settings_tab_*` actions, the `wkwctobag_*` AJAX action names, the `wkwctobag_delayed_*` scheduled events and the `wp_wkwctobag_sync_<type>_*` background-process hook prefixes.

Releases are tagged `vX.Y.Z` and published on the [releases page](https://github.com/bagisto/bagisto-sync-for-woocommerce/releases). Every release is described in [CHANGELOG.md](CHANGELOG.md).

### Upgrading

1. **Read the changelog** for the target version first, and pay attention to anything under *Security* or *Changed*.
2. **Back up** your database and the plugin directory. The plugin stores WooCommerce↔Bagisto ID mappings in post, term, user and order meta — losing them means a re-sync creates duplicates in Bagisto.
3. **Let any running sync finish.** Check **WC to Bagisto → Bulk Sync** for an active progress bar before upgrading; upgrading mid-queue can leave items unprocessed.
4. **Upgrade on staging first** if you have one, then verify the connection on **WC to Bagisto → Connection** with **Test Connection**.
5. **After upgrading**, run one small sync (a single product via the list-table bulk action) and check **WC to Bagisto → Logs** before resuming normal operation.

There is no database schema to migrate — the plugin stores everything in WordPress options and meta — so upgrades do not run an update routine.

### Downgrading

Downgrading is supported by replacing the plugin directory with the older version, because no schema migration runs. Settings written by a newer version are ignored by an older one rather than breaking it. Downgrading across a MAJOR version is not supported.

### Uninstalling

The plugin deliberately ships **no uninstall routine**, so deleting it leaves its settings, ID mappings and log files in place and you can reinstall without re-syncing. See [What happens to my data if I uninstall the plugin?](#faq) for how to remove them manually.

---

## License

This plugin is released under the **GNU General Public License v3.0 or later**, as declared in the plugin header (`License: GPLv3 or later`) and in the bundled `LICENSE` / `license.txt` files.

```
This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE. See the GNU General Public License for more details.
```

Copyright (C) 2026 Webkul.

Full text: [LICENSE](LICENSE) · <https://www.gnu.org/licenses/gpl-3.0.html>

The same declaration is made in the plugin header (with an SPDX identifier), `readme.txt`, `package.json` and `composer.json`. Third-party material bundled into or depended on by the plugin is inventoried in **[THIRD-PARTY-NOTICES.md](THIRD-PARTY-NOTICES.md)**.

<!-- TODO: confirm the exact legal entity name to use as the copyright holder
     (for example "Webkul Software Pvt. Ltd." rather than "Webkul") and update
     LICENSE-adjacent notices consistently if it differs. -->

---

## Credits and acknowledgements

- Developed and maintained by **[Webkul](https://webkul.com/)**.
- Built for **[Bagisto](https://bagisto.com/)** and **[WooCommerce](https://woocommerce.com/)**.
- Background queue processing extends `WP_Background_Process` from the [wp-background-processing](https://github.com/deliciousbrains/wp-background-processing) library (Delicious Brains / A5hleyRich), as bundled by WooCommerce. This plugin does not vendor a copy of it; verify its licence terms before redistributing one.
- The compiled admin bundle includes [jQuery](https://jquery.com/) (MIT), [Select2](https://select2.org/) (MIT) and `regenerator-runtime` (MIT); their notices are preserved in `assets/js/admin.js.LICENSE.txt`.
- Build tooling: [Webpack](https://webpack.js.org/), [Babel](https://babeljs.io/), [Grunt](https://gruntjs.com/) with `grunt-wp-i18n` and `grunt-checktextdomain`, and [`@wordpress/scripts`](https://www.npmjs.com/package/@wordpress/scripts).
- The Bagisto and WooCommerce names and logos are trademarks of their respective owners and are used here for identification only.

---

## Project status and known limitations

**Status:** version `1.0.0` — initial release. Actively developed; the public API surface (filters, option names, meta keys) may still change between minor versions.

Known limitations in the current release:

- **One-way sync only.** Data flows WooCommerce → Bagisto. Changes made in Bagisto are never pulled back, and deletions in WooCommerce are not propagated (except for tax rate deletions, which only clear the local mapping).
- **Bagisto attribute family is hardcoded to ID `1`** when creating products, so products always land in Bagisto's default attribute family.
- **Locale is hardcoded to `en`** when writing category translations and reading attribute option labels; non-English Bagisto installations may not map correctly.
- **Order sync creates temporary virtual products** in Bagisto to represent shipping charges, order fees and price-adjusted lines. They are deleted after the order is placed, but an interrupted sync can leave `wc-shipping-fee-demo`, `wc-custom-item-*`, `wc-fee-item-*` or `wc-demo-item-*` products behind.
- **Guest orders without a billing e-mail** are synced under a generated `guest-<phone>@example.com` (or `guest-checkout@example.com`) address.
- **Bulk product sync only covers published products**; drafts and private products are skipped.
- **Failed items retry three times** and are then recorded as permanently failed; there is no UI to re-queue them individually — re-run the relevant sync instead.
- **No uninstall routine.** Options, user/term/post/order meta and log files are left in place when the plugin is deleted.
- **No automated test suite.** Continuous integration runs PHP linting across 7.4–8.4, WordPress/WooCommerce coding standards (clean and blocking), WordPress Plugin Check, an i18n text-domain check, a build reproducibility check and package validation — but there are no unit or integration tests yet, so functional changes must be verified manually.
- **The admin bundle ships its own copy of jQuery and Select2** (~213 KB minified) while also declaring `jquery` and `select2` as script dependencies, so jQuery can be loaded twice on plugin screens. Fixing this would change the shipped bundle and risks a UI regression, so it is deliberately deferred.
- **A few admin strings rendered from JavaScript are not translatable** (for example the injected attribute bulk-action toolbar labels). Fixing this requires editing `src/` and rebuilding the bundle.
- **Bulk sync materialises the full record set in one request** (`'number' => -1` / `'limit' => -1`) before queueing. Very large catalogues may exhaust memory at that point; sync in smaller selections from the list tables if you hit this.
- **The build toolchain carries known npm advisories** (see [Dependency security](#dependency-security)). They are development-only and do not reach the distributed plugin; the production dependency tree is clean.
- The Bagisto REST API surface used here evolves between Bagisto releases; verify against your Bagisto version before upgrading either side.
