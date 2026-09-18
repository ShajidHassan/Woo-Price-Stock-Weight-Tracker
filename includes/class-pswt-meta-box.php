<?php
/**
 * "Change history" box on the product edit screen.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PSWT_Meta_Box
{
    const LIMIT = 10;

    public static function init()
    {
        add_action('add_meta_boxes_product', array(__CLASS__, 'register'));
    }

    public static function register()
    {
        if (!current_user_can(PSWT_Admin::CAPABILITY)) {
            return;
        }

        add_meta_box(
            'pswt_history',
            __('Change History', 'price-stock-weight-tracker-for-woocommerce'),
            array(__CLASS__, 'render'),
            'product',
            'normal',
            'default'
        );
    }

    /**
     * @param WP_Post $post Product post.
     */
    public static function render($post)
    {
        $result = PSWT_Repository::query(array('product_id' => $post->ID, 'per_page' => self::LIMIT));
        $items = $result['items'];
        $total = $result['total'];

        if (!$items) {
            echo '<p class="pswt-muted">' . esc_html__('No changes recorded for this product yet.', 'price-stock-weight-tracker-for-woocommerce') . '</p>';
            return;
        }

        $product = wc_get_product($post->ID);
        $has_variations = $product && $product->is_type('variable');
        ?>
        <table class="pswt-table pswt-table--compact">
            <thead>
                <tr>
                    <?php if ($has_variations) : ?>
                        <th><?php esc_html_e('Variation', 'price-stock-weight-tracker-for-woocommerce'); ?></th>
                    <?php endif; ?>
                    <th><?php esc_html_e('Changed', 'price-stock-weight-tracker-for-woocommerce'); ?></th>
                    <th><?php esc_html_e('From → To', 'price-stock-weight-tracker-for-woocommerce'); ?></th>
                    <th><?php esc_html_e('By', 'price-stock-weight-tracker-for-woocommerce'); ?></th>
                    <th><?php esc_html_e('When', 'price-stock-weight-tracker-for-woocommerce'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item) : ?>
                    <tr>
                        <?php if ($has_variations) : ?>
                            <td>
                                <?php
                                if ((int) $item->product_id !== (int) $post->ID) {
                                    $variation = wc_get_product($item->product_id);
                                    echo esc_html($variation ? wc_get_formatted_variation($variation, true, false, true) : '#' . $item->product_id);
                                } else {
                                    echo '<span class="pswt-muted">' . esc_html__('Parent', 'price-stock-weight-tracker-for-woocommerce') . '</span>';
                                }
                                ?>
                            </td>
                        <?php endif; ?>
                        <td><?php echo wp_kses_post(PSWT_Format::type_badge($item->change_type)); ?></td>
                        <td><?php echo wp_kses_post(PSWT_Format::change($item)); ?></td>
                        <td><?php echo wp_kses_post(PSWT_Format::user_html($item->changed_by)); ?></td>
                        <td><?php echo wp_kses_post(PSWT_Format::date($item->change_date)); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p class="pswt-meta-footer">
            <?php
            printf(
                /* translators: 1: number shown, 2: total number of changes */
                esc_html__('Showing the latest %1$s of %2$s changes.', 'price-stock-weight-tracker-for-woocommerce'),
                esc_html(number_format_i18n(count($items))),
                esc_html(number_format_i18n($total))
            );
            ?>
            <a href="<?php echo esc_url(PSWT_Admin::page_url('pswt-history', array('product_id' => $post->ID))); ?>"><?php esc_html_e('View full history', 'price-stock-weight-tracker-for-woocommerce'); ?> &rarr;</a>
        </p>
        <?php
    }
}
