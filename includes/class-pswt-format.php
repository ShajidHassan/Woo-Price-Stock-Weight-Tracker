<?php
/**
 * Presentation helpers: labels, values, badges, deltas, dates and users.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PSWT_Format
{
    /**
     * Human label for a change type.
     *
     * @param string $type Change type.
     * @return string
     */
    public static function type_label($type)
    {
        $labels = PSWT_Settings::trackable();
        $labels['price'] = __('Price', 'price-stock-weight-tracker-for-woocommerce');

        return isset($labels[$type]) ? $labels[$type] : ucfirst(str_replace('_', ' ', $type));
    }

    /**
     * Group a change type belongs to.
     *
     * @param string $type Change type.
     * @return string
     */
    public static function type_group($type)
    {
        foreach (PSWT_Repository::groups() as $group => $types) {
            if (in_array($type, $types, true)) {
                return $group;
            }
        }

        return 'other';
    }

    /**
     * Badge HTML for a change type.
     *
     * @param string $type Change type.
     * @return string
     */
    public static function type_badge($type)
    {
        return '<span class="pswt-badge pswt-badge--' . esc_attr(self::type_group($type)) . '">' . esc_html(self::type_label($type)) . '</span>';
    }

    /**
     * Whether a change type holds a number.
     *
     * @param string $type Change type.
     * @return bool
     */
    public static function is_numeric_type($type)
    {
        return in_array($type, array('regular_price', 'sale_price', 'price', 'stock_quantity', 'weight'), true);
    }

    /**
     * Formatted value for display (already escaped).
     *
     * @param string $type  Change type.
     * @param string $value Raw stored value.
     * @return string
     */
    public static function value($type, $value)
    {
        $value = (string) $value;

        if ($value === '') {
            return '<span class="pswt-muted">&mdash;</span>';
        }

        switch ($type) {
            case 'regular_price':
            case 'sale_price':
            case 'price':
                return wp_kses_post(wc_price((float) $value));

            case 'stock_status':
                return self::stock_badge($value);

            case 'stock_quantity':
                return esc_html(wc_stock_amount($value));

            case 'weight':
                return esc_html(wc_format_weight($value));

            default:
                return esc_html($value);
        }
    }

    /**
     * Coloured badge for a stock status.
     *
     * @param string $status Stock status slug.
     * @return string
     */
    public static function stock_badge($status)
    {
        $statuses = wc_get_product_stock_status_options();
        $label = isset($statuses[$status]) ? $statuses[$status] : $status;

        return '<span class="pswt-stock pswt-stock--' . esc_attr($status) . '">' . esc_html($label) . '</span>';
    }

    /**
     * "old → new" with a signed difference for numeric types (already escaped).
     *
     * @param object $row Log row.
     * @return string
     */
    public static function change($row)
    {
        $html = '<span class="pswt-change">' . self::value($row->change_type, $row->old_value)
            . '<span class="pswt-arrow" aria-hidden="true">&rarr;</span>'
            . self::value($row->change_type, $row->new_value) . '</span>';

        $delta = self::delta($row);
        if ($delta !== '') {
            $html .= ' ' . $delta;
        }

        return $html;
    }

    /**
     * Signed difference badge for numeric changes (already escaped).
     *
     * @param object $row Log row.
     * @return string
     */
    public static function delta($row)
    {
        if (!self::is_numeric_type($row->change_type) || $row->old_value === '' || $row->new_value === '') {
            return '';
        }

        $diff = (float) $row->new_value - (float) $row->old_value;
        if (abs($diff) < 0.000001) {
            return '';
        }

        $class = $diff > 0 ? 'up' : 'down';
        $sign = $diff > 0 ? '+' : '&minus;';

        switch ($row->change_type) {
            case 'stock_quantity':
                $formatted = esc_html(wc_stock_amount(abs($diff)));
                break;
            case 'weight':
                $formatted = esc_html(wc_format_weight(abs($diff)));
                break;
            default:
                $formatted = wp_kses_post(wc_price(abs($diff)));
        }

        return '<span class="pswt-delta pswt-delta--' . $class . '">' . $sign . $formatted . '</span>';
    }

    /**
     * Current live value of a property on a product (already escaped).
     *
     * @param WC_Product|false $product Product.
     * @param string           $type    Change type.
     * @return string
     */
    public static function current_value($product, $type)
    {
        if (!$product) {
            return '<span class="pswt-muted">&mdash;</span>';
        }

        switch ($type) {
            case 'regular_price':
                return self::value($type, $product->get_regular_price('edit'));
            case 'sale_price':
                return self::value($type, $product->get_sale_price('edit'));
            case 'price':
                return self::value($type, $product->get_price('edit'));
            case 'stock_status':
                return self::value($type, $product->get_stock_status('edit'));
            case 'stock_quantity':
                return self::value($type, (string) $product->get_stock_quantity('edit'));
            case 'weight':
                return self::value($type, $product->get_weight('edit'));
            case 'title':
                return self::value($type, $product->get_name('edit'));
        }

        return '';
    }

    /**
     * Display name for a user id, "System" for 0.
     *
     * @param int $user_id User id.
     * @return string
     */
    public static function user_name($user_id)
    {
        $user_id = (int) $user_id;
        if ($user_id === 0) {
            return __('System', 'price-stock-weight-tracker-for-woocommerce');
        }

        $user = get_user_by('id', $user_id);

        return $user ? $user->display_name : sprintf(
            /* translators: %d: user id */
            __('Deleted user #%d', 'price-stock-weight-tracker-for-woocommerce'),
            $user_id
        );
    }

    /**
     * Avatar and name for a user (already escaped).
     *
     * @param int $user_id User id.
     * @return string
     */
    public static function user_html($user_id)
    {
        $user_id = (int) $user_id;
        $avatar = $user_id ? get_avatar($user_id, 24, '', '', array('class' => 'pswt-avatar')) : '<span class="pswt-avatar pswt-avatar--system dashicons dashicons-admin-generic"></span>';

        return '<span class="pswt-user">' . $avatar . '<span>' . esc_html(self::user_name($user_id)) . '</span></span>';
    }

    /**
     * Site-local formatted date from a UTC MySQL datetime (already escaped).
     *
     * @param string $gmt_datetime UTC datetime.
     * @param bool   $with_time    Include the time.
     * @return string
     */
    public static function date($gmt_datetime, $with_time = true)
    {
        $timestamp = strtotime($gmt_datetime . ' UTC');
        if (!$timestamp) {
            return '';
        }

        $format = get_option('date_format');
        if ($with_time) {
            $format .= ' ' . get_option('time_format');
        }

        return '<time datetime="' . esc_attr(gmdate('c', $timestamp)) . '" title="' . esc_attr(human_time_diff($timestamp) . ' ' . __('ago', 'price-stock-weight-tracker-for-woocommerce')) . '">' . esc_html(wp_date($format, $timestamp)) . '</time>';
    }

    /**
     * Product cell with thumbnail, name, SKU and edit link (already escaped).
     *
     * @param int $product_id Product id.
     * @return string
     */
    public static function product_cell($product_id)
    {
        $product = wc_get_product($product_id);

        if (!$product) {
            return '<span class="pswt-product"><span class="pswt-thumb pswt-thumb--empty"></span><span class="pswt-product-text"><span class="pswt-muted">'
                . sprintf(
                    /* translators: %d: product id */
                    esc_html__('Deleted product #%d', 'price-stock-weight-tracker-for-woocommerce'),
                    (int) $product_id
                )
                . '</span></span></span>';
        }

        $edit_id = $product->is_type('variation') ? $product->get_parent_id() : $product->get_id();
        $edit_url = get_edit_post_link($edit_id, 'raw');
        $image_id = $product->get_image_id();
        if (!$image_id && $product->is_type('variation')) {
            $parent = wc_get_product($product->get_parent_id());
            $image_id = $parent ? $parent->get_image_id() : 0;
        }
        $thumb = $image_id ? wp_get_attachment_image($image_id, array(40, 40), false, array('class' => 'pswt-thumb')) : '<span class="pswt-thumb pswt-thumb--empty dashicons dashicons-format-image"></span>';

        $meta = array('#' . $product->get_id());
        if ($product->get_sku()) {
            $meta[] = $product->get_sku();
        }

        $name = $product->get_name();

        return '<span class="pswt-product">' . $thumb . '<span class="pswt-product-text">'
            . ($edit_url ? '<a class="pswt-product-name" href="' . esc_url($edit_url) . '">' . esc_html($name) . '</a>' : '<span class="pswt-product-name">' . esc_html($name) . '</span>')
            . '<span class="pswt-product-meta">' . esc_html(implode(' · ', $meta)) . '</span>'
            . '</span></span>';
    }
}
