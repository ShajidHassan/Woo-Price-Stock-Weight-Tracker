<?php
/**
 * Installation, upgrades and migration of data from the pre-3.0 tables.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PSWT_Install
{
    const DB_VERSION_OPTION = 'pswt_db_version';
    const LEGACY_MIGRATED_OPTION = 'pswt_legacy_migrated';

    /**
     * Legacy table suffixes created by versions 1.x and 2.x.
     *
     * @return array
     */
    public static function legacy_tables()
    {
        return array('price_change_history', 'stock_change_history', 'title_change_history', 'weight_change_history');
    }

    /**
     * Plugin activation.
     */
    public static function activate()
    {
        self::create_tables();
        self::migrate_legacy();
        self::schedule_events();
        update_option(self::DB_VERSION_OPTION, PSWT_DB_VERSION);
    }

    /**
     * Plugin deactivation.
     */
    public static function deactivate()
    {
        wp_clear_scheduled_hook('pswt_daily_prune');
    }

    /**
     * Create or upgrade the schema when the stored version differs from the code version.
     * Also runs when the plugin was copied in without activation.
     */
    public static function maybe_upgrade()
    {
        if (get_option(self::DB_VERSION_OPTION) !== PSWT_DB_VERSION) {
            self::create_tables();
            self::migrate_legacy();
            update_option(self::DB_VERSION_OPTION, PSWT_DB_VERSION);
        }

        if (is_admin() && !wp_next_scheduled('pswt_daily_prune')) {
            self::schedule_events();
        }
    }

    /**
     * dbDelta is idempotent: safe to run on every activation and upgrade.
     */
    public static function create_tables()
    {
        global $wpdb;

        $table = PSWT_Repository::table();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_id bigint(20) unsigned NOT NULL,
            parent_id bigint(20) unsigned NOT NULL DEFAULT 0,
            change_type varchar(32) NOT NULL,
            old_value text NOT NULL,
            new_value text NOT NULL,
            changed_by bigint(20) unsigned NOT NULL DEFAULT 0,
            change_date datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY product_id (product_id),
            KEY change_type (change_type),
            KEY change_date (change_date),
            KEY changed_by (changed_by)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Whether a table exists.
     *
     * @param string $table Full table name.
     * @return bool
     */
    public static function table_exists($table)
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema check during install.
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table;
    }

    /**
     * Copy rows from the four pre-3.0 tables into the unified log. Runs once; the old tables are left in place.
     */
    public static function migrate_legacy()
    {
        global $wpdb;

        if (get_option(self::LEGACY_MIGRATED_OPTION)) {
            return;
        }

        $log = PSWT_Repository::table();
        if (!self::table_exists($log)) {
            return;
        }

        // Legacy rows were stored in site-local time; the new table stores UTC.
        $offset = (int) wp_timezone()->getOffset(new DateTime('now', wp_timezone()));

        $price = $wpdb->prefix . 'price_change_history';
        if (self::table_exists($price)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off migration.
            $wpdb->query($wpdb->prepare(
                "INSERT INTO %i (product_id, parent_id, change_type, old_value, new_value, changed_by, change_date)
                 SELECT product_id, 0, 'price', price, new_price, changed_by, DATE_SUB(change_date, INTERVAL %d SECOND) FROM %i",
                $log,
                $offset,
                $price
            ));
        }

        $stock = $wpdb->prefix . 'stock_change_history';
        if (self::table_exists($stock)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off migration.
            $wpdb->query($wpdb->prepare(
                "INSERT INTO %i (product_id, parent_id, change_type, old_value, new_value, changed_by, change_date)
                 SELECT product_id, 0, 'stock_status', old_stock_status, new_stock_status, changed_by, DATE_SUB(change_date, INTERVAL %d SECOND) FROM %i",
                $log,
                $offset,
                $stock
            ));
        }

        // Title and weight tables only stored the old value. The new value is the next row's old value,
        // or the product's current value for the most recent row.
        $title = $wpdb->prefix . 'title_change_history';
        if (self::table_exists($title)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off migration.
            $wpdb->query($wpdb->prepare(
                "INSERT INTO %i (product_id, parent_id, change_type, old_value, new_value, changed_by, change_date)
                 SELECT t1.product_id, 0, 'title', t1.current_product_title,
                    COALESCE(
                        (SELECT t2.current_product_title FROM %i t2
                         WHERE t2.product_id = t1.product_id
                           AND (t2.change_date > t1.change_date OR (t2.change_date = t1.change_date AND t2.id > t1.id))
                         ORDER BY t2.change_date ASC, t2.id ASC LIMIT 1),
                        (SELECT p.post_title FROM {$wpdb->posts} p WHERE p.ID = t1.product_id),
                        ''
                    ),
                    t1.changed_by, DATE_SUB(t1.change_date, INTERVAL %d SECOND)
                 FROM %i t1",
                $log,
                $title,
                $offset,
                $title
            ));
        }

        $weight = $wpdb->prefix . 'weight_change_history';
        if (self::table_exists($weight)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off migration.
            $wpdb->query($wpdb->prepare(
                "INSERT INTO %i (product_id, parent_id, change_type, old_value, new_value, changed_by, change_date)
                 SELECT t1.product_id, 0, 'weight', t1.current_weight,
                    COALESCE(
                        (SELECT t2.current_weight FROM %i t2
                         WHERE t2.product_id = t1.product_id
                           AND (t2.change_date > t1.change_date OR (t2.change_date = t1.change_date AND t2.id > t1.id))
                         ORDER BY t2.change_date ASC, t2.id ASC LIMIT 1),
                        (SELECT pm.meta_value FROM {$wpdb->postmeta} pm WHERE pm.post_id = t1.product_id AND pm.meta_key = '_weight' LIMIT 1),
                        ''
                    ),
                    t1.changed_by, DATE_SUB(t1.change_date, INTERVAL %d SECOND)
                 FROM %i t1",
                $log,
                $weight,
                $offset,
                $weight
            ));
        }

        update_option(self::LEGACY_MIGRATED_OPTION, 1);
    }

    /**
     * Schedule the daily retention job.
     */
    public static function schedule_events()
    {
        if (!wp_next_scheduled('pswt_daily_prune')) {
            wp_schedule_event(time() + DAY_IN_SECONDS, 'daily', 'pswt_daily_prune');
        }
    }
}
