<?php
/**
 * Captures product changes from every save path: classic editor, quick/bulk edit, REST API,
 * CLI, importers and order-driven stock updates.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PSWT_Tracker
{
    /**
     * Stock quantities captured before an order-driven stock update, keyed by product id.
     *
     * @var array
     */
    private static $pending_stock = array();

    /**
     * Hook everything up.
     */
    public static function init()
    {
        add_action('woocommerce_before_product_object_save', array(__CLASS__, 'capture_object_changes'), 10, 2);
        add_action('post_updated', array(__CLASS__, 'capture_title_change'), 10, 3);
        add_action('woocommerce_product_before_set_stock', array(__CLASS__, 'remember_stock'));
        add_action('woocommerce_variation_before_set_stock', array(__CLASS__, 'remember_stock'));
        add_action('woocommerce_product_set_stock', array(__CLASS__, 'capture_stock_update'));
        add_action('woocommerce_variation_set_stock', array(__CLASS__, 'capture_stock_update'));
        add_action('before_delete_post', array(__CLASS__, 'on_product_deleted'), 10, 2);
    }

    /**
     * Compare the pending changes on a product object against what is stored.
     *
     * @param WC_Product         $product    Product about to be saved.
     * @param WC_Data_Store|null $data_store Data store.
     */
    public static function capture_object_changes($product, $data_store = null)
    {
        if (!$product instanceof WC_Product || !$product->get_id()) {
            return;
        }

        $watched = array('regular_price', 'sale_price', 'stock_status', 'stock_quantity', 'weight');
        $changes = array_intersect_key($product->get_changes(), array_flip($watched));
        if (!$changes) {
            return;
        }

        // A fresh read gives the values currently in the database.
        $stored = wc_get_product($product->get_id());
        if (!$stored) {
            return;
        }

        $parent_id = $product->is_type('variation') ? $product->get_parent_id() : 0;

        foreach ($changes as $prop => $new_value) {
            $getter = 'get_' . $prop;
            $old_value = self::normalize($stored->$getter('edit'));
            $new_value = self::normalize($new_value);

            if ($old_value === $new_value) {
                continue;
            }

            self::record($product->get_id(), $parent_id, $prop, $old_value, $new_value);
        }
    }

    /**
     * Product titles are saved through wp_update_post, so compare the post objects.
     *
     * @param int     $post_id     Post id.
     * @param WP_Post $post_after  Post after the update.
     * @param WP_Post $post_before Post before the update.
     */
    public static function capture_title_change($post_id, $post_after, $post_before)
    {
        if ($post_after->post_type !== 'product' || $post_after->post_title === $post_before->post_title) {
            return;
        }

        if (in_array($post_before->post_status, array('auto-draft', 'new'), true)) {
            return;
        }

        self::record($post_id, 0, 'title', $post_before->post_title, $post_after->post_title);
    }

    /**
     * Remember the quantity before WooCommerce applies a direct stock update (orders, refunds).
     *
     * @param WC_Product $product Product before the update.
     */
    public static function remember_stock($product)
    {
        if ($product instanceof WC_Product) {
            self::$pending_stock[$product->get_id()] = self::normalize($product->get_stock_quantity('edit'));
        }
    }

    /**
     * Record the quantity after a direct stock update.
     *
     * @param WC_Product $product Product after the update.
     */
    public static function capture_stock_update($product)
    {
        if (!$product instanceof WC_Product || !isset(self::$pending_stock[$product->get_id()])) {
            return;
        }

        $old_value = self::$pending_stock[$product->get_id()];
        unset(self::$pending_stock[$product->get_id()]);

        $new_value = self::normalize($product->get_stock_quantity('edit'));
        if ($old_value === $new_value) {
            return;
        }

        $parent_id = $product->is_type('variation') ? $product->get_parent_id() : 0;
        self::record($product->get_id(), $parent_id, 'stock_quantity', $old_value, $new_value);
    }

    /**
     * Remove log rows when a product or variation is permanently deleted.
     *
     * @param int          $post_id Post id.
     * @param WP_Post|null $post    Post.
     */
    public static function on_product_deleted($post_id, $post = null)
    {
        $post = $post ? $post : get_post($post_id);
        if ($post && in_array($post->post_type, array('product', 'product_variation'), true)) {
            PSWT_Repository::delete_for_product($post_id);
        }
    }

    /**
     * Write one change if that property is being tracked.
     *
     * @param int    $product_id Product id.
     * @param int    $parent_id  Parent id.
     * @param string $type       Change type.
     * @param string $old_value  Old value.
     * @param string $new_value  New value.
     */
    private static function record($product_id, $parent_id, $type, $old_value, $new_value)
    {
        if (!PSWT_Settings::is_tracked($type)) {
            return;
        }

        PSWT_Repository::insert($product_id, $parent_id, $type, $old_value, $new_value, get_current_user_id());

        /**
         * Fires after a product change has been logged.
         *
         * @param int    $product_id Product id.
         * @param string $type       Change type.
         * @param string $old_value  Old value.
         * @param string $new_value  New value.
         */
        do_action('pswt_change_logged', $product_id, $type, $old_value, $new_value);
    }

    /**
     * Normalise a property value to a comparable string.
     *
     * @param mixed $value Raw value.
     * @return string
     */
    private static function normalize($value)
    {
        if ($value === null || $value === false) {
            return '';
        }

        $value = trim((string) $value);

        if ($value !== '' && is_numeric($value)) {
            // Strip insignificant trailing zeros so "10" and "10.00" compare equal.
            $value = rtrim(rtrim(sprintf('%.6F', (float) $value), '0'), '.');
        }

        return $value;
    }
}
