# Changelog

All notable changes to **Bagisto Sync for WooCommerce** are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

_Nothing yet._

## [1.0.0] - 2026-09-10

First public release.

The connector itself was built before this repository was opened. The sections
below record both what it ships and the release-readiness work carried out
immediately before publication, so that the security and packaging decisions
are on record rather than lost in history.

### Added

- Secure token authentication: connect to Bagisto using a Personal Access Token
  with live connection verification.
- Dedicated settings tabs for Users, Customers, Products, Categories,
  Attributes, Taxes and Orders.
- Selective bulk sync directly from the native Users, Categories, Products and
  Orders list tables.
- Auto-sync and auto-update toggles per entity for creation and update events.
- One-to-one mapping tables for WooCommerce categories and tax classes.
- Background queue processing with a five-minute health check and automated
  retry for failed tasks.
- Real-time polling progress bar and floating toast notifications.
- Embedded log viewer with request/response detail, raw log download, log
  rotation, a configurable retention policy and a daily cleanup cron.
- WooCommerce High-Performance Order Storage (HPOS) compatibility.
- `README.md` — full project documentation.
- Links to the user documentation at <https://wpdoc.webkul.com/bagisto-connector/>
  from `README.md`, `readme.txt` and the GitHub issue templates.
- `CONTRIBUTING.md`, `CODE_OF_CONDUCT.md`, `SECURITY.md`, `RELEASING.md`,
  `THIRD-PARTY-NOTICES.md`, `CHANGELOG.md`.
- `.gitignore` (expanded), `.gitattributes`, `.editorconfig`, `.distignore`.
- `composer.json` and `phpcs.xml.dist` for PHP linting and WordPress/WooCommerce
  coding standards. The plugin still has **no PHP runtime dependencies** and
  ships no `vendor/` directory.
- GitHub issue and pull-request templates, a Dependabot configuration, and a CI
  workflow running PHP lint, coding standards, WordPress Plugin Check, an
  i18n text-domain check and a build verification.
- Explicit `Copyright (C) 2026 Webkul` notice and an SPDX identifier in the main
  plugin file.

### Security

