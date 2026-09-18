<?php
// Ensure the file is not accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Register the admin menu
add_action('admin_menu', 'register_daily_report_page');
function register_daily_report_page()
{
    add_menu_page(
        'Daily Report',
        'Daily Report',
        'edit_shop_orders',
        'daily-report',
        'display_daily_report_page',
        'dashicons-chart-bar',
        6
    );
}

function display_daily_report_page()
{
    $current_date = date('Y-m-d');
    $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : $current_date;
    $start_day = date('l', strtotime($start_date));
    $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : $current_date;
    $end_day = date('l', strtotime($end_date));

    // Fetch the data for the report
    $price_changes = fetch_price_change_history($start_date, $end_date);
    $stock_changes = fetch_stock_change_history($start_date, $end_date);
    $total_orders = fetch_total_orders($start_date, $end_date);
    $total_shipped = fetch_total_shipped_orders($start_date, $end_date);
    $new_products = fetch_new_products($start_date, $end_date);

    // Calculate counts
    $price_changes_count = count($price_changes);
    $stock_changes_count = count($stock_changes);
    $new_products_count = count($new_products);

?>
    <div class="wrap daily-report">
        <h1>Daily Report</h1>
        <form method="post" action="">
            <table class="form-table">
                <tr>
                    <th style="width: 85px;">Date Range:</th>
                    <td>
                        <input type="date" name="start_date" value="<?php echo esc_attr($start_date); ?>" required />
                        <input type="date" name="end_date" value="<?php echo esc_attr($end_date); ?>" required />
                        <input type="submit" name="filter_points" value="Filter" class="button-primary" />
                    </td>
                </tr>
            </table>
        </form>
        <div id="print-content">
            <p class="result-text">
                <?php if ($start_date === $end_date): ?>
                    Showing results of <?php echo esc_html($start_date); ?> (<?php echo esc_html($start_day); ?>)
                <?php else: ?>
                    Showing results of <?php echo esc_html($start_date); ?> (<?php echo esc_html($start_day); ?>)
                    to <?php echo esc_html($end_date); ?> (<?php echo esc_html($end_day); ?>)
                <?php endif; ?>
            </p>

            <button onclick="printReport()" class="button-print">Print Report</button>

            <div id="report-content" class="report-grid">
                <div class="report-box" id="price-change-history">
                    <h2 style="margin: 0; padding:0;">Price Change History (<?php echo esc_html($price_changes_count); ?>)</h2>
                    <div id="price-changes"><?php echo format_price_changes($price_changes); ?></div>
                </div>
                <div class="report-box" id="stock-change-history">
                    <h2 style="margin: 0; padding:0;">Stock Change History (<?php echo esc_html($stock_changes_count); ?>)</h2>
                    <div id="stock-changes"><?php echo format_stock_changes($stock_changes); ?></div>
                </div>
                <div class="report-box" id="new-products">
                    <h2>New Products Added (<?php echo esc_html($new_products_count); ?>)</h2>
                    <div id="new-products-list"><?php echo format_new_products($new_products); ?></div>
                </div>
                <div class="report-box" id="total-orders">
                    <h2>Total Orders and Shipments</h2>
                    <div class="total-shipped">Total Shipped: <span id="total-shipped"><?php echo esc_html($total_shipped); ?></span></div>
                    <div class="total-orders-count">Total Orders: <span id="total-orders-count"><?php echo esc_html($total_orders); ?></span></div>
                </div>
            </div>
        </div>
    </div>

    <script type="text/javascript">
        function printReport() {
            var printContent = document.getElementById("print-content").innerHTML;

            var printStyles = `
        <style>
            @media print {
                body {
                    background-color: #ffffff !important;
                    color: black;
                }

                .daily-report .report-grid {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    grid-template-rows: auto auto;
                    gap: 20px;
                    margin-top: 20px;
                }

                .daily-report .report-box {
                    padding: 20px;
                    border: 1px solid #ddd;
                    background: #fff !important;
                    border-radius: 5px;
                }

                /* Extra spacing before stock table when printing */
                .daily-report #stock-change-history {
                    margin-top: 24px !important;
                }

                .button-print {
                    display: none;
                }
                .daily-report #price-change-history table th,
                .daily-report #price-change-history table td,
                .daily-report #stock-change-history table th,
                .daily-report #stock-change-history table td {
                        white-space: nowrap;
                        overflow: hidden;
                        text-overflow: ellipsis;
        }
            }
                </style>
            `;

            var originalContent = document.body.innerHTML;
            document.body.innerHTML = printStyles + printContent;
            window.print();
            document.body.innerHTML = originalContent;
        }
    </script>

    <style>
        .daily-report .report-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            grid-template-rows: auto auto;
            gap: 20px;
            margin-top: 20px;
        }

        .daily-report .report-box {
            padding: 20px;
            border: 1px solid #ddd;
            background: #fff;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border-radius: 5px;
        }

        .daily-report #price-change-history {
            grid-column: 1;
            grid-row: 1;
        }

        .daily-report #stock-change-history {
            grid-column: 2;
            grid-row: 1;
        }

        .daily-report #product-add-history {
            grid-column: 1;
            grid-row: 2;
        }

        .daily-report #total-orders {
            grid-column: 2;
            grid-row: 2;
        }

        .daily-report #report-content h2 {
            margin-top: 0;
        }

        .daily-report #new-products table th,
        .daily-report #new-products table td {
            text-align: left;
            padding: 8px;
            border: 1px solid #ddd;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .daily-report #price-change-history table th,
        .daily-report #price-change-history table td,
        .daily-report #stock-change-history table th,
        .daily-report #stock-change-history table td {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .daily-report #new-products table {
            width: 100%;
            border-collapse: collapse;
        }

        .daily-report form {
            margin-bottom: 0;
        }

        .daily-report .result-text {
            color: #ff5722;
            margin-bottom: 5px;
        }

        .daily-report th {
            font-weight: 500;
        }

        .daily-report .button-primary {
            background: #007cba !important;
            width: 120px;
            font-size: 14px !important;
        }

        .daily-report .total-shipped,
        .daily-report .total-orders-count {
            font-size: 16px;
            font-weight: 600;
            color: #16578b;
            margin-bottom: 10px;
        }

        .daily-report .button-print {
            cursor: pointer;
            background: #000000;
            width: 115px;
            height: 32px;
            font-size: 14px;
            color: #fff;
            border-radius: 4px;
            border: 0;
            transition: 0.1s all linear;
        }

        .daily-report .button-print:hover {
            background: #383838;
        }

        table {
            table-layout: fixed;
            width: 100%;
        }

        th,
        td {
            word-wrap: normal !important;
        }
    </style>
