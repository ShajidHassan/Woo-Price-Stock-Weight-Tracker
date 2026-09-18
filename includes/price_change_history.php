<?php

// Ensure the file is not accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Include the necessary WordPress files
require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
require_once ABSPATH . 'wp-admin/includes/template.php';


// Function to add the custom admin menu
function price_change_history_admin_menu()
{
    add_submenu_page(
        'edit.php?post_type=product',
        __('Price Change History', 'price-change-history'),
        __('Price Change History', 'price-change-history'),
        'edit_shop_orders',
        'price-change-history',
        'price_change_history_page_callback',
        7
    );
}
add_action('admin_menu', 'price_change_history_admin_menu');


// Custom table class for the price change history list view
class Price_Change_History_List_Table extends WP_List_Table
{
    public function __construct()
    {
        parent::__construct(array(
            'singular' => __('Price Change', 'price-change-history'),
            'plural'   => __('Price Changes', 'price-change-history'),
            'ajax'     => false,
        ));
    }

    // Prepare the items for the table
    public function prepare_items()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'price_change_history';

        // Define column headers
        $columns = $this->get_columns();

        // Define hidden columns (optional)
        $hidden = array();

        // Define sortable columns
        $sortable = array(
            'product_id'   => array('product_id', false),
            'product_name'   => array('product_id', false),
            'old_price'    => array('old_price', false),
            'new_price'    => array('new_price', false),
            'current_price'    => array('current_price', false),
            'change_date'  => array('change_date', true),
        );

        // Configure the table
        $this->_column_headers = array($columns, $hidden, $sortable);

        // Get the data for the table
        $per_page = 50;
        $current_page = $this->get_pagenum();
        $search_query = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        $selected_user = isset($_GET['user']) ? intval($_GET['user']) : 'all';

        // Get users associated with specific roles
        $role_names = array('shop_manager', 'shop_manager_simple', 'administrator');
        $allowed_user_ids = array();

        foreach ($role_names as $role_name) {
            $users = get_users(array('role' => $role_name));
            foreach ($users as $user) {
                $allowed_user_ids[] = $user->ID;
            }
        }

        $where_clause = ' WHERE 1=1';

        // Filter by user
        if ($selected_user !== 'all' && in_array($selected_user, $allowed_user_ids)) {
            $where_clause .= ' AND changed_by = ' . intval($selected_user);
        }

        // Filter by search query
        if ($search_query) {
            $product_ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_title LIKE %s AND post_status != 'trash'",
                    '%' . $wpdb->esc_like($search_query) . '%'
                )
            );

            if (!empty($product_ids)) {
                $where_clause .= ' AND product_id IN (' . implode(',', $product_ids) . ')';
            } else {
                // If no product IDs found, set a condition that will return no results
                $where_clause .= ' AND 1=0';
            }
        }
        // Get the selected price difference filter
        $selected_price_difference = isset($_GET['price_difference']) ? $_GET['price_difference'] : 'all';

        // Fetch total items count after applying filters (without limit)
        $where_clause_without_limit = $where_clause; // Store the original $where_clause
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name" . $where_clause_without_limit);


        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
        ));

        // Fetch all items (without pagination limits)
        $this->items = $wpdb->get_results(
            "SELECT product_id, price, change_date, changed_by, new_price, price_difference
            FROM $table_name
            $where_clause_without_limit
            ORDER BY change_date DESC"
        );
        // Filter out deleted (trashed) products
        $this->items = array_filter($this->items, function ($item) {
            $post = get_post($item->product_id);
            return $post && $post->post_status !== 'trash';
        });

        // Apply price difference filtering based on the already fetched items
        $selected_price_difference = isset($_GET['price_difference']) ? $_GET['price_difference'] : 'all';
        switch ($selected_price_difference) {
            case 'lt0':
                $this->items = array_filter($this->items, function ($item) {
                    return $item->price_difference < 0;
                });
                break;
            case '0to50':
                $this->items = array_filter($this->items, function ($item) {
                    return $item->price_difference >= 0 && $item->price_difference <= 50;
                });
                break;
            case '50to100':
                $this->items = array_filter($this->items, function ($item) {
                    return $item->price_difference > 50 && $item->price_difference <= 100;
                });
                break;
            case '100to200':
                $this->items = array_filter($this->items, function ($item) {
                    return $item->price_difference > 100 && $item->price_difference <= 200;
                });
                break;
            case 'gt100':
                $this->items = array_filter($this->items, function ($item) {
                    return $item->price_difference > 100;
                });
                break;
            case 'gt200':
                $this->items = array_filter($this->items, function ($item) {
                    return $item->price_difference > 200;
                });
                break;
            default:
                break;
        }


        $total_filtered_items = count($this->items);
        $current_page = $this->get_pagenum();
        $per_page = $this->get_items_per_page('price_change_history_per_page', 50);


        if ($total_filtered_items > 0) {
            $this->set_pagination_args(array(
                'total_items' => $total_filtered_items,
                'per_page'    => $per_page,
            ));

            $this->items = array_slice($this->items, ($current_page - 1) * $per_page, $per_page);
        } else {

            $this->set_pagination_args(array(
                'total_items' => 0,
                'per_page'    => $per_page,
            ));
            $this->items = [];
        }
    }

    // Define columns for the table
    public function get_columns()
    {
        $columns = array(
            'product_id'   => __('Product ID', 'price-change-history'),
            'product_name' => __('Product Name', 'price-change-history'),
            'old_price'    => __('Old Price', 'price-change-history'),
            'new_price'    => __('Changed Price', 'price-change-history'),
            'current_price'    => __('Current Price', 'price-change-history'),
            'changed_by'   => __('Changed By', 'price-change-history'),
            'change_date'  => __('Change Date', 'price-change-history'),
        );

        return $columns;
    }

    // Define columns and data for the table
    public function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'product_id':
                $edit_url = get_edit_post_link($item->product_id);
                return '<a href="' . esc_url($edit_url) . '" target="_blank">' . $item->product_id . '</a>';

            case 'product_name':
                $product = wc_get_product($item->product_id);
                $product_name = $product ? $product->get_name() : __('Product not found', 'price-change-history');
                $product_permalink = $product ? $product->get_permalink() : '';
                return '<a href="' . esc_url($product_permalink) . '" target="_blank">' . $product_name . '</a>';

            case 'old_price':
                return wc_price($item->price);

            case 'new_price':
                $product = wc_get_product($item->product_id);
                if ($product && get_post_status($item->product_id) !== 'trash') {
                    if ($item->new_price != 0.0) {
                        // If product current price is greater, add green increment icon, else add red decrement icon
                        if ($item->new_price > $item->price) {
                            return wc_price($item->new_price) . ' <span class="dashicons dashicons-arrow-up-alt" style="color:green;"></span>' . wc_price($item->price_difference);
                        } elseif ($item->new_price < $item->price) {
                            return wc_price($item->new_price) . ' <span class="dashicons dashicons-arrow-down-alt" style="color:red;"></span>' . wc_price($item->price_difference);
                        }
                    } else {
                        return 'N/A';
                    }
                } else {
                    return '';
                }
                break;
                // return wc_price( $product ? $product->get_price() : 0 );

            case 'current_price':
                $product = wc_get_product($item->product_id);
                if ($product && get_post_status($item->product_id) !== 'trash') {
                    return wc_price($product->get_price());
                } else {
                    return '';
                }
            case 'changed_by':
                $user = get_user_by('id', $item->changed_by);
                $username = $user ? $user->display_name : __('Unknown', 'price-change-history');
                return $username;

            case 'change_date':
                return date('F j, Y H:i A', strtotime($item->change_date));

            default:
                return ''; // Show nothing for all other columns
        }
    }
}