- **TLS certificate verification is now enforced on every request to the Bagisto API.**
  All outbound calls previously passed `'sslverify' => false`, and product-image
  downloads additionally forced `https_ssl_verify` to false. Because these
  requests carry a Bagisto administrator bearer token, a network attacker able to
  present any certificate could previously intercept it. Requests now use the
  WordPress default (verification enabled).
  *Impact:* a Bagisto host with a self-signed or otherwise untrusted certificate
  will now fail to connect until a valid certificate is installed. See
  [README](README.md#tls-and-self-signed-certificates) for the development-only
  override that uses WordPress core's own filters.
- **All admin AJAX endpoints now require the `manage_woocommerce` capability.**
  Fifteen endpoints previously verified only the nonce, so any authenticated user
  who could obtain the shared admin nonce could enumerate WordPress users
  (including e-mail addresses), enumerate Bagisto customers, and start bulk syncs.
- **Removed the unauthenticated user-search endpoint.** The
  `wp_ajax_nopriv_wkwctobag_get_searched_users` registration exposed a user
  search returning IDs, display names and e-mail addresses to logged-out
  visitors. Nothing in the plugin used it; the authenticated
  `wp_ajax_wkwctobag_get_searched_users` handler is unchanged.
- **`wkwctobag_check_sync_status` now verifies a nonce and capability.** It
  previously had neither, leaking sync progress to any authenticated user. The
  admin JavaScript already sent the nonce, so no rebuild was required.
- **Log downloads are now CSRF-protected.** `wkwctobag_download_log` verifies a
  nonce in addition to the existing `manage_woocommerce` check, and the download
  link is generated with `wp_nonce_url()`.
- **Log directory protection now works on Apache 2.4.** The generated `.htaccess`
  previously contained only `Deny from all`, an Apache 2.2 directive ignored by
  Apache 2.4 without `mod_access_compat`. It now emits both syntaxes.
  `.htaccess` still has no effect on nginx — see [SECURITY.md](SECURITY.md) for
  the server-level rule to add there.
- **Removed absolute-path disclosure from the class autoloader.** A failed class
  load printed the resolved server path to the browser; it now names the class
  instead.
- Replaced `json_encode()` with `wp_json_encode()` in the Bagisto request helper.

### Changed

- `package.json` now declares `GPL-3.0-or-later` instead of the incorrect `ISC`,
  matching the plugin header, `LICENSE` and `license.txt`.
- Moved `watch` and `@babel/eslint-parser` from `dependencies` to
  `devDependencies`; both are build-time tools and neither is part of the
  distributed bundle. This clears three high-severity advisories from the
  production dependency tree.
- `package.json` `files` now lists `CHANGELOG.md` in place of the `changelog.txt`
  that never existed in the repository.
- Pinned `lodash` to `^4.18.1` via an `overrides` entry, clearing two
  high-severity advisories (GHSA-r5fr-rjxr-66jc, GHSA-f23m-r3pf-42rh). `lodash`
  is a transitive build-time dependency of `@wordpress/i18n` and is not present
  in the distributed bundle.
- Applied `npm audit fix` to the build toolchain. No declared dependency version
  changed; only transitive resolutions in `package-lock.json` moved. The
  production dependency tree now reports **0 vulnerabilities**.
- The distributed bundle (`assets/js/admin.js`, `assets/css/admin.css`) was
  verified byte-for-byte identical before and after every dependency change.
- Corrected the copyright holder, package name and stale
  `Report-Msgid-Bugs-To` slug in the `.pot` header. No translatable string changed.
- `readme.txt`: `Donate link` is now a header field rather than an invalid
  `== Donate Link ==` section, `Contributors` uses the lowercase WordPress.org
  slug, and new FAQ entries cover security reporting, sync direction and
  uninstall data retention.
- Aligned the Plugin Check ignore lists in the main plugin file with the files
  the repository actually contains.
- Corrected the stale product name printed by `bin/build.sh`.

### Removed

- `assets/js/forms.js` and `assets/js/store.js` — 99-byte empty webpack
  bootstraps with no entry point in `webpack.config.js`, never enqueued and
  never referenced anywhere in the codebase.

### Fixed

- `Gruntfile.js` no longer carries an executable permission bit.
- **Brought the codebase to zero WordPress/WooCommerce coding-standard
  violations** (from 149 errors and 21 warnings). 144 of these were resolved by
  writing the missing documentation — `@param`, `@throws`, `@var` and `@package`
  tags, class and function docblocks, terminal punctuation on inline comments —
  and by expanding 23 short ternaries into their long form. The remaining 26 are
  deliberate implementation choices, now recorded as explicit `phpcs:ignore`
  annotations with written justifications, or as a documented rule exclusion in
  `phpcs.xml.dist`. Nothing was suppressed without a stated reason.
  *No behaviour changed:* 48 of the 49 PHP files have byte-identical token
  streams before and after, and the one file that does differ
  (`class-wkwctobag-sync-customer.php`) only had its short ternaries expanded,
  with each WooCommerce getter hoisted into a local so it is still evaluated
  exactly once. The rewritten expressions were checked against the originals
  across 1,110 truth-table combinations with zero divergence.
- The `coding-standards` CI job is now blocking rather than advisory.
- **Stopped this plugin from contributing to the WordPress 6.7+
  `_load_textdomain_just_in_time` notice for the `woocommerce` text domain.**
  `WP_Background_Process::schedule_cron_healthcheck()` — the copy WooCommerce
  bundles — builds its schedule label with `__( 'Every %d minutes', 'woocommerce' )`
  and is hooked to `cron_schedules` from the parent constructor. This plugin
  creates one queue per sync type on `plugins_loaded`, which placed seven
  `woocommerce`-translating callbacks ahead of any code reading the cron
  schedules. `Wkwctobag_Background_Process` now overrides that method and
  returns the schedules untouched, because `wkwctobag_register_cron_schedules()`
  in the main plugin file already registers the same seven keys at the same 300
  second interval using this plugin's own text domain. Verified on WordPress
  6.8.3 with WooCommerce 9.6.0 and on WordPress 7.1 with WooCommerce 11.0.0:
  the same seven schedule keys, the same 300 second intervals, the queue API
  intact, and zero notices.
- Excluded `CODE_OF_CONDUCT.md`, `CONTRIBUTING.md`, `RELEASING.md` and
  `THIRD-PARTY-NOTICES.md` from Plugin Check's `unexpected_markdown_file`
  check. They are contributor documentation and are already excluded from the
  distributed package.
- Updated every repository URL after the GitHub repository was renamed from
  `bagisto/woocommerce-connector` to `bagisto/bagisto-sync-for-woocommerce`.
- **Fixed a fatal error when `WP_Background_Process` is unavailable.** The sync
  queues extend that class, which this plugin does not bundle — it comes from
  the copy WooCommerce ships and loads lazily. WordPress Plugin Check runs its
  runtime checks with every other plugin filtered out of `active_plugins`, so
  WooCommerce is absent and the plugin fataled with
  `Class "WP_Background_Process" not found`. The bootstrap now loads
  WooCommerce's `wp-async-request.php` and `wp-background-process.php`
  explicitly when the class is not already present, and bails out quietly if
  neither is available, instead of fataling. The queue class file carries a
  matching guard. This also removes a latent production risk: the plugin
  previously depended on some other code path having referenced a
  `WC_Background_Process` subclass first.
  Verified on WordPress 6.8.3 with WooCommerce 9.6.0 and WordPress 7.1 with
  WooCommerce 11.0.0 — identical queue behaviour with WooCommerce present, and
  a clean boot with WooCommerce absent. Plugin Check no longer reports a fatal.
- The CI dependency-audit job no longer raises a failure annotation for
  build-toolchain advisories that never ship to a user's site. Only the
  production tree (`npm audit --omit=dev`) can fail that job.
- Raised `Tested up to` from 7.0 to 7.1 in `readme.txt` and the plugin header,
  clearing Plugin Check's `outdated_tested_upto_header` error. Backed by a run
  on WordPress 7.1 with WooCommerce 11.0.0 and PHP 8.4: all 14 core classes
  autoload, all 7 sync classes construct, the front-end sync hooks register,
  the `wkwctobag_modify_capabilities` filter is honoured, all 7 cron schedules
  register at 300 seconds, the log-cleanup event is scheduled, and there are no
  notices, warnings or fatals. WordPress Plugin Check now reports no errors and
  no warnings, including with `--include-experimental`.
  Note that `WC tested up to` is still 10.9 even though the verification stack
  used WooCommerce 11.0.0; raise it separately when you are ready to make that
  claim too.

[Unreleased]: https://github.com/bagisto/bagisto-sync-for-woocommerce/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/bagisto/bagisto-sync-for-woocommerce/releases/tag/v1.0.0