<?php
}


// Format the data for display
function format_price_changes($changes)
{
    if (empty($changes)) return '<p>No price changes found.</p>';

    // Build per-user change counts summary
    $user_counts = array();
    $user_name_cache = array();
    foreach ($changes as $item) {
        $user_id = isset($item['changed_by']) ? (int) $item['changed_by'] : 0;
        if ($user_id > 0) {
            if (!isset($user_name_cache[$user_id])) {
                $user_obj = get_user_by('id', $user_id);
                $user_name_cache[$user_id] = $user_obj ? $user_obj->display_name : __('Unknown', 'price-change-history');
            }
            $user_name = $user_name_cache[$user_id];
        } else {
            $user_name = __('Unknown', 'price-change-history');
        }
        if (!isset($user_counts[$user_name])) {
            $user_counts[$user_name] = 0;
        }
        $user_counts[$user_name]++;
    }

    // Sort by number of changes (desc)
    arsort($user_counts);

    $list_parts = array();
    foreach ($user_counts as $name => $count) {
        $label = esc_html($name) . ' (' . intval($count) . ')';
        $list_parts[] = '<div class="user-item">' . $label . '</div>';
    }

    // Only show the right aligned stacked list (no left inline summary)
    $summary_html = '<div class="user-change-summary-row" style="display:flex; justify-content:flex-end; margin:12px 0 12px;">'
        . '<div class="user-change-side-list" style="text-align:right; min-width:240px; font-size:12px; line-height:1.4; margin-bottom:8px;">' . implode('', $list_parts) . '</div>'
        . '</div>';

    // Start table with headers
    $output = $summary_html;
    $output .= '<table class="wp-list-table widefat fixed striped table-view-list" border="1" cellpadding="10" cellspacing="0" style="border-collapse: collapse; width: 100%;">';
    $output .= '<thead>';
    $output .= '<tr>';
    $output .= '<th style="width: 40%;">Product Name</th>';
    $output .= '<th style="width: 15%;">Old Price</th>';
    $output .= '<th style="width: 15%;">New Price</th>';
    $output .= '<th style="width: 15%;">Change Date</th>';
    $output .= '<th style="width: 15%;">Changed By</th>';
    $output .= '</tr>';
    $output .= '</thead>';
    $output .= '<tbody>';

    // Loop through each change and display in table rows
    foreach ($changes as $change) {

        $user = get_user_by('id', $change['changed_by']);
        $username = $user ? $user->display_name : __('Unknown', 'price-change-history'); // Default to 'Unknown' if no user
        $formatted_date = date('Y-m-d', strtotime($change['change_date']));
        $product_url = get_permalink($change['product_id']);
        $product_title = get_the_title($change['product_id']);

        $output .= '<tr>';
        $output .= '<td><a href="' . esc_url($product_url) . '" target="_blank">' . esc_html($product_title) . '</a></td>';
        $output .= '<td>' . number_format((float) $change['old_price']) . '</td>';
        if ((float) $change['new_price'] > (float) $change['old_price']) {
            $output .= '<td><span style="color: green;">&#9650;</span> ' .
                number_format((float) $change['new_price']) . '</td>'; // Green upward arrow first
        } elseif ((float) $change['new_price'] < (float) $change['old_price']) {
            $output .= '<td><span style="color: red;">&#9660;</span> ' .
                number_format((float) $change['new_price']) . '</td>'; // Red downward arrow first
        } else {
            $output .= '<td>' . number_format((float) $change['new_price']) . '</td>'; // No change
        }
        $output .= '<td>' . esc_html($formatted_date) . '</td>';
        $output .= '<td>' . esc_html($username) . '</td>';
        $output .= '</tr>';
    }

    $output .= '</tbody>';
    $output .= '</table>';

    return $output;
}

