<?php
/**
 * Daily report: what changed, what was added and what was sold in a date range.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PSWT_Report
{
    /**
     * Render the report page.
     */
    public static function render()
    {
        if (!current_user_can(PSWT_Admin::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to view this page.', 'price-stock-weight-tracker-for-woocommerce'));
        }

        $today = wp_date('Y-m-d');

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only date range.
        $from = isset($_GET['from']) ? PSWT_History_Table::sanitize_date(sanitize_text_field(wp_unslash($_GET['from']))) : '';
        $to = isset($_GET['to']) ? PSWT_History_Table::sanitize_date(sanitize_text_field(wp_unslash($_GET['to']))) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        $from = $from ? $from : $today;
        $to = $to ? $to : $from;
        if ($from > $to) {
            list($from, $to) = array($to, $from);
        }

        $from_gmt = get_gmt_from_date($from . ' 00:00:00');
        $to_gmt = get_gmt_from_date($to . ' 23:59:59');

        $counts = PSWT_Repository::counts_by_group($from_gmt, $to_gmt);
        $price_changes = PSWT_Repository::query(array('group' => 'price', 'date_from' => $from, 'date_to' => $to, 'per_page' => -1))['items'];
        $stock_changes = PSWT_Repository::query(array('group' => 'stock', 'date_from' => $from, 'date_to' => $to, 'per_page' => -1))['items'];

        $orders = self::count_orders($from, $to);
        $completed = self::count_completed_orders($from, $to);
        $new_products = self::new_products($from, $to);

        $date_format = get_option('date_format');
        $range_label = $from === $to
            ? wp_date($date_format . ' (l)', strtotime($from . ' 12:00:00'))
            : sprintf(
                /* translators: 1: start date, 2: end date */
                __('%1$s to %2$s', 'price-stock-weight-tracker-for-woocommerce'),
                wp_date($date_format, strtotime($from . ' 12:00:00')),
                wp_date($date_format, strtotime($to . ' 12:00:00'))
            );

        $quick = array(
            'today' => array(__('Today', 'price-stock-weight-tracker-for-woocommerce'), $today, $today),
            'yesterday' => array(__('Yesterday', 'price-stock-weight-tracker-for-woocommerce'), wp_date('Y-m-d', strtotime('-1 day', current_time('timestamp'))), wp_date('Y-m-d', strtotime('-1 day', current_time('timestamp')))),
            'week' => array(__('Last 7 days', 'price-stock-weight-tracker-for-woocommerce'), wp_date('Y-m-d', strtotime('-6 days', current_time('timestamp'))), $today),
            'month' => array(__('Last 30 days', 'price-stock-weight-tracker-for-woocommerce'), wp_date('Y-m-d', strtotime('-29 days', current_time('timestamp'))), $today),
        );
        ?>
        <div class="wrap pswt-wrap pswt-report">
            <h1 class="wp-heading-inline"><?php esc_html_e('Daily Report', 'price-stock-weight-tracker-for-woocommerce'); ?></h1>
            <button type="button" class="page-title-action pswt-print"><?php esc_html_e('Print', 'price-stock-weight-tracker-for-woocommerce'); ?></button>
            <hr class="wp-header-end">

            <form method="get" class="pswt-filters pswt-no-print">
                <input type="hidden" name="page" value="pswt-report">
                <label class="screen-reader-text" for="pswt-from"><?php esc_html_e('From date', 'price-stock-weight-tracker-for-woocommerce'); ?></label>
                <input type="date" id="pswt-from" name="from" value="<?php echo esc_attr($from); ?>" required>
                <span class="pswt-filters-sep" aria-hidden="true">&ndash;</span>
                <label class="screen-reader-text" for="pswt-to"><?php esc_html_e('To date', 'price-stock-weight-tracker-for-woocommerce'); ?></label>
                <input type="date" id="pswt-to" name="to" value="<?php echo esc_attr($to); ?>" required>
                <button type="submit" class="button button-primary"><?php esc_html_e('Show', 'price-stock-weight-tracker-for-woocommerce'); ?></button>
                <span class="pswt-quick">
                    <?php foreach ($quick as $key => $range) : ?>
                        <a class="button-link<?php echo ($from === $range[1] && $to === $range[2]) ? ' is-active' : ''; ?>" href="<?php echo esc_url(PSWT_Admin::page_url('pswt-report', array('from' => $range[1], 'to' => $range[2]))); ?>"><?php echo esc_html($range[0]); ?></a>
                    <?php endforeach; ?>
                </span>
            </form>

            <p class="pswt-report-range"><?php echo esc_html($range_label); ?></p>

            <div class="pswt-stats pswt-stats--6">
                <?php
                self::stat($counts['price'], __('Price changes', 'price-stock-weight-tracker-for-woocommerce'));
                self::stat($counts['stock'], __('Stock changes', 'price-stock-weight-tracker-for-woocommerce'));
                self::stat($counts['title'] + $counts['weight'], __('Title & weight changes', 'price-stock-weight-tracker-for-woocommerce'));
                self::stat(count($new_products), __('New products', 'price-stock-weight-tracker-for-woocommerce'));
                self::stat($orders, __('Orders received', 'price-stock-weight-tracker-for-woocommerce'));
                self::stat($completed, __('Orders completed', 'price-stock-weight-tracker-for-woocommerce'));
                ?>
            </div>

            <div class="pswt-report-grid">
                <section class="pswt-card pswt-card--padded">
                    <h2><?php esc_html_e('Price changes', 'price-stock-weight-tracker-for-woocommerce'); ?> <span class="pswt-count"><?php echo esc_html(number_format_i18n(count($price_changes))); ?></span></h2>
                    <?php self::user_summary($price_changes); ?>
                    <?php self::changes_table($price_changes); ?>
                </section>

                <section class="pswt-card pswt-card--padded">
                    <h2><?php esc_html_e('Stock changes', 'price-stock-weight-tracker-for-woocommerce'); ?> <span class="pswt-count"><?php echo esc_html(number_format_i18n(count($stock_changes))); ?></span></h2>
                    <?php self::user_summary($stock_changes); ?>
                    <?php self::changes_table($stock_changes); ?>
                </section>

                <section class="pswt-card pswt-card--padded pswt-report-wide">
                    <h2><?php esc_html_e('New products', 'price-stock-weight-tracker-for-woocommerce'); ?> <span class="pswt-count"><?php echo esc_html(number_format_i18n(count($new_products))); ?></span></h2>
                    <?php self::products_table($new_products); ?>
                </section>
            </div>
        </div>
        <?php
    }

    /**
     * One stat tile.
     *
     * @param int    $value Value.
     * @param string $label Label.
     */
    private static function stat($value, $label)
    {
        echo '<div class="pswt-stat"><span class="pswt-stat-value">' . esc_html(number_format_i18n((int) $value)) . '</span><span class="pswt-stat-label">' . esc_html($label) . '</span></div>';
    }

    /**
     * "Name (count)" chips for the users behind a set of changes.
     *
     * @param object[] $items Log rows.
     */
    private static function user_summary($items)
    {
        if (!$items) {
            return;
        }

        $counts = array();
        foreach ($items as $item) {
            $id = (int) $item->changed_by;
            $counts[$id] = isset($counts[$id]) ? $counts[$id] + 1 : 1;
        }
        arsort($counts);

        echo '<div class="pswt-chips">';
        foreach ($counts as $user_id => $count) {
            echo '<span class="pswt-chip">' . esc_html(PSWT_Format::user_name($user_id)) . ' <strong>' . esc_html(number_format_i18n($count)) . '</strong></span>';
        }
        echo '</div>';
    }

    /**
     * Table of log rows.
     *
     * @param object[] $items Log rows.
     */
    private static function changes_table($items)
    {
        if (!$items) {
            echo '<p class="pswt-muted">' . esc_html__('Nothing in this period.', 'price-stock-weight-tracker-for-woocommerce') . '</p>';
            return;
        }
        ?>
        <table class="pswt-table pswt-table--compact">
            <thead>
                <tr>
                    <th><?php esc_html_e('Product', 'price-stock-weight-tracker-for-woocommerce'); ?></th>
                    <th><?php esc_html_e('Changed', 'price-stock-weight-tracker-for-woocommerce'); ?></th>
                    <th><?php esc_html_e('From → To', 'price-stock-weight-tracker-for-woocommerce'); ?></th>
                    <th><?php esc_html_e('By', 'price-stock-weight-tracker-for-woocommerce'); ?></th>
                    <th><?php esc_html_e('When', 'price-stock-weight-tracker-for-woocommerce'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item) : ?>
                    <tr>
                        <td><?php echo wp_kses_post(PSWT_Format::product_cell($item->product_id)); ?></td>
                        <td><?php echo wp_kses_post(PSWT_Format::type_badge($item->change_type)); ?></td>
                        <td><?php echo wp_kses_post(PSWT_Format::change($item)); ?></td>
                        <td><?php echo esc_html(PSWT_Format::user_name($item->changed_by)); ?></td>
                        <td><?php echo wp_kses_post(PSWT_Format::date($item->change_date)); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Table of newly created products.
     *
     * @param WC_Product[] $products Products.
     */
    private static function products_table($products)
    {
        if (!$products) {
            echo '<p class="pswt-muted">' . esc_html__('No products were added in this period.', 'price-stock-weight-tracker-for-woocommerce') . '</p>';
            return;
        }
        ?>
        <table class="pswt-table pswt-table--compact">
            <thead>
                <tr>
                    <th><?php esc_html_e('Product', 'price-stock-weight-tracker-for-woocommerce'); ?></th>
                    <th><?php esc_html_e('Regular price', 'price-stock-weight-tracker-for-woocommerce'); ?></th>
                    <th><?php esc_html_e('Sale price', 'price-stock-weight-tracker-for-woocommerce'); ?></th>
                    <th><?php esc_html_e('Stock', 'price-stock-weight-tracker-for-woocommerce'); ?></th>
                    <th><?php esc_html_e('Added by', 'price-stock-weight-tracker-for-woocommerce'); ?></th>
                    <th><?php esc_html_e('Added', 'price-stock-weight-tracker-for-woocommerce'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product) : ?>
                    <?php
                    $created = $product->get_date_created();
                    $stock = $product->managing_stock()
                        ? esc_html(wc_stock_amount($product->get_stock_quantity('edit')))
                        : PSWT_Format::stock_badge($product->get_stock_status('edit'));
                    ?>
                    <tr>
                        <td><?php echo wp_kses_post(PSWT_Format::product_cell($product->get_id())); ?></td>
                        <td><?php echo wp_kses_post(PSWT_Format::value('regular_price', $product->get_regular_price('edit'))); ?></td>
                        <td><?php echo wp_kses_post(PSWT_Format::value('sale_price', $product->get_sale_price('edit'))); ?></td>
                        <td><?php echo wp_kses_post($stock); ?></td>
                        <td><?php echo esc_html(PSWT_Format::user_name(get_post_field('post_author', $product->get_id()))); ?></td>
                        <td><?php echo $created ? wp_kses_post(PSWT_Format::date($created->date('Y-m-d H:i:s'), true)) : ''; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Number of orders placed in the range (any status except failed, cancelled, refunded or pending).
     *
     * @param string $from Y-m-d.
     * @param string $to   Y-m-d.
     * @return int
     */
    private static function count_orders($from, $to)
    {
        $excluded = array('wc-cancelled', 'wc-refunded', 'wc-failed', 'wc-pending', 'wc-checkout-draft');
        $statuses = array_values(array_diff(array_keys(wc_get_order_statuses()), $excluded));

        $ids = wc_get_orders(array(
            'type'         => 'shop_order',
            'status'       => $statuses,
            'date_created' => $from . '...' . $to,
            'limit'        => -1,
            'return'       => 'ids',
        ));

        return count($ids);
    }

    /**
     * Number of orders completed in the range.
     *
     * @param string $from Y-m-d.
     * @param string $to   Y-m-d.
     * @return int
     */
    private static function count_completed_orders($from, $to)
    {
        $ids = wc_get_orders(array(
            'type'           => 'shop_order',
            'status'         => 'completed',
            'date_completed' => $from . '...' . $to,
            'limit'          => -1,
            'return'         => 'ids',
        ));

        return count($ids);
    }

    /**
     * Published products created in the range.
     *
     * @param string $from Y-m-d.
     * @param string $to   Y-m-d.
     * @return WC_Product[]
     */
    private static function new_products($from, $to)
    {
        return wc_get_products(array(
            'status'       => 'publish',
            'date_created' => $from . '...' . $to,
            'limit'        => 500,
            'orderby'      => 'date',
            'order'        => 'DESC',
        ));
    }
}
