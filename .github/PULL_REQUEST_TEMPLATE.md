## Summary

<!-- What does this change and why? Link the issue it closes: "Closes #123". -->

## Type of change

- [ ] Bug fix (non-breaking)
- [ ] New feature (non-breaking)
- [ ] Breaking change (existing behaviour, option name, meta key or hook changes)
- [ ] Security fix
- [ ] Documentation / packaging / tooling only

## Checklist

- [ ] `composer check` passes (PHP lint + coding standards).
- [ ] `npm run i18n:check` passes.
- [ ] If I changed `src/`, I ran `npm run buildr` and committed the regenerated `assets/js/admin.js` / `assets/css/admin.css` in the same commit.
- [ ] If I changed `src/`, I did **not** leave unrelated toolchain churn in the bundle diff.
- [ ] If I added or changed translatable strings, I ran `npm run i18n` and committed the `.pot`.
- [ ] Every new user-facing string uses the `bagisto-sync-for-woocommerce` text domain.
- [ ] Any new admin endpoint verifies **both** a nonce **and** `current_user_can()`.
- [ ] No new `'sslverify' => false`, no new `wp_ajax_nopriv_` handler for store data.
- [ ] No secrets, tokens, customer data, log files, dumps, `node_modules/` or `vendor/` in the diff.
- [ ] Existing option names, meta keys, hook names and filter signatures are unchanged (or the break is called out above).
- [ ] `CHANGELOG.md` updated under `## [Unreleased]`.

## How was this tested?

<!-- Describe the manual verification you performed. -->

| | Version |
| --- | --- |
| WordPress | |
| WooCommerce | |
| PHP | |
| Bagisto | |

## Screenshots

<!-- Only if the change affects an admin screen. Redact store data. -->
