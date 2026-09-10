# Changelog

All notable changes to **Bagisto Sync for WooCommerce** are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

> **Maintainer note:** if `1.0.0` has not been published anywhere yet, fold the
> `[Unreleased]` section below into `[1.0.0]` and delete this note. If `1.0.0`
> *has* been published, release the entries below as `1.0.1` instead and bump
> the version in `bagisto-sync-for-woocommerce.php` (header + `WKWCTOBAG_VERSION`
> + `WKWCTOBAG_SCRIPT_VERSION`), `readme.txt` (`Stable tag`) and `package.json`.
> See [RELEASING.md](RELEASING.md).

## [Unreleased]

Release-readiness hardening ahead of the first public open-source release. No
feature was added or removed, and no admin screen, layout or workflow changed.

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

### Added

- `README.md` — full project documentation.
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

### Removed

- `assets/js/forms.js` and `assets/js/store.js` — 99-byte empty webpack
  bootstraps with no entry point in `webpack.config.js`, never enqueued and
  never referenced anywhere in the codebase.

### Fixed

- `Gruntfile.js` no longer carries an executable permission bit.

## [1.0.0] - 2026-08-05

### Added

- Initial release.
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

[Unreleased]: https://github.com/bagisto/woocommerce-connector/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/bagisto/woocommerce-connector/releases/tag/v1.0.0
