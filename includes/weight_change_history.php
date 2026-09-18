<?php
// Ensure the file is not accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Include the necessary WordPress files
require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
require_once ABSPATH . 'wp-admin/includes/template.php';


// Add the admin page for weight change history
function add_weight_change_history_admin_page()
{
    add_submenu_page(
        'edit.php?post_type=product',
        __('Weight Change History', 'weight-change-history'),
        __('Weight Change History', 'weight-change-history'),
        'edit_shop_orders',
        'weight-change-history',
        'weight_change_history_page_callback',
        9
    );
}
add_action('admin_menu', 'add_weight_change_history_admin_page');


// Callback function to display weight change history in the admin page
function weight_change_history_page_callback()
{
    // Create an instance of the custom table class
    $weight_change_history_table = new Weight_Change_History_List_Table();

    // Prepare the table items
    $weight_change_history_table->prepare_items();

    echo '<div class="wrap">';
    echo '<h1>' . __('Weight Change History', 'weight-change-history') . '</h1>';

    // Display the search form
?>
    <div class="tablenav top">
        <div class="alignleft actions">
            <form method="get" action="<?php echo admin_url('edit.php'); ?>">
                <input type="hidden" name="page" value="weight-change-history">
                <input type="hidden" name="post_type" value="product">
                <input style="width:270px" type="text" id="product-search" name="s" placeholder="Search by product name..." value="<?php echo isset($_REQUEST['s']) ? esc_attr($_REQUEST['s']) : ''; ?>">
                <input type="submit" value="Search" class="button">
            </form>
        </div>
        <div class="alignleft actions">
            <form method="get">
                <input type="hidden" name="page" value="weight-change-history">
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
                <input type="hidden" name="post_type" value="product">
                <input type="submit" class="button" value="Filter">
            </form>
        </div>
    </div>
<?php


    // Check if the table is empty
    if (empty($weight_change_history_table->items)) {
        echo '<p>' . __('No weight change history found.', 'weight-change-history') . '</p>';
    } else {
        // Display the custom table
        $weight_change_history_table->display();
    }

    echo '</div>';
}



// Custom table class for the weight change history list view
class Weight_Change_History_List_Table extends WP_List_Table
{
    public function __construct()
    {
        parent::__construct(
            array(
                'singular' => __('Weight Change', 'weight-change-history'),
                'plural' => __('Weight Changes', 'weight-change-history'),
                'ajax' => false,
            )
        );
    }

    // Prepare the items for the table
    public function prepare_items()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'weight_change_history';

        // Define column headers
        $columns = array(

            'product_id' => __('Product ID', 'weight-change-history'),
            'product_name' => __('Product Name', 'weight-change-history'),
            'weight_status' => __('Weight Change (old/new)', 'weight-change-history'),
            // 'new_weight' => __('Current Weight', 'weight-change-history'),
            'changed_by' => __('Changed By', 'weight-change-history'),
            'change_date' => __('Change Date', 'weight-change-history'),
        );

        // Define hidden columns (optional)
        $hidden = array();

        // Define sortable columns
        $sortable = array(

            'product_id' => array('product_id', false),
            'product_name' => array('product_name', false),
            'weight_status' => array('weight', false),
            'new_weight' => array('new_weight', false),
            'change_date' => array('change_date', true),
        );

        // Configure the table
        $this->_column_headers = array($columns, $hidden, $sortable);

        // Get the data for the table
        $per_page = 20;
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
                    "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_title LIKE %s",
                    '%' . $wpdb->esc_like($search_query) . '%'
                )
            );

            if (!empty($product_ids)) {
                $where_clause = ' WHERE product_id IN (' . implode(',', $product_ids) . ')';
            } else {
                // If no product IDs found, set a condition that will return no results
                $where_clause = ' WHERE 1=0';
            }
        }

        $total_items = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) 
            FROM $table_name
            $where_clause 
            AND product_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status != 'trash')"
            )
        );

        $this->set_pagination_args(
            array(
                'total_items' => $total_items,
                'per_page' => $per_page,
            )
        );

        $this->items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT product_id, current_weight, change_date, changed_by
                FROM $table_name
                $where_clause
                ORDER BY change_date DESC
                LIMIT %d OFFSET %d",
                $per_page,
                ($current_page - 1) * $per_page
            )
        );
        // Filter out deleted (trashed) products
        $this->items = array_filter($this->items, function ($item) {
            $post = get_post($item->product_id);
            return $post && $post->post_status !== 'trash';
        });
    }

    // Define columns for the table
    public function get_columns()
    {
        $columns = array(

            'product_id' => __('Product ID', 'weight-change-history'),
            'product_name' => __('Product Name', 'weight-change-history'),
            'weight_status' => __('Weight Change (old/new)', 'weight-change-history'),
            // 'new_weight' => __('Current Weight', 'weight-change-history'),
            'changed_by' => __('Changed By', 'weight-change-history'),
            'change_date' => __('Change Date', 'weight-change-history'),
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
                $product_name = $product ? $product->get_name() : __('Product not found', 'weight-change-history');
                $product_permalink = $product ? $product->get_permalink() : '';
                return '<a href="' . esc_url($product_permalink) . '" target="_blank">' . $product_name . '</a>';

            case 'weight_status':
                $current_weight = $item->current_weight;
                $product = wc_get_product($item->product_id);
                $new_weight = $product ? $product->get_weight() : 0;

                // Format current and new weights
                $formatted_current_weight = wc_format_weight($current_weight);
                $formatted_new_weight = wc_format_weight($new_weight);

                // Compare current and new weights and generate output
                if (floatval(trim($new_weight)) > floatval(trim($current_weight))) {
                    return $formatted_current_weight . ' <span class="dashicons dashicons-arrow-up-alt" style="color:green;"></span> ' . $formatted_new_weight;
                } elseif (floatval(trim($new_weight)) < floatval(trim($current_weight))) {
                    return $formatted_current_weight . ' <span class="dashicons dashicons-arrow-down-alt" style="color:red;"></span> ' . $formatted_new_weight;
                } elseif (floatval(trim($new_weight)) == floatval(trim($current_weight))) {
                    return $formatted_current_weight . ' <span class="dashicons dashicons-menu" style="color:blue;"></span> ' . $formatted_new_weight;
                }

                return '';

                // case 'new_weight':
                //     $product = wc_get_product($item->product_id);
                //     return $product ? $product->get_weight() : 0;

            case 'changed_by':
                $user = get_user_by('id', $item->changed_by);
                $username = $user ? $user->display_name : __('Unknown', 'weight-change-history');
                return $username;

            case 'change_date':
                return date('F j, Y H:i A', strtotime($item->change_date));

            default:
                return ''; // Show nothing for all other columns
        }
    }
}
