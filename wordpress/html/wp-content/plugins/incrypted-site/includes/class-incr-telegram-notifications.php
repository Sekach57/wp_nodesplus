<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class INCR_Telegram_Notifications {
    const CRON_HOOK = 'incr_telegram_notifications_hourly';

    public static function init() {
        add_action( 'init', [ __CLASS__, 'ensure_scheduled' ] );
        add_action( self::CRON_HOOK, [ __CLASS__, 'run' ] );
    }

    public static function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'incr_telegram_notifications';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            wp_user_id BIGINT UNSIGNED NOT NULL,
            node_id VARCHAR(64) NOT NULL,
            notif_type VARCHAR(32) NOT NULL,
            sent_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY notif_unique (wp_user_id, node_id, notif_type),
            KEY wp_user_id (wp_user_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public static function ensure_scheduled() {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time() + 60, 'hourly', self::CRON_HOOK );
        }
    }

    public static function unschedule() {
        $timestamp = wp_next_scheduled( self::CRON_HOOK );
        while ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::CRON_HOOK );
            $timestamp = wp_next_scheduled( self::CRON_HOOK );
        }
    }

    private static function notifications_table() {
        global $wpdb;
        return $wpdb->prefix . 'incr_telegram_notifications';
    }

    private static function links_table() {
        global $wpdb;
        return $wpdb->prefix . 'incr_telegram_links';
    }

    private static function get_bot_token() {
        $token = function_exists( 'incr_get_config' ) ? incr_get_config( 'INCR_TELEGRAM_BOT_TOKEN' ) : '';
        if ( $token === '' && defined( 'INCR_TELEGRAM_BOT_TOKEN' ) ) {
            $token = INCR_TELEGRAM_BOT_TOKEN;
        }
        if ( $token === '' ) {
            $env_value = getenv( 'INCR_TELEGRAM_BOT_TOKEN' );
            if ( $env_value !== false ) {
                $token = $env_value;
            }
        }
        $token = apply_filters( 'incr_telegram_bot_token', $token );
        return trim( (string) $token );
    }

    private static function ensure_table( $table_name, $creator ) {
        global $wpdb;
        $existing = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) );
        if ( $existing !== $table_name ) {
            call_user_func( $creator );
        }
    }

    public static function run() {
        $token = self::get_bot_token();
        if ( $token === '' ) {
            error_log( 'INCR_Telegram_Notifications: missing INCR_TELEGRAM_BOT_TOKEN' );
            return;
        }

        self::ensure_table( self::notifications_table(), [ __CLASS__, 'create_table' ] );
        self::ensure_table( self::links_table(), [ 'INCR_Telegram_Links', 'create_table' ] );

        if ( ! class_exists( 'INCR_Nodes_Store' ) ) {
            error_log( 'INCR_Telegram_Notifications: Nodes store missing' );
            return;
        }

        $window_24 = (int) apply_filters( 'incr_telegram_due_24h_window', 24 );
        $window_72 = (int) apply_filters( 'incr_telegram_due_72h_window', 72 );
        $max_window = max( $window_24, $window_72, 1 );

        $now_ts = current_time( 'timestamp' );
        $max_ts = $now_ts + ( $max_window * HOUR_IN_SECONDS );
        $max_datetime = wp_date( 'Y-m-d H:i:s', $max_ts );

        global $wpdb;
        $nodes_table = INCR_Nodes_Store::table_name();
        $links_table = self::links_table();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT n.user_id, n.node_id, n.node_type, n.due_date, l.telegram_chat_id
                 FROM {$nodes_table} n
                 INNER JOIN {$links_table} l ON l.wp_user_id = n.user_id
                 WHERE n.due_date IS NOT NULL
                   AND (
                        JSON_UNQUOTE(JSON_EXTRACT(n.raw_payload, '$.lifecycle_status')) = 'current'
                        OR JSON_EXTRACT(n.raw_payload, '$.lifecycle_status') IS NULL
                   )
                  AND n.due_date <= %s
                 ORDER BY l.telegram_chat_id, n.due_date ASC",
                $max_datetime
            ),
            ARRAY_A
        );

        if ( empty( $rows ) ) {
            return;
        }

        $today = wp_date( 'Y-m-d', $now_ts );
        $grouped = [];

        foreach ( $rows as $row ) {
            $due_ts = strtotime( $row['due_date'] );
            if ( $due_ts === false ) {
                continue;
            }

            $type = '';
            if ( $due_ts < $now_ts ) {
                $type = 'overdue';
            } else {
                $due_date_only = wp_date( 'Y-m-d', $due_ts );
                if ( $due_date_only === $today ) {
                    $type = 'due_today';
                } elseif ( $due_ts <= $now_ts + ( $window_24 * HOUR_IN_SECONDS ) ) {
                    $type = 'due_24h';
                } elseif ( $due_ts <= $now_ts + ( $window_72 * HOUR_IN_SECONDS ) ) {
                    $type = 'due_72h';
                } else {
                    continue;
                }
            }

            $user_id = (int) $row['user_id'];
            if ( $user_id <= 0 || empty( $row['telegram_chat_id'] ) ) {
                continue;
            }

            if ( ! isset( $grouped[ $user_id ] ) ) {
                $grouped[ $user_id ] = [
                    'chat_id' => (string) $row['telegram_chat_id'],
                    'items' => [],
                ];
            }

            $grouped[ $user_id ]['items'][] = [
                'node_id' => (string) $row['node_id'],
                'node_type' => (string) $row['node_type'],
                'due_date' => (string) $row['due_date'],
                'due_ts' => $due_ts,
                'notif_type' => $type,
            ];
        }

        foreach ( $grouped as $user_id => $data ) {
            $items = $data['items'];
            if ( empty( $items ) ) {
                continue;
            }

            $sent_map = self::load_sent_map( $user_id, $items );

            $by_type = [];
            foreach ( $items as $item ) {
                $key = $item['node_id'] . '|' . $item['notif_type'];
                if ( isset( $sent_map[ $key ] ) ) {
                    continue;
                }
                $by_type[ $item['notif_type'] ][] = $item;
            }

            if ( empty( $by_type ) ) {
                continue;
            }

            $message = self::build_grouped_message( $by_type, $now_ts );
            if ( $message === '' ) {
                continue;
            }

            $sent = self::send_message( $token, $data['chat_id'], $message );
            if ( ! $sent ) {
                error_log( 'INCR_Telegram_Notifications: send failed for user ' . $user_id );
                continue;
            }

            foreach ( $by_type as $nodes ) {
                self::mark_sent( $user_id, $nodes );
            }
        }
    }

    private static function load_sent_map( $user_id, array $items ) {
        $node_ids = [];
        $types = [];
        foreach ( $items as $item ) {
            $node_ids[] = (string) $item['node_id'];
            $types[] = (string) $item['notif_type'];
        }
        $node_ids = array_values( array_unique( $node_ids ) );
        $types = array_values( array_unique( $types ) );
        if ( empty( $node_ids ) || empty( $types ) ) {
            return [];
        }

        $node_placeholders = implode( ',', array_fill( 0, count( $node_ids ), '%s' ) );
        $type_placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );

        global $wpdb;
        $table = self::notifications_table();
        $sql = "SELECT node_id, notif_type FROM {$table} WHERE wp_user_id = %d
                AND node_id IN ({$node_placeholders})
                AND notif_type IN ({$type_placeholders})";
        $params = array_merge( [ (int) $user_id ], $node_ids, $types );
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

        $map = [];
        foreach ( $rows as $row ) {
            $map[ $row['node_id'] . '|' . $row['notif_type'] ] = true;
        }
        return $map;
    }

    private static function build_grouped_message( array $grouped_by_type, $now_ts ) {
        $sections = [
            'overdue' => [ 'label' => 'Overdue', 'items' => [] ],
            'today' => [ 'label' => 'Due today', 'items' => [] ],
            'tomorrow' => [ 'label' => 'Due tomorrow', 'items' => [] ],
            'in_2_days' => [ 'label' => 'Due in 2 days', 'items' => [] ],
            'in_3_days' => [ 'label' => 'Due in 3 days', 'items' => [] ],
        ];

        foreach ( $grouped_by_type as $type => $nodes ) {
            foreach ( $nodes as $node ) {
                $label = $node['node_type'] !== '' ? $node['node_type'] : $node['node_id'];
                $date = wp_date( 'Y-m-d', $node['due_ts'] );

                if ( $type === 'overdue' ) {
                    $sections['overdue']['items'][] = '- ' . $label . ' - OVERDUE since ' . $date;
                    continue;
                }

                $days_out = (int) floor( ( $node['due_ts'] - $now_ts ) / DAY_IN_SECONDS );
                if ( $days_out <= 0 ) {
                    $sections['today']['items'][] = '- ' . $label . ' - due ' . $date;
                } elseif ( $days_out === 1 ) {
                    $sections['tomorrow']['items'][] = '- ' . $label . ' - due ' . $date;
                } elseif ( $days_out === 2 ) {
                    $sections['in_2_days']['items'][] = '- ' . $label . ' - due ' . $date;
                } else {
                    $sections['in_3_days']['items'][] = '- ' . $label . ' - due ' . $date;
                }
            }
        }

        $lines = [ 'Node renewal reminder' ];
        foreach ( $sections as $section ) {
            if ( empty( $section['items'] ) ) {
                continue;
            }
            $lines[] = $section['label'] . ':';
            $lines = array_merge( $lines, $section['items'] );
        }

        if ( count( $lines ) === 1 ) {
            return '';
        }

        return implode( "\n", $lines );
    }

    private static function send_message( $token, $chat_id, $text ) {
        $response = wp_remote_post(
            "https://api.telegram.org/bot{$token}/sendMessage",
            [
                'headers' => [ 'Content-Type' => 'application/json' ],
                'body' => wp_json_encode(
                    [
                        'chat_id' => $chat_id,
                        'text' => $text,
                    ]
                ),
                'timeout' => 15,
            ]
        );

        if ( is_wp_error( $response ) ) {
            error_log( 'INCR_Telegram_Notifications: ' . $response->get_error_message() );
            return false;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code < 200 || $code >= 300 ) {
            error_log( 'INCR_Telegram_Notifications: Telegram API HTTP ' . $code );
            return false;
        }

        return true;
    }

    private static function mark_sent( $user_id, array $nodes ) {
        global $wpdb;
        $table = self::notifications_table();
        $sent_at = current_time( 'mysql' );

        foreach ( $nodes as $node ) {
            $wpdb->query(
                $wpdb->prepare(
                    "INSERT IGNORE INTO {$table} (wp_user_id, node_id, notif_type, sent_at)
                     VALUES (%d, %s, %s, %s)",
                    (int) $user_id,
                    (string) $node['node_id'],
                    (string) $node['notif_type'],
                    $sent_at
                )
            );
        }
    }
}
