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

        // Schedule twice daily sync if not already scheduled
        if (!wp_next_scheduled('incr_sync_all_nodes_job')) {
            wp_schedule_event(time(), 'twicedaily', 'incr_sync_all_nodes_job');
        }
    }

    public static function add_menu() {
        add_menu_page(
            'Nodes Dashboard',
            'NodesPlus',
            'manage_options',
            'incr-nodes-dashboard',
            [__CLASS__, 'render_dashboard'],
            'dashicons-networking',
            56
        );
        add_submenu_page(
            'incr-nodes-dashboard',
            'Nodes Dashboard',
            'Nodes Dashboard',
            'manage_options',
            'incr-nodes-dashboard',
            [__CLASS__, 'render_dashboard']
        );
        add_submenu_page(
            'incr-nodes-dashboard',
            'Telegram Links',
            'Telegram Links',
            'manage_options',
            'incr-telegram-links',
            [__CLASS__, 'render_telegram_links']
        );
        add_submenu_page(
            'incr-nodes-dashboard',
            'TG Notifications',
            'TG Notifications',
            'manage_options',
            'incr-tg-notifications',
            [__CLASS__, 'render_telegram_notifications']
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

    public static function render_dashboard() {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied');
        }

        $filters = [
            'user_id' => isset($_GET['user_id']) ? (int) $_GET['user_id'] : '',
            'node_type' => isset($_GET['node_type']) ? sanitize_text_field(wp_unslash($_GET['node_type'])) : '',
            'status' => isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '',
            'due_from' => isset($_GET['due_from']) ? sanitize_text_field(wp_unslash($_GET['due_from'])) : '',
            'due_to' => isset($_GET['due_to']) ? sanitize_text_field(wp_unslash($_GET['due_to'])) : '',
        ];

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
        echo '<label>User ID<br><input type="number" name="user_id" value="' . esc_attr($filters['user_id']) . '" style="width:140px;"></label>';
        echo '<label>Node Type<br><select name="node_type"><option value="">All</option>';
        foreach ($node_types as $type) {
            $selected = $filters['node_type'] === $type ? ' selected' : '';
            echo '<option value="' . esc_attr($type) . '"' . $selected . '>' . esc_html($type) . '</option>';
        }
        echo '</select></label>';
        echo '<label>Status<br><select name="status">';
        $status_options = ['' => 'All', 'active' => 'Active', 'overdue' => 'Overdue'];
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
                $status = '—';
                if (!empty($due_date)) {
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
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $notifications_table));

        echo '<div class="wrap">';
        echo '<h1>TG Notifications</h1>';

        if ($table_exists !== $notifications_table) {
            echo '<div class="notice notice-warning"><p>Telegram notifications table not found.</p></div>';
            echo '</div>';
            return;
        }

        $per_page = 20;
        $page = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $offset = ($page - 1) * $per_page;

        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$notifications_table}");
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    n.id, n.wp_user_id, n.node_id, n.notif_type, n.sent_at,
                    u.user_email, u.display_name,
                    l.tg_username
                 FROM {$notifications_table} n
                 LEFT JOIN {$wpdb->users} u ON u.ID = n.wp_user_id
                 LEFT JOIN {$links_table} l ON l.wp_user_id = n.wp_user_id
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
        echo '<th>Node ID</th>';
        echo '<th>Type</th>';
        echo '</tr></thead><tbody>';

        if (empty($rows)) {
            echo '<tr><td colspan="6">No notifications found.</td></tr>';
        } else {
            foreach ($rows as $row) {
                $user_label = $row['display_name'] ? $row['display_name'] : ('User #' . $row['wp_user_id']);
                $profile_url = admin_url('user-edit.php?user_id=' . (int) $row['wp_user_id']);
                $tg_display = $row['tg_username'] ? ('@' . $row['tg_username']) : '-';
                $type_label = isset($notif_type_labels[$row['notif_type']]) ? $notif_type_labels[$row['notif_type']] : $row['notif_type'];

                echo '<tr>';
                echo '<td>' . esc_html($row['sent_at']) . '</td>';
                echo '<td><a href="' . esc_url($profile_url) . '">' . esc_html($user_label) . '</a></td>';
                echo '<td>' . esc_html($row['user_email'] ?: '-') . '</td>';
                echo '<td>' . esc_html($tg_display) . '</td>';
                echo '<td>' . esc_html($row['node_id']) . '</td>';
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
}