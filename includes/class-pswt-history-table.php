<?php
/**
 * List table for the change history.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PSWT_History_Table extends WP_List_Table
{
    /**
     * Sanitized filters read from the request.
     *
     * @var array
     */
    public $filters = array();

    public function __construct()
    {
        parent::__construct(array(
            'singular' => 'pswt_change',
            'plural'   => 'pswt_changes',
            'ajax'     => false,
        ));
    }

    /**
     * Read and sanitize the list filters from the query string.
     * These filters only narrow a read-only listing, so no nonce is involved.
     *
     * @return array
     */
    public static function read_filters()
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only list filters.
        $group = isset($_GET['group']) ? sanitize_key(wp_unslash($_GET['group'])) : '';
        if (!isset(PSWT_Repository::groups()[$group])) {
            $group = '';
        }

        $user = isset($_GET['user']) ? sanitize_text_field(wp_unslash($_GET['user'])) : '';
        $user = ($user !== '' && ctype_digit($user)) ? (int) $user : '';

        $orderby = isset($_GET['orderby']) ? sanitize_key(wp_unslash($_GET['orderby'])) : 'change_date';
        if (!in_array($orderby, array('change_date', 'change_type', 'product_id', 'changed_by'), true)) {
            $orderby = 'change_date';
        }

        $order = isset($_GET['order']) && strtolower(sanitize_key(wp_unslash($_GET['order']))) === 'asc' ? 'ASC' : 'DESC';

        $filters = array(
            'group'      => $group,
            'user'       => $user,
            'product_id' => isset($_GET['product_id']) ? absint($_GET['product_id']) : 0,
            'search'     => isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '',
            'date_from'  => isset($_GET['date_from']) ? self::sanitize_date(sanitize_text_field(wp_unslash($_GET['date_from']))) : '',
            'date_to'    => isset($_GET['date_to']) ? self::sanitize_date(sanitize_text_field(wp_unslash($_GET['date_to']))) : '',
            'orderby'    => $orderby,
            'order'      => $order,
        );
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        if ($filters['date_from'] && $filters['date_to'] && $filters['date_from'] > $filters['date_to']) {
            list($filters['date_from'], $filters['date_to']) = array($filters['date_to'], $filters['date_from']);
        }

        return $filters;
    }

    /**
     * Accept only Y-m-d dates (the value must already be sanitized).
     *
     * @param string $value Sanitized value.
     * @return string
     */
    public static function sanitize_date($value)
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value) && strtotime($value) ? $value : '';
    }

    public function prepare_items()
    {
        $this->filters = self::read_filters();

        $per_page = $this->get_items_per_page('pswt_per_page', 25);
        $paged = $this->get_pagenum();

        $result = PSWT_Repository::query(array_merge($this->filters, array(
            'per_page' => $per_page,
            'paged'    => $paged,
        )));

        $this->items = $result['items'];
        $this->set_pagination_args(array(
            'total_items' => $result['total'],
            'per_page'    => $per_page,
        ));

        $this->_column_headers = array($this->get_columns(), array(), $this->get_sortable_columns(), 'product');
    }

    public function get_columns()
    {
        return array(
            'product'     => __('Product', 'price-stock-weight-tracker-for-woocommerce'),
            'change_type' => __('Changed', 'price-stock-weight-tracker-for-woocommerce'),
            'change'      => __('From → To', 'price-stock-weight-tracker-for-woocommerce'),
            'current'     => __('Now', 'price-stock-weight-tracker-for-woocommerce'),
            'changed_by'  => __('By', 'price-stock-weight-tracker-for-woocommerce'),
            'change_date' => __('When', 'price-stock-weight-tracker-for-woocommerce'),
        );
    }

    public function get_sortable_columns()
    {
        return array(
            'product'     => array('product_id', false),
            'change_type' => array('change_type', false),
            'changed_by'  => array('changed_by', false),
            'change_date' => array('change_date', true),
        );
    }

    protected function get_primary_column_name()
    {
        return 'product';
    }

    public function no_items()
    {
        if ($this->filters['search'] || $this->filters['user'] !== '' || $this->filters['date_from'] || $this->filters['date_to'] || $this->filters['group']) {
            esc_html_e('No changes match these filters.', 'price-stock-weight-tracker-for-woocommerce');
        } else {
            esc_html_e('No changes recorded yet. Edit a product price, stock, title or weight and it will show up here.', 'price-stock-weight-tracker-for-woocommerce');
        }
    }

    public function column_product($item)
    {
        return PSWT_Format::product_cell($item->product_id);
    }

    public function column_change_type($item)
    {
        return PSWT_Format::type_badge($item->change_type);
    }

    public function column_change($item)
    {
        return PSWT_Format::change($item);
    }

    public function column_current($item)
    {
        return PSWT_Format::current_value(wc_get_product($item->product_id), $item->change_type);
    }

    public function column_changed_by($item)
    {
        return PSWT_Format::user_html($item->changed_by);
    }

    public function column_change_date($item)
    {
        return PSWT_Format::date($item->change_date);
    }

    public function column_default($item, $column_name)
    {
        return '';
    }
}