function format_stock_changes($changes)
{
    if (empty($changes)) return '<p>No stock changes found.</p>';

    // Build per-user change counts summary
    $user_counts = array();
    $user_name_cache = array();
    foreach ($changes as $item) {
        $user_id = isset($item['changed_by']) ? (int) $item['changed_by'] : 0;
        if ($user_id > 0) {
            if (!isset($user_name_cache[$user_id])) {
                $user_obj = get_user_by('id', $user_id);
                $user_name_cache[$user_id] = $user_obj ? $user_obj->display_name : __('Unknown', 'stock-change-history');
            }
            $user_name = $user_name_cache[$user_id];
        } else {
            $user_name = __('Unknown', 'stock-change-history');
        }
        if (!isset($user_counts[$user_name])) {
            $user_counts[$user_name] = 0;
        }
        $user_counts[$user_name]++;
    }

    // Sort by number of changes (desc)
    arsort($user_counts);

    $list_parts = array();
    foreach ($user_counts as $name => $count) {
        $label = esc_html($name) . ' (' . intval($count) . ')';
        $list_parts[] = '<div class="user-item">' . $label . '</div>';
    }

    // Only show the right aligned stacked list (no left inline summary); add extra top margin for spacing from heading
    $summary_html = '<div class="user-change-summary-row" style="display:flex; justify-content:flex-end; margin:12px 0 12px;">'
        . '<div class="user-change-side-list" style="text-align:right; min-width:240px; font-size:12px; line-height:1.4; margin-bottom:8px;">' . implode('', $list_parts) . '</div>'
        . '</div>';

    // Start table with headers
    $output = $summary_html;
    $output .= '<table class="wp-list-table widefat fixed striped table-view-list" border="1" cellpadding="10" cellspacing="0" style="border-collapse: collapse; width: 100%;">';
    $output .= '<thead>';
    $output .= '<tr>';
    $output .= '<th style="width: 40%;">Product Name</th>';
    $output .= '<th style="width: 15%;">Old Stock</th>';
    $output .= '<th style="width: 15%;">New Stock</th>';
    $output .= '<th style="width: 15%;">Change Date</th>';
    $output .= '<th style="width: 15%;">Changed By</th>';
    $output .= '</tr>';
    $output .= '</thead>';
    $output .= '<tbody>';

    // Loop through each change and display in table rows
    foreach ($changes as $change) {
        // Get the user by ID (changed_by is the user ID)
        $user = get_user_by('id', $change['changed_by']);
        $username = $user ? $user->display_name : __('Unknown', 'stock-change-history'); // Default to 'Unknown' if no user
        $formatted_date = date('Y-m-d', strtotime($change['change_date']));
        $product_url = get_permalink($change['product_id']);
        $product_title = get_the_title($change['product_id']);

        $output .= '<tr>';
        $output .= '<td><a href="' . esc_url($product_url) . '" target="_blank">' . esc_html($product_title) . '</a></td>';
        $output .= '<td>' . get_stock_status_label($change['old_stock']) . '</td>';
        $output .= '<td>' . get_stock_status_label($change['new_stock']) . '</td>';
        $output .= '<td>' . esc_html($formatted_date) . '</td>';
        $output .= '<td>' . esc_html($username) . '</td>'; // Display the username here
        $output .= '</tr>';
    }

    $output .= '</tbody>';
    $output .= '</table>';

    return $output;
}

function get_stock_status_label($stock_status)
{
    if ($stock_status === 'instock') {
        return '<span style="color: green;">' . __('In stock', 'stock-change-history') . '</span>';
    } elseif ($stock_status === 'outofstock') {
        return '<span style="color: red;">' . __('Out of stock', 'stock-change-history') . '</span>';
    } else {
        return esc_html($stock_status);
    }
}

