<?php
// Ensure the file is not accessed directly.
if (!defined('ABSPATH')) {
    exit;
}


// Create the custom table during plugin activation

// Price Change Hostory Table
function price_change_history_install()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'price_change_history';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id int NOT NULL AUTO_INCREMENT,
        product_id int NOT NULL,
        price decimal(10, 2) NOT NULL,
        new_price decimal(10, 2) NOT NULL,
        price_difference decimal(10, 2) NOT NULL,
        change_date datetime NOT NULL,
        changed_by bigint NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'price_change_history_install');


//======= Stock Change History Table =================
function create_stock_change_history_table()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'stock_change_history';

    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint NOT NULL AUTO_INCREMENT,
            product_id bigint NOT NULL,
            old_stock_status varchar(20) NOT NULL,
            new_stock_status varchar(20) NOT NULL,
            change_date datetime NOT NULL,
            changed_by bigint NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}
add_action('init', 'create_stock_change_history_table');




// Title Change History Table Creation
function title_change_history_install()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'title_change_history';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id int NOT NULL AUTO_INCREMENT,
        product_id int NOT NULL,
        current_product_title varchar(255) NOT NULL,
        change_date datetime NOT NULL,
        changed_by bigint NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $result = dbDelta($sql);

    if ($wpdb->last_error !== '') {
        error_log('Failed to create title_change_history table: ' . $wpdb->last_error);
    } else {
        error_log('title_change_history table created successfully!');
    }
}
register_activation_hook(__FILE__, 'title_change_history_install');


// Weight Change History Table Creation
function weight_change_history_install()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'weight_change_history';

    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint NOT NULL AUTO_INCREMENT,
            product_id bigint NOT NULL,
            current_weight varchar(20) NOT NULL,
            change_date datetime NOT NULL,
            changed_by bigint NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $result = dbDelta($sql);

        if ($wpdb->last_error !== '') {
            error_log('Failed to create weight_change_history table: ' . $wpdb->last_error);
        } else {
            error_log('weight_change_history table created successfully!');
        }
    }
}
register_activation_hook(__FILE__, 'weight_change_history_install');
