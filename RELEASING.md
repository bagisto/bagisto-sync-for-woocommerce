# Release and versioning guide

This document is for maintainers cutting a release of Bagisto Sync for WooCommerce.

## Versioning policy

The project follows [Semantic Versioning 2.0.0](https://semver.org/spec/v2.0.0.html):

| Bump | When |
| --- | --- |
| **MAJOR** (`2.0.0`) | A breaking change: an option name, meta key, hook name or filter signature is removed or changed incompatibly; the minimum WordPress, WooCommerce, PHP or Bagisto version is raised in a way that drops support. |
| **MINOR** (`1.1.0`) | New functionality that is backward compatible — a new sync entity, a new setting, a new filter. |
| **PATCH** (`1.0.1`) | Backward-compatible bug fixes, security fixes, documentation and packaging changes. |

Anything below is a **public contract** and may only change in a MAJOR release:

- The `wkwctobag_*` option names.
- The `__wkwctobag_bagisto_*` post, term, user and order meta keys.
- The `wkwctobag_modify_capabilities` filter and the `wkwctobag_settings_tab_*` actions.
- The `wkwctobag_*` AJAX action names and the `wkwctobag_delayed_*` scheduled event names.
- The `wp_wkwctobag_sync_<type>_*` background-process hook prefixes.

## The version number lives in four places

Keep all four in sync — a mismatch breaks WordPress.org's stable-tag resolution
and cache-busting for the built assets:

| File | Field |
| --- | --- |
| `bagisto-sync-for-woocommerce.php` | `* Version:` header |
| `bagisto-sync-for-woocommerce.php` | `WKWCTOBAG_VERSION` constant |
| `bagisto-sync-for-woocommerce.php` | `WKWCTOBAG_SCRIPT_VERSION` constant (bump whenever `assets/` changes, so browsers re-fetch) |
| `readme.txt` | `Stable tag:` |
| `package.json` | `"version"` |

Verify with:

```bash
grep -n "Version:\|WKWCTOBAG_VERSION\|WKWCTOBAG_SCRIPT_VERSION" bagisto-sync-for-woocommerce.php
grep -n "^Stable tag:" readme.txt
grep -n '"version"' package.json
```

## Compatibility headers

Update these whenever you test against a newer platform release:

| File | Fields |
| --- | --- |
| `bagisto-sync-for-woocommerce.php` | `Requires at least`, `Tested up to`, `Requires PHP`, `Tested up to PHP`, `WC requires at least`, `WC tested up to` |
| `readme.txt` | the same six fields |

Never claim a "tested up to" version you have not actually tested against.

## Release checklist

1. **Confirm the working tree is clean** and you are on `main` with everything merged.
2. **Move `## [Unreleased]` in `CHANGELOG.md`** to a new version heading with today's date, and open a fresh empty `[Unreleased]` section.
3. **Mirror the changelog into `readme.txt`** under `== Changelog ==`, and add an `== Upgrade Notice ==` entry if the release needs operator action.
4. **Bump the version** in all the places listed above.
5. **Rebuild the assets:**
   ```bash
   npm ci
   npm run buildr
   ```
   Commit `assets/js/admin.js` and `assets/css/admin.css` if they changed.
6. **Regenerate translations:**
   ```bash
   npm run i18n
   ```
   Commit `languages/bagisto-sync-for-woocommerce.pot`.
7. **Run the full validation suite:**
   ```bash
   composer install
   composer check                 # PHP lint + WordPress/WooCommerce coding standards
   npm run i18n:check             # text-domain verification
   npm audit --omit=dev           # production dependency advisories
   ```
   Also run WordPress [Plugin Check](https://wordpress.org/plugins/plugin-check/) against a real install.
8. **Build the package and inspect it:**
   ```bash
   npm run zip
   unzip -l bagisto-sync-for-woocommerce.zip
   ```
   Confirm the archive contains **no** `src/`, `bin/`, `node_modules/`, `vendor/`, `.git*`, `.github/`, `composer.*`, `package-lock.json`, build configs, logs, `.env` files or database dumps.

   > `wp-scripts plugin-zip` follows npm-packlist, so it always adds `package.json`, `README.md`, `LICENSE` and `CHANGELOG.md` regardless of the `files` list. That is expected — none of them contain anything sensitive, and the plugin's own Plugin Check filter already whitelists `package.json`. The CI `package` job encodes exactly this policy.

   The archive should contain around 61 files and weigh roughly 330 KB.
9. **Smoke-test the built package** on a clean WordPress install: activate, connect to Bagisto, run one bulk sync of each entity type, and confirm the Logs screen shows no new errors.
10. **Tag the release** as `vX.Y.Z` and push the tag.
11. **Publish** — create the GitHub release with the changelog section as the release notes, and deploy to WordPress.org if applicable.

## Pre-publication checklist (first public release only)

The canonical repository is <https://github.com/bagisto/woocommerce-connector>.

**Decisions that must be made by the project owner**

- [ ] Confirm the legal entity name used as the copyright holder. The code currently says `Copyright (C) 2026 Webkul`; if the correct holder is a full legal entity name, update the main plugin file, `README.md` and `composer.json` consistently.
- [ ] Confirm trademark permission for the bundled WooCommerce and Bagisto wordmarks in `assets/images/` — see [THIRD-PARTY-NOTICES.md](THIRD-PARTY-NOTICES.md). Trademarks are not covered by the GPL.
- [ ] Decide whether the Composer package name should stay `webkul/bagisto-sync-for-woocommerce` or move to `bagisto/…` to match the GitHub organisation.
- [ ] Replace the remaining `TODO` placeholders in `SECURITY.md` and `CODE_OF_CONDUCT.md` with monitored contact addresses (currently they fall back to the general support desk).

**Repository settings to configure on GitHub**

- [ ] Settings → Code security → enable **Private vulnerability reporting** (`SECURITY.md` links to it).
- [ ] Settings → Code security → enable **Dependabot alerts**, **Dependabot security updates** and **secret scanning**.
- [ ] Settings → Branches → protect `main`: require a pull request and require the CI checks to pass.
- [ ] Settings → General → set the description, website and topics.
- [ ] Verify the first CI run is green before tagging anything.

**WordPress.org (only if also publishing there)**

- [ ] Add listing assets (banner, icon, screenshots) and a `== Screenshots ==` section to `readme.txt`.
- [ ] Confirm the `Contributors:` slug in `readme.txt` matches a real WordPress.org account.