function format_new_products($products)
{
    if (empty($products)) return '<p>No new products added.</p>';

    $output = '<table class="wp-list-table widefat fixed striped table-view-list" border="1" cellpadding="10" cellspacing="0" style="border-collapse: collapse; width: 100%;">';
    $output .= '<thead>
        <tr>
            <th style="width: 50%;">Product Name</th>
            <th style="width: 12%;">Price</th>
            <th style="width: 12%;">Sale Price</th>
            <th style="width: 10%;">Quantity</th>
            <th style="width: 16%;">Added By</th>
        </tr>
    </thead>';
    $output .= '<tbody>';

    foreach ($products as $product) {
        $regular_price = $product['regular_price'] ?: '-';
        $sale_price = $product['sale_price'] ?: '-';
        $stock_quantity = $product['stock_quantity'] !== null ? $product['stock_quantity'] : '-';
        $product_id = $product['ID']; // Product ID is still needed to fetch details
        $edit_link = get_edit_post_link($product_id); // Edit link for the product

        // Get the author's ID
        $author_id = get_post_field('post_author', $product_id);
        $author = get_user_by('id', $author_id);
        $added_by = $author ? $author->display_name : __('Unknown', 'text-domain'); // Fallback to 'Unknown' if no user found

        // Build the table row
        $output .= sprintf(
            '<tr>
                <td><a href="%s" target="_blank">%s</a></td>
                <td>%s</td>
                <td>%s</td>
                <td>%s</td>
                <td>%s</td>
            </tr>',
            esc_url(get_permalink($product['ID'])), // Link to the product
            esc_html($product['post_title']), // Product title
            esc_html($regular_price),
            esc_html($sale_price),
            esc_html($stock_quantity),
            esc_html($added_by) // Added By column
        );
    }

    $output .= '</tbody></table>';

    return $output;
}



function fetch_price_change_history($start_date, $end_date)
{
    global $wpdb;

    $query = $wpdb->prepare(
        "SELECT product_id, price AS old_price, new_price, DATE(change_date) AS change_date, changed_by 
         FROM {$wpdb->prefix}price_change_history 
         WHERE DATE(change_date) BETWEEN %s AND %s",
        $start_date,
        $end_date
    );

    return $wpdb->get_results($query, ARRAY_A);
}

function fetch_stock_change_history($start_date, $end_date)
{
    global $wpdb;

    $query = $wpdb->prepare(
        "SELECT product_id, old_stock_status AS old_stock, new_stock_status AS new_stock, DATE(change_date) AS change_date, changed_by 
         FROM {$wpdb->prefix}stock_change_history 
         WHERE DATE(change_date) BETWEEN %s AND %s",
        $start_date,
        $end_date
    );

    return $wpdb->get_results($query, ARRAY_A);
}

function fetch_new_products($start_date, $end_date)
{
    global $wpdb;

    $query = $wpdb->prepare(
        "SELECT p.ID, p.post_title, 
                pm_regular.meta_value AS regular_price, 
                pm_sale.meta_value AS sale_price,
                pm_stock.meta_value AS stock_quantity
         FROM {$wpdb->prefix}posts p
         LEFT JOIN {$wpdb->prefix}postmeta pm_regular 
           ON p.ID = pm_regular.post_id AND pm_regular.meta_key = '_regular_price'
         LEFT JOIN {$wpdb->prefix}postmeta pm_sale 
           ON p.ID = pm_sale.post_id AND pm_sale.meta_key = '_sale_price'
         LEFT JOIN {$wpdb->prefix}postmeta pm_stock 
           ON p.ID = pm_stock.post_id AND pm_stock.meta_key = '_stock'
         WHERE p.post_type = 'product' 
           AND p.post_status = 'publish' 
           AND DATE(p.post_date) BETWEEN %s AND %s",
        $start_date,
        $end_date
    );

    return $wpdb->get_results($query, ARRAY_A);
}

function fetch_total_orders($start_date, $end_date)
{
    global $wpdb;

    $query = $wpdb->prepare(
        "SELECT COUNT(*) 
         FROM {$wpdb->prefix}posts
         WHERE post_type = 'shop_order'
           AND post_status IN ('wc-processing', 'wc-on-hold', 'wc-address-issue', 'wc-completed')
           AND DATE(post_date) BETWEEN %s AND %s",
        $start_date,
        $end_date
    );

    return $wpdb->get_var($query);
}

function fetch_total_shipped_orders($start_date, $end_date)
{
    global $wpdb;

    $query = $wpdb->prepare(
        "SELECT COUNT(*) 
         FROM {$wpdb->prefix}posts p
         JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id
         WHERE p.post_type = 'shop_order'
           AND p.post_status = 'wc-completed'
           AND pm.meta_key = '_completed_date'
           AND DATE(pm.meta_value) BETWEEN %s AND %s",
        $start_date,
        $end_date
    );

    return $wpdb->get_var($query);
}
