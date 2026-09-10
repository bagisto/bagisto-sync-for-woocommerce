# Security Policy

## Supported versions

Security fixes are provided for the latest released version of Bagisto Sync for WooCommerce.

| Version | Supported |
| ------- | --------- |
| 1.0.x   | ✅        |
| < 1.0   | ❌        |

## Reporting a vulnerability

**Please do not report security vulnerabilities through public GitHub issues, pull requests, or discussion threads.**

Report them privately using either of the following channels:

1. **GitHub private vulnerability reporting** — <https://github.com/bagisto/woocommerce-connector/security/advisories/new>, or the repository's **Security** tab → **Report a vulnerability**. This is the preferred channel.
   <!-- MAINTAINER TODO: enable Settings → Code security → "Private vulnerability reporting" before publishing, otherwise the link above 404s. -->
2. **Webkul support desk** — <https://webkul.com/ticket/>, marking the ticket clearly as a **security report** so it is routed privately.

<!-- TODO: replace the support-desk fallback with a dedicated security mailbox
     (for example security@webkul.com) once one is published, and add a PGP key
     fingerprint here if encrypted reports should be supported. -->

Please include as much of the following as you can:

- A description of the issue and the impact you believe it has.
- The plugin version, WordPress version, WooCommerce version, PHP version and Bagisto version.
- Step-by-step reproduction instructions, and a proof of concept if you have one.
- Whether the issue is already public anywhere.

Do **not** include real customer data, live API tokens or production credentials in a report. Redact them, and rotate any token that may have been exposed.

## What to expect

| Stage | Target |
| --- | --- |
| Acknowledgement of your report | within 3 business days |
| Initial assessment and severity triage | within 10 business days |
| Fix or documented mitigation | depends on severity; critical issues are prioritised |

We will keep you updated while we work on a fix, credit you in the changelog and release notes unless you ask us not to, and coordinate a disclosure date with you. Please give us a reasonable opportunity to release a fix before disclosing publicly.

## Scope

In scope:

- The PHP, JavaScript and CSS shipped in this repository.
- The plugin's admin screens, AJAX endpoints, cron handlers and log handling.
- The way the plugin stores and transmits Bagisto API credentials.

Out of scope:

- Vulnerabilities in WordPress core, WooCommerce, Bagisto, or any third-party plugin or theme — report those to their respective maintainers.
- Vulnerabilities that require an already-compromised administrator account, direct database access, or filesystem access.
- Findings that only apply to a site whose own configuration disables WordPress security features.
- Automated scanner output with no demonstrated impact.

## Hardening notes for site operators

These are known, documented operational characteristics rather than vulnerabilities. Review them when deploying the plugin.

- **The Bagisto Personal Access Token is a full administrative credential.** It is stored unencrypted in the `wkwctobag_bagisto_admin_token` WordPress option, which is how WordPress stores integration credentials generally. Anyone with database access or with a capability that lets them read arbitrary options can read it. Scope the token as narrowly as Bagisto permits, and rotate it immediately if the WordPress site is ever compromised.
- **Sync logs contain personal data.** Log files under `wp-content/uploads/bagisto-sync-for-woocommerce-logs/` record API request and response detail, which can include customer names, e-mail addresses and order contents. The plugin writes an `index.php` stub and an `.htaccess` denying direct access (using both Apache 2.2 and Apache 2.4 syntax).
  **`.htaccess` has no effect on nginx.** On nginx, deny the directory in your server configuration, for example:

  ```nginx
  location ~* /wp-content/uploads/bagisto-sync-for-woocommerce-logs/ {
      deny all;
      return 403;
  }
  ```

  Consider also setting a short log retention period under **WC to Bagisto → Logs**.
- **TLS certificate verification is enabled** for all requests to the Bagisto API. If your Bagisto host presents a self-signed or otherwise untrusted certificate, install a valid certificate rather than disabling verification. See the note in [`README.md`](README.md#tls-and-self-signed-certificates) if you must override this in a local development environment.
- **Administrative capability.** Plugin screens require `manage_options` by default (filterable through `wkwctobag_modify_capabilities`); AJAX endpoints require `manage_woocommerce`. Review these before granting either capability to a role you do not fully trust.
