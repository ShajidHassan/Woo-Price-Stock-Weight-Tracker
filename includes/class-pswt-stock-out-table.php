<?php
/**
 * List table of products that are currently out of stock.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PSWT_Stock_Out_Table extends WP_List_Table
{
    /**
     * Sanitized filters.
     *
     * @var array
     */
    public $filters = array();

    /**
     * Total matching products.
     *
     * @var int
     */
    public $total = 0;

    public function __construct()
    {
        parent::__construct(array(
            'singular' => 'pswt_stock_out_product',
            'plural'   => 'pswt_stock_out_products',
            'ajax'     => false,
        ));
    }

    /**
     * Read and sanitize filters. Read-only listing, no nonce involved.
     *
     * @return array
     */
    public static function read_filters()
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only list filters.
        $orderby = isset($_GET['orderby']) ? sanitize_key(wp_unslash($_GET['orderby'])) : 'last_change';
        if (!in_array($orderby, array('product', 'stock_quantity', 'total_sales', 'last_change'), true)) {
            $orderby = 'last_change';
        }

        $filters = array(
            'search'   => isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '',
            'category' => isset($_GET['category']) ? absint($_GET['category']) : 0,
            'orderby'  => $orderby,
            'order'    => isset($_GET['order']) && strtolower(sanitize_key(wp_unslash($_GET['order']))) === 'asc' ? 'ASC' : 'DESC',
        );
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        return $filters;
    }

    public function prepare_items()
    {
        global $wpdb;

        $this->filters = self::read_filters();

        $per_page = $this->get_items_per_page('pswt_stock_out_per_page', 25);
        $paged = $this->get_pagenum();

        $cache_key = 'stock_out_' . md5(wp_json_encode($this->filters) . $per_page . $paged);
        $cached = wp_cache_get($cache_key, 'pswt');

        if (is_array($cached)) {
            $this->total = $cached['total'];
            $this->items = $cached['items'];
        } else {
            // The query is assembled from literal fragments; every value goes through a placeholder.
            $params = array('outofstock');
            $sql = ' FROM ' . $wpdb->posts . ' p INNER JOIN ' . $wpdb->wc_product_meta_lookup . " l ON l.product_id = p.ID WHERE p.post_type IN ('product', 'product_variation') AND p.post_status IN ('publish', 'private') AND l.stock_status = %s";

            if ($this->filters['search'] !== '') {
                $like = '%' . $wpdb->esc_like($this->filters['search']) . '%';
                $sql .= ' AND (p.post_title LIKE %s OR l.sku LIKE %s)';
                $params[] = $like;
                $params[] = $like;
            }

            if ($this->filters['category']) {
                // The category itself plus every descendant, matched on the parent product for variations.
                $term_ids = array_merge(array($this->filters['category']), (array) get_term_children($this->filters['category'], 'product_cat'));
                $sql .= ' AND COALESCE(NULLIF(p.post_parent, 0), p.ID) IN (SELECT tr.object_id FROM ' . $wpdb->term_relationships . ' tr INNER JOIN ' . $wpdb->term_taxonomy . " tt ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE tt.taxonomy = 'product_cat' AND tt.term_id IN (" . implode(',', array_fill(0, count($term_ids), '%d')) . '))';
                $params = array_merge($params, array_map('intval', $term_ids));
            }

            $this->total = (int) PSWT_Repository::get_var('SELECT COUNT(*)' . $sql, $params);

            switch ($this->filters['orderby']) {
                case 'product':
                    $sql .= ' ORDER BY p.post_title';
                    break;
                case 'stock_quantity':
                    $sql .= ' ORDER BY l.stock_quantity';
                    break;
                case 'total_sales':
                    $sql .= ' ORDER BY l.total_sales';
                    break;
                default:
                    $sql .= ' ORDER BY last_change';
            }
            $sql .= $this->filters['order'] === 'ASC' ? ' ASC, p.ID ASC' : ' DESC, p.ID DESC';
            $sql .= ' LIMIT %d OFFSET %d';
            $params[] = $per_page;
            $params[] = ($paged - 1) * $per_page;

            $this->items = PSWT_Repository::get_results(
                "SELECT p.ID AS product_id, l.stock_quantity, l.total_sales, (SELECT MAX(lg.change_date) FROM %i lg WHERE lg.product_id = p.ID AND lg.change_type = 'stock_status' AND lg.new_value = 'outofstock') AS last_change" . $sql,
                array_merge(array(PSWT_Repository::table()), $params)
            );

            wp_cache_set($cache_key, array('total' => $this->total, 'items' => $this->items), 'pswt', MINUTE_IN_SECONDS);
        }

        $this->set_pagination_args(array(
            'total_items' => $this->total,
            'per_page'    => $per_page,
        ));

        $this->_column_headers = array($this->get_columns(), array(), $this->get_sortable_columns(), 'product');
    }

    public function get_columns()
    {
        return array(
            'product'        => __('Product', 'price-stock-weight-tracker-for-woocommerce'),
            'categories'     => __('Categories', 'price-stock-weight-tracker-for-woocommerce'),
            'stock_quantity' => __('Stock', 'price-stock-weight-tracker-for-woocommerce'),
            'total_sales'    => __('Total sales', 'price-stock-weight-tracker-for-woocommerce'),
            'last_change'    => __('Out of stock since', 'price-stock-weight-tracker-for-woocommerce'),
        );
    }

    public function get_sortable_columns()
    {
        return array(
            'product'        => array('product', false),
            'stock_quantity' => array('stock_quantity', false),
            'total_sales'    => array('total_sales', false),
            'last_change'    => array('last_change', true),
        );
    }

    protected function get_primary_column_name()
    {
        return 'product';
    }

    public function no_items()
    {
        esc_html_e('No products are out of stock.', 'price-stock-weight-tracker-for-woocommerce');
    }

    public function column_product($item)
    {
        return PSWT_Format::product_cell($item->product_id);
    }

    public function column_categories($item)
    {
        $product = wc_get_product($item->product_id);
        if (!$product) {
            return '';
        }

        $parent_id = $product->is_type('variation') ? $product->get_parent_id() : $product->get_id();
        $terms = get_the_terms($parent_id, 'product_cat');
        if (!$terms || is_wp_error($terms)) {
            return '<span class="pswt-muted">&mdash;</span>';
        }

        return esc_html(implode(', ', wp_list_pluck($terms, 'name')));
    }

    public function column_stock_quantity($item)
    {
        if ($item->stock_quantity === null) {
            return PSWT_Format::stock_badge('outofstock');
        }

        return esc_html(wc_stock_amount($item->stock_quantity));
    }

    public function column_total_sales($item)
    {
        return esc_html(number_format_i18n((int) $item->total_sales));
    }

    public function column_last_change($item)
    {
        return $item->last_change ? PSWT_Format::date($item->last_change) : '<span class="pswt-muted">' . esc_html__('Unknown', 'price-stock-weight-tracker-for-woocommerce') . '</span>';
    }

    public function column_default($item, $column_name)
    {
        return '';
    }
}
