<?php
/**
 * Plugin settings: what to track, how long to keep it, and uninstall behaviour.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PSWT_Settings
{
    const OPTION = 'pswt_settings';

    /**
     * Trackable properties and their labels.
     *
     * @return array
     */
    public static function trackable()
    {
        return array(
            'regular_price'  => __('Regular price', 'price-stock-weight-tracker-for-woocommerce'),
            'sale_price'     => __('Sale price', 'price-stock-weight-tracker-for-woocommerce'),
            'stock_status'   => __('Stock status', 'price-stock-weight-tracker-for-woocommerce'),
            'stock_quantity' => __('Stock quantity', 'price-stock-weight-tracker-for-woocommerce'),
            'title'          => __('Product title', 'price-stock-weight-tracker-for-woocommerce'),
            'weight'         => __('Weight', 'price-stock-weight-tracker-for-woocommerce'),
        );
    }

    /**
     * Default settings.
     *
     * @return array
     */
    public static function defaults()
    {
        return array(
            'track'               => array_fill_keys(array_keys(self::trackable()), 1),
            'retention_days'      => 0,
            'delete_on_uninstall' => 0,
        );
    }

    /**
     * Current settings merged over defaults.
     *
     * @return array
     */
    public static function get()
    {
        $saved = get_option(self::OPTION, array());
        $saved = is_array($saved) ? $saved : array();
        $defaults = self::defaults();

        $settings = array_merge($defaults, $saved);
        $settings['track'] = array_merge($defaults['track'], isset($saved['track']) && is_array($saved['track']) ? $saved['track'] : array());

        return $settings;
    }

    /**
     * Whether a property is being tracked.
     *
     * @param string $type Change type.
     * @return bool
     */
    public static function is_tracked($type)
    {
        $settings = self::get();

        return !empty($settings['track'][$type]);
    }

    /**
     * Sanitize submitted settings.
     *
     * @param mixed $input Raw input.
     * @return array
     */
    public static function sanitize($input)
    {
        $input = is_array($input) ? $input : array();
        $track = array();

        foreach (array_keys(self::trackable()) as $type) {
            $track[$type] = !empty($input['track'][$type]) ? 1 : 0;
        }

        return array(
            'track'               => $track,
            'retention_days'      => isset($input['retention_days']) ? min(3650, absint($input['retention_days'])) : 0,
            'delete_on_uninstall' => !empty($input['delete_on_uninstall']) ? 1 : 0,
        );
    }
}
