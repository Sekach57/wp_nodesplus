<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Nodes snapshot storage (custom table)
class INCR_Nodes_Store {
    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'incr_nodes';
    }

    public static function create_table() {
        global $wpdb;
        $table = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            node_id VARCHAR(64) NOT NULL,
            node_type VARCHAR(190) NOT NULL,
            product_id BIGINT UNSIGNED NULL,
            status VARCHAR(32) NULL,
            created_at DATETIME NULL,
            due_date DATETIME NULL,
            price DECIMAL(10,2) NULL,
            billing_period VARCHAR(32) NULL,
            raw_payload LONGTEXT NULL,
            source VARCHAR(32) NOT NULL DEFAULT 'api',
            synced_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_user_node (user_id, node_id),
            KEY user_id (user_id),
            KEY node_id (node_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public static function upsert_nodes($user_id, array $nodes, $source = 'api') {
        global $wpdb;
        $table = self::table_name();
        $user_id = (int) $user_id;
        if ($user_id <= 0 || empty($nodes)) {
            return 0;
        }

        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
        if ($table_exists !== $table) {
            self::create_table();
        }

        $synced_at = current_time('mysql');
        $count = 0;

        foreach ($nodes as $node) {
            if (!is_array($node)) {
                continue;
            }
            $node_id = isset($node['id']) ? (string) $node['id'] : '';
            if ($node_id === '') {
                continue;
            }

            $node_type = isset($node['node_type']) ? (string) $node['node_type'] : '';
            $created_at = isset($node['created_at']) ? self::normalize_datetime($node['created_at']) : null;
            $due_date = isset($node['due_date']) ? self::normalize_datetime($node['due_date']) : null;
            $price = isset($node['price']) && $node['price'] !== '' ? (float) $node['price'] : null;
            $product_id = isset($node['product_id']) ? (int) $node['product_id'] : 0;
            if ($product_id <= 0 && $node_type !== '') {
                $product_id = self::resolve_product_id($node_type);
            }
            $product_id_value = $product_id > 0 ? (string) $product_id : null;

            $result = $wpdb->replace(
                $table,
                [
                    'user_id' => $user_id,
                    'node_id' => $node_id,
                    'node_type' => $node_type,
                    'product_id' => $product_id_value,
                    'status' => isset($node['status']) ? (string) $node['status'] : null,
                    'created_at' => $created_at,
                    'due_date' => $due_date,
                    'price' => $price,
                    'billing_period' => isset($node['billing_period']) ? (string) $node['billing_period'] : null,
                    'raw_payload' => wp_json_encode($node),
                    'source' => $source,
                    'synced_at' => $synced_at,
                ],
                [
                    '%d',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%f',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                ]
            );

            if ($result !== false) {
                $count++;
            } else {
                $error = $wpdb->last_error;
                if ($error) {
                    error_log('INCR_Nodes_Store upsert failed: ' . $error);
                }
            }
        }

        return $count;
    }

    public static function get_nodes_by_user($user_id) {
        global $wpdb;
        $table = self::table_name();
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return [];
        }

        $sql = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d ORDER BY due_date IS NULL, due_date ASC",
            $user_id
        );

        return $wpdb->get_results($sql, ARRAY_A);
    }

    public static function get_overdue_count($user_id) {
        global $wpdb;
        $table = self::table_name();
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return 0;
        }

        $now = current_time('mysql');
        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND due_date IS NOT NULL AND due_date <= %s",
            $user_id,
            $now
        );

        return (int) $wpdb->get_var($sql);
    }

    public static function get_all_nodes($args = []) {
        global $wpdb;
        $table = self::table_name();

        $defaults = [
            'page' => 1,
            'per_page' => 20,
            'user_id' => null,
            'status' => null,
            'node_type' => null,
            'search' => null,
            'overdue_only' => false,
            'due_date_from' => null,
            'due_date_to' => null,
            'orderby' => 'due_date',
            'order' => 'ASC',
        ];
        $args = wp_parse_args($args, $defaults);

        $page = max(1, (int) $args['page']);
        $per_page = max(1, (int) $args['per_page']);
        $offset = ($page - 1) * $per_page;

        $where = [];
        $params = [];

        if (!empty($args['user_id'])) {
            $where[] = 'user_id = %d';
            $params[] = (int) $args['user_id'];
        }

        if (!empty($args['status'])) {
            $today = current_time('Y-m-d');
            if ($args['status'] === 'overdue') {
                $where[] = 'due_date IS NOT NULL AND DATE(due_date) < %s';
                $params[] = $today;
            } elseif ($args['status'] === 'active') {
                $where[] = 'due_date IS NOT NULL AND DATE(due_date) >= %s';
                $params[] = $today;
            } else {
                $where[] = 'status = %s';
                $params[] = (string) $args['status'];
            }
        }

        if (!empty($args['node_type'])) {
            $where[] = 'node_type = %s';
            $params[] = (string) $args['node_type'];
        }

        if (!empty($args['search'])) {
            $where[] = '(node_id LIKE %s OR node_type LIKE %s)';
            $like = '%' . $wpdb->esc_like((string) $args['search']) . '%';
            $params[] = $like;
            $params[] = $like;
        }

        if (!empty($args['overdue_only'])) {
            $where[] = 'due_date IS NOT NULL AND due_date <= %s';
            $params[] = current_time('mysql');
        }

        if (!empty($args['due_date_from'])) {
            $from = self::normalize_datetime($args['due_date_from']);
            if ($from) {
                $where[] = 'due_date >= %s';
                $params[] = $from;
            }
        }

        if (!empty($args['due_date_to'])) {
            $to = self::normalize_datetime($args['due_date_to']);
            if ($to && preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', (string) $args['due_date_to'])) {
                $dt = new DateTimeImmutable((string) $args['due_date_to'], wp_timezone());
                $dt = $dt->setTime(23, 59, 59);
                $to = $dt->format('Y-m-d H:i:s');
            }
            if ($to) {
                $where[] = 'due_date <= %s';
                $params[] = $to;
            }
        }

        $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $allowed_orderby = ['id', 'user_id', 'node_id', 'node_type', 'status', 'created_at', 'due_date', 'price', 'synced_at'];
        $orderby = in_array($args['orderby'], $allowed_orderby, true) ? $args['orderby'] : 'due_date';
        $order = strtoupper($args['order']) === 'DESC' ? 'DESC' : 'ASC';

        $count_sql = "SELECT COUNT(*) FROM {$table} {$where_sql}";
        if ($orderby === 'due_date' && $order === 'ASC') {
            $items_sql = "SELECT * FROM {$table} {$where_sql} ORDER BY due_date IS NULL, due_date ASC LIMIT %d OFFSET %d";
        } else {
            $items_sql = "SELECT * FROM {$table} {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        }

        $count_params = $params;
        $items_params = array_merge($params, [$per_page, $offset]);

        $total = (int) $wpdb->get_var($wpdb->prepare($count_sql, $count_params));
        $items = $wpdb->get_results($wpdb->prepare($items_sql, $items_params), ARRAY_A);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
        ];
    }

    public static function get_status_counts($args = []) {
        global $wpdb;
        $table = self::table_name();
        $args = wp_parse_args($args, [
            'user_id' => null,
            'node_type' => null,
            'due_date_from' => null,
            'due_date_to' => null,
        ]);

        $where = [];
        $params = [];

        if (!empty($args['user_id'])) {
            $where[] = 'user_id = %d';
            $params[] = (int) $args['user_id'];
        }

        if (!empty($args['node_type'])) {
            $where[] = 'node_type = %s';
            $params[] = (string) $args['node_type'];
        }

        if (!empty($args['due_date_from'])) {
            $from = self::normalize_datetime($args['due_date_from']);
            if ($from) {
                $where[] = 'due_date >= %s';
                $params[] = $from;
            }
        }

        if (!empty($args['due_date_to'])) {
            $to = self::normalize_datetime($args['due_date_to']);
            if ($to && preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', (string) $args['due_date_to'])) {
                $dt = new DateTimeImmutable((string) $args['due_date_to'], wp_timezone());
                $dt = $dt->setTime(23, 59, 59);
                $to = $dt->format('Y-m-d H:i:s');
            }
            if ($to) {
                $where[] = 'due_date <= %s';
                $params[] = $to;
            }
        }

        $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $today = current_time('Y-m-d');

        $sql = "SELECT
            SUM(CASE WHEN due_date IS NOT NULL AND DATE(due_date) < %s THEN 1 ELSE 0 END) AS overdue_count,
            SUM(CASE WHEN due_date IS NOT NULL AND DATE(due_date) >= %s THEN 1 ELSE 0 END) AS active_count
            FROM {$table} {$where_sql}";

        $params = array_merge([$today, $today], $params);
        $row = $wpdb->get_row($wpdb->prepare($sql, $params), ARRAY_A);

        return [
            'overdue' => isset($row['overdue_count']) ? (int) $row['overdue_count'] : 0,
            'active' => isset($row['active_count']) ? (int) $row['active_count'] : 0,
        ];
    }

    public static function get_type_counts($args = []) {
        global $wpdb;
        $table = self::table_name();
        $args = wp_parse_args($args, [
            'user_id' => null,
            'status' => null,
            'due_date_from' => null,
            'due_date_to' => null,
        ]);

        $where = [];
        $params = [];

        if (!empty($args['user_id'])) {
            $where[] = 'user_id = %d';
            $params[] = (int) $args['user_id'];
        }

        if (!empty($args['status'])) {
            $today = current_time('Y-m-d');
            if ($args['status'] === 'overdue') {
                $where[] = 'due_date IS NOT NULL AND DATE(due_date) < %s';
                $params[] = $today;
            } elseif ($args['status'] === 'active') {
                $where[] = 'due_date IS NOT NULL AND DATE(due_date) >= %s';
                $params[] = $today;
            }
        }

        if (!empty($args['due_date_from'])) {
            $from = self::normalize_datetime($args['due_date_from']);
            if ($from) {
                $where[] = 'due_date >= %s';
                $params[] = $from;
            }
        }

        if (!empty($args['due_date_to'])) {
            $to = self::normalize_datetime($args['due_date_to']);
            if ($to && preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', (string) $args['due_date_to'])) {
                $dt = new DateTimeImmutable((string) $args['due_date_to'], wp_timezone());
                $dt = $dt->setTime(23, 59, 59);
                $to = $dt->format('Y-m-d H:i:s');
            }
            if ($to) {
                $where[] = 'due_date <= %s';
                $params[] = $to;
            }
        }

        $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT node_type, COUNT(*) AS total FROM {$table} {$where_sql} GROUP BY node_type ORDER BY node_type ASC";

        return $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
    }

    public static function get_distinct_node_types() {
        global $wpdb;
        $table = self::table_name();
        $sql = "SELECT DISTINCT node_type FROM {$table} WHERE node_type <> '' ORDER BY node_type ASC";
        return $wpdb->get_col($sql);
    }

    private static function resolve_product_id($node_type) {
        global $wpdb;
        $node_type = strtolower(trim((string) $node_type));
        if ($node_type === '') {
            return 0;
        }

        $product_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT p.ID FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE pm.meta_key = 'acf_node_name'
                AND LOWER(pm.meta_value) = LOWER(%s)
                AND p.post_type = 'product'
                AND p.post_status = 'publish'
                LIMIT 1",
                $node_type
            )
        );

        return $product_id ? (int) $product_id : 0;
    }

    private static function normalize_datetime($value) {
        if ($value === null || $value === '') {
            return null;
        }
        $timestamp = strtotime((string) $value);
        if (!$timestamp) {
            return null;
        }
        $dt = new DateTimeImmutable('@' . $timestamp);
        $dt = $dt->setTimezone(wp_timezone());
        return $dt->format('Y-m-d H:i:s');
    }
}
