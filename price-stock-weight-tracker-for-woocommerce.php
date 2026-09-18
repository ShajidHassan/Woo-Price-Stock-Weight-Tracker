<?php
/**
 * Plugin Name: Price, Stock & Weight Tracker for WooCommerce
 * Plugin URI: https://github.com/ShajidHassan/WooCommerce-Product-Change-History-Plugin-
 * Description: Keeps a complete history of price, stock, title and weight changes on WooCommerce products, with filterable reports, CSV export, an out-of-stock list and a daily activity report.
 * Version: 3.0.0
 * Author: Mirailit Limited
 * Author URI: https://mirailit.com/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: price-stock-weight-tracker-for-woocommerce
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 7.1
 * WC tested up to: 11.1
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PSWT_VERSION', '3.0.0');
define('PSWT_DB_VERSION', '3.0.0');
define('PSWT_FILE', __FILE__);
define('PSWT_PATH', plugin_dir_path(__FILE__));
define('PSWT_URL', plugin_dir_url(__FILE__));
define('PSWT_BASENAME', plugin_basename(__FILE__));

require_once PSWT_PATH . 'includes/class-pswt-settings.php';
require_once PSWT_PATH . 'includes/class-pswt-install.php';
require_once PSWT_PATH . 'includes/class-pswt-repository.php';
require_once PSWT_PATH . 'includes/class-pswt-format.php';
require_once PSWT_PATH . 'includes/class-pswt-tracker.php';
require_once PSWT_PATH . 'includes/class-pswt-admin.php';
require_once PSWT_PATH . 'includes/class-pswt-meta-box.php';
require_once PSWT_PATH . 'includes/class-pswt-report.php';
require_once PSWT_PATH . 'includes/class-pswt-export.php';

// Activation hooks must be registered from the main plugin file, otherwise they never fire.
register_activation_hook(__FILE__, array('PSWT_Install', 'activate'));
register_deactivation_hook(__FILE__, array('PSWT_Install', 'deactivate'));

/**
 * Declare compatibility with WooCommerce High-Performance Order Storage.
 */
function pswt_declare_hpos_compatibility()
{
    if (class_exists('Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
}
add_action('before_woocommerce_init', 'pswt_declare_hpos_compatibility');

/**
 * Admin notice shown when WooCommerce is not active.
 */
function pswt_woocommerce_missing_notice()
{
    if (!current_user_can('activate_plugins')) {
        return;
    }
    echo '<div class="notice notice-error"><p>' . esc_html__('Price, Stock & Weight Tracker for WooCommerce requires WooCommerce to be installed and active.', 'price-stock-weight-tracker-for-woocommerce') . '</p></div>';
}

/**
 * Boot the plugin once all plugins are loaded.
 */
function pswt_init()
{
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'pswt_woocommerce_missing_notice');
        return;
    }

    // Creates or upgrades the table if activation was skipped (e.g. manual upload) or the schema changed.
    PSWT_Install::maybe_upgrade();

    PSWT_Tracker::init();
    add_action('pswt_daily_prune', array('PSWT_Repository', 'prune_by_settings'));

    if (is_admin()) {
        PSWT_Admin::init();
        PSWT_Meta_Box::init();
        PSWT_Export::init();
    }
}
add_action('plugins_loaded', 'pswt_init');

/**
 * Settings link on the Plugins screen.
 *
 * @param array $links Existing links.
 * @return array
 */
function pswt_plugin_action_links($links)
{
    $links[] = '<a href="' . esc_url(admin_url('admin.php?page=pswt-history')) . '">' . esc_html__('History', 'price-stock-weight-tracker-for-woocommerce') . '</a>';
    $links[] = '<a href="' . esc_url(admin_url('admin.php?page=pswt-settings')) . '">' . esc_html__('Settings', 'price-stock-weight-tracker-for-woocommerce') . '</a>';

    return $links;
}
add_filter('plugin_action_links_' . PSWT_BASENAME, 'pswt_plugin_action_links');
