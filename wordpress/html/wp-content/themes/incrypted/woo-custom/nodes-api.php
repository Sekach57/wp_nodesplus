<?php
//add_action('woocommerce_order_status_completed', 'proceed_paid_order');
add_action('woocommerce_order_status_processing', 'proceed_paid_order');

function proceed_paid_order($order_id): void
{
    $order = wc_get_order($order_id);
    if (! $order) {
        return;
    }

    $user_id = $order->get_user_id();

    if (empty($user_id)) {
        return;
    }

    if ($order->get_meta('_np_api_called') === '1') {
        return;
    }
    $order->update_meta_data('_np_api_called', '1');
    $order->save_meta_data();

    $nodes = [];
    $is_all_prolongation = true;
    $has_prolongation = false;

    foreach ($order->get_items() as $item) {
        $product_name = get_field('acf_node_name', $item->get_product_id());
        $product_name = strtolower(trim($product_name));

        $quantity = $item->get_quantity();
        $item_is_prolongation = $item->get_meta('Is Prolongation') === 'yes';
        if ($item_is_prolongation) {
            $has_prolongation = true;
        } else {
            $is_all_prolongation = false;
        }

        if ($item_is_prolongation) {
            $node_id = $item->get_meta('Node ID');

            $node_data = [
                'node_type' => $product_name,
                'quantity' => $quantity,
                'prolongation_ids' => $node_id
            ];

            $nodes[] = $node_data;
        } else {
            $nodes[] = [
                'node_type' => $product_name,
                'quantity' => $quantity
            ];
        }
    }

    $data = [
        [
            'client_id' => $user_id . "",
            'order_id' => $order_id . "",
            'nodes' => $nodes,
            'prolongation' => $has_prolongation,
        ]
    ];

    if (count($nodes) < 1) {
        return;
    }

    $request_url = defined('INCR_IMPORT_URL') ? INCR_IMPORT_URL : '';
    // if (get_current_user_id() == 198833) {
    //     $request_url = defined('INCR_IMPORT_URL_DEV') ? INCR_IMPORT_URL_DEV : '';
    // }
    makeRequest("POST", $request_url, $data);

    // Auto-complete if ALL items are prolongation
    if ($is_all_prolongation && count($nodes) > 0) {
        $order->update_meta_data('_np_auto_completed', '1');
        $order->save_meta_data();
        $order->add_order_note('Order auto-completed: all items are prolongation (due dates updated via API).');
        $order->update_status('completed', '', true);
    }
}

// Suppress 'Completed order' email for auto-completed prolongation orders
add_filter('woocommerce_email_enabled_customer_completed_order', function($enabled, $order) {
    if ($order && $order->get_meta('_np_auto_completed') === '1') {
        return false;
    }
    return $enabled;
}, 10, 2);

function getNodesByUserID($user_id, $trigger_call = false){
    // if ($trigger_call) {
    //     $api_base = defined('INCR_API_BASE') ? INCR_API_BASE : '';
    //     $request_url = $api_base . '/clients/' . $user_id . '/nodes/';
    //     $request_result = makeRequest("GET", $request_url);
    //     $user_nodes = json_decode($request_result, true);
    //     set_transient('Incr_user_nodes_' . $user_id, $user_nodes, 3600);

    //     return;
    // }
    $user_nodes = get_transient('Incr_user_nodes_'.$user_id);
    if ($user_nodes === false) {
        $user_nodes = [];
    }

    if (empty($user_nodes) || $trigger_call) {
        $api_base = defined('INCR_API_BASE') ? INCR_API_BASE : '';
        $request_url = $api_base . '/clients/' . $user_id . '/nodes/';
        $request_result = makeRequest("GET", $request_url);
        $user_nodes = json_decode($request_result, true);
        set_transient('Incr_user_nodes_' . $user_id, $user_nodes, 3600);
    }
    return $user_nodes;
}

function incr_user_has_active_nodes($user_id) {
    $nodes = getNodesByUserID($user_id);
    if (!is_array($nodes) || empty($nodes) || isset($nodes['detail'])) {
        return false;
    }

    $now = current_time('timestamp');
    foreach ($nodes as $node) {
        if (empty($node['due_date'])) {
            continue;
        }
        $timestamp = strtotime($node['due_date']);
        if ($timestamp && $timestamp >= $now) {
            return true;
        }
    }

    return false;
}

add_action('rest_api_init', function () {
    register_rest_route('custom/v1', '/user/(?P<user_id>\d+)', [
        'methods'  => 'GET',
        'callback' => 'get_user_data_endpoint',
        'permission_callback' => 'check_bearer_auth'
        // 'permission_callback' => '__return_true'
    ]);
});
function check_bearer_auth(WP_REST_Request $request) {

    $auth_header = $request->get_header('authorization');
    if (!$auth_header) {
        return new WP_Error('no_auth_header', 'Authorization header missing', ['status' => 401]);
    }
    if ($auth_header !== INCR_AUTH_TOKEN) {
        return new WP_Error('invalid_token', 'Invalid token', ['status' => 403]);
    }

    return true;
}

function get_user_data_endpoint(WP_REST_Request $request) {

    $user_id = (int) $request->get_param('user_id');

    if (!$user_id) {
        return new WP_REST_Response([
            'error' => 'Invalid user_id'
        ], 400);
    }

    $user = get_userdata($user_id);

    if (!$user) {
        return new WP_REST_Response([
            'error' => 'User not found'
        ], 404);
    }
    $nodes = getNodesByUserID($user_id, true);

    $latestNodeTimeStamp = getLatestNodeFromArray($nodes);

    send_user_update('Update timestamp', [
        'user_id' => $user_id,
        'user_nodes_expiration_date' => $latestNodeTimeStamp
    ]);

    return new WP_REST_Response([
        'user_id'    => $user->ID,
        'time' => $latestNodeTimeStamp
    ], 200);
}

function getLatestNodeFromArray($nodes = []) {
    $latestTimestamp = 0;
    foreach ($nodes as $node) {

        if (empty($node['due_date'])) {
            continue;
        }

        $timestamp = strtotime($node['due_date']);

        if ($timestamp > $latestTimestamp) {
            $latestTimestamp = $timestamp;
        }
    }
    return $latestTimestamp;
}

function makeRequest(string $method, string $url, ?array $data = null, $authTokenSub = null)
{
    $logFile = WP_CONTENT_DIR . '/api-requests.log';
    $log = function($message) use ($logFile) {
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
    };
    $log('=== API REQUEST ===');
    $log('Method: ' . $method);
    $log('URL: ' . $url);

    if ($data) {
        $log('Request Data: ' . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    $authToken = defined('INCR_AUTH_TOKEN') ? INCR_AUTH_TOKEN : '';
    if ($authTokenSub) $authToken = $authTokenSub;
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST    => $method,
        CURLOPT_HEADER           => true,
        CURLOPT_RETURNTRANSFER   => true,
        CURLOPT_CONNECTTIMEOUT   => 5,
        CURLOPT_TIMEOUT          => 10,
        CURLOPT_HTTPHEADER       => [
            'Content-Type: application/json',
            'Authorization: ' . $authToken
        ]
    ]);

    if ($method === 'POST' && $data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);

    if (curl_error($ch)) {
        error_log('CURL Error: ' . curl_error($ch));
        $log('CURL Error: ' . curl_error($ch));
        curl_close($ch);
        return null;
    }

    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $body = substr($response, $headerSize);

    $log('=== API RESPONSE ===');
    $log('HTTP Code: ' . $httpCode);
    $log('Response Body: ' . $body);
    $log('==================');

    return $body;
}
