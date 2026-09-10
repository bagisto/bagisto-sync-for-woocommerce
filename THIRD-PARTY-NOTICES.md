# Third-Party Notices

Bagisto Sync for WooCommerce is distributed under the **GNU General Public
License v3.0 or later** (see [`LICENSE`](LICENSE)). It incorporates or depends on
the third-party material listed below.

This file is a good-faith inventory produced by inspecting the repository. Items
marked ⚠️ require confirmation by the project owner before public release.

---

## 1. Code bundled into the distributed plugin

These libraries are compiled into `assets/js/admin.js` by webpack and are
therefore redistributed with the plugin. Their notices are preserved verbatim in
[`assets/js/admin.js.LICENSE.txt`](assets/js/admin.js.LICENSE.txt), which webpack
generates alongside the bundle.

| Library | Version | License | Notes |
| --- | --- | --- | --- |
| [jQuery](https://jquery.com/) | 3.7.1 | MIT | Copyright OpenJS Foundation and other contributors. GPL-compatible. |
| [Select2](https://select2.org/) | 4.1.0-rc.0 | MIT | Copyright Kevin Brown, Igor Vaynberg, and Select2 contributors. GPL-compatible. |
| `regenerator-runtime` | bundled by Babel | MIT | Copyright (c) 2014-present, Facebook, Inc. GPL-compatible. |

All three are MIT licensed and therefore compatible with GPL-3.0-or-later
redistribution, provided the copyright and permission notices are retained —
which they are, in `assets/js/admin.js.LICENSE.txt`.

> **Note for maintainers:** WordPress already registers jQuery, and WooCommerce
> registers a Select2 fork (`selectWoo`). Bundling private copies is redundant
> and is discouraged by the WordPress.org plugin guidelines. Changing this would
> alter the shipped bundle and carries a regression risk, so it has been left
> as-is and recorded as a known limitation in the README.

## 2. Code depended on but NOT bundled

| Component | Provided by | Notes |
| --- | --- | --- |
| `WP_Background_Process` / `WP_Async_Request` | WooCommerce, at `woocommerce/includes/libraries/wp-background-process.php` | Originates from [wp-background-processing](https://github.com/deliciousbrains/wp-background-processing) (Delicious Brains / A5hleyRich). **No copy of this library is vendored in this repository** — the plugin extends the class WooCommerce loads. ⚠️ If a copy is ever vendored, its licence text must be added here and to the repository. |
| WordPress core, WooCommerce | Host site | GPLv2-or-later. Not redistributed by this plugin. |
| Dashicons | WordPress core | Used only by CSS class name; no font file is redistributed by this plugin. |

## 3. Build-time dependencies (not redistributed)

The packages in `devDependencies` (webpack, Babel, Grunt, `@wordpress/scripts`,
`sass`, and their transitive dependencies) are used only to produce the built
assets. They are **not** shipped in the plugin package and are not redistributed.
Their licences are recorded in `package-lock.json` and in each package's own
`node_modules` directory after `npm install`.

The `dependencies` block additionally lists `@wordpress/element`,
`@wordpress/hooks` and `@wordpress/i18n` (all GPL-2.0-or-later). Inspection of
the built bundle confirms **none of them, and no copy of lodash, is present in
`assets/js/admin.js`** — they are referenced only by the Babel configuration at
build time.

## 4. Images and other assets

| File | Content | Status |
| --- | --- | --- |
| `assets/images/woo-logo.png` | The WooCommerce wordmark | ⚠️ **Requires manual verification.** "WooCommerce" and its logo are trademarks of Automattic Inc. Trademarks are not covered by the GPL. Confirm that the intended use (identifying the integration inside the plugin's own admin screen) is permitted, or replace the asset. |
| `assets/images/bagisto-logo.png` | The Bagisto wordmark | ⚠️ **Requires manual verification.** "Bagisto" and its logo are trademarks of Webkul. As Webkul owns both this plugin and Bagisto this is expected to be fine, but confirm it explicitly. |
| `assets/images/top-logo.svg` | Plugin menu icon | Hand-authored SVG (two rectangles and a path). No third-party content detected. Assumed to be Webkul's own work — ⚠️ confirm. |

No fonts are redistributed with this plugin.

## 5. Fonts

None. The plugin uses the host site's admin typography and WordPress core's
Dashicons icon font; neither is redistributed here.

## 6. Reporting a licensing concern

If you believe any material in this repository is incorrectly attributed or
incompatibly licensed, please open an issue or contact
<https://webkul.com/ticket/>.