// Callback function to display price change history in the admin page
function price_change_history_page_callback()
{
    // Create an instance of the custom table class
    $price_change_history_table = new Price_Change_History_List_Table();

    // Prepare the table items
    $price_change_history_table->prepare_items();

    echo '<div class="wrap">';
    echo '<h1>' . __('Price Change History', 'price-change-history') . '</h1>';

    // Display the search form
?>
    <div class="tablenav top">
        <div class="alignleft actions">
            <form method="get" action="<?php echo admin_url('edit.php'); ?>">
                <input type="hidden" name="page" value="price-change-history">
                <input type="hidden" name="post_type" value="product">
                <input style="width:270px" type="text" id="product-search" name="s" placeholder="Search by product name..." value="<?php echo isset($_REQUEST['s']) ? esc_attr($_REQUEST['s']) : ''; ?>">
                <input type="submit" value="Search" class="button">
            </form>
        </div>
        <div class="alignleft actions">
            <form method="get">
                <input type="hidden" name="page" value="price-change-history">
                <?php
                // Dropdown menu for filtering by user roles
                $selected_user = isset($_GET['user']) ? sanitize_text_field($_GET['user']) : 'all';
                $user_roles = array('shop_manager', 'shop_manager_simple', 'administrator');
                ?>
                <select name="user" id="user-filter">
                    <option value="all" <?php selected('all', $selected_user); ?>>All Users</option>
                    <?php
                    foreach ($user_roles as $role) {
                        $users = get_users(array('role' => $role));
                        foreach ($users as $user) {
                            $user_id = $user->ID;
                            $username = $user->display_name;
                    ?>
                            <option value="<?php echo esc_attr($user_id); ?>" <?php selected($user_id, $selected_user); ?>><?php echo esc_html($username); ?></option>
                    <?php
                        }
                    }
                    ?>
                </select>
                <?php
                // Dropdown menu for filtering by price difference
                $selected_price_difference = isset($_GET['price_difference']) ? $_GET['price_difference'] : 'all';
                ?>
                <select name="price_difference" id="price-difference-filter">
                    <option value="all" <?php selected('all', $selected_price_difference); ?>>Filter by Price Difference</option>
                    <option value="lt0" <?php selected('lt0', $selected_price_difference); ?>>Difference < 0</option>
                    <option value="0to50" <?php selected('0to50', $selected_price_difference); ?>>0 to ¥50</option>
                    <option value="50to100" <?php selected('50to100', $selected_price_difference); ?>>¥50 to ¥100</option>
                    <option value="100to200" <?php selected('100to200', $selected_price_difference); ?>>¥100 to ¥200</option>
                    <option value="gt100" <?php selected('gt100', $selected_price_difference); ?>>Difference > ¥100</option>
                    <option value="gt200" <?php selected('gt200', $selected_price_difference); ?>>Difference > ¥200</option>
                </select>
                <input type="hidden" name="post_type" value="product">
                <input type="submit" class="button" value="Filter" id="filter-button">
            </form>
        </div>
    </div>
<?php

    // Check if the table is empty
    if (empty($price_change_history_table->items)) {
        echo '<p>' . __('No price change history found.', 'price-change-history') . '</p>';
    } else {
        // Display the custom table
        $price_change_history_table->display();
    }

    echo '</div>';
}




//Enqueue the price-change-history-filter.js
function enqueue_price_change_history_script($hook)
{
    $post_type_hook = 'edit.php?post_type=product';

    if ($hook === $post_type_hook) {
        wp_enqueue_script('price-change-history-filter', get_template_directory_uri() . '/js/price-change-history-filter.js', array('jquery'), '1.0', true);
    }
}
add_action('admin_enqueue_scripts', 'enqueue_price_change_history_script');
