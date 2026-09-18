<?php
// Ensure the file is not accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Include the necessary WordPress files
require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
require_once ABSPATH . 'wp-admin/includes/template.php';


// Add the admin page for stock out list
function add_stock_out_list_admin_page()
{
    add_submenu_page(
        'edit.php?post_type=product',
        __('Stock Out List', 'stock-out-list'),
        __('Stock Out List', 'stock-out-list'),
        'edit_shop_orders',
        'stock-out-list',
        'stock_out_list_page_callback',
        9
    );
}
add_action('admin_menu', 'add_stock_out_list_admin_page');


function display_category_options($categories, $depth = 0)
{
    foreach ($categories as $category) {
        // Indent subcategory names based on their depth in the hierarchy
        $indentation = str_repeat('&nbsp;&nbsp;&nbsp;', $depth);

        // Check if this category is selected
        $selected = selected($category->term_id, isset($_GET['category']) ? $_GET['category'] : '', false);

        // Display the option with category name and product count
        $optionText = $indentation . esc_html($category->name) . ' (' . $category->count . ')';
        echo '<option value="' . esc_attr($category->term_id) . '"' . $selected . '>' . $optionText . '</option>';

        // If this category has subcategories, display them
        $child_categories = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'parent' => $category->term_id,
        ]);

        if (!empty($child_categories)) {
            display_category_options($child_categories, $depth + 1);
        }
    }
}

// Get only top-level categories initially
$product_categories = get_terms([
    'taxonomy' => 'product_cat',
    'hide_empty' => false,
    'parent' => 0,
]);


// Callback function to display stock out list
function stock_out_list_page_callback()
{
    // Create an instance of the custom table class
    $stock_out_list_table = new Stock_Out_List_Table();

    // Prepare the table items
    $stock_out_list_table->prepare_items();

    echo '<div class="wrap">';
    echo '<h1>' . __('All Stock Out List', 'stock-out-list') . '</h1>';

    // Display the search form
    ?>
    <div class="tablenav top">
        <div class="tablenav top">
            <div class="alignleft actions">
                <form method="get">
                    <input type="hidden" name="page" value="stock-out-list">
                    <input type="hidden" name="post_type" value="product">
                    <input style="width:270px" type="text" id="product-search" name="s"
                        placeholder="Search by product name..."
                        value="<?php echo isset($_REQUEST['s']) ? esc_attr($_REQUEST['s']) : ''; ?>">
                    <input type="submit" value="Search" class="button">
                </form>
            </div>
            <div class="alignleft actions">
                <form method="get">
                    <input type="hidden" name="page" value="stock-out-list">
                    <input type="hidden" name="post_type" value="product">
                    <?php
                    // Get product categories
                    $product_categories = get_terms(array(
                        'taxonomy' => 'product_cat',
                        'hide_empty' => false,
                    ));
                    ?>
                    <select name="category">
                        <option value="">Filter by Category</option>
                        <?php display_category_options($product_categories); ?>
                    </select>
                    <input type="submit" value="Filter" class="button">
                </form>
            </div>
        </div>
        <?php

        // Check if the table is empty
        if (empty($stock_out_list_table->items)) {
            echo '<p>' . __('No stock out products found.', 'stock-out-list') . '</p>';
        } else {
            // Display the custom table
            $stock_out_list_table->display();
        }

        echo '</div>';
}


// Custom table class for the stock out list view
class Stock_Out_List_Table extends WP_List_Table
{
    public function __construct()
    {
        parent::__construct(
            array(
                'singular' => __('Stock Change', 'stock-out-list'),
                'plural' => __('Stock Changes', 'stock-out-list'),
                'ajax' => false,
            )
        );
    }

