<?php
/**
 * CSV export of the change history, honouring the current filters.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PSWT_Export
{
    public static function init()
    {
        add_action('admin_post_pswt_export', array(__CLASS__, 'handle'));
    }

    public static function handle()
    {
        check_admin_referer('pswt_export');

        if (!current_user_can(PSWT_Admin::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to export this data.', 'price-stock-weight-tracker-for-woocommerce'));
        }

        require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
        require_once PSWT_PATH . 'includes/class-pswt-history-table.php';

        $filters = PSWT_History_Table::read_filters();
        $result = PSWT_Repository::query(array_merge($filters, array('per_page' => -1)));

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="product-change-history-' . gmdate('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, array(
            'Product ID',
            'Product',
            'SKU',
            'Changed',
            'Old value',
            'New value',
            'Changed by',
            'Date (' . wp_timezone_string() . ')',
        ));

        foreach ($result['items'] as $item) {
            $product = wc_get_product($item->product_id);
            fputcsv($output, array(
                (int) $item->product_id,
                self::cell($product ? $product->get_name() : ''),
                self::cell($product ? $product->get_sku() : ''),
                self::cell(PSWT_Format::type_label($item->change_type)),
                self::cell($item->old_value),
                self::cell($item->new_value),
                self::cell(PSWT_Format::user_name($item->changed_by)),
                get_date_from_gmt($item->change_date, 'Y-m-d H:i:s'),
            ));
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- php://output stream.
        fclose($output);
        exit;
    }

    /**
     * Neutralise spreadsheet formula injection.
     *
     * @param string $value Cell value.
     * @return string
     */
    private static function cell($value)
    {
        $value = (string) $value;
        if ($value !== '' && strpbrk($value[0], '=+-@') !== false) {
            $value = "'" . $value;
        }

        return $value;
    }
}
