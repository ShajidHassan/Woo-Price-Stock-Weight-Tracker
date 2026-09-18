<?php
/**
 * Admin menus, assets, settings page and the history / out-of-stock pages.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PSWT_Admin
{
    const CAPABILITY = 'manage_woocommerce';

    /**
     * Hook suffixes of the plugin's own screens.
     *
     * @var array
     */
    private static $hooks = array();

    /**
     * Hook everything up.
     */
    public static function init()
    {
        require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
        require_once PSWT_PATH . 'includes/class-pswt-history-table.php';
        require_once PSWT_PATH . 'includes/class-pswt-stock-out-table.php';

        add_action('admin_menu', array(__CLASS__, 'register_menus'));
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
        add_filter('set_screen_option_pswt_per_page', array(__CLASS__, 'save_per_page'), 10, 3);
        add_filter('set_screen_option_pswt_stock_out_per_page', array(__CLASS__, 'save_per_page'), 10, 3);
    }

    /**
     * Register the top-level menu and its pages.
     */
    public static function register_menus()
    {
        $title = __('Product Tracker', 'price-stock-weight-tracker-for-woocommerce');

        $parent = add_menu_page($title, $title, self::CAPABILITY, 'pswt-history', array(__CLASS__, 'render_history'), 'dashicons-backup', 58);

        $history = add_submenu_page('pswt-history', __('Change History', 'price-stock-weight-tracker-for-woocommerce'), __('Change History', 'price-stock-weight-tracker-for-woocommerce'), self::CAPABILITY, 'pswt-history', array(__CLASS__, 'render_history'));
        $stock = add_submenu_page('pswt-history', __('Out of Stock', 'price-stock-weight-tracker-for-woocommerce'), __('Out of Stock', 'price-stock-weight-tracker-for-woocommerce'), self::CAPABILITY, 'pswt-stock-out', array(__CLASS__, 'render_stock_out'));
        $report = add_submenu_page('pswt-history', __('Daily Report', 'price-stock-weight-tracker-for-woocommerce'), __('Daily Report', 'price-stock-weight-tracker-for-woocommerce'), self::CAPABILITY, 'pswt-report', array('PSWT_Report', 'render'));
        $settings = add_submenu_page('pswt-history', __('Tracker Settings', 'price-stock-weight-tracker-for-woocommerce'), __('Settings', 'price-stock-weight-tracker-for-woocommerce'), 'manage_options', 'pswt-settings', array(__CLASS__, 'render_settings'));

        self::$hooks = array_filter(array($parent, $history, $stock, $report, $settings));

        add_action('load-' . $history, array(__CLASS__, 'history_screen_options'));
        add_action('load-' . $stock, array(__CLASS__, 'stock_out_screen_options'));
    }

    /**
     * "Number of items per page" screen option for the history page.
     */
    public static function history_screen_options()
    {
        add_screen_option('per_page', array(
            'label'   => __('Changes per page', 'price-stock-weight-tracker-for-woocommerce'),
            'default' => 25,
            'option'  => 'pswt_per_page',
        ));
    }

    /**
     * Screen option for the out-of-stock page.
     */
    public static function stock_out_screen_options()
    {
        add_screen_option('per_page', array(
            'label'   => __('Products per page', 'price-stock-weight-tracker-for-woocommerce'),
            'default' => 25,
            'option'  => 'pswt_stock_out_per_page',
        ));
    }

    /**
     * Persist the per-page screen option.
     *
     * @param mixed  $screen_option Existing value.
     * @param string $option        Option name.
     * @param int    $value         Submitted value.
     * @return int
     */
    public static function save_per_page($screen_option, $option, $value)
    {
        return max(1, min(500, (int) $value));
    }

    /**
     * Register the settings option.
     */
    public static function register_settings()
    {
        register_setting('pswt', PSWT_Settings::OPTION, array(
            'type'              => 'array',
            'sanitize_callback' => array('PSWT_Settings', 'sanitize'),
            'default'           => PSWT_Settings::defaults(),
        ));
    }

    /**
     * Load the stylesheet and script only on the plugin's screens and the product editor.
     *
     * @param string $hook Current admin page hook.
     */
    public static function enqueue_assets($hook)
    {
        $is_plugin_screen = in_array($hook, self::$hooks, true);

        $screen = get_current_screen();
        $is_product_editor = $screen && $screen->base === 'post' && $screen->post_type === 'product';

        if (!$is_plugin_screen && !$is_product_editor) {
            return;
        }

        wp_enqueue_style('pswt-admin', PSWT_URL . 'assets/css/admin.css', array(), PSWT_VERSION);

        if ($is_plugin_screen) {
            wp_enqueue_script('pswt-admin', PSWT_URL . 'assets/js/admin.js', array(), PSWT_VERSION, true);
        }
    }

    /**
     * Base URL of an admin page of this plugin.
     *
     * @param string $page Page slug.
     * @param array  $args Extra query args.
     * @return string
     */
    public static function page_url($page, $args = array())
    {
        return add_query_arg(array_merge(array('page' => $page), $args), admin_url('admin.php'));
    }

    /**
     * Change History page.
     */
    public static function render_history()
    {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to view this page.', 'price-stock-weight-tracker-for-woocommerce'));
        }

        $table = new PSWT_History_Table();
        $table->prepare_items();
        $filters = $table->filters;

        $counts = PSWT_Repository::counts_by_group();
        $today_gmt = get_gmt_from_date(wp_date('Y-m-d') . ' 00:00:00');
        $stats = array(
            array('label' => __('Today', 'price-stock-weight-tracker-for-woocommerce'), 'value' => PSWT_Repository::counts_by_group($today_gmt)['all']),
            array('label' => __('Last 7 days', 'price-stock-weight-tracker-for-woocommerce'), 'value' => PSWT_Repository::counts_by_group(gmdate('Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS))['all']),
            array('label' => __('Last 30 days', 'price-stock-weight-tracker-for-woocommerce'), 'value' => PSWT_Repository::counts_by_group(gmdate('Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS))['all']),
            array('label' => __('All time', 'price-stock-weight-tracker-for-woocommerce'), 'value' => $counts['all']),
        );

        $tabs = array(
            ''       => __('All', 'price-stock-weight-tracker-for-woocommerce'),
            'price'  => __('Price', 'price-stock-weight-tracker-for-woocommerce'),
            'stock'  => __('Stock', 'price-stock-weight-tracker-for-woocommerce'),
            'title'  => __('Title', 'price-stock-weight-tracker-for-woocommerce'),
            'weight' => __('Weight', 'price-stock-weight-tracker-for-woocommerce'),
        );

        // Query args that survive a tab switch.
        $sticky = array_filter(array(
            'user'       => $filters['user'],
            'product_id' => $filters['product_id'],
            's'          => $filters['search'],
            'date_from'  => $filters['date_from'],
            'date_to'    => $filters['date_to'],
        ), 'strlen');

        $export_url = wp_nonce_url(
            add_query_arg(array_merge(array('action' => 'pswt_export', 'group' => $filters['group']), $sticky), admin_url('admin-post.php')),
            'pswt_export'
        );

        $product_filter = $filters['product_id'] ? wc_get_product($filters['product_id']) : false;
        ?>
        <div class="wrap pswt-wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Change History', 'price-stock-weight-tracker-for-woocommerce'); ?></h1>
            <a href="<?php echo esc_url($export_url); ?>" class="page-title-action"><?php esc_html_e('Export CSV', 'price-stock-weight-tracker-for-woocommerce'); ?></a>
            <hr class="wp-header-end">

            <div class="pswt-stats">
                <?php foreach ($stats as $stat) : ?>
                    <div class="pswt-stat">
                        <span class="pswt-stat-value"><?php echo esc_html(number_format_i18n($stat['value'])); ?></span>
                        <span class="pswt-stat-label"><?php echo esc_html($stat['label']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <nav class="pswt-tabs" aria-label="<?php esc_attr_e('Change types', 'price-stock-weight-tracker-for-woocommerce'); ?>">
                <?php foreach ($tabs as $group => $label) : ?>
                    <?php
                    $url = self::page_url('pswt-history', array_merge($sticky, $group ? array('group' => $group) : array()));
                    $count = $group ? $counts[$group] : $counts['all'];
                    ?>
                    <a href="<?php echo esc_url($url); ?>" class="pswt-tab<?php echo $filters['group'] === $group ? ' is-active' : ''; ?>">
                        <?php echo esc_html($label); ?> <span class="pswt-count"><?php echo esc_html(number_format_i18n($count)); ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>

            <?php if ($product_filter) : ?>
                <div class="pswt-notice">
                    <?php
                    printf(
                        /* translators: %s: product name */
                        esc_html__('Showing changes for %s only.', 'price-stock-weight-tracker-for-woocommerce'),
                        '<strong>' . esc_html($product_filter->get_name()) . '</strong>'
                    );
                    ?>
                    <a href="<?php echo esc_url(self::page_url('pswt-history')); ?>"><?php esc_html_e('Show all products', 'price-stock-weight-tracker-for-woocommerce'); ?></a>
                </div>
            <?php endif; ?>

            <form method="get" class="pswt-filters">
                <input type="hidden" name="page" value="pswt-history">
                <?php if ($filters['group']) : ?>
                    <input type="hidden" name="group" value="<?php echo esc_attr($filters['group']); ?>">
                <?php endif; ?>
                <?php if ($filters['product_id']) : ?>
                    <input type="hidden" name="product_id" value="<?php echo esc_attr($filters['product_id']); ?>">
                <?php endif; ?>

                <label class="screen-reader-text" for="pswt-search"><?php esc_html_e('Search products', 'price-stock-weight-tracker-for-woocommerce'); ?></label>
                <input type="search" id="pswt-search" name="s" value="<?php echo esc_attr($filters['search']); ?>" placeholder="<?php esc_attr_e('Product name, SKU or ID', 'price-stock-weight-tracker-for-woocommerce'); ?>">

                <label class="screen-reader-text" for="pswt-user"><?php esc_html_e('Changed by', 'price-stock-weight-tracker-for-woocommerce'); ?></label>
                <select name="user" id="pswt-user">
                    <option value=""><?php esc_html_e('Anyone', 'price-stock-weight-tracker-for-woocommerce'); ?></option>
                    <?php foreach (PSWT_Repository::get_users() as $user_id => $name) : ?>
                        <option value="<?php echo esc_attr($user_id); ?>" <?php selected((string) $user_id, (string) $filters['user']); ?>><?php echo esc_html($name); ?></option>
                    <?php endforeach; ?>
                </select>

                <label class="screen-reader-text" for="pswt-from"><?php esc_html_e('From date', 'price-stock-weight-tracker-for-woocommerce'); ?></label>
                <input type="date" id="pswt-from" name="date_from" value="<?php echo esc_attr($filters['date_from']); ?>">
                <span class="pswt-filters-sep" aria-hidden="true">&ndash;</span>
                <label class="screen-reader-text" for="pswt-to"><?php esc_html_e('To date', 'price-stock-weight-tracker-for-woocommerce'); ?></label>
                <input type="date" id="pswt-to" name="date_to" value="<?php echo esc_attr($filters['date_to']); ?>">

                <button type="submit" class="button button-primary"><?php esc_html_e('Filter', 'price-stock-weight-tracker-for-woocommerce'); ?></button>
                <?php if ($sticky || $filters['group']) : ?>
                    <a href="<?php echo esc_url(self::page_url('pswt-history')); ?>" class="button-link pswt-reset"><?php esc_html_e('Reset', 'price-stock-weight-tracker-for-woocommerce'); ?></a>
                <?php endif; ?>
            </form>

            <div class="pswt-card">
                <?php $table->display(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Out of Stock page.
     */
    public static function render_stock_out()
    {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to view this page.', 'price-stock-weight-tracker-for-woocommerce'));
        }

        $table = new PSWT_Stock_Out_Table();
        $table->prepare_items();
        $filters = $table->filters;
        ?>
        <div class="wrap pswt-wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Out of Stock', 'price-stock-weight-tracker-for-woocommerce'); ?></h1>
            <hr class="wp-header-end">

            <div class="pswt-stats">
                <div class="pswt-stat">
                    <span class="pswt-stat-value"><?php echo esc_html(number_format_i18n($table->total)); ?></span>
                    <span class="pswt-stat-label"><?php esc_html_e('Products out of stock', 'price-stock-weight-tracker-for-woocommerce'); ?></span>
                </div>
            </div>

            <form method="get" class="pswt-filters">
                <input type="hidden" name="page" value="pswt-stock-out">

                <label class="screen-reader-text" for="pswt-search"><?php esc_html_e('Search products', 'price-stock-weight-tracker-for-woocommerce'); ?></label>
                <input type="search" id="pswt-search" name="s" value="<?php echo esc_attr($filters['search']); ?>" placeholder="<?php esc_attr_e('Product name or SKU', 'price-stock-weight-tracker-for-woocommerce'); ?>">

                <label class="screen-reader-text" for="pswt-category"><?php esc_html_e('Category', 'price-stock-weight-tracker-for-woocommerce'); ?></label>
                <?php
                wp_dropdown_categories(array(
                    'taxonomy'         => 'product_cat',
                    'name'             => 'category',
                    'id'               => 'pswt-category',
                    'selected'         => $filters['category'],
                    'show_option_all'  => __('All categories', 'price-stock-weight-tracker-for-woocommerce'),
                    'hierarchical'     => true,
                    'show_count'       => true,
                    'hide_empty'       => false,
                    'value_field'      => 'term_id',
                ));
                ?>

                <button type="submit" class="button button-primary"><?php esc_html_e('Filter', 'price-stock-weight-tracker-for-woocommerce'); ?></button>
                <?php if ($filters['search'] !== '' || $filters['category']) : ?>
                    <a href="<?php echo esc_url(self::page_url('pswt-stock-out')); ?>" class="button-link pswt-reset"><?php esc_html_e('Reset', 'price-stock-weight-tracker-for-woocommerce'); ?></a>
                <?php endif; ?>
            </form>

            <div class="pswt-card">
                <?php $table->display(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Settings page.
     */
    public static function render_settings()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to view this page.', 'price-stock-weight-tracker-for-woocommerce'));
        }

        $settings = PSWT_Settings::get();

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- flag set by options.php after a save, nothing is acted on.
        if (isset($_GET['settings-updated'])) {
            add_settings_error('pswt_messages', 'pswt_saved', __('Settings saved.', 'price-stock-weight-tracker-for-woocommerce'), 'updated');
        }
        settings_errors('pswt_messages');
        ?>
        <div class="wrap pswt-wrap">
            <h1><?php esc_html_e('Tracker Settings', 'price-stock-weight-tracker-for-woocommerce'); ?></h1>

            <form method="post" action="options.php" class="pswt-settings">
                <?php settings_fields('pswt'); ?>

                <div class="pswt-card pswt-card--padded">
                    <h2><?php esc_html_e('What to track', 'price-stock-weight-tracker-for-woocommerce'); ?></h2>
                    <p class="description"><?php esc_html_e('Untracked properties are ignored from now on; existing history is kept.', 'price-stock-weight-tracker-for-woocommerce'); ?></p>
                    <div class="pswt-checks">
                        <?php foreach (PSWT_Settings::trackable() as $type => $label) : ?>
                            <label class="pswt-check">
                                <input type="checkbox" name="<?php echo esc_attr(PSWT_Settings::OPTION); ?>[track][<?php echo esc_attr($type); ?>]" value="1" <?php checked(!empty($settings['track'][$type])); ?>>
                                <?php echo wp_kses_post(PSWT_Format::type_badge($type)); ?>
                                <span><?php echo esc_html($label); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="pswt-card pswt-card--padded">
                    <h2><?php esc_html_e('Data retention', 'price-stock-weight-tracker-for-woocommerce'); ?></h2>
                    <label for="pswt-retention"><?php esc_html_e('Delete history older than', 'price-stock-weight-tracker-for-woocommerce'); ?></label>
                    <input type="number" id="pswt-retention" name="<?php echo esc_attr(PSWT_Settings::OPTION); ?>[retention_days]" value="<?php echo esc_attr($settings['retention_days']); ?>" min="0" max="3650" step="1" class="small-text">
                    <span><?php esc_html_e('days', 'price-stock-weight-tracker-for-woocommerce'); ?></span>
                    <p class="description"><?php esc_html_e('0 keeps history forever. Clean-up runs once a day.', 'price-stock-weight-tracker-for-woocommerce'); ?></p>
                </div>

                <div class="pswt-card pswt-card--padded">
                    <h2><?php esc_html_e('Uninstall', 'price-stock-weight-tracker-for-woocommerce'); ?></h2>
                    <label class="pswt-check">
                        <input type="checkbox" name="<?php echo esc_attr(PSWT_Settings::OPTION); ?>[delete_on_uninstall]" value="1" <?php checked(!empty($settings['delete_on_uninstall'])); ?>>
                        <span><?php esc_html_e('Delete all tracked history and settings when the plugin is deleted', 'price-stock-weight-tracker-for-woocommerce'); ?></span>
                    </label>
                </div>

                <?php submit_button(__('Save Settings', 'price-stock-weight-tracker-for-woocommerce')); ?>
            </form>
        </div>
        <?php
    }
}
