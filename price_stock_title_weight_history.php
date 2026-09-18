<?php

/**
 * Plugin Name: WooCommerce Price, Stock, Title and Weight Change History
 * Description: Track and log price, stock, title, and weight changes seamlessly within WooCommerce with our all-in-one plugin, ensuring a clear history of product modifications.
 * Version: 2.0.1
 * Author: Rashed Mazumder
 * Author URI: https://mirailit.com/
 * Text Domain: price-stock-title-weight-change-history
 * Domain Path: /languages
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Include the files for different functionalities
require_once plugin_dir_path(__FILE__) . 'includes/price_change_history.php';
require_once plugin_dir_path(__FILE__) . 'includes/stock_change_history.php';
require_once plugin_dir_path(__FILE__) . 'includes/title_change_history.php';
require_once plugin_dir_path(__FILE__) . 'includes/weight_change_history.php';
require_once plugin_dir_path(__FILE__) . 'includes/stock_out_list.php';
require_once plugin_dir_path(__FILE__) . 'includes/database.php';
require_once plugin_dir_path(__FILE__) . 'includes/daily-report.php';



// Hook into product update to store price change history
function price_change_history_store_history_on_update($product_id)
{
    // Get the product's current regular price
    $current_regular_price = get_post_meta($product_id, '_regular_price', true);

    // Get the product's current sale price
    $current_sale_price = get_post_meta($product_id, '_sale_price', true);

    // Determine the current price (either regular price or sale price if set)
    $current_price = $current_sale_price ? $current_sale_price : $current_regular_price;

    // Get the new regular price from the $_POST data (after update)
    $new_regular_price = isset($_POST['_regular_price']) ? $_POST['_regular_price'] : '';

    // Get the new sale price from the $_POST data (after update)
    $new_sale_price = isset($_POST['_sale_price']) ? $_POST['_sale_price'] : '';

    // Determine the new price (either regular price or sale price if set)
    $new_price = $new_sale_price ? $new_sale_price : $new_regular_price;


    // Stock Change Checks
    $current_stock_status = get_post_meta($product_id, '_stock_status', true);
    $new_stock_status = isset($_POST['_stock_status']) ? $_POST['_stock_status'] : '';

    // Get the user ID of the current user
    $user_id = get_current_user_id();

    // Check if the price has been changed
    if ($current_price !== $new_price) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'price_change_history';

        // If it's an update (product already has a price), store the price change history
        if ($current_price) {
            $wpdb->insert(
                $table_name,
                array(
                    'product_id' => $product_id,
                    'price' => $current_price,
                    'change_date' => current_time('mysql'),
                    'changed_by' => $user_id,
                    'new_price' => $new_price,
                    'price_difference' => floatval($new_price) - floatval($current_price)
                ),
                array('%d', '%f', '%s', '%d')
            );
        }
    }


    // Check if the stock status has been changed and store the stock change history
    if ($current_stock_status !== $new_stock_status) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'stock_change_history';

        // If it's an update (product already has a stock status), store the stock change history
        if ($current_stock_status) {
            $wpdb->insert(
                $table_name,
                array(
                    'product_id'       => $product_id,
                    'old_stock_status' => $current_stock_status,
                    'new_stock_status' => $new_stock_status,
                    'change_date'      => current_time('mysql'),
                    'changed_by'       => $user_id,
                ),
                array('%d', '%s', '%s', '%s', '%d')
            );
        }
    }
}
// This action works after Single page and product page in admin panel
add_action('save_post_product', 'price_change_history_store_history_on_update', 10, 3);



function capture_previous_product_title($post_ID, $post_after, $post_before)
{
    // Check if the post is a product and if it's being updated
    if ($post_after->post_type === 'product' && $post_after->post_title !== $post_before->post_title) {
        $prev_product_title = get_post_meta($post_ID, 'previous_product_title', true);

        // Get the current product title before the update
        $current_product_title = $post_before->post_title;

        if (empty($prev_product_title)) {
            // If the previous title is empty, set it to the current title before the update
            update_post_meta($post_ID, 'previous_product_title', $current_product_title);
            // error_log('Prev Product Title is already set: ' . $prev_product_title);
        } else {
            // If the previous title exists and is different from the current title before the update
            if ($prev_product_title !== $current_product_title) {
                // Update the previous title with the current title before the update
                update_post_meta($post_ID, 'previous_product_title', $current_product_title);
                // error_log('Prev Product Title Updated to Previous Title: ' . $prev_product_title);
            } else {
                // Update the previous title with the current title before the update
                update_post_meta($post_ID, 'previous_product_title', $current_product_title);
                // error_log('Prev Product Title is already set: ' . $prev_product_title);
            }
        }

        // error_log('Prev Product Title: ' . $prev_product_title);
        // error_log('Current Product Title: ' . $current_product_title);
    }
}
add_action('post_updated', 'capture_previous_product_title', 10, 3);



// Function to track title and weight change history on product update
function title_change_history_store_history_on_update($product_id)
{
    $prev_product_title = get_post_meta($product_id, 'previous_product_title', true);
    $current_product_title = get_the_title($product_id);

    // If the previous title is different from the current title or it's empty, update it
    if ($prev_product_title !== $current_product_title && !empty($prev_product_title)) {
        update_post_meta($product_id, 'previous_product_title', $prev_product_title);
        // error_log('Prev Product Title Updated to Previous Title: ' . $prev_product_title);
    } else {
        update_post_meta($product_id, 'previous_product_title', $current_product_title); // Update previous title to current title
        $prev_product_title = $current_product_title; // Update $prev_product_title variable
        // error_log('Prev Product Title is already set: ' . $prev_product_title);
    }

    // error_log('Prev Product Title: ' . $prev_product_title);
    // error_log('Current Product Title: ' . $current_product_title);


    // Weight Change Checks
    $current_weight = get_post_meta($product_id, '_weight', true);
    $new_weight = isset($_POST['_weight']) ? sanitize_text_field($_POST['_weight']) : '';

    // Get the user ID of the current user
    $user_id = get_current_user_id();

    // Check if both title and weight have changed
    if (
        $current_product_title !== $prev_product_title ||
        $current_weight !== $new_weight
    ) {
        global $wpdb;
        $table_name_title = $wpdb->prefix . 'title_change_history';
        $table_name_weight = $wpdb->prefix . 'weight_change_history';

        // Insert title change history
        if ($current_product_title !== $prev_product_title) {
            $wpdb->insert(
                $table_name_title,
                array(
                    'product_id' => $product_id,
                    'current_product_title' => $prev_product_title,
                    'change_date' => current_time('mysql'),
                    'changed_by' => $user_id
                ),
                array('%d', '%s', '%s', '%d')
            );
        }


        // Insert weight change history
        if ($current_weight !== $new_weight) {
            $wpdb->insert(
                $table_name_weight,
                array(
                    'product_id' => $product_id,
                    'current_weight' => $current_weight,
                    'change_date' => current_time('mysql'),
                    'changed_by' => $user_id
                ),
                array('%d', '%s', '%s', '%d')
            );
        }
    }
}
// // This action works after Single page and product page in admin panel
add_action('save_post_product', 'title_change_history_store_history_on_update', 10, 3);


//===== ========== Display Price Change History: ===============
function price_change_history_get_history($product_id)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'price_change_history';
    $history = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT price, change_date, changed_by, new_price, price_difference FROM $table_name WHERE product_id = %d ORDER BY change_date DESC",
            $product_id
        )
    );
    return $history;
}



// Add custom meta box for price change history
function price_change_history_add_meta_box()
{
    add_meta_box(
        'price_change_history_meta_box',
        __('Price Change History', 'price-change-history'),
        'price_change_history_meta_box_callback',
        'product',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'price_change_history_add_meta_box');

// Callback function to display price change history in the meta box
function price_change_history_meta_box_callback($post)
{

    $product_id = $post->ID;
    $history = price_change_history_get_history($product_id);

    if ($history) {
        // Get the current product price and display it as the last row
        $current_product = wc_get_product($product_id);
        $current_price = $current_product->get_price();

        echo '<p><strong>' . __('Current Price:', 'price-change-history') . '</strong> ' . wc_price($current_price) . ' (' . date('F j, Y') . ')</p>';

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>' . __('Old Price', 'price-change-history') . '</th><th>' . __('Changed Price', 'price-change-history') . '</th><th>' . __('Changed Date', 'price-change-history') . '</th><th>' . __('Changed By', 'price-change-history') . '</th></tr></thead>';
        echo '<tbody>';

        $count = 0; // Counter Initialized

        foreach ($history as $change) {

            // Break the loop if we have already displayed 5 rows
            if ($count >= 5) {
                break;
            }

            $user = get_user_by('id', $change->changed_by);
            $username = $user ? $user->display_name : __('Unknown', 'price-change-history');

            echo '<tr>';
            echo '<td>' . wc_price($change->price) . '</td>';
            echo '<td>';
            if ($change->new_price != 0.0) {
                if ($change->new_price > $change->price) {
                    echo wc_price($change->new_price) . ' <span class="dashicons dashicons-arrow-up-alt" style="color:green;"></span>' . wc_price($change->price_difference);
                } elseif ($change->new_price < $change->price) {
                    echo wc_price($change->new_price) . ' <span class="dashicons dashicons-arrow-down-alt" style="color:red;"></span>' . wc_price($change->price_difference);
                }
            } else {
                echo 'N/A';
            }
            echo '</td>';
            echo '<td>' . date('F j, Y H:i A', strtotime($change->change_date)) . '</td>';
            echo '<td>' . $username . '</td>';

            echo '</tr>';

            $count++; // Increment the counter after each iteration
        }

        echo '</tbody>';
        echo '</table>';
    } else {
        echo __('No price change history found.', 'price-change-history');
    }
}




//===== ========== Display title change history: ===============
function title_change_history_get_history($product_id)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'title_change_history';
    $history = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT current_product_title, change_date, changed_by FROM $table_name WHERE product_id = %d ORDER BY change_date DESC",
            $product_id
        )
    );
    return $history;
}



// Add custom meta box for title change history
function title_change_history_add_meta_box()
{
    add_meta_box(
        'title_change_history_meta_box',
        __('Title Change History', 'title-change-history'),
        'title_change_history_meta_box_callback',
        'product',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'title_change_history_add_meta_box');

// Callback function to display title change history in the meta box
function title_change_history_meta_box_callback($post)
{

    $product_id = $post->ID;
    $history = title_change_history_get_history($product_id);

    if ($history) {
        // Get the current product title and display it as the last row
        $prev_product_title = get_post_meta($product_id, 'previous_product_title', true);
        $current_product_title = get_the_title($product_id);

        echo '<p><strong>' . __('Current Product Title:', 'title-change-history') . '</strong> ' . $current_product_title . '</p>';

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>' . __('Old Title', 'title-change-history') . '</th><th>' . __('Changed Date', 'title-change-history') . '</th><th>' . __('Changed By', 'title-change-history') . '</th></tr></thead>';
        echo '<tbody>';

        $count = 0; // Counter Initialized

        foreach ($history as $change) {

            // Break the loop if we have already displayed 5 rows
            if ($count >= 5) {
                break;
            }

            $user = get_user_by('id', $change->changed_by);
            $username = $user ? $user->display_name : __('Unknown', 'title-change-history');

            echo '<tr>';
            echo '<td>' . $change->current_product_title . '</td>';
            echo '<td>' . date('F j, Y H:i A', strtotime($change->change_date)) . '</td>';
            echo '<td>' . $username . '</td>';

            echo '</tr>';
            update_post_meta($product_id, 'previous_product_title', $current_product_title);

            $count++; // Increment the counter after each iteration
        }

        echo '</tbody>';
        echo '</table>';
    } else {
        echo __('No title change history found.', 'title-change-history');
    }
}




//===== ========== Display stock change history: ===============
function stock_change_history_get_history($product_id)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'stock_change_history';
    $history = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT old_stock_status, new_stock_status, change_date, changed_by FROM $table_name WHERE product_id = %d ORDER BY change_date DESC",
            $product_id
        )
    );
    return $history;
}



// Add custom meta box for stock change history
function stock_change_history_add_meta_box()
{
    add_meta_box(
        'stock_change_history_meta_box',
        __('Stock Change History', 'stock-change-history'),
        'stock_change_history_meta_box_callback',
        'product',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'stock_change_history_add_meta_box');

// Callback function to display stock change history in the meta box
function stock_change_history_meta_box_callback($post)
{

    // Function to convert stock status to a more readable format
    function get_readable_stock_status($stock_status)
    {
        if ($stock_status === 'instock') {
            return '<span style="color: green;">' . __('In stock', 'stock-change-history');
        } elseif ($stock_status === 'outofstock') {
            return '<span style="color: red;">' . __('Out of stock', 'stock-change-history');
        } else {
            return $stock_status;
        }
    }

    $product_id = $post->ID;
    $history = stock_change_history_get_history($product_id);
    $product = wc_get_product($product_id);
    // Fetch the stock status using WooCommerce function
    $product_stock_status = $product->get_stock_status();
    $readable_stock_status = get_readable_stock_status($product_stock_status);
    if ($history) {

        echo '<p><strong>' . __('Current Stock Status:', 'stock-change-history') . '</strong> ' . $readable_stock_status . '</p>';

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>' . __('Old Status', 'stock-change-history') . '</th><th>' . __('Changed To', 'stock-change-history') . '</th><th>' . __('Changed Date', 'stock-change-history') . '</th><th>' . __('Changed By', 'stock-change-history') . '</th></tr></thead>';
        echo '<tbody>';

        $count = 0; // Counter Initialized

        foreach ($history as $change) {

            // Break the loop if we have already displayed 5 rows
            if ($count >= 5) {
                break;
            }

            $user = get_user_by('id', $change->changed_by);
            $username = $user ? $user->display_name : __('Unknown', 'stock-change-history');

            echo '<tr>';
            echo '<td>' . get_readable_stock_status($change->old_stock_status) . '</td>';
            echo '<td>' . get_readable_stock_status($change->new_stock_status) . '</td>';
            echo '<td>' . date('F j, Y H:i A', strtotime($change->change_date)) . '</td>';
            echo '<td>' . $username . '</td>';
            echo '</tr>';

            $count++; // Increment the counter after each iteration
        }

        echo '</tbody>';
        echo '</table>';
    } else {
        echo __('No stock change history found.', 'stock-change-history');
    }
}