    // Prepare the items for the table
    public function prepare_items()
    {
        global $wpdb;

        // Capture 'orderby' and 'order' parameters from the URL
        $orderby = (!empty($_GET['orderby'])) ? $_GET['orderby'] : 'change_date';
        $order = (!empty($_GET['order'])) ? $_GET['order'] : 'desc';

        // Ensure these parameters are safe to use in a SQL query
        $orderby = esc_sql($orderby);
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

        // Define the columns that are allowed to be sorted by
        $valid_orderby_columns = ['product_id', 'product_name', 'stock_status', 'total_sale', 'change_date'];
        if (!in_array($orderby, $valid_orderby_columns)) {
            $orderby = 'change_date';
        }

        // Define column headers, hidden columns, and sortable columns
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();

        // Configure the table
        $this->_column_headers = array($columns, $hidden, $sortable);

        $per_page = 50;
        $current_page = $this->get_pagenum();

        $category_condition = '';
        if (!empty($_GET['category'])) {
            $category_id = intval($_GET['category']); // Ensure it's an integer to avoid SQL injection
            $category_condition = $wpdb->prepare(" AND p.ID IN (
                SELECT tr.object_id FROM {$wpdb->term_relationships} tr
                INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                WHERE tt.taxonomy = 'product_cat' AND tt.term_id = %d)", $category_id);
        }

        // Before main SELECT query, prepare a subquery or join to include total_sales data
        $subquery_total_sales = "(SELECT pm2.meta_value FROM {$wpdb->postmeta} pm2 WHERE pm2.post_id = p.ID AND pm2.meta_key = 'total_sales' LIMIT 1) AS total_sale";

        // Determine the correct ORDER BY clause
        if ($orderby === 'total_sale') {
            // When sorting by total_sale, cast the meta_value to an integer for numeric sorting
            $orderby_clause = "CAST((SELECT pm2.meta_value FROM {$wpdb->postmeta} pm2 WHERE pm2.post_id = p.ID AND pm2.meta_key = 'total_sales' LIMIT 1) AS UNSIGNED)";
        } else {
            $orderby_clause = $orderby;
        }

        $search_query = isset($_REQUEST['s']) ? $_REQUEST['s'] : ''; // Ensure 's' is always defined

        $query = $wpdb->prepare(
            "
    SELECT p.ID AS product_id,
           p.post_title AS product_name,
           pm.meta_value AS stock_status,
           MAX(sch.change_date) AS change_date,
           (SELECT pm2.meta_value FROM {$wpdb->postmeta} pm2 WHERE pm2.post_id = p.ID AND pm2.meta_key = 'total_sales' LIMIT 1) AS total_sale
    FROM {$wpdb->posts} p
    INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
    LEFT JOIN {$wpdb->prefix}stock_change_history sch ON p.ID = sch.product_id
    WHERE p.post_type = 'product'
      AND p.post_status = 'publish'
      AND pm.meta_key = '_stock_status'
      AND pm.meta_value = 'outofstock'
      " . $category_condition . "
      AND (p.post_title LIKE %s)
    GROUP BY p.ID",
            '%' . $wpdb->esc_like($search_query) . '%'
        );

        $final_query = $query . " ORDER BY " . $orderby_clause . " " . $order;
        $final_query .= $wpdb->prepare(" LIMIT %d OFFSET %d", $per_page, ($current_page - 1) * $per_page);

        // Execute the query
        $products = $wpdb->get_results($final_query);

        // Initialize the items array
        $this->items = array();

        // Process retrieved products
        foreach ($products as $product) {
            $this->items[] = array(
                'product_id' => $product->product_id,
                'product_name' => $product->product_name,
                'stock_status' => $product->stock_status,
                'total_sale' => $this->get_total_sales_for_product($product->product_id),
                'change_date' => $product->change_date,
            );
        }

        // Count total items with 'outofstock' status
        $total_items_query = "
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} AS p
            INNER JOIN {$wpdb->postmeta} AS pm ON p.ID = pm.post_id
            WHERE p.post_type = 'product'
            AND p.post_status = 'publish'
            AND pm.meta_key = '_stock_status'
            AND pm.meta_value = 'outofstock'" .
            $category_condition;

        $total_items = $wpdb->get_var($total_items_query);

        // Set total items count for pagination
        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page' => $per_page,
        ));
    }

    // Function to get total sales for a product
    private function get_total_sales_for_product($product_id)
    {
        if (class_exists('WC_Product')) {
            $product = wc_get_product($product_id);
            if ($product) {
                return $product->get_total_sales();
            }
        }

        return 0; // Default to 0 if WooCommerce is not active or product doesn't exist
    }

    // Define columns for the table
    public function get_columns()
    {
        $columns = array(
            'product_id' => __('Product ID', 'stock-out-list'),
            'product_name' => __('Product Name', 'stock-out-list'),
            'stock_status' => __('Stock Status', 'stock-out-list'),
            'total_sale' => __('Total Sale', 'stock-out-list'),
            'change_date' => __('Change Date', 'stock-out-list'),
        );

        return $columns;
    }

    // Define sortable columns
    public function get_sortable_columns()
    {
        $sortable_columns = array(
            'product_id' => array('product_id', false),
            'product_name' => array('product_name', false),
            'total_sale' => array('total_sale', false),
            'change_date' => array('change_date', false),
        );

        return $sortable_columns;
    }

    // Define columns and data for the table
    public function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'product_id':
                $edit_url = get_edit_post_link($item['product_id']);
                return '<a href="' . esc_url($edit_url) . '" target="_blank">' . $item['product_id'] . '</a>';

            case 'product_name':
                $product = wc_get_product($item['product_id']);
                $product_name = $product ? $product->get_name() : __('Product not found', 'stock-out-list');
                $product_permalink = $product ? $product->get_permalink() : '';
                return '<a href="' . esc_url($product_permalink) . '" target="_blank">' . $product_name . '</a>';

            case 'stock_status':
                $product = wc_get_product($item['product_id']);
                $stock_status = $product ? $product->get_stock_status() : '';
                return $this->get_stock_status_label($stock_status);

            case 'total_sale':
                $total_sales = $this->get_total_sales_for_product($item['product_id']);
                return $total_sales;

            case 'change_date':
                return isset($item['change_date']) ? date('F j, Y H:i A', strtotime($item['change_date'])) : '';

            default:
                return ''; // Show nothing for all other columns
        }
    }

    // Helper function to get stock status label with color
    private function get_stock_status_label($stock_status)
    {
        if ($stock_status === 'instock') {
            return '<span style="color: green;">' . __('In stock', 'stock-out-list') . '</span>';
        } elseif ($stock_status === 'outofstock') {
            return '<span style="color: red;">' . __('Out of stock', 'stock-out-list') . '</span>';
        } else {
            return $stock_status;
        }
    }
}
