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
        add_submenu_page(
            'incr-nodes-dashboard',
            'Send TG Message',
            'Send TG Message',
            'manage_options',
            'incr-send-tg-message',
            [__CLASS__, 'render_send_tg_message']
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
                $label = $type_row['node_type'] !== '' ? $type_row['node_type'] : 'â€”';
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
                $status = 'â€”';
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
                            echo '<td>â€”</td>';
                        }
                    } elseif ($key === 'node_type') {
                        $value = isset($row[$key]) ? $row[$key] : '';
                        if ($value) {
                            $filter_url = add_query_arg(['page' => 'incr-nodes-dashboard', 'node_type' => $value], admin_url('admin.php'));
                            echo '<td><a href="' . esc_url($filter_url) . '">' . esc_html($value) . '</a></td>';
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
                'prev_text' => 'Â«',
                'next_text' => 'Â»',
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

        if (empty($upcoming)) {
            echo '<p>No pending notifications.</p>';
        } else {
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
        echo '<option value="">— Select user —</option>';
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

}