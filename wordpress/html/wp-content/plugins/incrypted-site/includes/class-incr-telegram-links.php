<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class INCR_Telegram_Links {
    public static function init() {
        add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
    }

    public static function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'incr_telegram_links';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            wp_user_id BIGINT UNSIGNED NOT NULL,
            telegram_chat_id BIGINT NOT NULL,
            telegram_user_id BIGINT NOT NULL,
            tg_username VARCHAR(190) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY wp_user_id (wp_user_id),
            UNIQUE KEY telegram_chat_id (telegram_chat_id),
            KEY telegram_user_id (telegram_user_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public static function register_routes() {
        register_rest_route(
            'nodesplus/v1',
            '/telegram/link',
            [
                'methods' => 'POST',
                'callback' => [ __CLASS__, 'handle_link' ],
                'permission_callback' => [ __CLASS__, 'check_secret' ],
            ]
        );

        register_rest_route(
            'nodesplus/v1',
            '/telegram/nodes',
            [
                'methods' => 'GET',
                'callback' => [ __CLASS__, 'handle_nodes' ],
                'permission_callback' => [ __CLASS__, 'check_secret' ],
            ]
        );
    }

    public static function check_secret( WP_REST_Request $request ) {
        $secret = '';
        if ( function_exists( 'incr_get_config' ) ) {
            $secret = incr_get_config( 'INCR_TELEGRAM_BOT_SECRET', '', 'NP_BOT_SECRET' );
        } elseif ( defined( 'INCR_TELEGRAM_BOT_SECRET' ) ) {
            $secret = INCR_TELEGRAM_BOT_SECRET;
        } else {
            $env_value = getenv( 'NP_BOT_SECRET' );
            if ( $env_value !== false ) {
                $secret = $env_value;
            } else {
                $env_value = getenv( 'INCR_TELEGRAM_BOT_SECRET' );
                if ( $env_value !== false ) {
                    $secret = $env_value;
                }
            }
        }
        $secret = apply_filters( 'incr_telegram_bot_secret', $secret );
        $secret = trim( (string) $secret );

        if ( $secret === '' ) {
            return new WP_Error( 'nodesplus_telegram_secret', 'Bot secret not configured.', [ 'status' => 500 ] );
        }

        $provided = (string) $request->get_header( 'x-np-bot-secret' );
        if ( $provided === '' || ! hash_equals( $secret, $provided ) ) {
            return new WP_Error( 'nodesplus_telegram_forbidden', 'Forbidden.', [ 'status' => 403 ] );
        }

        return true;
    }

    private static function token_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'incr_telegram_connect_tokens';
    }

    private static function links_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'incr_telegram_links';
    }

    private static function ensure_table( $table_name, $creator ) {
        global $wpdb;
        $existing = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) );
        if ( $existing !== $table_name ) {
            call_user_func( $creator );
        }
    }

    public static function handle_link( WP_REST_Request $request ) {
        $params = $request->get_json_params();
        if ( ! is_array( $params ) ) {
            $params = $request->get_body_params();
        }

        $token = isset( $params['token'] ) ? sanitize_text_field( (string) $params['token'] ) : '';
        $chat_id = isset( $params['telegram_chat_id'] ) ? (int) $params['telegram_chat_id'] : 0;
        $telegram_user_id = isset( $params['telegram_user_id'] ) ? (int) $params['telegram_user_id'] : 0;
        $username = isset( $params['username'] ) ? sanitize_text_field( (string) $params['username'] ) : '';

        if ( $token === '' || $chat_id === 0 || $telegram_user_id === 0 ) {
            return new WP_Error( 'nodesplus_telegram_invalid', 'Missing required fields.', [ 'status' => 400 ] );
        }

        self::ensure_table( self::links_table_name(), [ __CLASS__, 'create_table' ] );
        if ( class_exists( 'INCR_Telegram_Connect' ) ) {
            self::ensure_table( self::token_table_name(), [ 'INCR_Telegram_Connect', 'create_table' ] );
        }

        global $wpdb;
        $token_table = self::token_table_name();
        $now = gmdate( 'Y-m-d H:i:s' );

        $token_row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$token_table} WHERE token = %s AND used_at IS NULL AND expires_at >= %s",
                $token,
                $now
            ),
            ARRAY_A
        );

        if ( ! $token_row ) {
            return new WP_Error( 'nodesplus_telegram_token', 'Token is invalid or expired.', [ 'status' => 400 ] );
        }

        $wp_user_id = (int) $token_row['wp_user_id'];
        if ( $wp_user_id <= 0 ) {
            return new WP_Error( 'nodesplus_telegram_token', 'Token is invalid.', [ 'status' => 400 ] );
        }

        $links_table = self::links_table_name();

        $existing_link = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$links_table} WHERE telegram_chat_id = %d",
                $chat_id
            ),
            ARRAY_A
        );

        if ( $existing_link && (int) $existing_link['wp_user_id'] !== $wp_user_id ) {
            return new WP_Error( 'nodesplus_telegram_conflict', 'Telegram chat already linked to another user.', [ 'status' => 409 ] );
        }

        $wpdb->update(
            $token_table,
            [ 'used_at' => $now ],
            [ 'id' => (int) $token_row['id'] ],
            [ '%s' ],
            [ '%d' ]
        );

        $insert_sql = $wpdb->prepare(
            "INSERT INTO {$links_table}
                (wp_user_id, telegram_chat_id, telegram_user_id, tg_username, created_at, updated_at)
             VALUES (%d, %d, %d, %s, %s, %s)
             ON DUPLICATE KEY UPDATE
                telegram_chat_id = VALUES(telegram_chat_id),
                telegram_user_id = VALUES(telegram_user_id),
                tg_username = VALUES(tg_username),
                updated_at = VALUES(updated_at)",
            $wp_user_id,
            $chat_id,
            $telegram_user_id,
            $username,
            $now,
            $now
        );

        $result = $wpdb->query( $insert_sql );
        if ( $result === false ) {
            return new WP_Error( 'nodesplus_telegram_store', 'Unable to store Telegram link.', [ 'status' => 500 ] );
        }

        return new WP_REST_Response( [ 'linked' => true ], 200 );
    }

    public static function handle_nodes( WP_REST_Request $request ) {
        $chat_id = (int) $request->get_param( 'chat_id' );
        if ( $chat_id === 0 ) {
            return new WP_Error( 'nodesplus_telegram_invalid', 'Missing chat_id.', [ 'status' => 400 ] );
        }

        self::ensure_table( self::links_table_name(), [ __CLASS__, 'create_table' ] );

        global $wpdb;
        $links_table = self::links_table_name();
        $link = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT wp_user_id FROM {$links_table} WHERE telegram_chat_id = %d",
                $chat_id
            ),
            ARRAY_A
        );

        if ( ! $link ) {
            return new WP_Error( 'nodesplus_telegram_not_found', 'Chat not linked.', [ 'status' => 404 ] );
        }

        $wp_user_id = (int) $link['wp_user_id'];
        if ( $wp_user_id <= 0 ) {
            return new WP_Error( 'nodesplus_telegram_not_found', 'Chat not linked.', [ 'status' => 404 ] );
        }

        if ( ! class_exists( 'INCR_Nodes_Store' ) ) {
            return new WP_Error( 'nodesplus_telegram_nodes', 'Nodes store unavailable.', [ 'status' => 500 ] );
        }

        $nodes_table = INCR_Nodes_Store::table_name();
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT node_id, node_type, due_date, raw_payload
                 FROM {$nodes_table}
                 WHERE user_id = %d
                   AND (
                        JSON_UNQUOTE(JSON_EXTRACT(raw_payload, '$.lifecycle_status')) = 'current'
                        OR JSON_EXTRACT(raw_payload, '$.lifecycle_status') IS NULL
                   )
                 ORDER BY due_date IS NULL, due_date ASC",
                $wp_user_id
            ),
            ARRAY_A
        );

        $now = current_time( 'timestamp' );
        $nodes = [];
        foreach ( $rows as $row ) {
            $due_date = $row['due_date'];
            $derived_status = 'unknown';
            if ( ! empty( $due_date ) ) {
                $due_ts = strtotime( $due_date );
                if ( $due_ts !== false ) {
                    $derived_status = ( $due_ts < $now ) ? 'overdue' : 'active';
                }
            }

            $nodes[] = [
                'node_id' => $row['node_id'],
                'node_type' => $row['node_type'],
                'due_date' => $due_date,
                'derived_status' => $derived_status,
            ];
        }

        return new WP_REST_Response( [ 'nodes' => $nodes ], 200 );
    }
}
