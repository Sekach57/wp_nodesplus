<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class INCR_Nodes_Admin {
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu']);
        add_action('admin_post_incr_refresh_user_nodes', [__CLASS__, 'handle_refresh_user_nodes']);
        add_action('admin_post_incr_sync_all_nodes', [__CLASS__, 'handle_sync_all_nodes']);
        add_action('admin_post_incr_save_nodes_columns', [__CLASS__, 'handle_save_nodes_columns']);
        add_action('incr_sync_all_nodes_job', [__CLASS__, 'run_sync_all_nodes']);
        add_action('admin_post_incr_send_tg_message', [__CLASS__, 'handle_send_tg_message']);
        add_action('admin_post_incr_export_revenue', [__CLASS__, 'handle_export_revenue']);
        add_action('admin_post_incr_send_project_update', [__CLASS__, 'handle_send_project_update']);
        add_action('edit_user_profile', [__CLASS__, 'render_user_profile_section']);
        add_action('show_user_profile', [__CLASS__, 'render_user_profile_section']);

        // Schedule twice daily sync if not already scheduled
        if (!wp_next_scheduled('incr_sync_all_nodes_job')) {
            wp_schedule_event(time(), 'twicedaily', 'incr_sync_all_nodes_job');
        }
    }

    public static function add_menu() {
        add_menu_page(
            'NodesPlus Overview',
            'NodesPlus',
            'manage_options',
            'incr-nodes-overview',
            [__CLASS__, 'render_overview'],
            'dashicons-networking',
            56
        );
        add_submenu_page(
            'incr-nodes-overview',
            'Overview',
            'Overview',
            'manage_options',
            'incr-nodes-overview',
            [__CLASS__, 'render_overview']
        );
        add_submenu_page(
            'incr-nodes-overview',
            'Nodes Dashboard',
            'Nodes Dashboard',
            'manage_options',
            'incr-nodes-dashboard',
            [__CLASS__, 'render_dashboard']
        );
        add_submenu_page(
            'incr-nodes-overview',
            'Telegram Links',
            'Telegram Links',
            'manage_options',
            'incr-telegram-links',
            [__CLASS__, 'render_telegram_links']
        );
        add_submenu_page(
            'incr-nodes-overview',
            'TG Notifications',
            'TG Notifications',
            'manage_options',
            'incr-tg-notifications',
            [__CLASS__, 'render_telegram_notifications']
        );
        add_submenu_page(
            'incr-nodes-overview',
            'Send TG Message',
            'Send TG Message',
            'manage_options',
            'incr-send-tg-message',
            [__CLASS__, 'render_send_tg_message']
        );
        add_submenu_page(
            'incr-nodes-overview',
            'Project Updates',
            'Project Updates',
            'manage_options',
            'incr-project-updates',
            [__CLASS__, 'render_project_updates']
        );
        add_submenu_page(
            'incr-nodes-overview',
            'Report',
            'Report',
            'manage_options',
            'incr-nodes-report',
            ['INCR_Nodes_Report', 'render_report']
        );
    }

    public static function handle_refresh_user_nodes() {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied');
        }

        if (empty($_POST['incr_nodes_refresh_nonce']) || !wp_verify_nonce($_POST['incr_nodes_refresh_nonce'], 'incr_nodes_refresh')) {
            wp_die('Invalid nonce');
        }

        $user_id = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
        if ($user_id <= 0) {
            wp_safe_redirect(add_query_arg('incr_notice', 'missing_user', wp_get_referer() ?: admin_url('admin.php?page=incr-nodes-dashboard')));
            exit;
        }

        if (function_exists('getNodesByUserID')) {
            $nodes = getNodesByUserID($user_id, true);
            $count = is_array($nodes) ? count($nodes) : 0;
            $db_count = 0;
            if (class_exists('INCR_Nodes_Store')) {
                if (is_array($nodes) && !isset($nodes['detail'])) {
                    INCR_Nodes_Store::upsert_nodes($user_id, $nodes, 'api');
                }
                $db_result = INCR_Nodes_Store::get_all_nodes([
                    'user_id' => $user_id,
                    'page' => 1,
                    'per_page' => 1,
                ]);
                $db_count = isset($db_result['total']) ? (int) $db_result['total'] : 0;
            }
            $notice = 'refreshed_' . $count . '_' . $db_count;
        } else {
            $notice = 'missing_fetch';
        }

        $redirect = admin_url('admin.php?page=incr-nodes-dashboard');
        $redirect = add_query_arg('user_id', $user_id, $redirect);
        $redirect = add_query_arg('incr_notice', $notice, $redirect);
        wp_safe_redirect($redirect);
        exit;
    }

    public static function handle_sync_all_nodes() {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied');
        }

        if (empty($_POST['incr_nodes_sync_all_nonce']) || !wp_verify_nonce($_POST['incr_nodes_sync_all_nonce'], 'incr_nodes_sync_all')) {
            wp_die('Invalid nonce');
        }

        if (!function_exists('getNodesByUserID')) {
            $redirect = add_query_arg('incr_notice', 'sync_all_missing_fetch', admin_url('admin.php?page=incr-nodes-dashboard'));
            wp_safe_redirect($redirect);
            exit;
        }

        $result = self::run_sync_all_nodes();
        $notice = 'sync_all_complete_0_0_0';
        if (is_array($result)) {
            $notice = 'sync_all_complete_' . (int) $result['users_synced'] . '_' . (int) $result['api_rows'] . '_' . (int) $result['db_rows'];
        }

        $redirect = add_query_arg('incr_notice', $notice, admin_url('admin.php?page=incr-nodes-dashboard'));
        if (!headers_sent()) {
            wp_safe_redirect($redirect);
            exit;
        }
        echo '<script>window.location.href=' . wp_json_encode($redirect) . ';</script>';
        exit;
    }

    public static function run_sync_all_nodes() {
        if (!function_exists('getNodesByUserID')) {
            return null;
        }

        @set_time_limit(0);
        $users = get_users(['fields' => 'ID']);
        $users_synced = 0;
        $api_rows = 0;
        $db_rows = 0;

        foreach ($users as $user_id) {
            $nodes = getNodesByUserID((int) $user_id, true);
            $api_rows += is_array($nodes) ? count($nodes) : 0;
            if (class_exists('INCR_Nodes_Store')) {
                if (is_array($nodes) && !isset($nodes['detail'])) {
                    INCR_Nodes_Store::upsert_nodes((int) $user_id, $nodes, 'api');
                } elseif (is_array($nodes) && isset($nodes['detail'])) {
                    // API says no nodes — archive all existing nodes for this user
                    INCR_Nodes_Store::archive_all_user_nodes((int) $user_id);
                }
                $db_rows += count(INCR_Nodes_Store::get_nodes_by_user((int) $user_id));
            }
            $users_synced++;
        }

        set_transient(
            'incr_sync_all_last_result',
            [
                'users_synced' => $users_synced,
                'api_rows' => $api_rows,
                'db_rows' => $db_rows,
            ],
            HOUR_IN_SECONDS
        );

        return [
            'users_synced' => $users_synced,
            'api_rows' => $api_rows,
            'db_rows' => $db_rows,
        ];
    }

    public static function handle_save_nodes_columns() {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied');
        }

        if (empty($_POST['incr_nodes_columns_nonce']) || !wp_verify_nonce($_POST['incr_nodes_columns_nonce'], 'incr_nodes_columns')) {
            wp_die('Invalid nonce');
        }

        $all_columns = self::columns();
        $selected = isset($_POST['columns']) && is_array($_POST['columns']) ? array_map('sanitize_text_field', wp_unslash($_POST['columns'])) : [];
        $selected = array_values(array_intersect(array_keys($all_columns), $selected));
        $hidden = array_values(array_diff(array_keys($all_columns), $selected));

        update_user_meta(get_current_user_id(), 'incr_nodes_hidden_columns', $hidden);

        $redirect = admin_url('admin.php?page=incr-nodes-dashboard');
        wp_safe_redirect($redirect);
        exit;
    }

    private static function columns() {
        return [
            'user_id' => 'User ID',
            'user_email' => 'User Email',
            'node_id' => 'Node ID',
            'node_type' => 'Node Type',
            'status' => 'Status',
            'due_date' => 'Due date',
            'created_at' => 'Created at',
            'product_id' => 'Product ID',
            'synced_at' => 'Synced at',
            'source' => 'Source',
            'actions' => 'Actions',
        ];
    }

    /* ─── Overview page ─── */

    public static function render_overview() {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied');
        }

        self::render_overview_styles();

        echo '<div class="wrap incr-overview">';
        echo '<h1>NodesPlus Overview</h1>';

        // A. Summary cards
        self::render_overview_summary();

        // B. Revenue (current month)
        self::render_overview_revenue();

        // C + D. Two-column: Expiring Soon + Recent Orders
        echo '<div class="incr-ov-columns">';
        self::render_overview_expiring();
        self::render_overview_recent_orders();
        echo '</div>';

        // E. User lookup
        self::render_overview_user_lookup();

        echo '</div>';
    }

    private static function render_overview_styles() {
        ?>
        <style>
        .incr-overview { max-width: 1400px; }
        .incr-ov-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin: 20px 0;
        }
        .incr-ov-card {
            background: #fff;
            border: 1px solid #c3c4c7;
            border-left: 4px solid #2271b1;
            padding: 16px 20px;
            border-radius: 0 4px 4px 0;
        }
        .incr-ov-card .incr-ov-card__icon {
            font-size: 20px;
            margin-right: 8px;
            vertical-align: middle;
        }
        .incr-ov-card .incr-ov-card__label {
            font-size: 13px;
            color: #50575e;
            display: block;
            margin-bottom: 4px;
        }
        .incr-ov-card .incr-ov-card__value {
            font-size: 28px;
            font-weight: 600;
            color: #1d2327;
        }
        .incr-ov-card--green  { border-left-color: #00a32a; }
        .incr-ov-card--yellow { border-left-color: #dba617; }
        .incr-ov-card--red    { border-left-color: #d63638; }
        .incr-ov-card--gray   { border-left-color: #787c82; }

        .incr-ov-revenue {
            background: #fff;
            border: 1px solid #c3c4c7;
            padding: 16px 20px;
            margin: 0 0 20px;
        }
        .incr-ov-revenue h2 { margin-top: 0; }
        .incr-ov-revenue-numbers {
            display: flex;
            gap: 32px;
            flex-wrap: wrap;
        }
        .incr-ov-revenue-numbers .incr-ov-rev-item span {
            display: block;
            font-size: 13px;
            color: #50575e;
        }
        .incr-ov-revenue-numbers .incr-ov-rev-item strong {
            font-size: 22px;
        }

        .incr-ov-columns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        @media (max-width: 1200px) {
            .incr-ov-columns { grid-template-columns: 1fr; }
        }

        .incr-ov-lookup {
            background: #fff;
            border: 1px solid #c3c4c7;
            padding: 16px 20px;
            margin-bottom: 20px;
        }
        .incr-ov-lookup h2 { margin-top: 0; }
        .incr-ov-lookup-form {
            display: flex;
            gap: 12px;
            align-items: flex-end;
            margin-bottom: 16px;
        }
        .incr-ov-node-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 12px;
        }
        .incr-ov-node-card {
            background: #f6f7f7;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            padding: 12px 16px;
        }
        .incr-ov-node-card__header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        .incr-ov-node-card__type {
            font-weight: 600;
            font-size: 14px;
        }
        .incr-ov-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
            color: #fff;
        }
        .incr-ov-badge--active  { background: #00a32a; }
        .incr-ov-badge--overdue { background: #d63638; }
        .incr-ov-node-card__meta {
            font-size: 12px;
            color: #50575e;
            margin-bottom: 8px;
        }
        .incr-ov-progress-wrap {
            background: #dcdcde;
            border-radius: 3px;
            height: 6px;
            overflow: hidden;
        }
        .incr-ov-progress-bar {
            height: 100%;
            border-radius: 3px;
            background: #00a32a;
            transition: width .3s;
        }
        .incr-ov-progress-bar--overdue { background: #d63638; }

        /* Revenue filters */
        .incr-ov-revenue-filters {
            display: flex;
            gap: 12px;
            align-items: flex-end;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }
        .incr-ov-revenue-filters label {
            font-size: 13px;
            color: #50575e;
        }
        .incr-ov-revenue-filters input[type="date"] {
            padding: 4px 8px;
        }
        .incr-ov-revenue-presets {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
        }
        .incr-ov-revenue-presets a {
            padding: 4px 12px;
            background: #f0f0f1;
            border: 1px solid #c3c4c7;
            border-radius: 3px;
            text-decoration: none;
            font-size: 12px;
            color: #2271b1;
        }
        .incr-ov-revenue-presets a:hover,
        .incr-ov-revenue-presets a.active {
            background: #2271b1;
            color: #fff;
            border-color: #2271b1;
        }

        /* Revenue summary cards */
        .incr-ov-rev-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }
        .incr-ov-rev-card {
            background: #f6f7f7;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            padding: 12px 16px;
            text-align: center;
        }
        .incr-ov-rev-card__label {
            font-size: 12px;
            color: #50575e;
            display: block;
            margin-bottom: 4px;
        }
        .incr-ov-rev-card__value {
            font-size: 24px;
            font-weight: 600;
            color: #1d2327;
            display: block;
        }

        /* Growth indicators */
        .incr-ov-growth {
            display: inline-block;
            font-size: 12px;
            font-weight: 600;
            margin-top: 4px;
        }
        .incr-ov-growth--up { color: #00a32a; }
        .incr-ov-growth--up::before { content: "\25B2 "; font-size: 10px; }
        .incr-ov-growth--down { color: #d63638; }
        .incr-ov-growth--down::before { content: "\25BC "; font-size: 10px; }
        .incr-ov-growth--flat { color: #787c82; }
        .incr-ov-growth--flat::before { content: "\2014 "; }

        /* Bar chart */
        .incr-ov-chart {
            display: flex;
            align-items: flex-end;
            gap: 4px;
            height: 120px;
            margin-bottom: 20px;
            padding: 0 4px;
        }
        .incr-ov-chart-col {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            height: 100%;
            min-width: 0;
        }
        .incr-ov-chart-bar {
            flex: 1;
            width: 100%;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            min-height: 0;
        }
        .incr-ov-chart-bar__fill--purchase {
            background: #2271b1;
            border-radius: 3px 3px 0 0;
        }
        .incr-ov-chart-bar__fill--renewal {
            background: #00a32a;
        }
        .incr-ov-chart-bar__fill--purchase + .incr-ov-chart-bar__fill--renewal {
            border-radius: 0;
        }
        .incr-ov-chart-bar__fill--purchase:last-child {
            border-radius: 3px 3px 0 0;
        }
        .incr-ov-chart-label {
            font-size: 10px;
            color: #50575e;
            margin-top: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
            text-align: center;
        }
        .incr-ov-chart-legend {
            display: flex;
            gap: 16px;
            margin-bottom: 8px;
            font-size: 12px;
            color: #50575e;
        }
        .incr-ov-chart-legend span::before {
            content: "";
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 2px;
            margin-right: 4px;
            vertical-align: middle;
        }
        .incr-ov-chart-legend .legend-purchase::before { background: #2271b1; }
        .incr-ov-chart-legend .legend-renewal::before { background: #00a32a; }
        </style>
        <?php
    }

    private static function render_overview_summary() {
        $status_counts = class_exists('INCR_Nodes_Store') ? INCR_Nodes_Store::get_status_counts() : ['active' => 0, 'overdue' => 0];
        $expiring = class_exists('INCR_Nodes_Store') ? INCR_Nodes_Store::get_expiring_count(7) : 0;
        $users = class_exists('INCR_Nodes_Store') ? INCR_Nodes_Store::get_distinct_user_count() : 0;
        $total = $status_counts['active'] + $status_counts['overdue'];

        $dashboard_url = admin_url('admin.php?page=incr-nodes-dashboard');

        echo '<div class="incr-ov-cards">';

        // Total Nodes
        echo '<div class="incr-ov-card">';
        echo '<span class="incr-ov-card__label"><span class="dashicons dashicons-networking incr-ov-card__icon"></span>Total Nodes</span>';
        echo '<span class="incr-ov-card__value">' . esc_html($total) . '</span>';
        echo '</div>';

        // Active
        echo '<a href="' . esc_url(add_query_arg('status', 'active', $dashboard_url)) . '" class="incr-ov-card incr-ov-card--green" style="text-decoration:none;">';
        echo '<span class="incr-ov-card__label"><span class="dashicons dashicons-yes-alt incr-ov-card__icon"></span>Active</span>';
        echo '<span class="incr-ov-card__value">' . esc_html($status_counts['active']) . '</span>';
        echo '</a>';

        // Expiring Soon
        $expiring_url = add_query_arg([
            'status' => 'active',
            'due_from' => current_time('Y-m-d'),
            'due_to' => date('Y-m-d', strtotime('+7 days', strtotime(current_time('Y-m-d')))),
        ], $dashboard_url);
        echo '<a href="' . esc_url($expiring_url) . '" class="incr-ov-card incr-ov-card--yellow" style="text-decoration:none;">';
        echo '<span class="incr-ov-card__label"><span class="dashicons dashicons-warning incr-ov-card__icon"></span>Expiring Soon</span>';
        echo '<span class="incr-ov-card__value">' . esc_html($expiring) . '</span>';
        echo '</a>';

        // Expired
        echo '<a href="' . esc_url(add_query_arg('status', 'overdue', $dashboard_url)) . '" class="incr-ov-card incr-ov-card--red" style="text-decoration:none;">';
        echo '<span class="incr-ov-card__label"><span class="dashicons dashicons-dismiss incr-ov-card__icon"></span>Expired</span>';
        echo '<span class="incr-ov-card__value">' . esc_html($status_counts['overdue']) . '</span>';
        echo '</a>';

        // Users with Nodes
        echo '<div class="incr-ov-card incr-ov-card--gray">';
        echo '<span class="incr-ov-card__label"><span class="dashicons dashicons-groups incr-ov-card__icon"></span>Users with Nodes</span>';
        echo '<span class="incr-ov-card__value">' . esc_html($users) . '</span>';
        echo '</div>';

        echo '</div>';
    }

    private static function get_revenue_data($from, $to) {
        if (!function_exists('wc_get_orders')) {
            return ['months' => [], 'totals' => ['orders' => 0, 'total' => 0.0, 'purchases' => 0.0, 'renewals' => 0.0]];
        }

        $orders = wc_get_orders([
            'status' => ['wc-completed', 'wc-processing'],
            'date_created' => $from . '...' . $to,
            'limit' => -1,
        ]);

        $months = [];
        $order_ids_by_month = [];

        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                $product_name = $item->get_name();
                if (stripos($product_name, 'Wallet Topup') !== false) {
                    continue;
                }

                $date_created = $order->get_date_created();
                if (!$date_created) {
                    continue;
                }
                $month_key = $date_created->date('Y-m');
                $line_total = (float) $item->get_total();
                $op_type = $item->get_meta('Operation Type');
                $is_renewal = ($op_type === 'Prolongation');

                if (!isset($months[$month_key])) {
                    $months[$month_key] = ['total' => 0.0, 'purchases' => 0.0, 'renewals' => 0.0, 'orders' => 0];
                    $order_ids_by_month[$month_key] = [];
                }

                $months[$month_key]['total'] += $line_total;
                if ($is_renewal) {
                    $months[$month_key]['renewals'] += $line_total;
                } else {
                    $months[$month_key]['purchases'] += $line_total;
                }

                $order_id = $order->get_id();
                if (!in_array($order_id, $order_ids_by_month[$month_key], true)) {
                    $order_ids_by_month[$month_key][] = $order_id;
                    $months[$month_key]['orders']++;
                }
            }
        }

        // Fill gaps with zero months
        $start = new \DateTime($from . '-01');
        $end = new \DateTime($to);
        $end->modify('first day of this month');
        $interval = new \DateInterval('P1M');
        $period = new \DatePeriod($start, $interval, $end->modify('+1 month'));

        $filled = [];
        foreach ($period as $dt) {
            $key = $dt->format('Y-m');
            $label = $dt->format('F Y');
            $data = isset($months[$key]) ? $months[$key] : ['total' => 0.0, 'purchases' => 0.0, 'renewals' => 0.0, 'orders' => 0];
            $data['label'] = $label;
            $filled[$key] = $data;
        }

        $totals = ['orders' => 0, 'total' => 0.0, 'purchases' => 0.0, 'renewals' => 0.0];
        foreach ($filled as $m) {
            $totals['orders'] += $m['orders'];
            $totals['total'] += $m['total'];
            $totals['purchases'] += $m['purchases'];
            $totals['renewals'] += $m['renewals'];
        }

        return ['months' => $filled, 'totals' => $totals];
    }

    private static function calc_growth($current, $previous) {
        if ($previous == 0 && $current > 0) {
            return ['value' => 0, 'direction' => 'up', 'label' => 'New'];
        }
        if ($previous == 0 && $current == 0) {
            return ['value' => 0, 'direction' => 'flat', 'label' => '0%'];
        }
        $pct = round((($current - $previous) / $previous) * 100, 1);
        if ($pct > 0) {
            return ['value' => $pct, 'direction' => 'up', 'label' => '+' . $pct . '%'];
        } elseif ($pct < 0) {
            return ['value' => $pct, 'direction' => 'down', 'label' => $pct . '%'];
        }
        return ['value' => 0, 'direction' => 'flat', 'label' => '0%'];
    }

    private static function render_overview_revenue() {
        echo '<div class="incr-ov-revenue postbox">';
        echo '<h2>Revenue</h2>';

        if (!function_exists('wc_get_orders')) {
            echo '<p>WooCommerce not available. No revenue data.</p>';
            echo '</div>';
            return;
        }

        $overview_url = admin_url('admin.php?page=incr-nodes-overview');

        // Read date params with defaults (current month)
        $default_from = date('Y-m-01');
        $default_to = date('Y-m-t');
        $rev_from = isset($_GET['rev_from']) ? sanitize_text_field(wp_unslash($_GET['rev_from'])) : $default_from;
        $rev_to = isset($_GET['rev_to']) ? sanitize_text_field(wp_unslash($_GET['rev_to'])) : $default_to;

        // Validate dates
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $rev_from)) {
            $rev_from = $default_from;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $rev_to)) {
            $rev_to = $default_to;
        }

        // A) Date range form
        echo '<form method="get" class="incr-ov-revenue-filters">';
        echo '<input type="hidden" name="page" value="incr-nodes-overview" />';
        // Preserve lookup param if present
        $lookup = isset($_GET['lookup']) ? sanitize_text_field(wp_unslash($_GET['lookup'])) : '';
        if ($lookup !== '') {
            echo '<input type="hidden" name="lookup" value="' . esc_attr($lookup) . '" />';
        }
        echo '<label>From<br><input type="date" name="rev_from" value="' . esc_attr($rev_from) . '"></label>';
        echo '<label>To<br><input type="date" name="rev_to" value="' . esc_attr($rev_to) . '"></label>';
        echo '<button class="button button-primary" style="margin-bottom:1px;">Apply</button>';
        echo '</form>';

        // Quick presets
        $this_month_from = date('Y-m-01');
        $this_month_to = date('Y-m-t');
        $three_months_from = date('Y-m-01', strtotime('-2 months'));
        $three_months_to = date('Y-m-t');
        $this_year_from = date('Y-01-01');
        $this_year_to = date('Y-m-t');

        $presets = [
            'This Month' => [$this_month_from, $this_month_to],
            'Last 3 Months' => [$three_months_from, $three_months_to],
            'This Year' => [$this_year_from, $this_year_to],
        ];

        echo '<div class="incr-ov-revenue-presets">';
        foreach ($presets as $label => $dates) {
            $url = add_query_arg(['page' => 'incr-nodes-overview', 'rev_from' => $dates[0], 'rev_to' => $dates[1]], admin_url('admin.php'));
            if ($lookup !== '') {
                $url = add_query_arg('lookup', $lookup, $url);
            }
            $active = ($rev_from === $dates[0] && $rev_to === $dates[1]) ? ' active' : '';
            echo '<a href="' . esc_url($url) . '" class="' . esc_attr($active) . '">' . esc_html($label) . '</a>';
        }
        echo '</div>';

        // Get data for selected period
        $data = self::get_revenue_data($rev_from, $rev_to);
        $totals = $data['totals'];
        $months = $data['months'];

        // Calculate comparison period (same length, immediately before)
        $from_ts = strtotime($rev_from);
        $to_ts = strtotime($rev_to);
        $period_days = ($to_ts - $from_ts) / 86400;
        $prev_to = date('Y-m-d', $from_ts - 86400);
        $prev_from = date('Y-m-d', strtotime($prev_to) - (int) $period_days * 86400);
        $prev_data = self::get_revenue_data($prev_from, $prev_to);
        $prev_totals = $prev_data['totals'];

        $growth_total = self::calc_growth($totals['total'], $prev_totals['total']);
        $growth_purchases = self::calc_growth($totals['purchases'], $prev_totals['purchases']);
        $growth_renewals = self::calc_growth($totals['renewals'], $prev_totals['renewals']);
        $growth_orders = self::calc_growth($totals['orders'], $prev_totals['orders']);

        // B) Summary cards
        echo '<div class="incr-ov-rev-cards">';

        $cards = [
            ['label' => 'Total Revenue', 'value' => wc_price($totals['total']), 'growth' => $growth_total],
            ['label' => 'Purchases', 'value' => wc_price($totals['purchases']), 'growth' => $growth_purchases],
            ['label' => 'Renewals', 'value' => wc_price($totals['renewals']), 'growth' => $growth_renewals],
            ['label' => 'Orders', 'value' => number_format_i18n($totals['orders']), 'growth' => $growth_orders],
        ];

        foreach ($cards as $card) {
            echo '<div class="incr-ov-rev-card">';
            echo '<span class="incr-ov-rev-card__label">' . esc_html($card['label']) . '</span>';
            echo '<span class="incr-ov-rev-card__value">' . wp_kses_post($card['value']) . '</span>';
            echo '<span class="incr-ov-growth incr-ov-growth--' . esc_attr($card['growth']['direction']) . '">' . esc_html($card['growth']['label']) . '</span>';
            echo '</div>';
        }

        echo '</div>';

        // C) Bar chart
        if (!empty($months)) {
            $max_total = 0;
            foreach ($months as $m) {
                if ($m['total'] > $max_total) {
                    $max_total = $m['total'];
                }
            }

            echo '<div class="incr-ov-chart-legend">';
            echo '<span class="legend-purchase">Purchases</span>';
            echo '<span class="legend-renewal">Renewals</span>';
            echo '</div>';

            echo '<div class="incr-ov-chart">';
            foreach ($months as $key => $m) {
                $pct_purchase = $max_total > 0 ? ($m['purchases'] / $max_total) * 100 : 0;
                $pct_renewal = $max_total > 0 ? ($m['renewals'] / $max_total) * 100 : 0;
                $short_label = date('M', strtotime($key . '-01'));
                $title_text = $m['label'] . ': Total ' . number_format($m['total'], 2) . ', Purchases ' . number_format($m['purchases'], 2) . ', Renewals ' . number_format($m['renewals'], 2);

                echo '<div class="incr-ov-chart-col" title="' . esc_attr($title_text) . '">';
                echo '<div class="incr-ov-chart-bar">';
                if ($pct_purchase > 0) {
                    echo '<div class="incr-ov-chart-bar__fill--purchase" style="height:' . esc_attr(round($pct_purchase, 1)) . '%;"></div>';
                }
                if ($pct_renewal > 0) {
                    echo '<div class="incr-ov-chart-bar__fill--renewal" style="height:' . esc_attr(round($pct_renewal, 1)) . '%;"></div>';
                }
                if ($pct_purchase == 0 && $pct_renewal == 0) {
                    echo '<div style="height:1px; background:#dcdcde;"></div>';
                }
                echo '</div>';
                echo '<div class="incr-ov-chart-label">' . esc_html($short_label) . '</div>';
                echo '</div>';
            }
            echo '</div>';
        }

        // D) Monthly breakdown table
        if (!empty($months)) {
            echo '<table class="widefat striped" style="margin-bottom:16px;">';
            echo '<thead><tr><th>Month</th><th>Orders</th><th>Total</th><th>Purchases</th><th>Renewals</th><th>Change</th></tr></thead>';
            echo '<tbody>';

            $prev_month_total = null;
            foreach ($months as $m) {
                $change_html = '';
                if ($prev_month_total !== null) {
                    $g = self::calc_growth($m['total'], $prev_month_total);
                    $change_html = '<span class="incr-ov-growth incr-ov-growth--' . esc_attr($g['direction']) . '">' . esc_html($g['label']) . '</span>';
                } else {
                    $change_html = '<span class="incr-ov-growth incr-ov-growth--flat">&mdash;</span>';
                }

                echo '<tr>';
                echo '<td>' . esc_html($m['label']) . '</td>';
                echo '<td>' . esc_html($m['orders']) . '</td>';
                echo '<td>' . wp_kses_post(wc_price($m['total'])) . '</td>';
                echo '<td>' . wp_kses_post(wc_price($m['purchases'])) . '</td>';
                echo '<td>' . wp_kses_post(wc_price($m['renewals'])) . '</td>';
                echo '<td>' . $change_html . '</td>';
                echo '</tr>';

                $prev_month_total = $m['total'];
            }

            // Footer totals
            echo '<tr style="font-weight:700; background:#f0f0f1;">';
            echo '<td>Total</td>';
            echo '<td>' . esc_html($totals['orders']) . '</td>';
            echo '<td>' . wp_kses_post(wc_price($totals['total'])) . '</td>';
            echo '<td>' . wp_kses_post(wc_price($totals['purchases'])) . '</td>';
            echo '<td>' . wp_kses_post(wc_price($totals['renewals'])) . '</td>';
            echo '<td></td>';
            echo '</tr>';

            echo '</tbody></table>';
        }

        // E) Export CSV button
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:8px;">';
        echo '<input type="hidden" name="action" value="incr_export_revenue" />';
        echo wp_nonce_field('incr_export_revenue', 'incr_export_revenue_nonce', true, false);
        echo '<input type="hidden" name="rev_from" value="' . esc_attr($rev_from) . '" />';
        echo '<input type="hidden" name="rev_to" value="' . esc_attr($rev_to) . '" />';
        echo '<button class="button button-secondary">Export CSV</button>';
        echo '</form>';

        echo '</div>';
    }

    public static function handle_export_revenue() {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied');
        }

        if (empty($_POST['incr_export_revenue_nonce']) || !wp_verify_nonce($_POST['incr_export_revenue_nonce'], 'incr_export_revenue')) {
            wp_die('Invalid nonce');
        }

        $rev_from = isset($_POST['rev_from']) ? sanitize_text_field(wp_unslash($_POST['rev_from'])) : date('Y-m-01');
        $rev_to = isset($_POST['rev_to']) ? sanitize_text_field(wp_unslash($_POST['rev_to'])) : date('Y-m-t');

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $rev_from)) {
            $rev_from = date('Y-m-01');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $rev_to)) {
            $rev_to = date('Y-m-t');
        }

        $data = self::get_revenue_data($rev_from, $rev_to);
        $months = $data['months'];
        $totals = $data['totals'];

        $filename = 'nodesplus-revenue-' . $rev_from . '-to-' . $rev_to . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        // UTF-8 BOM for Excel
        echo "\xEF\xBB\xBF";

        $out = fopen('php://output', 'w');
        fputcsv($out, ['Month', 'Orders', 'Total Revenue', 'Purchases', 'Renewals', 'Change %']);

        $prev_total = null;
        foreach ($months as $m) {
            $change = '';
            if ($prev_total !== null) {
                $g = self::calc_growth($m['total'], $prev_total);
                $change = $g['label'];
            }

            fputcsv($out, [
                $m['label'],
                $m['orders'],
                number_format($m['total'], 2, '.', ''),
                number_format($m['purchases'], 2, '.', ''),
                number_format($m['renewals'], 2, '.', ''),
                $change,
            ]);

            $prev_total = $m['total'];
        }

        // Totals row
        fputcsv($out, [
            'TOTAL',
            $totals['orders'],
            number_format($totals['total'], 2, '.', ''),
            number_format($totals['purchases'], 2, '.', ''),
            number_format($totals['renewals'], 2, '.', ''),
            '',
        ]);

        fclose($out);
        exit;
    }

    private static function render_overview_expiring() {
        echo '<div class="postbox" style="padding: 12px 20px;">';
        echo '<h2 style="margin-top:0;">Expiring Soon (7 days)</h2>';

        if (!class_exists('INCR_Nodes_Store')) {
            echo '<p>No data.</p></div>';
            return;
        }

        $today = current_time('Y-m-d');
        $in_7_days = date('Y-m-d', strtotime('+7 days', strtotime($today)));

        $result = INCR_Nodes_Store::get_all_nodes([
            'status' => 'active',
            'due_date_from' => $today,
            'due_date_to' => $in_7_days,
            'orderby' => 'due_date',
            'order' => 'ASC',
            'per_page' => 10,
            'page' => 1,
        ]);

        $items = $result['items'];

        if (empty($items)) {
            echo '<p>No nodes expiring in the next 7 days.</p></div>';
            return;
        }

        $user_cache = [];
        echo '<table class="widefat striped" style="border:0;">';
        echo '<thead><tr><th>User</th><th>Email</th><th>Node Type</th><th>Node ID</th><th>Due Date</th><th>Days Left</th></tr></thead><tbody>';

        foreach ($items as $row) {
            $uid = (int) $row['user_id'];
            if (!isset($user_cache[$uid])) {
                $user_cache[$uid] = get_user_by('id', $uid);
            }
            $user = $user_cache[$uid];
            $display_name = $user ? $user->display_name : 'User #' . $uid;
            $email = $user ? $user->user_email : '-';

            $due = $row['due_date'] ? substr($row['due_date'], 0, 10) : '-';
            $days_left = $row['due_date'] ? max(0, (int) ((strtotime($row['due_date']) - strtotime($today)) / 86400)) : '-';

            echo '<tr>';
            echo '<td>' . esc_html($display_name) . '</td>';
            echo '<td>' . esc_html($email) . '</td>';
            echo '<td>' . esc_html($row['node_type']) . '</td>';
            echo '<td style="font-size:11px;">' . esc_html($row['node_id']) . '</td>';
            echo '<td>' . esc_html($due) . '</td>';
            echo '<td>' . esc_html($days_left) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';

        if ($result['total'] > 10) {
            $url = add_query_arg([
                'page' => 'incr-nodes-dashboard',
                'status' => 'active',
                'due_from' => $today,
                'due_to' => $in_7_days,
            ], admin_url('admin.php'));
            echo '<p style="margin-bottom:0;"><a href="' . esc_url($url) . '">View all ' . esc_html($result['total']) . ' in Nodes Dashboard &rarr;</a></p>';
        }

        echo '</div>';
    }

    private static function render_overview_recent_orders() {
        echo '<div class="postbox" style="padding: 12px 20px;">';
        echo '<h2 style="margin-top:0;">Recent Node Orders</h2>';

        if (!function_exists('wc_get_orders')) {
            echo '<p>WooCommerce not available.</p></div>';
            return;
        }

        $orders = wc_get_orders([
            'status' => ['wc-completed', 'wc-processing'],
            'orderby' => 'date',
            'order' => 'DESC',
            'limit' => 20,
        ]);

        $rows = [];
        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                $product_name = $item->get_name();
                if (stripos($product_name, 'Wallet Topup') !== false) {
                    continue;
                }

                $op_type = $item->get_meta('Operation Type');
                $type_label = ($op_type === 'Prolongation') ? 'Renewal' : 'Purchase';

                $rows[] = [
                    'date' => $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i') : '-',
                    'user' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                    'product' => $product_name,
                    'type' => $type_label,
                    'total' => $item->get_total(),
                ];

                if (count($rows) >= 5) {
                    break 2;
                }
            }
        }

        if (empty($rows)) {
            echo '<p>No recent node orders.</p></div>';
            return;
        }

        echo '<table class="widefat striped" style="border:0;">';
        echo '<thead><tr><th>Date</th><th>User</th><th>Product</th><th>Type</th><th>Total</th></tr></thead><tbody>';

        foreach ($rows as $r) {
            echo '<tr>';
            echo '<td>' . esc_html($r['date']) . '</td>';
            echo '<td>' . esc_html($r['user']) . '</td>';
            echo '<td>' . esc_html($r['product']) . '</td>';
            echo '<td>' . esc_html($r['type']) . '</td>';
            echo '<td>' . wp_kses_post(wc_price($r['total'])) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    private static function render_overview_user_lookup() {
        echo '<div class="incr-ov-lookup">';
        echo '<h2>User Lookup</h2>';

        $lookup = isset($_GET['lookup']) ? sanitize_text_field(wp_unslash($_GET['lookup'])) : '';

        echo '<form method="get" class="incr-ov-lookup-form">';
        echo '<input type="hidden" name="page" value="incr-nodes-overview" />';
        echo '<label>User ID or Email<br><input type="text" name="lookup" value="' . esc_attr($lookup) . '" placeholder="e.g. 42 or user@example.com" style="width:280px;"></label>';
        echo '<button class="button button-primary">Look up</button>';
        echo '</form>';

        if ($lookup !== '') {
            $user = null;
            if (is_numeric($lookup)) {
                $user = get_user_by('id', (int) $lookup);
            }
            if (!$user) {
                $user = get_user_by('email', $lookup);
            }

            if (!$user) {
                echo '<div class="notice notice-warning" style="margin:0;"><p>User not found for: <strong>' . esc_html($lookup) . '</strong></p></div>';
                echo '</div>';
                return;
            }

            echo '<div style="margin-bottom:12px;">';
            echo '<strong>' . esc_html($user->display_name) . '</strong> ';
            echo '(' . esc_html($user->user_email) . ') ';
            echo '&mdash; ID: ' . esc_html($user->ID);
            $profile_url = admin_url('user-edit.php?user_id=' . $user->ID);
            echo ' <a href="' . esc_url($profile_url) . '">Edit profile</a>';
            echo '</div>';

            if (!class_exists('INCR_Nodes_Store')) {
                echo '<p>Nodes store not available.</p></div>';
                return;
            }

            $nodes = INCR_Nodes_Store::get_nodes_by_user($user->ID);

            if (empty($nodes)) {
                echo '<p>No nodes found for this user.</p>';
                $dashboard_url = add_query_arg(['page' => 'incr-nodes-dashboard', 'user_id' => $user->ID], admin_url('admin.php'));
                echo '<a href="' . esc_url($dashboard_url) . '">View in Nodes Dashboard &rarr;</a>';
                echo '</div>';
                return;
            }

            $today = current_time('Y-m-d');

            echo '<div class="incr-ov-node-cards">';
            foreach ($nodes as $node) {
                $due = $node['due_date'] ? substr($node['due_date'], 0, 10) : null;
                $is_overdue = $due && $due < $today;
                $status_label = $due ? ($is_overdue ? 'Overdue' : 'Active') : 'Unknown';
                $badge_class = $is_overdue ? 'incr-ov-badge--overdue' : 'incr-ov-badge--active';

                // Progress bar
                $progress_pct = 0;
                if ($node['created_at'] && $node['due_date']) {
                    $created_ts = strtotime($node['created_at']);
                    $due_ts = strtotime($node['due_date']);
                    $now_ts = time();
                    $total_days = max(1, ($due_ts - $created_ts) / 86400);
                    $elapsed_days = ($now_ts - $created_ts) / 86400;
                    $progress_pct = max(0, min(100, ($elapsed_days / $total_days) * 100));
                }

                $days_left = $due ? (int) ((strtotime($due) - strtotime($today)) / 86400) : null;
                $created_display = $node['created_at'] ? substr($node['created_at'], 0, 10) : '-';

                echo '<div class="incr-ov-node-card">';
                echo '<div class="incr-ov-node-card__header">';
                echo '<span class="incr-ov-node-card__type">' . esc_html($node['node_type'] ?: 'Unknown') . '</span>';
                echo '<span class="incr-ov-badge ' . esc_attr($badge_class) . '">' . esc_html($status_label) . '</span>';
                echo '</div>';
                echo '<div class="incr-ov-node-card__meta">';
                echo 'Created: ' . esc_html($created_display) . '<br>';
                echo 'Due: ' . esc_html($due ?: '-');
                if ($days_left !== null) {
                    if ($days_left > 0) {
                        echo ' (' . esc_html($days_left) . 'd left)';
                    } elseif ($days_left === 0) {
                        echo ' (today)';
                    } else {
                        echo ' (' . esc_html(abs($days_left)) . 'd overdue)';
                    }
                }
                echo '</div>';
                echo '<div class="incr-ov-progress-wrap">';
                echo '<div class="incr-ov-progress-bar' . ($is_overdue ? ' incr-ov-progress-bar--overdue' : '') . '" style="width:' . esc_attr(round($progress_pct, 1)) . '%"></div>';
                echo '</div>';
                echo '</div>';
            }
            echo '</div>';

            $dashboard_url = add_query_arg(['page' => 'incr-nodes-dashboard', 'user_id' => $user->ID], admin_url('admin.php'));
            echo '<p><a href="' . esc_url($dashboard_url) . '">View in Nodes Dashboard &rarr;</a></p>';
        }

        echo '</div>';
    }

    public static function render_dashboard() {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied');
        }

        $filters = [
            'user_id' => isset($_GET['user_id']) ? (int) $_GET['user_id'] : '',
            'user_email' => isset($_GET['user_email']) ? sanitize_email(wp_unslash($_GET['user_email'])) : '',
            'node_type' => isset($_GET['node_type']) ? sanitize_text_field(wp_unslash($_GET['node_type'])) : '',
            'status' => isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '',
            'due_from' => isset($_GET['due_from']) ? sanitize_text_field(wp_unslash($_GET['due_from'])) : '',
            'due_to' => isset($_GET['due_to']) ? sanitize_text_field(wp_unslash($_GET['due_to'])) : '',
        ];

        // If email provided but not user_id, look up user_id
        if ($filters['user_email'] && !$filters['user_id']) {
            $user = get_user_by('email', $filters['user_email']);
            if ($user) {
                $filters['user_id'] = $user->ID;
            }
        }

        $page = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $per_page = 20;
        $orderby = isset($_GET['orderby']) ? sanitize_text_field(wp_unslash($_GET['orderby'])) : 'due_date';
        $order = isset($_GET['order']) ? sanitize_text_field(wp_unslash($_GET['order'])) : 'ASC';

        $args = [
            'page' => $page,
            'per_page' => $per_page,
            'user_id' => $filters['user_id'] ? $filters['user_id'] : null,
            'node_type' => $filters['node_type'] !== '' ? $filters['node_type'] : null,
            'status' => $filters['status'] !== '' ? $filters['status'] : null,
            'due_date_from' => $filters['due_from'] !== '' ? $filters['due_from'] : null,
            'due_date_to' => $filters['due_to'] !== '' ? $filters['due_to'] : null,
            'orderby' => $orderby,
            'order' => $order,
        ];

        $result = class_exists('INCR_Nodes_Store') ? INCR_Nodes_Store::get_all_nodes($args) : ['items' => [], 'total' => 0, 'page' => $page, 'per_page' => $per_page];
        $items = $result['items'];
        $total = (int) $result['total'];
        $total_pages = $per_page > 0 ? (int) ceil($total / $per_page) : 1;
        $node_types = class_exists('INCR_Nodes_Store') ? INCR_Nodes_Store::get_distinct_node_types() : [];
        $status_counts = class_exists('INCR_Nodes_Store') ? INCR_Nodes_Store::get_status_counts([
            'user_id' => $filters['user_id'] ? $filters['user_id'] : null,
            'node_type' => $filters['node_type'] !== '' ? $filters['node_type'] : null,
            'due_date_from' => $filters['due_from'] !== '' ? $filters['due_from'] : null,
            'due_date_to' => $filters['due_to'] !== '' ? $filters['due_to'] : null,
        ]) : ['active' => 0, 'overdue' => 0];
        $type_counts = class_exists('INCR_Nodes_Store') ? INCR_Nodes_Store::get_type_counts([
            'user_id' => $filters['user_id'] ? $filters['user_id'] : null,
            'status' => $filters['status'] !== '' ? $filters['status'] : null,
            'due_date_from' => $filters['due_from'] !== '' ? $filters['due_from'] : null,
            'due_date_to' => $filters['due_to'] !== '' ? $filters['due_to'] : null,
        ]) : [];

        $notice = isset($_GET['incr_notice']) ? sanitize_text_field(wp_unslash($_GET['incr_notice'])) : '';

        echo '<div class="wrap">';
        echo '<h1>Nodes Dashboard</h1>';

        if (strpos($notice, 'refreshed_') === 0) {
            $parts = explode('_', $notice);
            $count = isset($parts[1]) ? (int) $parts[1] : 0;
            $db_count = isset($parts[2]) ? (int) $parts[2] : 0;
            echo '<div class="notice notice-success is-dismissible"><p>User nodes refreshed. API rows: ' . esc_html($count) . '. DB rows: ' . esc_html($db_count) . '.</p></div>';
        } elseif ($notice === 'sync_all_scheduled') {
            echo '<div class="notice notice-info is-dismissible"><p>Sync all started. Refresh this page in a minute to see results.</p></div>';
        } elseif (strpos($notice, 'sync_all_') === 0) {
            $parts = explode('_', $notice);
            $users_synced = isset($parts[2]) ? (int) $parts[2] : 0;
            $api_rows = isset($parts[3]) ? (int) $parts[3] : 0;
            $db_rows = isset($parts[4]) ? (int) $parts[4] : 0;
            echo '<div class="notice notice-success is-dismissible"><p>Sync all complete. Users: ' . esc_html($users_synced) . '. API rows: ' . esc_html($api_rows) . '. DB rows: ' . esc_html($db_rows) . '.</p></div>';
        } elseif ($notice === 'sync_all_missing_fetch') {
            echo '<div class="notice notice-error is-dismissible"><p>Sync all failed: fetch function not available.</p></div>';
        } elseif ($notice === 'missing_user') {
            echo '<div class="notice notice-warning is-dismissible"><p>User ID is required for refresh.</p></div>';
        } elseif ($notice === 'missing_fetch') {
            echo '<div class="notice notice-error is-dismissible"><p>Fetch function not available.</p></div>';
        }

        $last_result = get_transient('incr_sync_all_last_result');
        if (is_array($last_result)) {
            delete_transient('incr_sync_all_last_result');
            $users_synced = isset($last_result['users_synced']) ? (int) $last_result['users_synced'] : 0;
            $api_rows = isset($last_result['api_rows']) ? (int) $last_result['api_rows'] : 0;
            $db_rows = isset($last_result['db_rows']) ? (int) $last_result['db_rows'] : 0;
            echo '<div class="notice notice-success is-dismissible"><p>Sync all complete. Users: ' . esc_html($users_synced) . '. API rows: ' . esc_html($api_rows) . '. DB rows: ' . esc_html($db_rows) . '.</p></div>';
        }

        echo '<form method="get" style="margin-bottom: 16px;">';
        echo '<input type="hidden" name="page" value="incr-nodes-dashboard" />';
        echo '<div style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">';
        echo '<label>User ID<br><input type="number" name="user_id" value="' . esc_attr($filters['user_id']) . '" style="width:100px;"></label>';
        echo '<label>Email<br><input type="email" name="user_email" value="' . esc_attr($filters['user_email']) . '" placeholder="user@example.com" style="width:200px;"></label>';
        echo '<label>Node Type<br><select name="node_type"><option value="">All</option>';
        foreach ($node_types as $type) {
            $selected = $filters['node_type'] === $type ? ' selected' : '';
            echo '<option value="' . esc_attr($type) . '"' . $selected . '>' . esc_html($type) . '</option>';
        }
        echo '</select></label>';
        echo '<label>Status<br><select name="status">';
        $status_options = ['' => 'All', 'active' => 'Active', 'overdue' => 'Overdue', 'archived' => 'Archived'];
        foreach ($status_options as $value => $label) {
            $selected = $filters['status'] === $value ? ' selected' : '';
            echo '<option value="' . esc_attr($value) . '"' . $selected . '>' . esc_html($label) . '</option>';
        }
        echo '</select></label>';
        echo '<label>Due from<br><input type="date" name="due_from" value="' . esc_attr($filters['due_from']) . '"></label>';
        echo '<label>Due to<br><input type="date" name="due_to" value="' . esc_attr($filters['due_to']) . '"></label>';
        echo '<button class="button">Filter</button>';
        echo '</div>';
        echo '</form>';

        $base_filters = [
            'page' => 'incr-nodes-dashboard',
            'user_id' => $filters['user_id'] ?: null,
            'user_email' => $filters['user_email'] ?: null,
            'node_type' => $filters['node_type'] ?: null,
            'due_from' => $filters['due_from'] ?: null,
            'due_to' => $filters['due_to'] ?: null,
        ];
        $active_url = add_query_arg(array_merge($base_filters, ['status' => 'active']), admin_url('admin.php'));
        $overdue_url = add_query_arg(array_merge($base_filters, ['status' => 'overdue']), admin_url('admin.php'));

        echo '<div style="display:flex; gap:16px; flex-wrap:wrap; margin: 8px 0 12px;">';
        echo '<a class="postbox" href="' . esc_url($active_url) . '" style="padding:12px 16px; min-width:180px; text-decoration:none; display:inline-block;">';
        echo '<strong>Active</strong><br><span style="font-size:20px;">' . esc_html($status_counts['active']) . '</span>';
        echo '</a>';
        echo '<a class="postbox" href="' . esc_url($overdue_url) . '" style="padding:12px 16px; min-width:180px; text-decoration:none; display:inline-block;">';
        echo '<strong>Overdue</strong><br><span style="font-size:20px;">' . esc_html($status_counts['overdue']) . '</span>';
        echo '</a>';
        if (!empty($type_counts)) {
            echo '<div class="postbox" style="padding:12px 16px; min-width:240px;">';
            echo '<strong>By type</strong><br>';
            foreach ($type_counts as $type_row) {
                $label = $type_row['node_type'] !== '' ? $type_row['node_type'] : '—';
                echo '<div>' . esc_html($label) . ': ' . esc_html($type_row['total']) . '</div>';
            }
            echo '</div>';
        }
        echo '</div>';

        // Node Holders summary
        if (!empty($node_types)) {
            global $wpdb;
            $nodes_tbl = $wpdb->prefix . 'incr_nodes';

            echo '<div class="postbox" style="padding:12px 16px; margin-bottom:16px;">';
            echo '<h3 style="margin:0 0 8px;">Node Holders</h3>';
            echo '<table class="widefat striped" style="max-width:800px;"><thead><tr>';
            echo '<th>Node Type</th><th>Holders</th><th>Users</th>';
            echo '</tr></thead><tbody>';

            foreach ($node_types as $nt) {
                $holders = $wpdb->get_results($wpdb->prepare(
                    "SELECT DISTINCT n.user_id FROM {$nodes_tbl} n WHERE n.node_type = %s AND n.user_id > 0",
                    $nt
                ), ARRAY_A);

                $count = count($holders);
                $user_labels = [];
                foreach ($holders as $h) {
                    $uid = (int) $h['user_id'];
                    $u = get_userdata($uid);
                    if ($u) {
                        $profile_url = admin_url('user-edit.php?user_id=' . $uid);
                        $user_labels[] = '<a href="' . esc_url($profile_url) . '">' . esc_html($u->display_name ?: $u->user_login) . '</a>';
                    } else {
                        $user_labels[] = '#' . $uid;
                    }
                }

                echo '<tr>';
                echo '<td><strong>' . esc_html($nt) . '</strong></td>';
                echo '<td>' . $count . '</td>';
                echo '<td>' . implode(', ', $user_labels) . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table></div>';
        }

        if ($filters['user_id']) {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-bottom: 16px;">';
            echo '<input type="hidden" name="action" value="incr_refresh_user_nodes" />';
            echo '<input type="hidden" name="user_id" value="' . esc_attr($filters['user_id']) . '" />';
            echo wp_nonce_field('incr_nodes_refresh', 'incr_nodes_refresh_nonce', true, false);
            echo '<button class="button button-secondary">Refresh user nodes</button>';
            echo '</form>';
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-bottom: 16px;">';
        echo '<input type="hidden" name="action" value="incr_sync_all_nodes" />';
        echo wp_nonce_field('incr_nodes_sync_all', 'incr_nodes_sync_all_nonce', true, false);
        echo '<button class="button" onclick="return confirm(\'Sync all users now?\')">Sync all users</button>';
        echo '</form>';

        $all_columns = self::columns();
        $hidden_columns = get_user_meta(get_current_user_id(), 'incr_nodes_hidden_columns', true);
        $hidden_columns = is_array($hidden_columns) ? $hidden_columns : [];
        $visible_columns = array_diff_key($all_columns, array_flip($hidden_columns));

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin: 0 0 16px;">';
        echo '<input type="hidden" name="action" value="incr_save_nodes_columns" />';
        echo wp_nonce_field('incr_nodes_columns', 'incr_nodes_columns_nonce', true, false);
        echo '<div style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">';
        echo '<strong>Columns:</strong>';
        foreach ($all_columns as $key => $label) {
            $checked = isset($visible_columns[$key]) ? ' checked' : '';
            echo '<label style="margin-right:8px;"><input type="checkbox" name="columns[]" value="' . esc_attr($key) . '"' . $checked . '> ' . esc_html($label) . '</label>';
        }
        echo '<button class="button button-secondary">Save columns</button>';
        echo '</div>';
        echo '</form>';

        $base_table_filters = [
            'page' => 'incr-nodes-dashboard',
            'user_id' => $filters['user_id'] ?: null,
            'user_email' => $filters['user_email'] ?: null,
            'node_type' => $filters['node_type'] ?: null,
            'due_from' => $filters['due_from'] ?: null,
            'due_to' => $filters['due_to'] ?: null,
            'status' => $filters['status'] ?: null,
        ];
        $status_cycle = $filters['status'] === 'active' ? 'overdue' : ($filters['status'] === 'overdue' ? '' : 'active');
        $status_url = add_query_arg(array_merge($base_table_filters, ['status' => $status_cycle]), admin_url('admin.php'));
        $order_toggle = (isset($_GET['order']) && strtoupper((string) $_GET['order']) === 'DESC') ? 'ASC' : 'DESC';
        $due_sort_url = add_query_arg(array_merge($base_table_filters, ['orderby' => 'due_date', 'order' => $order_toggle]), admin_url('admin.php'));

        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>';
        foreach ($visible_columns as $key => $label) {
            if ($key === 'status') {
                echo '<th><a href="' . esc_url($status_url) . '">' . esc_html($label) . '</a></th>';
            } elseif ($key === 'due_date') {
                echo '<th><a href="' . esc_url($due_sort_url) . '">' . esc_html($label) . '</a></th>';
            } else {
                echo '<th>' . esc_html($label) . '</th>';
            }
        }
        echo '</tr></thead><tbody>';

        if (empty($items)) {
            echo '<tr><td colspan="' . esc_attr(count($visible_columns)) . '">No nodes found.</td></tr>';
        } else {
            $today = current_time('Y-m-d');
            $user_cache = [];
            foreach ($items as $row) {
                $due_date = isset($row['due_date']) ? $row['due_date'] : '';
                $is_archived = !empty($row['is_archived']);
                $status = '—';
                if ($is_archived) {
                    $status = 'archived';
                } elseif (!empty($due_date)) {
                    $status = (substr($due_date, 0, 10) < $today) ? 'overdue' : 'active';
                }

                $raw_id = 'raw-' . esc_attr($row['id']);
                echo '<tr>';
                foreach ($visible_columns as $key => $label) {
                    if ($key === 'status') {
                        echo '<td>' . esc_html($status) . '</td>';
                    } elseif ($key === 'user_email') {
                        $uid = isset($row['user_id']) ? (int) $row['user_id'] : 0;
                        if ($uid > 0) {
                            if (!array_key_exists($uid, $user_cache)) {
                                $user = get_user_by('id', $uid);
                                $user_cache[$uid] = $user ? $user->user_email : '';
                            }
                            echo '<td>' . esc_html($user_cache[$uid]) . '</td>';
                        } else {
                            echo '<td>—</td>';
                        }
                    } elseif ($key === 'node_type') {
                        $value = isset($row[$key]) ? $row[$key] : '';
                        if ($value) {
                            $filter_url = add_query_arg(['page' => 'incr-nodes-dashboard', 'node_type' => $value], admin_url('admin.php'));
                            echo '<td><a href="' . esc_url($filter_url) . '">' . esc_html($value) . '</a></td>';
                        } else {
                            echo '<td>�</td>';
                        }
                    } elseif ($key === 'actions') {
                        echo '<td><a href="#" class="incr-view-raw" data-target="' . esc_attr($raw_id) . '">View raw</a></td>';
                    } else {
                        $value = isset($row[$key]) ? $row[$key] : '';
                        echo '<td>' . esc_html($value) . '</td>';
                    }
                }
                echo '</tr>';
                if (isset($visible_columns['actions'])) {
                    echo '<tr id="' . esc_attr($raw_id) . '" class="incr-raw-row" style="display:none;">';
                    echo '<td colspan="' . esc_attr(count($visible_columns)) . '"><pre style="white-space:pre-wrap; max-height:260px; overflow:auto;">' . esc_html($row['raw_payload']) . '</pre></td>';
                    echo '</tr>';
                }
            }
        }

        echo '</tbody></table>';

        if ($total_pages > 1) {
            $pagination_args = $base_table_filters;
            $pagination_args['paged'] = '%#%';
            $base = add_query_arg($pagination_args, admin_url('admin.php'));
            echo '<div class="tablenav"><div class="tablenav-pages">';
            echo paginate_links([
                'base' => $base,
                'format' => '',
                'current' => $page,
                'total' => $total_pages,
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
            ]);
            echo '</div></div>';
        }

        echo '<script>
        document.addEventListener("click", function(e) {
            var link = e.target.closest(".incr-view-raw");
            if (!link) { return; }
            e.preventDefault();
            var target = document.getElementById(link.getAttribute("data-target"));
            if (!target) { return; }
            target.style.display = target.style.display === "table-row" ? "none" : "table-row";
        });
        </script>';

        echo '</div>';
    }

    public static function render_telegram_links() {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied');
        }

        global $wpdb;
        $links_table = $wpdb->prefix . 'incr_telegram_links';
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $links_table));

        echo '<div class="wrap">';
        echo '<h1>Telegram Links</h1>';

        if ($table_exists !== $links_table) {
            echo '<div class="notice notice-warning"><p>Telegram links table not found.</p></div>';
            echo '</div>';
            return;
        }

        $per_page = 20;
        $page = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $offset = ($page - 1) * $per_page;

        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$links_table}");
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT l.id, l.wp_user_id, l.telegram_chat_id, l.telegram_user_id, l.tg_username, l.created_at, l.updated_at,
                        u.user_email, u.display_name
                 FROM {$links_table} l
                 LEFT JOIN {$wpdb->users} u ON u.ID = l.wp_user_id
                 ORDER BY l.created_at DESC
                 LIMIT %d OFFSET %d",
                $per_page,
                $offset
            ),
            ARRAY_A
        );

        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>ID</th>';
        echo '<th>User</th>';
        echo '<th>Email</th>';
        echo '<th>Telegram Username</th>';
        echo '<th>Chat ID</th>';
        echo '<th>Telegram User ID</th>';
        echo '<th>Linked At</th>';
        echo '<th>Updated At</th>';
        echo '</tr></thead><tbody>';

        if (empty($rows)) {
            echo '<tr><td colspan="8">No Telegram links found.</td></tr>';
        } else {
            foreach ($rows as $row) {
                $user_label = $row['display_name'] ? $row['display_name'] : ('User #' . $row['wp_user_id']);
                $profile_url = admin_url('user-edit.php?user_id=' . (int) $row['wp_user_id']);
                echo '<tr>';
                echo '<td>' . esc_html($row['id']) . '</td>';
                echo '<td><a href="' . esc_url($profile_url) . '">' . esc_html($user_label) . '</a></td>';
                echo '<td>' . esc_html($row['user_email'] ?: '-') . '</td>';
                echo '<td>' . esc_html($row['tg_username'] ?: '-') . '</td>';
                echo '<td>' . esc_html($row['telegram_chat_id']) . '</td>';
                echo '<td>' . esc_html($row['telegram_user_id']) . '</td>';
                echo '<td>' . esc_html($row['created_at']) . '</td>';
                echo '<td>' . esc_html($row['updated_at']) . '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';

        $total_pages = $per_page > 0 ? (int) ceil($total / $per_page) : 1;
        if ($total_pages > 1) {
            $base = add_query_arg(
                [
                    'page' => 'incr-telegram-links',
                    'paged' => '%#%',
                ],
                admin_url('admin.php')
            );
            $page_links = paginate_links([
                'base' => $base,
                'format' => '',
                'prev_text' => '«',
                'next_text' => '»',
                'total' => $total_pages,
                'current' => $page,
            ]);
            if ($page_links) {
                echo '<div class="tablenav"><div class="tablenav-pages">' . $page_links . '</div></div>';
            }
        }

        echo '</div>';
    }

    public static function render_telegram_notifications() {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied');
        }

        global $wpdb;
        $notifications_table = $wpdb->prefix . 'incr_telegram_notifications';
        $links_table = $wpdb->prefix . 'incr_telegram_links';
        $nodes_table = $wpdb->prefix . 'incr_nodes';
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $notifications_table));

        echo '<div class="wrap">';
        echo '<h1>TG Notifications</h1>';

        if ($table_exists !== $notifications_table) {
            echo '<div class="notice notice-warning"><p>Telegram notifications table not found.</p></div>';
            echo '</div>';
            return;
        }

        // Notification Overview — summary table of all TG users
        self::render_notification_overview();

        // Upcoming notifications section
        self::render_upcoming_notifications();

        echo '<h2>Sent Notifications</h2>';

        $per_page = 20;
        $page = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $offset = ($page - 1) * $per_page;

        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$notifications_table}");
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    n.id, n.wp_user_id, n.node_id, n.notif_type, n.sent_at,
                    u.user_email, u.display_name,
                    l.tg_username,
                    nd.node_type
                 FROM {$notifications_table} n
                 LEFT JOIN {$wpdb->users} u ON u.ID = n.wp_user_id
                 LEFT JOIN {$links_table} l ON l.wp_user_id = n.wp_user_id
                 LEFT JOIN {$nodes_table} nd ON nd.node_id = n.node_id
                 ORDER BY n.sent_at DESC
                 LIMIT %d OFFSET %d",
                $per_page,
                $offset
            ),
            ARRAY_A
        );

        $notif_type_labels = [
            'overdue'   => 'Overdue',
            'due_today' => 'Due Today',
            'due_24h'   => 'Due in 24h',
            'due_72h'   => 'Due in 72h',
        ];

        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>Date/Time</th>';
        echo '<th>User</th>';
        echo '<th>Email</th>';
        echo '<th>Telegram</th>';
        echo '<th>Node</th>';
        echo '<th>Node ID</th>';
        echo '<th>Type</th>';
        echo '</tr></thead><tbody>';

        if (empty($rows)) {
            echo '<tr><td colspan="7">No notifications found.</td></tr>';
        } else {
            foreach ($rows as $row) {
                $user_label = $row['display_name'] ? $row['display_name'] : ('User #' . $row['wp_user_id']);
                $profile_url = admin_url('user-edit.php?user_id=' . (int) $row['wp_user_id']);
                $tg_display = $row['tg_username'] ? ('@' . $row['tg_username']) : '-';
                $type_label = isset($notif_type_labels[$row['notif_type']]) ? $notif_type_labels[$row['notif_type']] : $row['notif_type'];
                $node_name = $row['node_type'] ? ucfirst($row['node_type']) : '-';

                echo '<tr>';
                echo '<td>' . esc_html($row['sent_at']) . '</td>';
                echo '<td><a href="' . esc_url($profile_url) . '">' . esc_html($user_label) . '</a></td>';
                echo '<td>' . esc_html($row['user_email'] ?: '-') . '</td>';
                echo '<td>' . esc_html($tg_display) . '</td>';
                echo '<td>' . esc_html($node_name) . '</td>';
                echo '<td style="font-size:11px;">' . esc_html($row['node_id']) . '</td>';
                echo '<td>' . esc_html($type_label) . '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';

        $total_pages = $per_page > 0 ? (int) ceil($total / $per_page) : 1;
        if ($total_pages > 1) {
            $base = add_query_arg(
                [
                    'page' => 'incr-tg-notifications',
                    'paged' => '%#%',
                ],
                admin_url('admin.php')
            );
            $page_links = paginate_links([
                'base' => $base,
                'format' => '',
                'prev_text' => '�',
                'next_text' => '�',
                'total' => $total_pages,
                'current' => $page,
            ]);
            if ($page_links) {
                echo '<div class="tablenav"><div class="tablenav-pages">' . $page_links . '</div></div>';
            }
        }

        echo '</div>';
    }

    private static function render_notification_overview() {
        global $wpdb;
        $links_table = $wpdb->prefix . 'incr_telegram_links';
        $nodes_table = $wpdb->prefix . 'incr_nodes';
        $notif_table = $wpdb->prefix . 'incr_telegram_notifications';

        // Main query: one row per TG user with node stats
        $users = $wpdb->get_results(
            "SELECT
                l.wp_user_id, u.display_name, u.user_email, l.tg_username,
                COUNT(n.id) as total_nodes,
                SUM(CASE WHEN n.due_date < NOW() THEN 1 ELSE 0 END) as overdue_count,
                SUM(CASE WHEN n.due_date >= NOW() AND n.due_date <= NOW() + INTERVAL 72 HOUR THEN 1 ELSE 0 END) as expiring_72h,
                MIN(CASE WHEN n.due_date >= NOW() THEN n.due_date END) as next_due
            FROM {$links_table} l
            INNER JOIN {$wpdb->users} u ON u.ID = l.wp_user_id
            LEFT JOIN {$nodes_table} n ON n.user_id = l.wp_user_id AND n.due_date IS NOT NULL
            GROUP BY l.wp_user_id
            ORDER BY overdue_count DESC, next_due ASC",
            ARRAY_A
        );

        // Batch query: pending notification count per user
        $pending_rows = $wpdb->get_results(
            "SELECT n.user_id, COUNT(*) as pending_count
            FROM {$nodes_table} n
            INNER JOIN {$links_table} l ON l.wp_user_id = n.user_id
            WHERE n.due_date IS NOT NULL
              AND n.due_date <= NOW() + INTERVAL 72 HOUR
              AND NOT EXISTS (
                  SELECT 1 FROM {$notif_table} tn
                  WHERE tn.wp_user_id = n.user_id
                    AND tn.node_id = n.node_id
                    AND tn.notif_type = CASE
                        WHEN n.due_date < NOW() THEN 'overdue'
                        WHEN DATE(n.due_date) = CURDATE() THEN 'due_today'
                        WHEN n.due_date <= NOW() + INTERVAL 24 HOUR THEN 'due_24h'
                        ELSE 'due_72h'
                    END
              )
            GROUP BY n.user_id",
            ARRAY_A
        );

        $pending_map = [];
        foreach ($pending_rows as $pr) {
            $pending_map[(int) $pr['user_id']] = (int) $pr['pending_count'];
        }

        echo '<h2>Notification Overview</h2>';

        if (empty($users)) {
            echo '<p>No users with Telegram linked.</p>';
            return;
        }

        echo '<table class="widefat fixed striped" style="margin-bottom:24px;">';
        echo '<thead><tr>';
        echo '<th>User</th>';
        echo '<th>Email</th>';
        echo '<th>Telegram</th>';
        echo '<th style="text-align:center;">Total Nodes</th>';
        echo '<th style="text-align:center;">Overdue</th>';
        echo '<th style="text-align:center;">Expiring 72h</th>';
        echo '<th>Next Due</th>';
        echo '<th style="text-align:center;">Status</th>';
        echo '</tr></thead><tbody>';

        foreach ($users as $row) {
            $uid = (int) $row['wp_user_id'];
            $user_label = $row['display_name'] ?: ('User #' . $uid);
            $profile_url = admin_url('user-edit.php?user_id=' . $uid);
            $tg_display = $row['tg_username'] ? ('@' . $row['tg_username']) : '-';
            $overdue = (int) $row['overdue_count'];
            $expiring = (int) $row['expiring_72h'];
            $total = (int) $row['total_nodes'];
            $next_due = $row['next_due'] ?: '-';
            $pending = isset($pending_map[$uid]) ? $pending_map[$uid] : 0;

            // Status badge
            if ($pending > 0) {
                $status_html = '<span style="display:inline-block;padding:3px 10px;border-radius:3px;background:#dc3545;color:#fff;font-weight:600;font-size:12px;">Pending (' . $pending . ')</span>';
            } elseif ($overdue > 0 || $expiring > 0) {
                $status_html = '<span style="display:inline-block;padding:3px 10px;border-radius:3px;background:#28a745;color:#fff;font-weight:600;font-size:12px;">All sent</span>';
            } else {
                $status_html = '<span style="display:inline-block;padding:3px 10px;border-radius:3px;background:#999;color:#fff;font-weight:600;font-size:12px;">No expiring</span>';
            }

            echo '<tr>';
            echo '<td><a href="' . esc_url($profile_url) . '">' . esc_html($user_label) . '</a></td>';
            echo '<td>' . esc_html($row['user_email'] ?: '-') . '</td>';
            echo '<td>' . esc_html($tg_display) . '</td>';
            echo '<td style="text-align:center;">' . esc_html($total) . '</td>';
            echo '<td style="text-align:center;' . ($overdue > 0 ? 'color:#dc3545;font-weight:bold;' : '') . '">' . esc_html($overdue) . '</td>';
            echo '<td style="text-align:center;' . ($expiring > 0 ? 'color:#fd7e14;font-weight:bold;' : '') . '">' . esc_html($expiring) . '</td>';
            echo '<td>' . esc_html($next_due) . '</td>';
            echo '<td style="text-align:center;">' . $status_html . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    private static function render_upcoming_notifications() {
        global $wpdb;
        $links_table = $wpdb->prefix . 'incr_telegram_links';
        $nodes_table = $wpdb->prefix . 'incr_nodes';
        $notifications_table = $wpdb->prefix . 'incr_telegram_notifications';

        $now = current_time('mysql');
        $today_end = current_time('Y-m-d') . ' 23:59:59';
        $in_24h = date('Y-m-d H:i:s', strtotime('+24 hours', current_time('timestamp')));
        $in_72h = date('Y-m-d H:i:s', strtotime('+72 hours', current_time('timestamp')));

        // Get users with Telegram linked and their nodes that need notifications
        $sql = "SELECT
                    nd.user_id as wp_user_id,
                    nd.node_id,
                    nd.node_type,
                    nd.due_date,
                    u.display_name,
                    u.user_email,
                    l.tg_username,
                    CASE
                        WHEN nd.due_date < %s THEN 'overdue'
                        WHEN nd.due_date <= %s THEN 'due_today'
                        WHEN nd.due_date <= %s THEN 'due_24h'
                        WHEN nd.due_date <= %s THEN 'due_72h'
                        ELSE 'future'
                    END as pending_type
                FROM {$nodes_table} nd
                INNER JOIN {$links_table} l ON l.wp_user_id = nd.user_id
                INNER JOIN {$wpdb->users} u ON u.ID = nd.user_id
                WHERE nd.due_date <= %s
                ORDER BY nd.due_date ASC";

        $pending = $wpdb->get_results(
            $wpdb->prepare($sql, $now, $today_end, $in_24h, $in_72h, $in_72h),
            ARRAY_A
        );

        // Filter out already sent notifications
        $upcoming = [];
        foreach ($pending as $row) {
            if ($row['pending_type'] === 'future') {
                continue;
            }

            // Check if this notification was already sent
            $already_sent = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$notifications_table}
                 WHERE wp_user_id = %d AND node_id = %s AND notif_type = %s",
                $row['wp_user_id'],
                $row['node_id'],
                $row['pending_type']
            ));

            if (!$already_sent) {
                $upcoming[] = $row;
            }
        }

        $type_colors = [
            'overdue'   => '#dc3545',
            'due_today' => '#fd7e14',
            'due_24h'   => '#ffc107',
            'due_72h'   => '#28a745',
        ];

        $type_labels = [
            'overdue'   => 'Overdue',
            'due_today' => 'Due Today',
            'due_24h'   => 'Due in 24h',
            'due_72h'   => 'Due in 72h',
        ];

        echo '<h2>Upcoming Notifications</h2>';

        // Summary line
        $user_ids = array_unique(array_column($upcoming, 'wp_user_id'));
        $pending_count = count($upcoming);
        $user_count = count($user_ids);

        if (empty($upcoming)) {
            echo '<p style="color:#28a745;font-weight:600;">No pending notifications. All users are notified.</p>';
        } else {
            echo '<p><strong>' . esc_html($pending_count) . ' pending notification' . ($pending_count !== 1 ? 's' : '') . '</strong> for <strong>' . esc_html($user_count) . ' user' . ($user_count !== 1 ? 's' : '') . '</strong></p>';

            // Group by type for summary
            $by_type = [];
            foreach ($upcoming as $row) {
                $t = $row['pending_type'];
                if (!isset($by_type[$t])) {
                    $by_type[$t] = 0;
                }
                $by_type[$t]++;
            }

            echo '<div style="display:flex; gap:12px; margin-bottom:16px;">';
            foreach ($type_labels as $type => $label) {
                $count = isset($by_type[$type]) ? $by_type[$type] : 0;
                $color = $type_colors[$type];
                echo '<div style="padding:8px 16px; background:' . esc_attr($color) . '; color:#fff; border-radius:4px;">';
                echo '<strong>' . esc_html($label) . '</strong>: ' . esc_html($count);
                echo '</div>';
            }
            echo '</div>';

            echo '<table class="widefat fixed striped" style="margin-bottom:24px;">';
            echo '<thead><tr>';
            echo '<th>User</th>';
            echo '<th>Email</th>';
            echo '<th>Telegram</th>';
            echo '<th>Node</th>';
            echo '<th>Due Date</th>';
            echo '<th>Type</th>';
            echo '</tr></thead><tbody>';

            foreach ($upcoming as $row) {
                $user_label = $row['display_name'] ? $row['display_name'] : ('User #' . $row['wp_user_id']);
                $profile_url = admin_url('user-edit.php?user_id=' . (int) $row['wp_user_id']);
                $tg_display = $row['tg_username'] ? ('@' . $row['tg_username']) : '-';
                $type_label = isset($type_labels[$row['pending_type']]) ? $type_labels[$row['pending_type']] : $row['pending_type'];
                $color = isset($type_colors[$row['pending_type']]) ? $type_colors[$row['pending_type']] : '#666';
                $node_name = $row['node_type'] ? ucfirst($row['node_type']) : '-';

                echo '<tr>';
                echo '<td><a href="' . esc_url($profile_url) . '">' . esc_html($user_label) . '</a></td>';
                echo '<td>' . esc_html($row['user_email'] ?: '-') . '</td>';
                echo '<td>' . esc_html($tg_display) . '</td>';
                echo '<td>' . esc_html($node_name) . '</td>';
                echo '<td>' . esc_html($row['due_date']) . '</td>';
                echo '<td style="background:' . esc_attr($color) . '; color:#fff; font-weight:bold; text-align:center;">' . esc_html($type_label) . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        }
    }

    public static function render_send_tg_message() {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied');
        }

        global $wpdb;
        $links_table = $wpdb->prefix . 'incr_telegram_links';

        // Get users with Telegram linked
        $tg_users = $wpdb->get_results(
            "SELECT l.wp_user_id, l.tg_username, l.telegram_chat_id, u.display_name, u.user_email
             FROM {$links_table} l
             INNER JOIN {$wpdb->users} u ON u.ID = l.wp_user_id
             ORDER BY u.display_name ASC",
            ARRAY_A
        );

        $notice = isset($_GET['tg_notice']) ? sanitize_text_field(wp_unslash($_GET['tg_notice'])) : '';

        echo '<div class="wrap">';
        echo '<h1>Send TG Message</h1>';

        if ($notice === 'sent') {
            echo '<div class="notice notice-success is-dismissible"><p>Message sent successfully!</p></div>';
        } elseif ($notice === 'error') {
            echo '<div class="notice notice-error is-dismissible"><p>Failed to send message. Check error log.</p></div>';
        } elseif ($notice === 'no_user') {
            echo '<div class="notice notice-warning is-dismissible"><p>User not found or not linked to Telegram.</p></div>';
        } elseif ($notice === 'no_token') {
            echo '<div class="notice notice-error is-dismissible"><p>Telegram bot token not configured.</p></div>';
        } elseif ($notice === 'empty_message') {
            echo '<div class="notice notice-warning is-dismissible"><p>Message cannot be empty.</p></div>';
        }

        if (empty($tg_users)) {
            echo '<div class="notice notice-warning"><p>No users with Telegram linked.</p></div>';
            echo '</div>';
            return;
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="max-width:600px;">';
        echo '<input type="hidden" name="action" value="incr_send_tg_message" />';
        echo wp_nonce_field('incr_send_tg_message', 'incr_send_tg_message_nonce', true, false);

        echo '<table class="form-table">';

        // User selector
        echo '<tr><th><label for="tg_user_id">Select User</label></th><td>';
        echo '<select name="tg_user_id" id="tg_user_id" style="width:100%;" required>';
        echo '<option value="">� Select user �</option>';
        foreach ($tg_users as $user) {
            $label = $user['display_name'] ?: 'User #' . $user['wp_user_id'];
            $label .= ' (' . $user['user_email'] . ')';
            if ($user['tg_username']) {
                $label .= ' @' . $user['tg_username'];
            }
            echo '<option value="' . esc_attr($user['wp_user_id']) . '">' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '</td></tr>';

        // Message textarea
        echo '<tr><th><label for="tg_message">Message</label></th><td>';
        echo '<textarea name="tg_message" id="tg_message" rows="6" style="width:100%;" required placeholder="Enter your message here..."></textarea>';
        echo '<p class="description">Plain text. Emojis supported.</p>';
        echo '</td></tr>';

        echo '</table>';

        echo '<p><button type="submit" class="button button-primary button-large">Send Message</button></p>';
        echo '</form>';

        // Recent sent messages log
        echo '<h2 style="margin-top:40px;">Recent Manual Messages</h2>';
        $log_table = $wpdb->prefix . 'incr_telegram_manual_messages';
        $log_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $log_table));

        if ($log_exists === $log_table) {
            $recent = $wpdb->get_results(
                "SELECT m.*, u.display_name, u.user_email
                 FROM {$log_table} m
                 LEFT JOIN {$wpdb->users} u ON u.ID = m.wp_user_id
                 ORDER BY m.sent_at DESC
                 LIMIT 20",
                ARRAY_A
            );

            if (!empty($recent)) {
                echo '<table class="widefat fixed striped">';
                echo '<thead><tr><th>Date</th><th>User</th><th>Message</th><th>Status</th></tr></thead><tbody>';
                foreach ($recent as $row) {
                    $user_label = $row['display_name'] ?: 'User #' . $row['wp_user_id'];
                    $status_color = $row['status'] === 'sent' ? '#28a745' : '#dc3545';
                    echo '<tr>';
                    echo '<td>' . esc_html($row['sent_at']) . '</td>';
                    echo '<td>' . esc_html($user_label) . '</td>';
                    echo '<td style="max-width:300px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">' . esc_html($row['message']) . '</td>';
                    echo '<td style="color:' . $status_color . ';">' . esc_html(ucfirst($row['status'])) . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            } else {
                echo '<p>No messages sent yet.</p>';
            }
        } else {
            echo '<p>Message log table will be created after first message.</p>';
        }

        echo '</div>';
    }

    public static function handle_send_tg_message() {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied');
        }

        if (empty($_POST['incr_send_tg_message_nonce']) || !wp_verify_nonce($_POST['incr_send_tg_message_nonce'], 'incr_send_tg_message')) {
            wp_die('Invalid nonce');
        }

        $user_id = isset($_POST['tg_user_id']) ? (int) $_POST['tg_user_id'] : 0;
        $message = isset($_POST['tg_message']) ? sanitize_textarea_field(wp_unslash($_POST['tg_message'])) : '';

        $redirect_url = admin_url('admin.php?page=incr-send-tg-message');

        if (!$user_id) {
            wp_safe_redirect(add_query_arg('tg_notice', 'no_user', $redirect_url));
            exit;
        }

        if (trim($message) === '') {
            wp_safe_redirect(add_query_arg('tg_notice', 'empty_message', $redirect_url));
            exit;
        }

        // Get bot token
        $token = '';
        if (function_exists('incr_get_config')) {
            $token = incr_get_config('INCR_TELEGRAM_BOT_TOKEN');
        }
        if ($token === '' && defined('INCR_TELEGRAM_BOT_TOKEN')) {
            $token = INCR_TELEGRAM_BOT_TOKEN;
        }
        if ($token === '') {
            $env_value = getenv('INCR_TELEGRAM_BOT_TOKEN');
            if ($env_value !== false) {
                $token = $env_value;
            }
        }

        if (!$token) {
            wp_safe_redirect(add_query_arg('tg_notice', 'no_token', $redirect_url));
            exit;
        }

        // Get user's chat_id
        global $wpdb;
        $links_table = $wpdb->prefix . 'incr_telegram_links';
        $chat_id = $wpdb->get_var($wpdb->prepare(
            "SELECT telegram_chat_id FROM {$links_table} WHERE wp_user_id = %d",
            $user_id
        ));

        if (!$chat_id) {
            wp_safe_redirect(add_query_arg('tg_notice', 'no_user', $redirect_url));
            exit;
        }

        // Send message
        $success = self::send_tg_message($token, $chat_id, $message);

        // Log the message
        self::log_manual_message($user_id, $message, $success ? 'sent' : 'failed');

        $notice = $success ? 'sent' : 'error';
        wp_safe_redirect(add_query_arg('tg_notice', $notice, $redirect_url));
        exit;
    }

    private static function send_tg_message($token, $chat_id, $text) {
        $response = wp_remote_post(
            "https://api.telegram.org/bot{$token}/sendMessage",
            [
                'headers' => ['Content-Type' => 'application/json'],
                'body' => wp_json_encode([
                    'chat_id' => $chat_id,
                    'text' => $text,
                ]),
                'timeout' => 15,
            ]
        );

        if (is_wp_error($response)) {
            error_log('INCR Send TG Message: ' . $response->get_error_message());
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            error_log('INCR Send TG Message: Telegram API HTTP ' . $code . ' - ' . wp_remote_retrieve_body($response));
            return false;
        }

        return true;
    }

    private static function log_manual_message($user_id, $message, $status) {
        global $wpdb;
        $table = $wpdb->prefix . 'incr_telegram_manual_messages';

        // Create table if not exists
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            wp_user_id BIGINT(20) UNSIGNED NOT NULL,
            message TEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'sent',
            sent_at DATETIME NOT NULL,
            sent_by BIGINT(20) UNSIGNED NOT NULL,
            PRIMARY KEY (id),
            KEY wp_user_id (wp_user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $wpdb->insert($table, [
            'wp_user_id' => $user_id,
            'message' => $message,
            'status' => $status,
            'sent_at' => current_time('mysql'),
            'sent_by' => get_current_user_id(),
        ]);
    }

    // =========================================================================
    // Project Updates — broadcast by node type
    // =========================================================================

    public static function render_project_updates() {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied');
        }

        global $wpdb;
        $nodes_table = $wpdb->prefix . 'incr_nodes';
        $links_table = $wpdb->prefix . 'incr_telegram_links';

        // Notices
        $notice = isset($_GET['pu_notice']) ? sanitize_text_field(wp_unslash($_GET['pu_notice'])) : '';

        echo '<div class="wrap">';
        echo '<h1>Project Updates</h1>';

        if ($notice) {
            if (preg_match('/^sent_(\d+)$/', $notice, $m)) {
                echo '<div class="notice notice-success is-dismissible"><p>Update sent to ' . (int) $m[1] . ' users.</p></div>';
            } elseif ($notice === 'no_recipients') {
                echo '<div class="notice notice-warning is-dismissible"><p>No users with this node type and Telegram linked.</p></div>';
            } elseif ($notice === 'no_token') {
                echo '<div class="notice notice-error is-dismissible"><p>Telegram bot token not configured.</p></div>';
            } elseif ($notice === 'empty_message') {
                echo '<div class="notice notice-warning is-dismissible"><p>Message cannot be empty.</p></div>';
            } elseif ($notice === 'empty_node_type') {
                echo '<div class="notice notice-warning is-dismissible"><p>Please select a node type.</p></div>';
            }
        }

        // Get node types with recipient counts
        $node_types = class_exists('INCR_Nodes_Store') ? INCR_Nodes_Store::get_distinct_node_types() : [];

        $type_recipient_counts = [];
        foreach ($node_types as $type) {
            $count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT l.telegram_chat_id)
                 FROM {$nodes_table} n
                 INNER JOIN {$links_table} l ON l.wp_user_id = n.user_id
                 WHERE n.node_type = %s",
                $type
            ));
            $type_recipient_counts[$type] = $count;
        }

        // Send form
        echo '<h2>Send Update</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="incr_send_project_update">';
        wp_nonce_field('incr_project_update', 'incr_project_update_nonce');

        echo '<table class="form-table"><tbody>';

        echo '<tr><th><label for="node_type">Node Type</label></th><td>';
        echo '<select name="node_type" id="node_type" required>';
        echo '<option value="">— Select —</option>';
        foreach ($node_types as $type) {
            $count = $type_recipient_counts[$type];
            echo '<option value="' . esc_attr($type) . '">' . esc_html($type) . ' (' . $count . ' recipients)</option>';
        }
        echo '</select></td></tr>';

        echo '<tr><th><label for="pu_message">Message</label></th><td>';
        echo '<textarea name="pu_message" id="pu_message" rows="6" cols="60" required></textarea>';
        echo '</td></tr>';

        echo '</tbody></table>';
        echo '<p><input type="submit" class="button button-primary" value="Send Update" onclick="return confirm(\'Send this update to all matching users?\');"></p>';
        echo '</form>';

        // History table
        echo '<hr><h2>History</h2>';
        $history_table = $wpdb->prefix . 'incr_project_updates';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$history_table}'") === $history_table) {
            $rows = $wpdb->get_results(
                "SELECT * FROM {$history_table} ORDER BY sent_at DESC LIMIT 20",
                ARRAY_A
            );

            if ($rows) {
                echo '<table class="widefat striped"><thead><tr>';
                echo '<th>Date</th><th>Node Type</th><th>Message</th><th>Recipients</th><th>Sent By</th>';
                echo '</tr></thead><tbody>';

                foreach ($rows as $row) {
                    $sender = get_userdata((int) $row['sent_by']);
                    $sender_label = $sender ? $sender->display_name : '#' . $row['sent_by'];
                    $msg_preview = mb_strlen($row['message']) > 80
                        ? mb_substr($row['message'], 0, 80) . '…'
                        : $row['message'];

                    echo '<tr>';
                    echo '<td>' . esc_html($row['sent_at']) . '</td>';
                    echo '<td>' . esc_html($row['node_type']) . '</td>';
                    echo '<td style="max-width:300px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">' . esc_html($msg_preview) . '</td>';
                    echo '<td>' . (int) $row['recipients_count'] . '</td>';
                    echo '<td>' . esc_html($sender_label) . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            } else {
                echo '<p>No updates sent yet.</p>';
            }
        } else {
            echo '<p>History will appear after the first update is sent.</p>';
        }

        echo '</div>';
    }

    public static function handle_send_project_update() {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied');
        }

        if (empty($_POST['incr_project_update_nonce']) || !wp_verify_nonce($_POST['incr_project_update_nonce'], 'incr_project_update')) {
            wp_die('Invalid nonce');
        }

        $node_type = isset($_POST['node_type']) ? sanitize_text_field(wp_unslash($_POST['node_type'])) : '';
        $message   = isset($_POST['pu_message']) ? sanitize_textarea_field(wp_unslash($_POST['pu_message'])) : '';

        $redirect_url = admin_url('admin.php?page=incr-project-updates');

        if (trim($node_type) === '') {
            wp_safe_redirect(add_query_arg('pu_notice', 'empty_node_type', $redirect_url));
            exit;
        }

        if (trim($message) === '') {
            wp_safe_redirect(add_query_arg('pu_notice', 'empty_message', $redirect_url));
            exit;
        }

        // Get bot token
        $token = '';
        if (function_exists('incr_get_config')) {
            $token = incr_get_config('INCR_TELEGRAM_BOT_TOKEN');
        }
        if ($token === '' && defined('INCR_TELEGRAM_BOT_TOKEN')) {
            $token = INCR_TELEGRAM_BOT_TOKEN;
        }
        if ($token === '') {
            $env_value = getenv('INCR_TELEGRAM_BOT_TOKEN');
            if ($env_value !== false) {
                $token = $env_value;
            }
        }

        if (!$token) {
            wp_safe_redirect(add_query_arg('pu_notice', 'no_token', $redirect_url));
            exit;
        }

        // Get recipients
        global $wpdb;
        $nodes_table = $wpdb->prefix . 'incr_nodes';
        $links_table = $wpdb->prefix . 'incr_telegram_links';

        $chat_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT l.telegram_chat_id
             FROM {$nodes_table} n
             INNER JOIN {$links_table} l ON l.wp_user_id = n.user_id
             WHERE n.node_type = %s",
            $node_type
        ));

        if (empty($chat_ids)) {
            wp_safe_redirect(add_query_arg('pu_notice', 'no_recipients', $redirect_url));
            exit;
        }

        // Send to all recipients
        $sent_count = 0;
        foreach ($chat_ids as $chat_id) {
            if (self::send_tg_message($token, $chat_id, $message)) {
                $sent_count++;
            }
        }

        // Log
        self::log_project_update($node_type, $message, $sent_count);

        wp_safe_redirect(add_query_arg('pu_notice', 'sent_' . $sent_count, $redirect_url));
        exit;
    }

    private static function log_project_update($node_type, $message, $recipients_count) {
        global $wpdb;
        $table = $wpdb->prefix . 'incr_project_updates';

        $wpdb->query("CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            node_type VARCHAR(190) NOT NULL,
            message TEXT NOT NULL,
            recipients_count INT UNSIGNED NOT NULL DEFAULT 0,
            sent_by BIGINT(20) UNSIGNED NOT NULL,
            sent_at DATETIME NOT NULL,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $wpdb->insert($table, [
            'node_type'        => $node_type,
            'message'          => $message,
            'recipients_count' => $recipients_count,
            'sent_by'          => get_current_user_id(),
            'sent_at'          => current_time('mysql'),
        ]);
    }

    public static function render_user_profile_section($user) {
        if (!current_user_can('manage_options')) {
            return;
        }

        $nodes = class_exists('INCR_Nodes_Store') ? INCR_Nodes_Store::get_nodes_by_user($user->ID) : [];
        $overdue_count = class_exists('INCR_Nodes_Store') ? INCR_Nodes_Store::get_overdue_count($user->ID) : 0;

        $orders = [];
        $total_spent = 0;
        if (function_exists('wc_get_orders')) {
            $orders = wc_get_orders([
                'customer_id' => $user->ID,
                'limit'       => 20,
                'orderby'     => 'date',
                'order'       => 'DESC',
                'status'      => ['wc-completed', 'wc-processing', 'wc-on-hold', 'wc-pending'],
            ]);
            foreach ($orders as $order) {
                $total_spent += (float) $order->get_total();
            }
        }

        $today = current_time('Y-m-d');
        ?>
        <h2>NodesPlus</h2>
        <style>
            .np-profile-cards { display: flex; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; }
            .np-profile-card { background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 12px 20px; min-width: 120px; }
            .np-profile-card__value { font-size: 24px; font-weight: 600; line-height: 1.2; }
            .np-profile-card__label { font-size: 12px; color: #646970; text-transform: uppercase; }
            .np-profile-card--danger .np-profile-card__value { color: #d63638; }
            .np-profile-table { border-collapse: collapse; width: 100%; }
            .np-profile-table th, .np-profile-table td { padding: 8px 10px; text-align: left; border-bottom: 1px solid #e0e0e0; }
            .np-profile-table th { font-weight: 600; background: #f6f7f7; }
            .np-profile-table tr.np-row-overdue td { color: #d63638; }
            .np-badge { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 12px; font-weight: 500; line-height: 1.4; }
            .np-badge--purchase { background: #e7f0ff; color: #2271b1; }
            .np-badge--prolongation { background: #fef0e5; color: #c45a00; }
            .np-badge--overdue { background: #fcecec; color: #d63638; }
            .np-badge--active { background: #edfaef; color: #1a7a2e; }
        </style>

        <div class="np-profile-cards">
            <div class="np-profile-card">
                <div class="np-profile-card__value"><?php echo count($nodes); ?></div>
                <div class="np-profile-card__label">Total Nodes</div>
            </div>
            <div class="np-profile-card<?php echo $overdue_count > 0 ? ' np-profile-card--danger' : ''; ?>">
                <div class="np-profile-card__value"><?php echo $overdue_count; ?></div>
                <div class="np-profile-card__label">Overdue</div>
            </div>
            <div class="np-profile-card">
                <div class="np-profile-card__value"><?php echo count($orders); ?></div>
                <div class="np-profile-card__label">Total Orders</div>
            </div>
            <div class="np-profile-card">
                <div class="np-profile-card__value">$<?php echo number_format($total_spent, 2); ?></div>
                <div class="np-profile-card__label">Total Spent</div>
            </div>
        </div>

        <h3>Nodes</h3>
        <?php if (empty($nodes)) : ?>
            <p>No nodes found.</p>
        <?php else : ?>
            <table class="np-profile-table widefat">
                <thead>
                    <tr>
                        <th>Node ID</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Due Date</th>
                        <th>Days Left</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($nodes as $node) :
                        $due = $node['due_date'] ? substr($node['due_date'], 0, 10) : null;
                        $is_overdue = $due && $due < $today;
                        $days_left = $due ? (int) ((strtotime($due) - strtotime($today)) / 86400) : null;
                        $status_label = $due ? ($is_overdue ? 'Overdue' : 'Active') : 'Unknown';
                        $badge_class = $is_overdue ? 'np-badge--overdue' : 'np-badge--active';
                    ?>
                        <tr class="<?php echo $is_overdue ? 'np-row-overdue' : ''; ?>">
                            <td><?php echo esc_html($node['node_id']); ?></td>
                            <td><?php echo esc_html($node['node_type'] ?: '-'); ?></td>
                            <td><span class="np-badge <?php echo esc_attr($badge_class); ?>"><?php echo esc_html($status_label); ?></span></td>
                            <td><?php echo esc_html($node['created_at'] ? substr($node['created_at'], 0, 10) : '-'); ?></td>
                            <td><?php echo esc_html($due ?: '-'); ?></td>
                            <td><?php
                                if ($days_left === null) {
                                    echo '-';
                                } elseif ($days_left > 0) {
                                    echo esc_html($days_left . 'd');
                                } elseif ($days_left === 0) {
                                    echo 'Today';
                                } else {
                                    echo esc_html(abs($days_left) . 'd overdue');
                                }
                            ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php
            $dashboard_url = add_query_arg(['page' => 'incr-nodes-dashboard', 'user_id' => $user->ID], admin_url('admin.php'));
            echo '<p><a href="' . esc_url($dashboard_url) . '">View in Nodes Dashboard &rarr;</a></p>';
            ?>
        <?php endif; ?>

        <h3>Order History</h3>
        <?php if (empty($orders)) : ?>
            <p>No orders found.</p>
        <?php else : ?>
            <table class="np-profile-table widefat">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Type</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order) :
                        $order_id = $order->get_id();
                        $order_url = admin_url('post.php?post=' . $order_id . '&action=edit');
                        $items = $order->get_items();
                        $item_names = [];
                        $op_types = [];
                        foreach ($items as $item) {
                            $item_names[] = $item->get_name();
                            $op = $item->get_meta('Operation Type');
                            if ($op && !in_array($op, $op_types, true)) {
                                $op_types[] = $op;
                            }
                        }
                        if (empty($op_types)) {
                            $op_types[] = 'Purchase';
                        }
                    ?>
                        <tr>
                            <td><a href="<?php echo esc_url($order_url); ?>">#<?php echo esc_html($order_id); ?></a></td>
                            <td><?php echo esc_html($order->get_date_created() ? $order->get_date_created()->date('Y-m-d') : '-'); ?></td>
                            <td><?php echo esc_html(wc_get_order_status_name($order->get_status())); ?></td>
                            <td><?php echo esc_html(implode(', ', $item_names) ?: '-'); ?></td>
                            <td>$<?php echo esc_html(number_format((float) $order->get_total(), 2)); ?></td>
                            <td><?php
                                foreach ($op_types as $op_type) {
                                    $badge = $op_type === 'Prolongation' ? 'np-badge--prolongation' : 'np-badge--purchase';
                                    echo '<span class="np-badge ' . esc_attr($badge) . '">' . esc_html($op_type) . '</span> ';
                                }
                            ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif;
    }

}