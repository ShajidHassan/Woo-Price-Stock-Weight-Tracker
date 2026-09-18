<?php

// Ensure the file is not accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Include the necessary WordPress files
require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
require_once ABSPATH . 'wp-admin/includes/template.php';


// Function to add the custom admin menu
function title_change_history_admin_menu()
{
    add_submenu_page(
        'edit.php?post_type=product',
        __('Title Change History', 'title-change-history'),
        __('Title Change History', 'title-change-history'),
        'edit_shop_orders',
        'title-change-history',
        'title_change_history_page_callback',
        8
    );
}
add_action('admin_menu', 'title_change_history_admin_menu');


// Custom table class for the title change history list view
class Title_Change_History_List_Table extends WP_List_Table
{
    public function __construct()
    {
        parent::__construct(array(
            'singular' => __('Title Change', 'title-change-history'),
            'plural'   => __('Title Changes', 'title-change-history'),
            'ajax'     => false,
        ));
    }

    // Prepare the items for the table
    public function prepare_items()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'title_change_history';

        // Define column headers
        $columns = $this->get_columns();

        // Define hidden columns (optional)
        $hidden = array();

        // Define sortable columns
        $sortable = array(
            'product_id'   => array('product_id', false),
            'product_name' => array('product_id', false),
            'title_status' => array('title_status', false),
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

        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
        ));

        $this->items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT product_id, current_product_title, change_date, changed_by
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
            'product_id'   => __('Product ID', 'title-change-history'),
            'product_name' => __('Product Name', 'title-change-history'),
            'title_status' => __('Title Change (old/new)', 'title-change-history'),
            'changed_by'   => __('Changed By', 'title-change-history'),
            'change_date'  => __('Change Date', 'title-change-history'),
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
                $product_name = $product ? $product->get_name() : __('Product not found', 'title-change-history');
                $product_permalink = $product ? $product->get_permalink() : '';
                return '<a href="' . esc_url($product_permalink) . '" target="_blank">' . $product_name . '</a>';

            case 'title_status':
                $old_title = $item->current_product_title;
                $product = wc_get_product($item->product_id);
                $new_title = $product ? $product->get_title() : '';

                $changed_characters_old = '';
                $changed_characters_new = '';

                // Find the minimum length between old and new titles
                $length = min(strlen($old_title), strlen($new_title));

                // Display changes for the old title
                for ($i = 0; $i < $length; $i++) {
                    if ($old_title[$i] !== $new_title[$i]) {
                        $changed_characters_old .= '<span style="color: red;">' . esc_html($old_title[$i]) . '</span>';
                    } else {
                        $changed_characters_old .= esc_html($old_title[$i]);
                    }
                }

                if (strlen($old_title) > $length) {
                    $changed_characters_old .= '<span style="color: red;">' . esc_html(substr($old_title, $length)) . '</span>';
                }

                for ($i = 0; $i < $length; $i++) {
                    if ($old_title[$i] !== $new_title[$i]) {
                        $changed_characters_new .= '<span style="color: red;">' . esc_html($new_title[$i]) . '</span>';
                    } else {
                        $changed_characters_new .= '<span style="color: green;">' . esc_html($new_title[$i]);
                    }
                }

                if (strlen($new_title) > $length) {
                    $changed_characters_new .= '<span style="color: red;">' . esc_html(substr($new_title, $length)) . '</span>';
                }

                return '<span>' . $changed_characters_old . '</span>' . ' <span class="dashicons dashicons-arrow-right-alt" style="color:#515151; font-size:16px;margin-top:3px;"></span> ' . '<span>' . $changed_characters_new . '</span>';



                // case 'new_title':
                //     $product = wc_get_product($item->product_id);
                //     return $product ? $product->get_title() : 0;

            case 'changed_by':
                $user = get_user_by('id', $item->changed_by);
                $username = $user ? $user->display_name : __('Unknown', 'title-change-history');
                return $username;

            case 'change_date':
                return date('F j, Y H:i A', strtotime($item->change_date));

            default:
                return '';
        }
    }
}

// Callback function to display title change history in the admin page
function title_change_history_page_callback()
{
    // Create an instance of the custom table class
    $title_change_history_table = new Title_Change_History_List_Table();

    // Prepare the table items
    $title_change_history_table->prepare_items();

    echo '<div class="wrap">';
    echo '<h1>' . __('Title Change History', 'title-change-history') . '</h1>';

    // Display the search form
?>
    <div class="tablenav top">
        <div class="alignleft actions">
            <form method="get" action="<?php echo admin_url('edit.php'); ?>">
                <input type="hidden" name="page" value="title-change-history">
                <input type="hidden" name="post_type" value="product">
                <input style="width:270px" type="text" id="product-search" name="s" placeholder="Search by product name..." value="<?php echo isset($_REQUEST['s']) ? esc_attr($_REQUEST['s']) : ''; ?>">
                <input type="submit" value="Search" class="button">
            </form>
        </div>
        <div class="alignleft actions">
            <form method="get">
                <input type="hidden" name="page" value="title-change-history">
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
    if (empty($title_change_history_table->items)) {
        echo '<p>' . __('No title change history found.', 'title-change-history') . '</p>';
    } else {
        // Display the custom table
        $title_change_history_table->display();
    }

    echo '</div>';
}
