# Price, Stock & Weight Tracker for WooCommerce

Keeps a complete history of price, stock, title and weight changes on WooCommerce products, with filterable reports, CSV export, an out-of-stock list and a daily activity report.

## Features

- Records regular price, sale price, stock status, stock quantity, title and weight changes with old value, new value, user and time.
- Captures every editing path: product editor, quick edit, bulk edit, REST API, WP-CLI, importers, and order-driven stock reductions.
- Tracks variations individually.
- **Change History** page with type tabs, search by name / SKU / ID, user and date filters, sortable columns and CSV export.
- **Change History** box on the product edit screen.
- **Out of Stock** list with category filter, total sales and the date each product went out of stock.
- **Daily Report** with stat tiles, per-user breakdown and print layout. HPOS compatible.
- **Settings** for tracked properties, retention period and uninstall clean-up.

## Requirements

- WordPress 6.2+
- PHP 7.4+
- WooCommerce 7.1+

## Installation

Upload the `price-stock-weight-tracker-for-woocommerce` folder to `wp-content/plugins/` and activate it. The history table is created on activation. Data from versions 1.x and 2.x is migrated automatically.

## Development

The plugin has no build step. Run the official [Plugin Check](https://wordpress.org/plugins/plugin-check/) before releasing:

```
wp plugin check price-stock-weight-tracker-for-woocommerce
```

## License

GPLv2 or later.
