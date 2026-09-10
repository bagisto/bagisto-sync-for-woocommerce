=== Bagisto Sync for WooCommerce ===
Contributors: webkul
Donate link: https://marketplace.webkul.com/knowledgebase/make-payment-customization/
Requires at least: 6.5
Tested up to: 7.1
Stable tag: 1.0.0
Requires PHP: 7.4
Tested up to PHP: 8.4
WC requires at least: 9.0
WC tested up to: 10.9
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Tags: bagisto, bagisto sync, woocommerce bagisto, sync bagisto, bagisto sync for woocommerce

A WooCommerce addon by Webkul to seamlessly connect and sync WooCommerce store data with Bagisto.

== Description ==

Bagisto Sync for WooCommerce provides store owners with the functionality to seamlessly connect their WooCommerce store with Bagisto. It allows the store data, including products, categories, customers, orders, taxes, coupons, and other information, to be synchronized from WooCommerce to Bagisto with ease.

The connector helps reduce manual work by automatically sending updates from WooCommerce to Bagisto whenever changes are made. It also processes synchronization tasks in the background and maintains detailed sync logs, making it easy to manage large amounts of data and track every synchronization activity.

= Features =

* Secure Token Authentication: Connect securely to Bagisto using Personal Access Tokens (PAT) with live connection verification.
* Dedicated Settings Tabs: Individual settings tabs for Users, Customers, Products, Categories, Attributes, Taxes, and Orders.
* Selective Bulk Sync: Trigger batch sync directly from native Users, Categories, Products, and Orders list tables.
* Auto-Sync & Update Toggles: Fine-grained enable/disable controls for automatic sync on creation and updates.
* Category & Tax Mapping: Dedicated 1-to-1 mapping tables for WooCommerce Categories and Tax Classes.
* Background Queue Processing: Non-blocking queue running every 5 minutes with automated retry for failed tasks.
* Real-Time Polling & Progress: Live polling progress bar and floating toast notifications.
* Logging & Maintenance: Embedded log viewer with request/response details, raw log download, and daily cron cleanup.

If you find this plugin helpful and would like to support further development, please consider [donating here](https://marketplace.webkul.com/knowledgebase/make-payment-customization/).

== Installation ==

1. Upload the `bagisto-sync-for-woocommerce` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure the plugin using the plugin menu.

== Frequently Asked Questions ==

= How do I connect WooCommerce with Bagisto? =
Go to Bagisto Sync for WooCommerce > Connection in your WordPress admin panel, enter your Bagisto Store URL and Admin API Token, and click Connect Store.

= Where can I request support or report issues? =
For any queries or custom requirements, please submit a ticket at [Webkul Support](https://webkul.com/ticket/).

= How do I report a security vulnerability? =
Please do not report security issues publicly. Follow the private disclosure process described in SECURITY.md in the project's source repository, or submit a ticket at https://webkul.com/ticket/ marked as a security report.

= Does data sync back from Bagisto to WooCommerce? =
No. Synchronisation is one way: WooCommerce to Bagisto.

= What happens to my data if I delete the plugin? =
The plugin does not ship an uninstall routine, so its settings, the WooCommerce-to-Bagisto ID mappings and the sync log files are intentionally left in place if you delete it. This means you can reinstall without re-syncing. Remove the `wkwctobag_*` options and the `wp-content/uploads/bagisto-sync-for-woocommerce-logs` directory manually if you want a clean removal.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release of Bagisto Sync for WooCommerce.
