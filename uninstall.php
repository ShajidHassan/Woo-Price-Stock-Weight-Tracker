<?php
/**
 * Runs when the plugin is deleted from the Plugins screen.
 * History is only removed when the "delete on uninstall" setting is enabled.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

wp_clear_scheduled_hook('pswt_daily_prune');

$pswt_settings = get_option('pswt_settings', array());

if (!empty($pswt_settings['delete_on_uninstall'])) {
    global $wpdb;

    $pswt_tables = array(
        $wpdb->prefix . 'pswt_change_log',
        // Tables created by versions 1.x and 2.x.
        $wpdb->prefix . 'price_change_history',
        $wpdb->prefix . 'stock_change_history',
        $wpdb->prefix . 'title_change_history',
        $wpdb->prefix . 'weight_change_history',
    );

    foreach ($pswt_tables as $pswt_table) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- removing the plugin's own tables on uninstall.
        $wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $pswt_table));
    }

    delete_option('pswt_settings');
    delete_option('pswt_db_version');
    delete_option('pswt_legacy_migrated');
    delete_metadata('post', 0, 'previous_product_title', '', true);
}
