<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class INCR_Nodes_Report {

    private static $transient_key = 'incr_report_last_data';

    public static function init() {
        add_action('admin_post_incr_generate_report', [__CLASS__, 'handle_generate_report']);
        add_action('admin_post_incr_export_report_csv', [__CLASS__, 'handle_export_csv']);
        add_action('admin_post_incr_export_report_excel', [__CLASS__, 'handle_export_excel']);
    }

    // -------------------------------------------------------------------------
    //  Render
    // -------------------------------------------------------------------------

    public static function render_report() {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied');
        }

        $notice = isset($_GET['incr_report_notice']) ? sanitize_text_field($_GET['incr_report_notice']) : '';
        $data   = get_transient(self::$transient_key);

        echo '<div class="wrap">';
        echo '<h1>NodesPlus Report</h1>';

        // Notice
        if ($notice === 'generated') {
            echo '<div class="notice notice-success is-dismissible"><p>Report generated successfully.</p></div>';
        } elseif ($notice === 'sync_error') {
            echo '<div class="notice notice-error is-dismissible"><p>Sync encountered errors. Report generated with available data.</p></div>';
        }

        // Generate button
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin: 15px 0;">';
        echo '<input type="hidden" name="action" value="incr_generate_report">';
        wp_nonce_field('incr_generate_report', 'incr_report_nonce');
        echo '<button type="submit" class="button button-primary button-hero">Generate Report</button>';
        if ($data) {
            echo '<span style="margin-left:15px;color:#666;">Last generated: ' . esc_html($data['generated_at'] ?? '—') . '</span>';
        }
        echo '</form>';

        if (!$data) {
            echo '<p>Click <strong>Generate Report</strong> to sync data from API and build the report.</p>';
            echo '</div>';
            return;
        }

        $stats      = $data['stats'] ?? [];
        $overdue    = $data['overdue'] ?? [];
        $user_cache = $data['user_cache'] ?? [];

        // Summary cards
        self::render_summary_cards($stats);

        // Export buttons
        echo '<div style="margin: 20px 0;">';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-block;margin-right:10px;">';
        echo '<input type="hidden" name="action" value="incr_export_report_csv">';
        wp_nonce_field('incr_export_report_csv', 'incr_export_nonce');
        echo '<button type="submit" class="button">Export CSV</button>';
        echo '</form>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-block;">';
        echo '<input type="hidden" name="action" value="incr_export_report_excel">';
        wp_nonce_field('incr_export_report_excel', 'incr_export_nonce_xls');
        echo '<button type="submit" class="button">Export Excel</button>';
        echo '</form>';
        echo '</div>';

        // Overdue table
        self::render_overdue_table($overdue, $user_cache);

        echo '</div>';
    }

    private static function render_summary_cards($stats) {
        $cards = [
            ['label' => 'Current Nodes', 'value' => $stats['total'] ?? 0,          'color' => '#2271b1'],
            ['label' => 'Active',        'value' => $stats['active'] ?? 0,         'color' => '#00a32a'],
            ['label' => 'Overdue',       'value' => $stats['overdue'] ?? 0,        'color' => '#d63638'],
            ['label' => 'Cancelled (mo)','value' => $stats['archived_month'] ?? 0, 'color' => '#996800'],
            ['label' => 'New (30d)',     'value' => $stats['new_30d'] ?? 0,        'color' => '#dba617'],
            ['label' => 'Renewals',      'value' => $stats['renewals'] ?? 0,       'color' => '#8c5dcc'],
        ];

        echo '<div style="display:flex;gap:12px;flex-wrap:wrap;margin:20px 0;">';
        foreach ($cards as $card) {
            echo '<div style="background:#fff;border:1px solid #c3c4c7;border-top:4px solid ' . esc_attr($card['color']) . ';padding:15px 25px;min-width:120px;text-align:center;">';
            echo '<div style="font-size:28px;font-weight:700;color:' . esc_attr($card['color']) . ';">' . (int) $card['value'] . '</div>';
            echo '<div style="font-size:13px;color:#50575e;margin-top:4px;">' . esc_html($card['label']) . '</div>';
            echo '</div>';
        }
        echo '</div>';

        // By type table
        $by_type = $stats['by_type'] ?? [];
        if (!empty($by_type)) {
            echo '<h3>Breakdown by Type</h3>';
            echo '<table class="widefat fixed striped" style="max-width:600px;">';
            echo '<thead><tr><th>Type</th><th>Total</th><th>Active</th><th>Overdue</th></tr></thead><tbody>';
            foreach ($by_type as $type => $counts) {
                echo '<tr>';
                echo '<td><strong>' . esc_html($type ?: '(unknown)') . '</strong></td>';
                echo '<td>' . (int) ($counts['total'] ?? 0) . '</td>';
                echo '<td>' . (int) ($counts['active'] ?? 0) . '</td>';
                echo '<td>' . (int) ($counts['overdue'] ?? 0) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
    }

    private static function render_overdue_table($items, $user_cache) {
        $count = count($items);
        echo '<h2>Overdue Nodes (' . $count . ')</h2>';

        if ($count === 0) {
            echo '<p>No overdue nodes found.</p>';
            return;
        }

        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>Node ID</th><th>User Email</th><th>User ID</th><th>Node Type</th>';
        echo '<th>Due Date</th><th>Created At</th><th>Status</th><th>Days Overdue</th>';
        echo '<th>Price</th><th>Billing Period</th>';
        echo '</tr></thead><tbody>';

        $now = current_time('timestamp');

        foreach ($items as $node) {
            $user_id = (int) ($node['user_id'] ?? 0);
            $email   = $user_cache[$user_id] ?? '—';

            $due_date    = $node['due_date'] ?? '';
            $days_overdue = 0;
            if ($due_date) {
                $due_ts = strtotime($due_date);
                if ($due_ts && $due_ts < $now) {
                    $days_overdue = (int) floor(($now - $due_ts) / DAY_IN_SECONDS);
                }
            }

            echo '<tr>';
            echo '<td>' . esc_html($node['node_id'] ?? '') . '</td>';
            echo '<td>' . esc_html($email) . '</td>';
            echo '<td>' . $user_id . '</td>';
            echo '<td>' . esc_html($node['node_type'] ?? '') . '</td>';
            echo '<td>' . esc_html($due_date ? date('Y-m-d', strtotime($due_date)) : '—') . '</td>';
            echo '<td>' . esc_html(!empty($node['created_at']) ? date('Y-m-d', strtotime($node['created_at'])) : '—') . '</td>';
            echo '<td>' . esc_html($node['status'] ?? '') . '</td>';
            echo '<td style="color:#d63638;font-weight:600;">' . $days_overdue . '</td>';
            echo '<td>' . esc_html($node['price'] ?? '—') . '</td>';
            echo '<td>' . esc_html($node['billing_period'] ?? '—') . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    // -------------------------------------------------------------------------
    //  Data
    // -------------------------------------------------------------------------

    public static function get_report_data() {
        $all_result = INCR_Nodes_Store::get_all_nodes([
            'per_page' => 99999,
            'orderby'  => 'due_date',
            'order'    => 'ASC',
        ]);
        $all_nodes = $all_result['items'] ?? [];

        $overdue_result = INCR_Nodes_Store::get_all_nodes([
            'per_page'     => 99999,
            'overdue_only' => true,
            'orderby'      => 'due_date',
            'order'        => 'ASC',
        ]);
        $overdue = $overdue_result['items'] ?? [];

        // Build user email cache
        $user_ids = array_unique(array_map(function ($n) {
            return (int) $n['user_id'];
        }, $overdue));

        $user_cache = [];
        foreach ($user_ids as $uid) {
            if ($uid <= 0) continue;
            $user = get_userdata($uid);
            $user_cache[$uid] = $user ? $user->user_email : '(deleted)';
        }

        $stats = self::build_statistics($all_nodes);
        $stats['archived_month'] = self::count_archived_this_month();

        return [
            'stats'        => $stats,
            'overdue'      => $overdue,
            'user_cache'   => $user_cache,
            'generated_at' => current_time('Y-m-d H:i:s'),
        ];
    }

    private static function build_statistics($all_nodes) {
        $now_ts    = current_time('timestamp');
        $thirty_ago = strtotime('-30 days', $now_ts);

        $total    = count($all_nodes);
        $active   = 0;
        $overdue  = 0;
        $new_30d  = 0;
        $renewals = 0;
        $by_type  = [];

        foreach ($all_nodes as $node) {
            $type = $node['node_type'] ?? '(unknown)';
            if (!isset($by_type[$type])) {
                $by_type[$type] = ['total' => 0, 'active' => 0, 'overdue' => 0];
            }
            $by_type[$type]['total']++;

            $due_date = $node['due_date'] ?? '';
            $is_overdue = false;
            if ($due_date) {
                $due_ts = strtotime($due_date);
                if ($due_ts && $due_ts < $now_ts) {
                    $is_overdue = true;
                }
            }

            if ($is_overdue) {
                $overdue++;
                $by_type[$type]['overdue']++;
            } else {
                $active++;
                $by_type[$type]['active']++;
            }

            // New in last 30 days
            $created = $node['created_at'] ?? '';
            if ($created) {
                $created_ts = strtotime($created);
                if ($created_ts && $created_ts >= $thirty_ago) {
                    $new_30d++;
                }
            }

            // Count renewals — billing_period set indicates renewal-capable node
            $bp = $node['billing_period'] ?? '';
            if ($bp !== '' && $bp !== null) {
                $renewals++;
            }
        }

        ksort($by_type);

        return [
            'total'    => $total,
            'active'   => $active,
            'overdue'  => $overdue,
            'new_30d'  => $new_30d,
            'renewals' => $renewals,
            'by_type'  => $by_type,
        ];
    }

    private static function count_archived_this_month() {
        global $wpdb;
        $table = INCR_Nodes_Store::table_name();
        $month_start = date('Y-m-01 00:00:00');
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE is_archived = 1 AND archived_at >= %s",
            $month_start
        ));
    }

    // -------------------------------------------------------------------------
    //  Handlers
    // -------------------------------------------------------------------------

    public static function handle_generate_report() {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied');
        }
        if (empty($_POST['incr_report_nonce']) || !wp_verify_nonce($_POST['incr_report_nonce'], 'incr_generate_report')) {
            wp_die('Invalid nonce');
        }

        // Sync all nodes from API
        $notice = 'generated';
        if (class_exists('INCR_Nodes_Admin') && method_exists('INCR_Nodes_Admin', 'run_sync_all_nodes')) {
            $sync_result = INCR_Nodes_Admin::run_sync_all_nodes();
            if (is_array($sync_result) && !empty($sync_result['errors'])) {
                $notice = 'sync_error';
            }
        }

        // Build report data and cache
        $data = self::get_report_data();
        set_transient(self::$transient_key, $data, HOUR_IN_SECONDS);

        wp_safe_redirect(add_query_arg('incr_report_notice', $notice, admin_url('admin.php?page=incr-nodes-report')));
        exit;
    }

    public static function handle_export_csv() {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied');
        }
        if (empty($_POST['incr_export_nonce']) || !wp_verify_nonce($_POST['incr_export_nonce'], 'incr_export_report_csv')) {
            wp_die('Invalid nonce');
        }

        $data = get_transient(self::$transient_key);
        if (!$data) {
            $data = self::get_report_data();
        }

        $stats      = $data['stats'] ?? [];
        $overdue    = $data['overdue'] ?? [];
        $user_cache = $data['user_cache'] ?? [];
        $now_ts     = current_time('timestamp');

        $filename = 'nodesplus-report-' . date('Y-m-d-His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');

        $output = fopen('php://output', 'w');
        // UTF-8 BOM for Excel
        fwrite($output, "\xEF\xBB\xBF");

        // Summary section
        fputcsv($output, ['=== SUMMARY ===']);
        fputcsv($output, ['Metric', 'Value']);
        fputcsv($output, ['Current Nodes', $stats['total'] ?? 0]);
        fputcsv($output, ['Active', $stats['active'] ?? 0]);
        fputcsv($output, ['Overdue', $stats['overdue'] ?? 0]);
        fputcsv($output, ['Cancelled This Month', $stats['archived_month'] ?? 0]);
        fputcsv($output, ['New (30 days)', $stats['new_30d'] ?? 0]);
        fputcsv($output, ['Renewals', $stats['renewals'] ?? 0]);
        fputcsv($output, ['Generated', $data['generated_at'] ?? '']);
        fputcsv($output, []);

        // By type
        $by_type = $stats['by_type'] ?? [];
        if (!empty($by_type)) {
            fputcsv($output, ['=== BY TYPE ===']);
            fputcsv($output, ['Type', 'Total', 'Active', 'Overdue']);
            foreach ($by_type as $type => $counts) {
                fputcsv($output, [$type ?: '(unknown)', $counts['total'] ?? 0, $counts['active'] ?? 0, $counts['overdue'] ?? 0]);
            }
            fputcsv($output, []);
        }

        // Overdue detail
        fputcsv($output, ['=== OVERDUE NODES ===']);
        fputcsv($output, ['Node ID', 'User Email', 'User ID', 'Node Type', 'Due Date', 'Created At', 'Status', 'Days Overdue', 'Price', 'Billing Period']);

        foreach ($overdue as $node) {
            $uid   = (int) ($node['user_id'] ?? 0);
            $email = $user_cache[$uid] ?? '—';

            $due_date     = $node['due_date'] ?? '';
            $days_overdue = 0;
            if ($due_date) {
                $due_ts = strtotime($due_date);
                if ($due_ts && $due_ts < $now_ts) {
                    $days_overdue = (int) floor(($now_ts - $due_ts) / DAY_IN_SECONDS);
                }
            }

            fputcsv($output, [
                $node['node_id'] ?? '',
                $email,
                $uid,
                $node['node_type'] ?? '',
                $due_date ? date('Y-m-d', strtotime($due_date)) : '',
                !empty($node['created_at']) ? date('Y-m-d', strtotime($node['created_at'])) : '',
                $node['status'] ?? '',
                $days_overdue,
                $node['price'] ?? '',
                $node['billing_period'] ?? '',
            ]);
        }

        fclose($output);
        exit;
    }

    public static function handle_export_excel() {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied');
        }
        if (empty($_POST['incr_export_nonce_xls']) || !wp_verify_nonce($_POST['incr_export_nonce_xls'], 'incr_export_report_excel')) {
            wp_die('Invalid nonce');
        }

        $data = get_transient(self::$transient_key);
        if (!$data) {
            $data = self::get_report_data();
        }

        $stats      = $data['stats'] ?? [];
        $overdue    = $data['overdue'] ?? [];
        $user_cache = $data['user_cache'] ?? [];
        $now_ts     = current_time('timestamp');

        $filename = 'nodesplus-report-' . date('Y-m-d-His') . '.xls';

        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"';
        $xml .= ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";

        // Styles
        $xml .= '<Styles>';
        $xml .= '<Style ss:ID="Default"><Font ss:Size="11"/></Style>';
        $xml .= '<Style ss:ID="Header"><Font ss:Bold="1" ss:Size="11"/><Interior ss:Color="#D9E1F2" ss:Pattern="Solid"/></Style>';
        $xml .= '<Style ss:ID="SectionHeader"><Font ss:Bold="1" ss:Size="13"/></Style>';
        $xml .= '</Styles>';

        // Sheet 1: Summary
        $xml .= '<Worksheet ss:Name="Summary">' . "\n";
        $xml .= '<Table>' . "\n";

        $xml .= self::excel_row(['NodesPlus Report — Summary'], 'SectionHeader');
        $xml .= self::excel_row(['Generated', $data['generated_at'] ?? '']);
        $xml .= self::excel_row([]);

        $xml .= self::excel_row(['Metric', 'Value'], 'Header');
        $xml .= self::excel_row(['Current Nodes', $stats['total'] ?? 0], '', [false, true]);
        $xml .= self::excel_row(['Active', $stats['active'] ?? 0], '', [false, true]);
        $xml .= self::excel_row(['Overdue', $stats['overdue'] ?? 0], '', [false, true]);
        $xml .= self::excel_row(['Cancelled This Month', $stats['archived_month'] ?? 0], '', [false, true]);
        $xml .= self::excel_row(['New (30 days)', $stats['new_30d'] ?? 0], '', [false, true]);
        $xml .= self::excel_row(['Renewals', $stats['renewals'] ?? 0], '', [false, true]);
        $xml .= self::excel_row([]);

        $by_type = $stats['by_type'] ?? [];
        if (!empty($by_type)) {
            $xml .= self::excel_row(['Breakdown by Type'], 'SectionHeader');
            $xml .= self::excel_row(['Type', 'Total', 'Active', 'Overdue'], 'Header');
            foreach ($by_type as $type => $counts) {
                $xml .= self::excel_row([
                    $type ?: '(unknown)',
                    $counts['total'] ?? 0,
                    $counts['active'] ?? 0,
                    $counts['overdue'] ?? 0,
                ], '', [false, true, true, true]);
            }
        }

        $xml .= '</Table></Worksheet>' . "\n";

        // Sheet 2: Overdue Nodes
        $xml .= '<Worksheet ss:Name="Overdue Nodes">' . "\n";
        $xml .= '<Table>' . "\n";

        $xml .= self::excel_row([
            'Node ID', 'User Email', 'User ID', 'Node Type',
            'Due Date', 'Created At', 'Status', 'Days Overdue',
            'Price', 'Billing Period',
        ], 'Header');

        foreach ($overdue as $node) {
            $uid   = (int) ($node['user_id'] ?? 0);
            $email = $user_cache[$uid] ?? '—';

            $due_date     = $node['due_date'] ?? '';
            $days_overdue = 0;
            if ($due_date) {
                $due_ts = strtotime($due_date);
                if ($due_ts && $due_ts < $now_ts) {
                    $days_overdue = (int) floor(($now_ts - $due_ts) / DAY_IN_SECONDS);
                }
            }

            $xml .= self::excel_row([
                $node['node_id'] ?? '',
                $email,
                $uid,
                $node['node_type'] ?? '',
                $due_date ? date('Y-m-d', strtotime($due_date)) : '',
                !empty($node['created_at']) ? date('Y-m-d', strtotime($node['created_at'])) : '',
                $node['status'] ?? '',
                $days_overdue,
                $node['price'] ?? '',
                $node['billing_period'] ?? '',
            ], '', [false, false, true, false, false, false, false, true, true, false]);
        }

        $xml .= '</Table></Worksheet>' . "\n";
        $xml .= '</Workbook>';

        echo $xml;
        exit;
    }

    private static function excel_row($cells, $style = '', $is_number = []) {
        $row = '<Row>';
        foreach ($cells as $i => $val) {
            $style_attr = $style ? ' ss:StyleID="' . $style . '"' : '';
            $numeric    = isset($is_number[$i]) && $is_number[$i] && is_numeric($val);
            $type       = $numeric ? 'Number' : 'String';
            $safe_val   = $numeric ? $val : htmlspecialchars((string) $val, ENT_XML1, 'UTF-8');
            $row       .= '<Cell' . $style_attr . '><Data ss:Type="' . $type . '">' . $safe_val . '</Data></Cell>';
        }
        $row .= '</Row>' . "\n";
        return $row;
    }
}
