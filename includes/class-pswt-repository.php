<?php
/**
 * All reads and writes against the change log table.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PSWT_Repository
{
    /**
     * Change types grouped for the tab navigation.
     *
     * @return array group => array of change types
     */
    public static function groups()
    {
        return array(
            'price'  => array('regular_price', 'sale_price', 'price'),
            'stock'  => array('stock_status', 'stock_quantity'),
            'title'  => array('title'),
            'weight' => array('weight'),
        );
    }

    /**
     * Full table name.
     *
     * @return string
     */
    public static function table()
    {
        global $wpdb;

        return $wpdb->prefix . 'pswt_change_log';
    }

    /**
     * Insert one change.
     *
     * @param int    $product_id Product or variation id.
     * @param int    $parent_id  Parent id for variations, 0 otherwise.
     * @param string $type       Change type.
     * @param string $old        Old value.
     * @param string $new        New value.
     * @param int    $user_id    User who made the change, 0 for system.
     * @return int|false Row id or false.
     */
    public static function insert($product_id, $parent_id, $type, $old, $new, $user_id)
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- plugin-owned table.
        $result = $wpdb->insert(
            self::table(),
            array(
                'product_id'  => absint($product_id),
                'parent_id'   => absint($parent_id),
                'change_type' => sanitize_key($type),
                'old_value'   => (string) $old,
                'new_value'   => (string) $new,
                'changed_by'  => absint($user_id),
                'change_date' => current_time('mysql', true),
            ),
            array('%d', '%d', '%s', '%s', '%s', '%d', '%s')
        );

        if ($result) {
            self::bump_cache();
            return (int) $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Run a prepared SELECT and return the rows.
     *
     * This is the only place dynamic SQL reaches $wpdb. Every caller assembles $sql from literal fragments and
     * %s / %d / %i placeholders and passes all values through $params, so nothing user-supplied is ever
     * concatenated into the query text. The static analyser cannot follow that assembly, hence the exemption.
     *
     * @param string $sql    Query with placeholders.
     * @param array  $params Placeholder values.
     * @return object[]
     */
    public static function get_results($sql, array $params)
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- see docblock; every caller caches the result in the 'pswt' group.
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params));

        return is_array($rows) ? $rows : array();
    }

    /**
     * Run a prepared SELECT and return a single value. Same contract as get_results().
     *
     * @param string $sql    Query with placeholders.
     * @param array  $params Placeholder values.
     * @return string|null
     */
    public static function get_var($sql, array $params)
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- see get_results(); every caller caches the result in the 'pswt' group.
        return $wpdb->get_var($wpdb->prepare($sql, $params));
    }

    /**
     * SQL fragment restricting change_type to one tab group. Literal strings only, kept in step with groups().
     *
     * @param string $group Group key.
     * @return string
     */
    private static function group_sql($group)
    {
        switch ($group) {
            case 'price':
                return " AND log.change_type IN ('regular_price', 'sale_price', 'price')";
            case 'stock':
                return " AND log.change_type IN ('stock_status', 'stock_quantity')";
            case 'title':
                return " AND log.change_type = 'title'";
            case 'weight':
                return " AND log.change_type = 'weight'";
        }

        return '';
    }

    /**
     * Query the log with filters and pagination.
     *
     * @param array $args {
     *     @type string   $group       price|stock|title|weight or '' for all.
     *     @type string   $type        Exact change type.
     *     @type int|null $user        User id (0 = system), '' or null for any.
     *     @type int      $product_id  Restrict to one product (and its variations).
     *     @type string   $search      Product title, SKU or id.
     *     @type string   $date_from   Y-m-d in site timezone.
     *     @type string   $date_to     Y-m-d in site timezone.
     *     @type string   $orderby     change_date|change_type|product_id|changed_by.
     *     @type string   $order       ASC|DESC.
     *     @type int      $per_page    Rows per page, -1 for all.
     *     @type int      $paged       Page number.
     * }
     * @return array {items: object[], total: int}
     */
    public static function query($args)
    {
        global $wpdb;

        $args = wp_parse_args($args, array(
            'group'      => '',
            'type'       => '',
            'user'       => '',
            'product_id' => 0,
            'search'     => '',
            'date_from'  => '',
            'date_to'    => '',
            'orderby'    => 'change_date',
            'order'      => 'DESC',
            'per_page'   => 25,
            'paged'      => 1,
        ));

        $cache_key = 'query_' . md5(wp_json_encode($args)) . '_' . self::cache_version();
        $cached = wp_cache_get($cache_key, 'pswt');
        if (is_array($cached)) {
            return $cached;
        }

        // The query is assembled from literal fragments; every value goes through a placeholder.
        $params = array(self::table());
        $sql = ' FROM %i log INNER JOIN ' . $wpdb->posts . " p ON p.ID = log.product_id AND p.post_status <> 'trash' WHERE 1=1";

        if ($args['type'] !== '') {
            $sql .= ' AND log.change_type = %s';
            $params[] = $args['type'];
        } elseif ($args['group'] !== '') {
            $sql .= self::group_sql($args['group']);
        }

        if ($args['user'] !== '' && $args['user'] !== null) {
            $sql .= ' AND log.changed_by = %d';
            $params[] = (int) $args['user'];
        }

        if ($args['product_id']) {
            $sql .= ' AND (log.product_id = %d OR log.parent_id = %d)';
            $params[] = (int) $args['product_id'];
            $params[] = (int) $args['product_id'];
        }

        if ($args['search'] !== '') {
            $like = '%' . $wpdb->esc_like($args['search']) . '%';
            $sql .= ' AND log.product_id IN (SELECT ps.ID FROM ' . $wpdb->posts . ' ps LEFT JOIN ' . $wpdb->wc_product_meta_lookup . " ls ON ls.product_id = ps.ID WHERE ps.post_type IN ('product', 'product_variation') AND (ps.post_title LIKE %s OR ls.sku LIKE %s OR ps.ID = %d))";
            $params[] = $like;
            $params[] = $like;
            $params[] = ctype_digit((string) $args['search']) ? (int) $args['search'] : 0;
        }

        if ($args['date_from'] !== '') {
            $sql .= ' AND log.change_date >= %s';
            $params[] = get_gmt_from_date($args['date_from'] . ' 00:00:00');
        }
        if ($args['date_to'] !== '') {
            $sql .= ' AND log.change_date <= %s';
            $params[] = get_gmt_from_date($args['date_to'] . ' 23:59:59');
        }

        $total = (int) self::get_var('SELECT COUNT(*)' . $sql, $params);

        switch ($args['orderby']) {
            case 'change_type':
                $sql .= ' ORDER BY log.change_type';
                break;
            case 'product_id':
                $sql .= ' ORDER BY log.product_id';
                break;
            case 'changed_by':
                $sql .= ' ORDER BY log.changed_by';
                break;
            default:
                $sql .= ' ORDER BY log.change_date';
        }
        $sql .= strtoupper($args['order']) === 'ASC' ? ' ASC, log.id ASC' : ' DESC, log.id DESC';

        if ((int) $args['per_page'] > 0) {
            $sql .= ' LIMIT %d OFFSET %d';
            $params[] = (int) $args['per_page'];
            $params[] = max(0, ((int) $args['paged'] - 1) * (int) $args['per_page']);
        }

        $items = self::get_results('SELECT log.*' . $sql, $params);

        $result = array('items' => $items, 'total' => $total);
        wp_cache_set($cache_key, $result, 'pswt', 5 * MINUTE_IN_SECONDS);

        return $result;
    }

    /**
     * Latest changes for one product, including its variations.
     *
     * @param int $product_id Product id.
     * @param int $limit      Max rows.
     * @return object[]
     */
    public static function get_for_product($product_id, $limit = 10)
    {
        $result = self::query(array('product_id' => $product_id, 'per_page' => $limit, 'paged' => 1));

        return $result['items'];
    }

    /**
     * Users who appear in the log, for the filter dropdown.
     *
     * @return array user_id => display name
     */
    public static function get_users()
    {
        global $wpdb;

        $cache_key = 'users_' . self::cache_version();
        $cached = wp_cache_get($cache_key, 'pswt');
        if (is_array($cached)) {
            return $cached;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- plugin-owned table, result cached.
        $ids = $wpdb->get_col($wpdb->prepare('SELECT DISTINCT changed_by FROM %i ORDER BY changed_by ASC', self::table()));

        $users = array();
        foreach ((array) $ids as $id) {
            $id = (int) $id;
            $users[$id] = PSWT_Format::user_name($id);
        }

        wp_cache_set($cache_key, $users, 'pswt', 5 * MINUTE_IN_SECONDS);

        return $users;
    }

    /**
     * Number of changes per group inside a UTC date range.
     *
     * @param string $from_gmt MySQL datetime, '' for no lower bound.
     * @param string $to_gmt   MySQL datetime, '' for no upper bound.
     * @return array group => count, plus 'all'.
     */
    public static function counts_by_group($from_gmt = '', $to_gmt = '')
    {
        global $wpdb;

        $cache_key = 'counts_' . md5($from_gmt . '|' . $to_gmt) . '_' . self::cache_version();
        $cached = wp_cache_get($cache_key, 'pswt');
        if (is_array($cached)) {
            return $cached;
        }

        $params = array(self::table());
        $sql = 'SELECT log.change_type, COUNT(*) AS total FROM %i log INNER JOIN ' . $wpdb->posts . " p ON p.ID = log.product_id AND p.post_status <> 'trash' WHERE 1=1";

        if ($from_gmt !== '') {
            $sql .= ' AND log.change_date >= %s';
            $params[] = $from_gmt;
        }
        if ($to_gmt !== '') {
            $sql .= ' AND log.change_date <= %s';
            $params[] = $to_gmt;
        }
        $sql .= ' GROUP BY log.change_type';

        $rows = self::get_results($sql, $params);

        $counts = array_fill_keys(array_keys(self::groups()), 0);
        $counts['all'] = 0;
        foreach ($rows as $row) {
            foreach (self::groups() as $group => $types) {
                if (in_array($row->change_type, $types, true)) {
                    $counts[$group] += (int) $row->total;
                }
            }
            $counts['all'] += (int) $row->total;
        }

        wp_cache_set($cache_key, $counts, 'pswt', 5 * MINUTE_IN_SECONDS);

        return $counts;
    }

    /**
     * Delete all log rows for a product (called when the product is permanently deleted).
     *
     * @param int $product_id Product id.
     */
    public static function delete_for_product($product_id)
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table.
        $wpdb->delete(self::table(), array('product_id' => absint($product_id)), array('%d'));
        self::bump_cache();
    }

    /**
     * Delete rows older than the retention setting.
     */
    public static function prune_by_settings()
    {
        global $wpdb;

        $settings = PSWT_Settings::get();
        $days = (int) $settings['retention_days'];
        if ($days <= 0) {
            return;
        }

        $cutoff = gmdate('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- scheduled clean-up of plugin-owned table.
        $wpdb->query($wpdb->prepare('DELETE FROM %i WHERE change_date < %s', self::table(), $cutoff));
        self::bump_cache();
    }

    /**
     * Cache namespace version, bumped on every write so cached reads become stale.
     *
     * @return int
     */
    private static function cache_version()
    {
        $version = wp_cache_get('cache_version', 'pswt');
        if (!$version) {
            $version = 1;
            wp_cache_set('cache_version', $version, 'pswt');
        }

        return (int) $version;
    }

    /**
     * Invalidate cached reads.
     */
    private static function bump_cache()
    {
        wp_cache_set('cache_version', self::cache_version() + 1, 'pswt');
    }
}
