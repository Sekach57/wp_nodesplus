<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class INCR_Telegram_Wallet {
    const CRON_HOOK = 'incr_telegram_wallet_prompt';

    public static function init() {
        add_action( 'init', [ __CLASS__, 'ensure_scheduled' ] );
        add_action( self::CRON_HOOK, [ __CLASS__, 'run_prompt_job' ] );
        add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
        add_filter( 'cron_schedules', [ __CLASS__, 'register_schedules' ] );
    }

    public static function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'incr_telegram_charge_intents';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            wp_user_id BIGINT UNSIGNED NOT NULL,
            telegram_chat_id BIGINT NOT NULL,
            node_ids LONGTEXT NOT NULL,
            total_amount DECIMAL(10,2) NOT NULL,
            currency VARCHAR(8) NOT NULL,
            status VARCHAR(32) NOT NULL,
            created_at DATETIME NOT NULL,
            expires_at DATETIME NOT NULL,
            responded_at DATETIME NULL,
            response VARCHAR(32) NULL,
            PRIMARY KEY  (id),
            KEY wp_user_id (wp_user_id),
            KEY telegram_chat_id (telegram_chat_id),
            KEY status (status)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public static function register_schedules( $schedules ) {
        if ( ! isset( $schedules['incr_four_hours'] ) ) {
            $schedules['incr_four_hours'] = [
                'interval' => 4 * HOUR_IN_SECONDS,
                'display' => 'Every 4 hours',
            ];
        }
        return $schedules;
    }

    public static function ensure_scheduled() {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time() + 120, 'incr_four_hours', self::CRON_HOOK );
        }
    }

    public static function unschedule() {
        $timestamp = wp_next_scheduled( self::CRON_HOOK );
        while ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::CRON_HOOK );
            $timestamp = wp_next_scheduled( self::CRON_HOOK );
        }
    }

    public static function register_routes() {
        register_rest_route(
            'nodesplus/v1',
            '/telegram/charge',
            [
                'methods' => 'POST',
                'callback' => [ __CLASS__, 'handle_charge' ],
                'permission_callback' => [ __CLASS__, 'check_secret' ],
            ]
        );
    }

    public static function check_secret( WP_REST_Request $request ) {
        if ( class_exists( 'INCR_Telegram_Links' ) && method_exists( 'INCR_Telegram_Links', 'check_secret' ) ) {
            return INCR_Telegram_Links::check_secret( $request );
        }

        $secret = function_exists( 'incr_get_config' ) ? incr_get_config( 'INCR_TELEGRAM_BOT_SECRET', '', 'NP_BOT_SECRET' ) : '';
        if ( $secret === '' && defined( 'INCR_TELEGRAM_BOT_SECRET' ) ) {
            $secret = INCR_TELEGRAM_BOT_SECRET;
        }
        if ( $secret === '' ) {
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

    private static function intents_table() {
        global $wpdb;
        return $wpdb->prefix . 'incr_telegram_charge_intents';
    }

    private static function links_table() {
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

    private static function resolve_product_id_from_node_type( $node_type ) {
        global $wpdb;
        $node_type = strtolower( trim( (string) $node_type ) );
        if ( $node_type === '' ) {
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

    private static function get_product_price_for_user( $product_id, $user_id ) {
        if ( $product_id <= 0 ) {
            return 0.0;
        }
        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return 0.0;
        }

        $previous_user = get_current_user_id();
        if ( $user_id && $user_id !== $previous_user ) {
            wp_set_current_user( $user_id );
        }

        $price = (float) $product->get_price();
        $display_price = wc_get_price_to_display( $product, [ 'price' => $price ] );

        if ( $user_id && $user_id !== $previous_user ) {
            wp_set_current_user( $previous_user );
        }

        return (float) $display_price;
    }

    private static function format_money( $amount, $user_id ) {
        if ( function_exists( 'woo_wallet_wc_price_args' ) ) {
            return wc_price( $amount, woo_wallet_wc_price_args( $user_id ) );
        }
        return wc_price( $amount );
    }

    private static function send_telegram_prompt( $chat_id, $message, $intent_id ) {
        $token = self::get_bot_token();
        if ( $token === '' ) {
            error_log( 'INCR_Telegram_Wallet: missing bot token' );
            return false;
        }

        $payload = [
            'chat_id' => $chat_id,
            'text' => $message,
            'reply_markup' => [
                'inline_keyboard' => [
                    [
                        [ 'text' => 'Yes, charge', 'callback_data' => 'charge:yes:' . $intent_id ],
                        [ 'text' => 'No', 'callback_data' => 'charge:no:' . $intent_id ],
                    ],
                ],
            ],
        ];

        $response = wp_remote_post(
            "https://api.telegram.org/bot{$token}/sendMessage",
            [
                'headers' => [ 'Content-Type' => 'application/json' ],
                'body' => wp_json_encode( $payload ),
                'timeout' => 15,
            ]
        );

        if ( is_wp_error( $response ) ) {
            error_log( 'INCR_Telegram_Wallet: send failed: ' . $response->get_error_message() );
            return false;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code < 200 || $code >= 300 ) {
            error_log( 'INCR_Telegram_Wallet: Telegram API HTTP ' . $code );
            return false;
        }

        return true;
    }

    public static function run_prompt_job() {
        if ( ! function_exists( 'woo_wallet' ) ) {
            error_log( 'INCR_Telegram_Wallet: WooWallet not available' );
            return;
        }

        self::ensure_table( self::intents_table(), [ __CLASS__, 'create_table' ] );
        self::ensure_table( self::links_table(), [ 'INCR_Telegram_Links', 'create_table' ] );

        if ( ! class_exists( 'INCR_Nodes_Store' ) ) {
            error_log( 'INCR_Telegram_Wallet: Nodes store missing' );
            return;
        }

        global $wpdb;
        $nodes_table = INCR_Nodes_Store::table_name();
        $links_table = self::links_table();
        $intents_table = self::intents_table();

        $now = current_time( 'timestamp' );
        $to = $now + DAY_IN_SECONDS;
        $from_dt = wp_date( 'Y-m-d H:i:s', $now );
        $to_dt = wp_date( 'Y-m-d H:i:s', $to );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT n.user_id, n.node_id, n.node_type, n.product_id, n.due_date, l.telegram_chat_id
                 FROM {$nodes_table} n
                 INNER JOIN {$links_table} l ON l.wp_user_id = n.user_id
                 WHERE n.due_date IS NOT NULL
                   AND n.due_date >= %s
                   AND n.due_date < %s
                   AND (
                        JSON_UNQUOTE(JSON_EXTRACT(n.raw_payload, '$.lifecycle_status')) = 'current'
                        OR JSON_EXTRACT(n.raw_payload, '$.lifecycle_status') IS NULL
                   )
                 ORDER BY l.telegram_chat_id, n.due_date ASC",
                $from_dt,
                $to_dt
            ),
            ARRAY_A
        );

        if ( empty( $rows ) ) {
            return;
        }

        $grouped = [];
        foreach ( $rows as $row ) {
            $user_id = (int) $row['user_id'];
            if ( $user_id <= 0 ) {
                continue;
            }
            if ( ! isset( $grouped[ $user_id ] ) ) {
                $grouped[ $user_id ] = [
                    'chat_id' => (string) $row['telegram_chat_id'],
                    'nodes' => [],
                ];
            }
            $grouped[ $user_id ]['nodes'][] = $row;
        }

        foreach ( $grouped as $user_id => $data ) {
            $chat_id = $data['chat_id'];
            if ( $chat_id === '' ) {
                continue;
            }

            $pending = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$intents_table} WHERE wp_user_id = %d AND status = 'pending' AND expires_at >= %s LIMIT 1",
                    $user_id,
                    wp_date( 'Y-m-d H:i:s', $now )
                )
            );
            if ( $pending ) {
                continue;
            }

            $nodes = [];
            $total = 0.0;
            foreach ( $data['nodes'] as $row ) {
                $product_id = isset( $row['product_id'] ) ? (int) $row['product_id'] : 0;
                if ( $product_id <= 0 ) {
                    $product_id = self::resolve_product_id_from_node_type( $row['node_type'] );
                }
                $price = self::get_product_price_for_user( $product_id, $user_id );
                if ( $price <= 0 ) {
                    continue;
                }
                $total += $price;
                $nodes[] = [
                    'node_id' => $row['node_id'],
                    'node_type' => $row['node_type'],
                    'due_date' => $row['due_date'],
                    'price' => $price,
                ];
            }

            if ( empty( $nodes ) ) {
                continue;
            }

            $wallet_balance = (float) woo_wallet()->wallet->get_wallet_balance( $user_id, 'edit' );
            $currency = get_woocommerce_currency();

            $node_ids = array_map( function ( $node ) { return $node['node_id']; }, $nodes );
            $created_at = wp_date( 'Y-m-d H:i:s', $now );
            $expires_at = wp_date( 'Y-m-d H:i:s', $now + DAY_IN_SECONDS );

            $wpdb->insert(
                $intents_table,
                [
                    'wp_user_id' => $user_id,
                    'telegram_chat_id' => $chat_id,
                    'node_ids' => wp_json_encode( $node_ids ),
                    'total_amount' => $total,
                    'currency' => $currency,
                    'status' => 'pending',
                    'created_at' => $created_at,
                    'expires_at' => $expires_at,
                ],
                [ '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s' ]
            );

            $intent_id = (int) $wpdb->insert_id;
            if ( $intent_id <= 0 ) {
                continue;
            }

            $balance_text = self::format_money( $wallet_balance, $user_id );
            $total_text = self::format_money( $total, $user_id );

            $lines = [
                'Hello, your due date is tomorrow, your wallet balance is ' . $balance_text . '.',
                'Total to charge: ' . $total_text . '.',
                'Do you want to be charged for nodes?',
            ];

            foreach ( $nodes as $node ) {
                $label = $node['node_type'] !== '' ? $node['node_type'] : $node['node_id'];
                $due = wp_date( 'Y-m-d', strtotime( $node['due_date'] ) );
                $lines[] = '- ' . $label . ' — due ' . $due;
            }

            if ( $wallet_balance < $total ) {
                $lines[] = 'Your balance is low. You can add funds in My Wallet.';
            }

            $message = implode( "\n", $lines );
            self::send_telegram_prompt( $chat_id, $message, $intent_id );
        }
    }

    public static function handle_charge( WP_REST_Request $request ) {
        $params = $request->get_json_params();
        if ( ! is_array( $params ) ) {
            $params = $request->get_body_params();
        }

        $intent_id = isset( $params['intent_id'] ) ? (int) $params['intent_id'] : 0;
        $action = isset( $params['action'] ) ? sanitize_text_field( (string) $params['action'] ) : '';

        if ( $intent_id <= 0 || ( $action !== 'approve' && $action !== 'decline' ) ) {
            return new WP_Error( 'nodesplus_telegram_invalid', 'Invalid request.', [ 'status' => 400 ] );
        }

        self::ensure_table( self::intents_table(), [ __CLASS__, 'create_table' ] );

        global $wpdb;
        $table = self::intents_table();
        $intent = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d",
                $intent_id
            ),
            ARRAY_A
        );

        if ( ! $intent || $intent['status'] !== 'pending' ) {
            return new WP_Error( 'nodesplus_telegram_invalid', 'Intent not available.', [ 'status' => 400 ] );
        }

        $now = wp_date( 'Y-m-d H:i:s', current_time( 'timestamp' ) );
        if ( $intent['expires_at'] < $now ) {
            $wpdb->update(
                $table,
                [ 'status' => 'expired', 'responded_at' => $now, 'response' => 'expired' ],
                [ 'id' => $intent_id ],
                [ '%s', '%s', '%s' ],
                [ '%d' ]
            );
            return new WP_Error( 'nodesplus_telegram_invalid', 'Intent expired.', [ 'status' => 400 ] );
        }

        if ( $action === 'decline' ) {
            $wpdb->update(
                $table,
                [ 'status' => 'declined', 'responded_at' => $now, 'response' => 'declined' ],
                [ 'id' => $intent_id ],
                [ '%s', '%s', '%s' ],
                [ '%d' ]
            );
            return new WP_REST_Response( [ 'status' => 'declined' ], 200 );
        }

        if ( ! function_exists( 'woo_wallet' ) ) {
            return new WP_Error( 'nodesplus_wallet_missing', 'Wallet not available.', [ 'status' => 500 ] );
        }

        $user_id = (int) $intent['wp_user_id'];
        $amount = (float) $intent['total_amount'];
        $balance = (float) woo_wallet()->wallet->get_wallet_balance( $user_id, 'edit' );
        if ( $balance < $amount ) {
            return new WP_Error( 'nodesplus_wallet_insufficient', 'Insufficient wallet balance.', [ 'status' => 400 ] );
        }

        $note = 'Nodes renewal charge (Telegram approval).';
        $transaction_id = woo_wallet()->wallet->debit( $user_id, $amount, $note, [ 'currency' => $intent['currency'] ] );
        if ( ! $transaction_id ) {
            return new WP_Error( 'nodesplus_wallet_debit', 'Unable to debit wallet.', [ 'status' => 500 ] );
        }

        $wpdb->update(
            $table,
            [ 'status' => 'charged', 'responded_at' => $now, 'response' => 'approved' ],
            [ 'id' => $intent_id ],
            [ '%s', '%s', '%s' ],
            [ '%d' ]
        );

        return new WP_REST_Response( [ 'status' => 'charged', 'amount' => $amount ], 200 );
    }
}
