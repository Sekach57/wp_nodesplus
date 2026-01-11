<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class INCR_Telegram_Connect {
    public static function init() {
        add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
    }

    public static function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'incr_telegram_connect_tokens';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            wp_user_id BIGINT UNSIGNED NOT NULL,
            token CHAR(36) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY token (token),
            KEY wp_user_id (wp_user_id),
            KEY expires_at (expires_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public static function register_routes() {
        register_rest_route(
            'nodesplus/v1',
            '/telegram/connect',
            [
                'methods' => 'POST',
                'callback' => [ __CLASS__, 'handle_connect' ],
                'permission_callback' => function () {
                    return is_user_logged_in();
                },
            ]
        );
    }

    public static function handle_connect( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return new WP_Error( 'nodesplus_telegram_auth', 'Authentication required.', [ 'status' => 401 ] );
        }

        $bot_username = function_exists( 'incr_get_config' ) ? incr_get_config( 'INCR_TELEGRAM_BOT_USERNAME' ) : '';
        if ( $bot_username === '' && defined( 'INCR_TELEGRAM_BOT_USERNAME' ) ) {
            $bot_username = INCR_TELEGRAM_BOT_USERNAME;
        }
        if ( $bot_username === '' ) {
            $env_value = getenv( 'INCR_TELEGRAM_BOT_USERNAME' );
            if ( $env_value !== false ) {
                $bot_username = $env_value;
            }
        }
        $bot_username = apply_filters( 'incr_telegram_bot_username', $bot_username );
        $bot_username = trim( (string) $bot_username );

        if ( $bot_username === '' ) {
            return new WP_Error( 'nodesplus_telegram_config', 'Telegram bot username is not configured.', [ 'status' => 500 ] );
        }

        $token = wp_generate_uuid4();
        $now = gmdate( 'Y-m-d H:i:s' );
        $expires_at = gmdate( 'Y-m-d H:i:s', time() + 600 );

        global $wpdb;
        $table_name = $wpdb->prefix . 'incr_telegram_connect_tokens';
        $existing = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) );
        if ( $existing !== $table_name ) {
            self::create_table();
        }
        $inserted = $wpdb->insert(
            $table_name,
            [
                'wp_user_id' => $user_id,
                'token' => $token,
                'expires_at' => $expires_at,
                'used_at' => null,
                'created_at' => $now,
            ],
            [ '%d', '%s', '%s', '%s', '%s' ]
        );

        if ( false === $inserted ) {
            if ( $wpdb->last_error ) {
                error_log( 'INCR_Telegram_Connect: insert failed: ' . $wpdb->last_error );
            }
            return new WP_Error( 'nodesplus_telegram_store', 'Unable to create connect token.', [ 'status' => 500 ] );
        }

        $redirect_url = sprintf(
            'https://t.me/%s?start=%s',
            rawurlencode( $bot_username ),
            rawurlencode( $token )
        );

        return new WP_REST_Response( [ 'redirect_url' => $redirect_url ], 200 );
    }
}
