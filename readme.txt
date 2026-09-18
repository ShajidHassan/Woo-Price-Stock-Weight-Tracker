=== Price, Stock & Weight Tracker for WooCommerce ===
Contributors: mirailit
Tags: woocommerce, price history, stock history, product changes, audit log
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 3.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Keeps a complete history of price, stock, title and weight changes on WooCommerce products, with reports, CSV export and an out-of-stock list.

== Description ==

Every time a product's price, stock, title or weight changes, this plugin records what changed, what the old and new values were, who did it and when. It works with every way a product can be edited: the product editor, quick edit, bulk edit, the REST API, WP-CLI, importers, and stock reductions triggered by orders and refunds.

**Change History** shows all changes in one filterable list. Switch between price, stock, title and weight, search by product name, SKU or ID, narrow by the person who made the change or a date range, sort any column, and export the current view to CSV.

**Product editor box** shows the latest changes right on the product edit screen, including changes on each variation, with a link to the full history.

**Out of Stock** lists every product and variation that is currently out of stock with its category, total sales, and the date it went out of stock. Filter by category and search by name or SKU.

**Daily Report** summarises a day or a date range: price changes, stock changes, new products, orders received and orders completed, with a per-user breakdown and a print-friendly layout.

**Settings** let you choose which properties to track, how long to keep history, and whether to remove all data when the plugin is deleted.

= What is tracked =

* Regular price and sale price
* Stock status and stock quantity (including automatic reductions from orders)
* Product title
* Weight

Variations are tracked individually and shown under their parent product.

= Compatibility =

* WooCommerce High-Performance Order Storage (HPOS) is fully supported.
* Requires WooCommerce 7.1 or later.

= Developers =

The action `pswt_change_logged` fires after each change is recorded, with the product id, change type, old value and new value.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, or install it from Plugins > Add New.
2. Activate the plugin. The history table is created automatically.
3. Find the pages under the new **Product Tracker** menu in the WordPress admin.
4. Optionally adjust what is tracked under Product Tracker > Settings.

If you are upgrading from version 2.x, your existing price, stock, title and weight history is migrated into the new unified history table on first load. The old tables are left untouched.

== Frequently Asked Questions ==

= Does it track changes made through the REST API or importers? =

Yes. Changes are captured from WooCommerce's product save routine, so every editing path is covered, including WP-CLI, the REST API, CSV imports and third-party sync tools.

= Are stock reductions from orders recorded? =

Yes. When an order reduces stock, the quantity change is recorded and attributed to "System" unless a logged-in user triggered it.

= Which users can see the history? =

Anyone with the Manage WooCommerce capability, which by default means Administrators and Shop Managers. The Settings page requires the Manage Options capability.

= Does it slow down my store? =

No. Nothing runs on the front end. Changes are recorded with a single insert when a product is saved, and the admin pages use indexed queries with caching.

= Can I delete old history automatically? =

Yes. Set a retention period in days under Settings. A daily task removes anything older.

= What happens when a product is deleted? =

Its history is removed when the product is permanently deleted. Trashed products are hidden from the lists until restored.

== Screenshots ==

1. Change History with type tabs, filters and stat tiles.
2. Change History box on the product edit screen.
3. Out of Stock list.
4. Daily Report.
5. Settings.

== Changelog ==

= 3.0.0 =

* New - Single unified history table replacing four separate tables. Existing data is migrated automatically.
* New - Changes are captured from WooCommerce's product save routine, so quick edit, bulk edit, REST API, WP-CLI and importers are all tracked.
* New - Stock quantity tracking, including reductions from orders and refunds.
* New - Variations are tracked individually.
* New - Redesigned admin pages with stat tiles, type tabs, product thumbnails, badges and signed differences.
* New - Date range filter, sortable columns, per-page screen option and CSV export on the Change History page.
* New - Settings page to choose tracked properties, set a retention period and control uninstall behaviour.
* New - Daily Report rebuilt with quick date ranges and a print layout. Order counts now use the WooCommerce order API, so HPOS stores are supported.
* Fix - History table was not created on activation because the activation hooks were registered from an included file.
* Fix - Product data is no longer read from the raw request, which was unsanitized and missed most editing paths.
* Fix - All output is escaped and all database queries use prepared statements.
* Fix - Assets load only on the plugin's own screens.
* Fix - Dates are stored in UTC and displayed in the site timezone.

= 2.0.1 =

* Stock Out List page added.

= 2.0.0 =

* Daily Report page added.

= 1.0.0 =

* Initial release.

== Upgrade Notice ==

= 3.0.0 =
Major rewrite. Existing history is migrated automatically. The plugin now lives under a new "Product Tracker" menu.
