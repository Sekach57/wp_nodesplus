<?php
class ProductRenewalSystem
{
    /**
     * Find products by nodes name using ACF field
     * @param array $nodes Array of ["node_id", "node_name"] pairs
     * @return array
     */
    public function findProductsByNodes($nodes) {
        global $wpdb;
        $found_products = [];

        foreach ($nodes as $index => $node_data) {
            if (!is_array($node_data) || count($node_data) < 2) {
                continue;
            }

            $node_id = trim($node_data[0]);
            $node_name = trim($node_data[1]);

            $product_id = $wpdb->get_var($wpdb->prepare("
            SELECT p.ID FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE pm.meta_key = 'acf_node_name'
            AND LOWER(pm.meta_value) = LOWER(%s)
            AND p.post_type = 'product'
            AND p.post_status = 'publish'
            LIMIT 1
        ", $node_name));

            if ($product_id) {
                $product = wc_get_product($product_id);

                if ($product) {
                    $found_products[] = [
                        'product' => $product,
                        'original_index' => $index,
                        'node_id' => $node_id,
                        'node_name' => $product->get_title(),
                        'unique_key' => $product_id . '_' . $node_id,
                        'created_at'  => $node_data[2],
                        'due_date'  => $node_data[3]
                    ];
                }
            }
        }

        return $found_products;
    }

    /**
     * Display renewal form for nodes
     * @param array $nodes Array of ["node_id", "node_name"] pairs
     * @param string $form_id
     */
    public function displayNodesRenewalForm($nodes, $form_id = 'renewal-form') {
        $products = $this->findProductsByNodes($nodes);

        if (empty($products)) {
            echo '<p>' . __('Nodes were not found','incrypted') . '</p>';
            return;
        }

        ?>
        <div id="<?php echo $form_id; ?>" class="renewal-products-form nodes-renewal">
            <?php // Title moved to page-level header to avoid duplication. ?>
            <form id="renewal-form-submit">
                <div class="nodes-renewal__grid">
                <?php foreach ($products as $product_data):
                    $product = $product_data['product'];
                    $node_id = $product_data['node_id'];
                    $node_name = $product_data['node_name'];
                    $unique_key = $product_data['unique_key'];
                    $created_at = '--';
                    if (!empty($product_data['created_at'])) {
                        $date = new DateTime($product_data['created_at']);
                        $created_at = $date->format('d.m.Y');
                    }
                    $due_date = '--';
                    if (!empty($product_data['due_date'])) {
                        $date = new DateTime($product_data['due_date']);
                        $due_date = $date->format('d.m.Y');
                    }
                    $status = $product_data['status'] ?? __('Active', 'incrypted');
                    $status_slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $status));
                    $status_slug = trim($status_slug, '-');
                    if ($status_slug === '') {
                        $status_slug = 'active';
                    }
                    $status_class = 'node-card--status-' . $status_slug;
                    get_template_part(
                        'template-parts/nodes/node-card',
                        null,
                        [
                            'node_id' => $node_id,
                            'node_name' => $node_name,
                            'unique_key' => $unique_key,
                            'product_id' => $product->get_id(),
                            'price_html' => $product->get_price_html(),
                            'created_at' => $created_at,
                            'due_date' => $due_date,
                            'status' => $status,
                            'status_class' => $status_class,
                        ]
                    );
                    ?>
                <?php endforeach; ?>
                </div>

                <div class="renewal-actions">
                    <button type="button" id="select-all-renewals"><?= __('Select all','incrypted') ?></button>
                    <button type="button" id="deselect-all-renewals"><?= __('Deselect all','incrypted') ?></button>
                    <button type="submit" id="add-renewals-to-cart" class="button-primary">
                        <?= __('Add for prolongation','incrypted') ?>
                    </button>
                </div>
            </form>
        </div>
        <?php
    }
}

function incr_theme_get_config( $key, $default = '', $env_key = '' ) {
    if ( function_exists( 'incr_get_config' ) ) {
        return incr_get_config( $key, $default, $env_key );
    }
    if ( defined( $key ) ) {
        $value = constant( $key );
        if ( $value !== '' ) {
            return $value;
        }
    }
    $env_key = $env_key !== '' ? $env_key : $key;
    $env_value = getenv( $env_key );
    if ( $env_value !== false && $env_value !== '' ) {
        return $env_value;
    }
    return $default;
}

add_action('wp_ajax_add_renewal_products_to_cart', 'handle_renewal_products_ajax');
add_action('wp_ajax_nopriv_add_renewal_products_to_cart', 'handle_renewal_products_ajax');

function handle_renewal_products_ajax() {

    if (!wp_verify_nonce($_POST['nonce'], 'renewal_nonce')) {
        $resp = __('Auth token invalid!', 'incrypted');
        wp_send_json_error($resp);
    }

    if (empty($_POST['product_ids']) || !is_array($_POST['product_ids'])) {
        $resp = __('Nodes are not selected', 'incrypted');
        wp_send_json_error($resp);
    }

    $selected_keys = $_POST['product_ids'];

    $added_products = [];
    $skipped_products = [];

    foreach ($selected_keys as $unique_key) {

        $temp = explode('_', $unique_key);
        $product_id = intval($temp[0]);
        $node_id = sanitize_text_field($temp[1]);

        $product = wc_get_product($product_id);
        if (!$product) {
            continue;
        }

        // Check if this specific product + node_id combination already exists in cart
        $already_in_cart = false;
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            if ($cart_item['product_id'] == $product_id &&
                isset($cart_item['is_prolongation']) &&
                $cart_item['is_prolongation'] &&
                isset($cart_item['node_id']) &&
                $cart_item['node_id'] == $node_id) {
                $already_in_cart = true;
                $skipped_products[] = $product->get_name() . ' (Node id: ' . $node_id . ')';
                break;
            }
        }

        // Skip if already in cart
        if ($already_in_cart) {
            continue;
        }

        // Always quantity = 1 for nodes
        $quantity = 1;

        $cart_item_data = [
            'is_prolongation' => true,
            'prolongation_type' => 'renewal',
            'added_for_renewal' => current_time('mysql'),
            'node_id' => $node_id,
            'unique_key' => 'renewal_' . $product_id . '_' . $node_id . '_' . time()
        ];

        $cart_item_key = WC()->cart->add_to_cart(
            $product_id,
            $quantity,
            0,
            [],
            $cart_item_data
        );

        if ($cart_item_key) {
            $added_products[] = $product->get_name() . ' (Node id: ' . $node_id . ')';
        }
    }

    // Form message for user
    $message = '';
    if (!empty($added_products)) {
        $message .= __('Nodes added', 'incrypted') . ': ' . count($added_products);
        if (!empty($skipped_products)) {
            $message .= '. ';
        }
    }

    if (!empty($skipped_products)) {
        $message .= __('Skipped', 'incrypted') . ': ' . implode(', ', $skipped_products);
    }

    if (!empty($added_products) || !empty($skipped_products)) {
        wp_send_json_success([
            'message' => $message,
            'added_products' => $added_products,
            'skipped_products' => $skipped_products
        ]);
    } else {
        $resp = __('Nodes were not added to cart', 'incrypted');
        wp_send_json_error($resp);
    }
    die();
}

// ================================
// Show info in the cart
// ================================

add_filter('woocommerce_get_item_data', function($item_data, $cart_item) {
    if (isset($cart_item['is_prolongation']) && $cart_item['is_prolongation']) {
        $item_data[] = [
            'key' => __('Action', 'incrypted'),
            'value' => '<strong style="color: #e2401c;">' . __('Prolongation', 'incrypted') . '</strong>',
            'display' => ''
        ];

        // Show Node ID if available
        if (isset($cart_item['node_id']) && !empty($cart_item['node_id'])) {
            $item_data[] = [
                'key' => 'Node ID',
                'value' => $cart_item['node_id'],
                'display' => ''
            ];
        }
    } else {
        $item_data[] = [
            'key' => __('Action', 'incrypted'),
            'value' => '<span style="color: #46b450;">' . __('Purchase', 'incrypted') . '</span>',
            'display' => ''
        ];
    }

    return $item_data;
}, 10, 2);

/**
 * Save metadata in order
 */
add_action('woocommerce_checkout_create_order_line_item', function($item, $cart_item_key, $values, $order) {

    if (isset($values['is_prolongation'])) {
        $item->add_meta_data('Is Prolongation', $values['is_prolongation'] ? 'yes' : 'no');
        $item->add_meta_data('Operation Type', $values['is_prolongation'] ? 'Prolongation' : 'Purchase');

        if (isset($values['added_for_renewal'])) {
            $item->add_meta_data('Added For Renewal', $values['added_for_renewal']);
        }

        if (isset($values['node_id']) && !empty($values['node_id'])) {
            $item->add_meta_data('Node ID', $values['node_id']);
        }
    }

}, 10, 4);

// New helper function for nodes
function display_nodes_renewal_form($nodes, $form_id = 'hosting-renewal-form') {
    $renewal_system = new ProductRenewalSystem();
    $renewal_system->displayNodesRenewalForm($nodes, $form_id);
}

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

    $nodes = [];
    $is_prolongation = false;

    foreach ($order->get_items() as $item) {
        $product_name = get_field('acf_node_name', $item->get_product_id());
        $product_name = strtolower(trim($product_name));

        $quantity = $item->get_quantity();
        $is_prolongation = $item->get_meta('Is Prolongation') === 'yes';

        if ($is_prolongation) {
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
            'prolongation' => $is_prolongation,
        ]
    ];

    if (count($nodes) < 1) {
        return;
    }

    $request_url = incr_theme_get_config( 'INCR_IMPORT_URL' );
    // if (get_current_user_id() == 198833) {
    //     $request_url = incr_theme_get_config( 'INCR_IMPORT_URL_DEV' );
    // }
    makeRequest("POST", $request_url, $data);
}

function getNodesByUserID($user_id, $trigger_call = false){
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return [];
    }

    $cache_key = 'Incr_user_nodes_' . $user_id;
    if (!$trigger_call) {
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }
    }

    $api_base = incr_theme_get_config( 'INCR_API_BASE' );
    $request_url = $api_base ? $api_base . '/clients/' . $user_id . '/nodes/' : '';
    if ($request_url === '') {
        error_log('INCR nodes API base missing for user ' . $user_id);
        return class_exists('INCR_Nodes_Store') ? INCR_Nodes_Store::get_nodes_by_user($user_id) : [];
    }

    $request_result = makeRequest("GET", $request_url);
    $decoded = is_string($request_result) ? json_decode($request_result, true) : null;

    if (!is_array($decoded) || (isset($decoded['detail']) && !is_array($decoded[0] ?? null))) {
        $detail = is_array($decoded) && isset($decoded['detail']) ? $decoded['detail'] : 'invalid_response';
        error_log('INCR nodes API failed for user ' . $user_id . ': ' . $detail);
        return class_exists('INCR_Nodes_Store') ? INCR_Nodes_Store::get_nodes_by_user($user_id) : [];
    }

    $nodes_payload = $decoded;
    if (isset($decoded['nodes']) && is_array($decoded['nodes'])) {
        $nodes_payload = $decoded['nodes'];
    }

    $normalized = [];
    foreach ($nodes_payload as $node) {
        if (!is_array($node)) {
            continue;
        }
        $normalized_node = $node;
        if (!array_key_exists('id', $normalized_node)) {
            $normalized_node['id'] = null;
        }
        if (!array_key_exists('node_type', $normalized_node)) {
            $normalized_node['node_type'] = null;
        }
        if (!array_key_exists('created_at', $normalized_node)) {
            $normalized_node['created_at'] = null;
        }
        if (!array_key_exists('due_date', $normalized_node)) {
            $normalized_node['due_date'] = null;
        }
        $normalized[] = $normalized_node;
    }

    if (class_exists('INCR_Nodes_Store')) {
        INCR_Nodes_Store::upsert_nodes($user_id, $normalized, 'api');
    }

    set_transient($cache_key, $normalized, 900);
    return $normalized;
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
    $auth_token = incr_theme_get_config( 'INCR_AUTH_TOKEN' );
    if ( $auth_token === '' ) {
        return new WP_Error( 'missing_auth_token', 'Authorization token missing', [ 'status' => 500 ] );
    }
    if ($auth_header !== $auth_token) {
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

    $dry_run_flag = strtolower( incr_theme_get_config( 'INCR_DRY_RUN_IMPORTS' ) );
    if (
        $method === 'POST'
        && $data
        && function_exists( 'np_env' )
        && np_env() !== 'prod'
        && in_array( $dry_run_flag, [ '1', 'true', 'yes' ], true )
    ) {
        $log('Dry-run enabled: skipping outbound POST.');
        return null;
    }

    $authToken = incr_theme_get_config( 'INCR_AUTH_TOKEN' );
    if ($authTokenSub) $authToken = $authTokenSub;
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HEADER => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
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

add_action('wp_ajax_add_to_cart_custom', 'handle_custom_add_to_cart');
add_action('wp_ajax_nopriv_add_to_cart_custom', 'handle_custom_add_to_cart');

function handle_custom_add_to_cart() {
    check_ajax_referer('custom_cart_nonce', 'nonce');

    $product_id = intval($_POST['product_id']);
    $quantity = intval($_POST['quantity']);

    if ($product_id && $quantity > 0) {
        $result = WC()->cart->add_to_cart($product_id, $quantity);

        if ($result) {
            wp_send_json_success([
                'message' => __('Added to cart', 'incrypted'),
                'cart_count' => WC()->cart->get_cart_contents_count(),
                'cart_total' => WC()->cart->get_cart_total()
            ]);
        } else {
            wp_send_json_error(['message' => 'Error']);
        }
    } else {
        wp_send_json_error(['message' => __('Incorrect parameters', 'incrypted')]);
    }
}

add_action('wp_ajax_get_cart_count', 'get_cart_count');
add_action('wp_ajax_nopriv_get_cart_count', 'get_cart_count');

function get_cart_count() {
    wp_send_json_success([
        'cart_count' => WC()->cart->get_cart_contents_count(),
        'cart_total' => WC()->cart->get_cart_total()
    ]);
}

add_shortcode('custom_nodes_list', 'display_custom_nodes');

function display_custom_nodes($atts) {
    $atts = shortcode_atts([
        'category' => '',
        'ids' => '',
    ], $atts);

    $args = [
        'post_type' => 'product',
        'posts_per_page' => -1,
        'post_status' => 'publish'
    ];

    if ($atts['category']) {
        $args['tax_query'] = [
            [
                'taxonomy' => 'product_cat',
                'field' => 'slug',
                'terms' => $atts['category']
            ]
        ];
    }

    if ($atts['ids']) {
        $args['post__in'] = explode(',', $atts['ids']);
    }

    $products = new WP_Query($args);

    $current_lang = pll_current_language();

    switch ($current_lang) {
        case "en":
            $piece = "pc";
            $pieces = "pcs";
            $more = "More...";
            break;

        default:
            $piece = "шт.";
            $pieces = "шт.";
            $more = "Детальніше...";
            break;
    }

    $nodes = get_field('acf_nodes_block');

    ob_start();
    ?>
    <section id="nodes" class="nodes">
        <div class="container">
            <h2><?= $nodes["header"] ?></h2>
            <div class="node_items">
                <?php while ($products->have_posts()) : $products->the_post();
                    $product = wc_get_product(get_the_ID());
                    $price = $product->get_price_html();
                    $image = wp_get_attachment_image_src(get_post_thumbnail_id(), 'full');
                    ?>
                    <div class="node_item" data-product-id="<?php echo get_the_ID(); ?>">
                        <div class="node_item_info">
                            <div class="node_item_title">
                                <?php if ($image): ?>
                                    <img src="<?php echo esc_url($image[0]); ?>" alt="<?php echo esc_attr(get_the_title()); ?>"/>
                                <?php endif; ?>
                                <?php the_title(); ?>
                            </div>
                            <div class="node_item_description">
                                <?php echo wp_trim_words(get_the_excerpt(), 20); ?>
                            </div>
                        </div>
                        <div class="node_item_price"><b><?php echo $price; ?></b> <?= $pieces ?></div>
                        <div class="node_item_action_btns">
                            <div class="custom_quantity">
                                <div class="minus">-</div>
                                <input type="text" class="node_quantity" placeholder="1" value="1" />
                                <div class="plus">+</div>
                            </div>
                            <button class="btn_2 quantity-btn" data-quantity="1">1 <?= $piece ?></button>
                            <button class="btn_2 quantity-btn" data-quantity="5">5 <?= $pieces ?></button>
                            <button class="btn_2 quantity-btn" data-quantity="10">10 <?= $pieces ?></button>
                            <button class="btn_2 quantity-btn" data-quantity="25">25 <?= $pieces ?></button>

                            <button class="btn add-to-cart-btn"><?= $nodes["buy_now_button_text"] ?></button>
                        </div>
                    </div>
                <?php endwhile; wp_reset_postdata(); ?>
            </div>
            <a href="<?php echo wc_get_checkout_url(); ?>" class="btn start_order" id="checkout-btn"><?= $nodes["start_order_button_text"] ?></a>
        </div>

        <div class="np-modal" id="np-node-modal" aria-hidden="true">
            <div class="np-modal__backdrop" data-np-modal-close></div>
            <div class="np-modal__panel" role="dialog" aria-modal="true" aria-labelledby="np-node-modal-title">
                <div class="np-modal__header">
                    <div class="np-modal__brand">
                        <img class="np-modal__logo" src="" alt="" aria-hidden="true" />
                        <h3 class="np-modal__title" id="np-node-modal-title">Node details</h3>
                    </div>
                    <button type="button" class="np-modal__close" data-np-modal-close aria-label="<?php esc_attr_e( 'Close', 'incrypted' ); ?>">×</button>
                </div>
                <div class="np-modal__body">
                    <div class="np-modal__column np-modal__column--details">
                        <div class="np-modal__card np-modal__details-panel">
                            <div class="np-modal__content">
                                <!-- Content injected via JS -->
                            </div>
                        </div>
                    </div>
                    <div class="np-modal__column np-modal__column--actions">
                        <div class="np-modal__card np-modal__actions-panel">
                            <div class="np-modal__price"></div>
                            <div class="np-modal__social">
                                <a class="np-modal__social-button" href="#" target="_blank" rel="noopener" data-np-modal-discord aria-label="<?php esc_attr_e( 'Discord', 'incrypted' ); ?>">
                                    <svg viewBox="0 -28.5 256 256" aria-hidden="true" focusable="false">
                                        <path d="M216.856339,16.5966031 C200.285002,8.84328665 182.566144,3.2084988 164.041564,0 C161.766523,4.11318106 159.108624,9.64549908 157.276099,14.0464379 C137.583995,11.0849896 118.072967,11.0849896 98.7430163,14.0464379 C96.9108417,9.64549908 94.1925838,4.11318106 91.8971895,0 C73.3526068,3.2084988 55.6133949,8.86399117 39.0420583,16.6376612 C5.61752293,67.146514 -3.4433191,116.400813 1.08711069,164.955721 C23.2560196,181.510915 44.7403634,191.567697 65.8621325,198.148576 C71.0772151,190.971126 75.7283628,183.341335 79.7352139,175.300261 C72.104019,172.400575 64.7949724,168.822202 57.8887866,164.667963 C59.7209612,163.310589 61.5131304,161.891452 63.2445898,160.431257 C105.36741,180.133187 151.134928,180.133187 192.754523,160.431257 C194.506336,161.891452 196.298154,163.310589 198.110326,164.667963 C191.183787,168.842556 183.854737,172.420929 176.223542,175.320965 C180.230393,183.341335 184.861538,190.991831 190.096624,198.16893 C211.238746,191.588051 232.743023,181.531619 254.911949,164.955721 C260.227747,108.668201 245.831087,59.8662432 216.856339,16.5966031 Z M85.4738752,135.09489 C72.8290281,135.09489 62.4592217,123.290155 62.4592217,108.914901 C62.4592217,94.5396472 72.607595,82.7145587 85.4738752,82.7145587 C98.3405064,82.7145587 108.709962,94.5189427 108.488529,108.914901 C108.508531,123.290155 98.3405064,135.09489 85.4738752,135.09489 Z M170.525237,135.09489 C157.88039,135.09489 147.510584,123.290155 147.510584,108.914901 C147.510584,94.5396472 157.658606,82.7145587 170.525237,82.7145587 C183.391518,82.7145587 193.761324,94.5189427 193.539891,108.914901 C193.539891,123.290155 183.391518,135.09489 170.525237,135.09489 Z"></path>
                                    </svg>
                                </a>
                                <a class="np-modal__social-button" href="#" target="_blank" rel="noopener" data-np-modal-telegram aria-label="<?php esc_attr_e( 'Telegram', 'incrypted' ); ?>">
                                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                        <path d="M22 3L2.6 11.1c-.9.4-.9 1.7.1 2l4.8 1.6 2 6.1c.3.9 1.5 1 2 .2l2.9-4 5.2 3.8c.8.6 1.9.1 2.1-.9L24 4.4c.2-1.1-.8-2-2-1.4Z"></path>
                                    </svg>
                                </a>
                                <a class="np-modal__social-button" href="#" target="_blank" rel="noopener" data-np-modal-twitter aria-label="<?php esc_attr_e( 'Twitter', 'incrypted' ); ?>">
                                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                        <path d="M18.9 2H22l-7.3 8.3L23.6 22h-7l-5.5-7.2L4.7 22H1.5l7.8-8.9L.7 2h7.2l4.9 6.4L18.9 2Zm-2 18h2.1L7.1 4h-2L16.9 20Z"></path>
                                    </svg>
                                </a>
                            </div>
                            <a class="np-modal__secondary" href="#" target="_blank" rel="noopener" data-np-modal-guide><?php esc_html_e( 'Installation guide', 'incrypted' ); ?></a>
                            <a class="np-modal__action" href="#" data-np-modal-add-to-cart><?php esc_html_e( 'Add to cart', 'incrypted' ); ?></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php
    return ob_get_clean();
}

/////////////////////////////////////////////////
//// Додати вкладку "ноди" в профілі користувача
//// Показати всі ноди користувача
/////////////////////////////////////////////////
function custom_account_nodes_endpoint() {
    add_rewrite_endpoint( 'nodes', EP_ROOT | EP_PAGES );
}
add_action( 'init', 'custom_account_nodes_endpoint' );

function custom_account_menu_item( $items ) {
    $new_items = array();

    foreach ( $items as $key => $value ) {
        $new_items[$key] = $value;
        if ( $key === 'dashboard' ) {
            $new_items['nodes'] = 'Nodes';
        }
    }

    return $new_items;
}
add_filter( 'woocommerce_account_menu_items', 'custom_account_menu_item' );

function custom_account_nodes_content() {
    $is_empty = false;
    echo '<h2 class="mb-5">' . __('Your nodes', 'incrypted') . '</h2>';

    $user = wp_get_current_user();
    $user_id = $user->ID;
    $user_nodes = getNodesByUserID($user_id);

    if(!empty($user_nodes['detail'])){
        $is_empty = true;
        switch ($user_nodes['detail']) {
            case __('Client not found or has no nodes', 'incrypted'):
            default:
                echo __('Looks like you don’t have any nodes yet', 'incrypted');
                break;
        }
    }

    if(!$is_empty){
        $nodes_to_renew = [];

        foreach ($user_nodes as $node){
            $nodes_to_renew[] = [$node["id"], $node["node_type"], $node["created_at"], $node["due_date"]];
        }

        display_nodes_renewal_form($nodes_to_renew, 'nodes-renewal-form');
    }


}
add_action( 'woocommerce_account_nodes_endpoint', 'custom_account_nodes_content' );

add_filter('pll_the_language_link', function($url, $slug, $locale) {
    if (!function_exists('pll_current_language')) {
        return $url;
    }

    if (empty($url)) {
        return $url;
    }

    $has_nodes = false;

    $gv = get_query_var('nodes', null);
    if ($gv !== null && $gv !== false && $gv !== '') {
        $has_nodes = true;
    }

    if (!$has_nodes) {
        global $wp;
        if (!empty($wp->query_vars) && array_key_exists('nodes', $wp->query_vars)) {
            $has_nodes = true;
        }
    }

    if (!$has_nodes && !empty($_SERVER['REQUEST_URI'])) {
        $request = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
        $parts = explode('/', $request);
        if (in_array('nodes', $parts, true)) {
            $has_nodes = true;
        }
    }

    if ($has_nodes) {
        $path = trim(parse_url($url, PHP_URL_PATH) ?: '', '/');
        if ($path === '' || substr($path, -5) !== 'nodes') {
            $url = rtrim($url, '/') . '/nodes/';
        }
    }

    return $url;
}, 10, 3);


/////////////////////////////////////////////////
//// Інший функціонал по кастомізації woo
/////////////////////////////////////////////////

function remove_product_link_in_order_details( $product_name, $item, $is_visible ) {
    if ( is_wc_endpoint_url( 'view-order' ) ) {
        return $item->get_name();
    }
    return $product_name;
}
add_filter( 'woocommerce_order_item_name', 'remove_product_link_in_order_details', 10, 3 );

add_filter( 'woocommerce_return_to_shop_redirect', 'custom_return_to_shop_url' );
function custom_return_to_shop_url() {
    return home_url('/');
}

function set_custom_main_class() {
    if (
        function_exists( 'is_woocommerce' ) && is_woocommerce()
        || is_cart()
        || is_checkout()
        || is_account_page()
    ) {
        if(is_account_page()){
            return 'custom_woocommerce account_page';
        }
        return 'custom_woocommerce';
    }

    return 'main_page';
}

add_filter( 'woocommerce_cart_item_name', 'cart_item_name_no_link_global', 10, 3 );
function cart_item_name_no_link_global( $product_name, $cart_item, $cart_item_key ) {
    return $cart_item['data']->get_name();
}

add_filter( 'woocommerce_checkout_fields', 'custom_remove_checkout_fields' );
function custom_remove_checkout_fields( $fields ) {

    unset($fields['shipping']['shipping_first_name']);
    unset($fields['shipping']['shipping_last_name']);
    unset($fields['shipping']['shipping_company']);
    unset($fields['shipping']['shipping_address_1']);
    unset($fields['shipping']['shipping_address_2']);
    unset($fields['shipping']['shipping_city']);
    unset($fields['shipping']['shipping_postcode']);
    unset($fields['shipping']['shipping_country']);
    unset($fields['shipping']['shipping_state']);

    unset($fields['billing']['billing_phone']);
    unset($fields['billing']['billing_last_name']);
    unset($fields['billing']['billing_company']);
    unset($fields['billing']['billing_address_1']);
    unset($fields['billing']['billing_address_2']);
    unset($fields['billing']['billing_city']);
    unset($fields['billing']['billing_postcode']);
    unset($fields['billing']['billing_country']);
    unset($fields['billing']['billing_state']);

    return $fields;
}

add_filter( 'gettext', 'custom_translation', 20, 3 );
function custom_translation( $translated_text, $text, $domain ) {
    if ( $translated_text === 'Вже замовляли у нас?' ) {
        $translated_text = 'Вже маєте акаунт у нас на сайті?';
    }
    if ( $translated_text === 'Натисніть тут, щоб увійти' ) {
        $translated_text = 'Увійти в акаунт';
    }
    if ( $translated_text === 'Майстерня' ) {
        $translated_text = 'Dashboard';
    }
    return $translated_text;
}

add_filter( 'woocommerce_checkout_fields', 'remove_order_comments_field' );
function remove_order_comments_field( $fields ) {
    if ( isset( $fields['order']['order_comments'] ) ) {
        unset( $fields['order']['order_comments'] );
    }
    return $fields;
}

add_filter( 'woocommerce_order_item_name', 'remove_product_links_from_thankyou', 10, 3 );
function remove_product_links_from_thankyou( $item_name, $item, $is_visible ) {
    if ( is_order_received_page() ) {
        $item_name = $item->get_name();
    }
    return $item_name;
}

add_filter( 'woocommerce_account_menu_items', 'remove_download_and_address_link' );
function remove_download_and_address_link( $items ) {
    unset( $items['edit-address'] );
    unset( $items['downloads'] );
    return $items;
}

add_filter( 'woocommerce_get_query_vars', 'remove_address_endpoint' );
function remove_address_endpoint( $endpoints ) {
    if ( isset( $endpoints['edit-address'] ) ) {
        unset( $endpoints['edit-address'] );
    }
    return $endpoints;
}

add_filter( 'woocommerce_get_query_vars', 'remove_download_endpoint' );
function remove_download_endpoint( $endpoints ) {
    if ( isset( $endpoints['downloads'] ) ) {
        unset( $endpoints['downloads'] );
    }
    return $endpoints;
}

add_filter('woocommerce_cart_item_quantity', function($product_quantity, $cart_item_key, $cart_item) {

    if (!is_cart()) {
        return $product_quantity;
    }

    if (isset($cart_item['is_prolongation']) && $cart_item['is_prolongation']) {
        $quantity = $cart_item['quantity'];

        return sprintf(
            '<span class="readonly-quantity">%s</span>',
            $quantity
        );
    }

    return $product_quantity;
}, 10, 3);

add_action('woocommerce_cart_loaded_from_session', function() {

    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
        if (isset($cart_item['is_prolongation']) && $cart_item['is_prolongation']) {
            add_filter('woocommerce_cart_item_quantity_' . $cart_item_key, function($quantity, $key) {
                return WC()->cart->get_cart()[$key]['quantity'];
            }, 10, 2);
        }
    }
});

add_filter( 'woocommerce_edit_account_form_fields', 'remove_name_fields_from_edit_account' );
function remove_name_fields_from_edit_account( $fields ) {
    if ( !is_array( $fields ) ) {
        return $fields;
    }

    if ( isset( $fields['account_first_name'] ) ) {
        unset( $fields['account_first_name'] );
    }
    if ( isset( $fields['account_last_name'] ) ) {
        unset( $fields['account_last_name'] );
    }

    return $fields;
}


add_filter( 'woocommerce_edit_account_form', function() {
    remove_action( 'woocommerce_edit_account_form', 'woocommerce_edit_account_form_start', 10 );
});

add_filter( 'woocommerce_save_account_details_required_fields', function( $fields ) {
    unset( $fields['account_first_name'] );
    unset( $fields['account_last_name'] );
    return $fields;
});

add_filter('woocommerce_billing_fields', 'make_billing_fields_optional', 10, 1);
add_filter('woocommerce_shipping_fields', 'make_shipping_fields_optional', 10, 1);

function make_billing_fields_optional($fields) {
    if (isset($fields['billing_first_name'])) {
        $fields['billing_first_name']['required'] = false;
    }
    if (isset($fields['billing_last_name'])) {
        $fields['billing_last_name']['required'] = false;
    }

    if (isset($fields['billing_email'])) {
        $fields['billing_email']['required'] = false;
    }

    return $fields;
}

function make_shipping_fields_optional($fields) {
    if (isset($fields['shipping_first_name'])) {
        $fields['shipping_first_name']['required'] = false;
    }
    if (isset($fields['shipping_last_name'])) {
        $fields['shipping_last_name']['required'] = false;
    }

    return $fields;
}

add_filter('woocommerce_cart_needs_shipping', '__return_false');



/**
 * Автоматически применяет купоны для кастомных ролей
 * с исключением пополнений Terra Wallet.
 */
add_action('woocommerce_before_calculate_totals', 'apply_role_coupon_automatically', 10, 1);

function apply_role_coupon_automatically( $cart ) {
    if ( is_admin() && ! defined('DOING_AJAX') ) return;
    if ( ! is_user_logged_in() ) return;

    $user = wp_get_current_user();

    $roles_and_coupons = get_field('acf_discounts', 'option');

    if(empty($roles_and_coupons) || count($roles_and_coupons) < 1) {
        return;
    }

    $role_coupons = [];

    foreach ($roles_and_coupons as $item) {
        $role_coupons[ $item['user_role'] ] = $item['coupon'];
    }

    $skip = false;
    foreach ( $cart->get_cart() as $cart_item ) {
        $product = $cart_item['data'];

        if (
                $product->get_slug() == "wallet-topup" ||
                $product->get_id() == 289
        ) {
            $skip = true;
            break;
        }
    }

    if ( $skip ) return;

    foreach ( $user->roles as $role ) {
        if ( isset( $role_coupons[$role] ) && ! $cart->has_discount( $role_coupons[$role] ) ) {
            $cart->apply_coupon( $role_coupons[$role] );
        }
    }
}

add_filter('woocommerce_coupons_enabled', 'disable_coupon_field_but_keep_function', 20);

function disable_coupon_field_but_keep_function($enabled) {
    if ( is_cart() || is_checkout() ) {
        return false;
    }
    return $enabled;
}

add_filter('woocommerce_add_message', function($message) {
    if ( stripos($message, 'купон') !== false || stripos($message, 'coupon') !== false ) {
        return '';
    }
    return $message;
}, 10, 1);

add_filter('woocommerce_add_error', function($message) {
    if ( stripos($message, 'купон') !== false || stripos($message, 'coupon') !== false ) {
        return '';
    }
    return $message;
}, 10, 1);


add_filter( 'woocommerce_cart_totals_coupon_html', function( $coupon_html, $coupon ) {
    $discount_amount = WC()->cart->get_coupon_discount_amount( $coupon->get_code(), WC()->cart->display_cart_ex_tax );
    return wc_price( $discount_amount );
}, 10, 2 );

//TerraWallet customization
add_action('woocommerce_order_status_processing', 'custom_referral_commission_on_order', 10, 1);

function custom_referral_commission_on_order($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }

    $user_id = $order->get_user_id();
    if (!$user_id) {
        return;
    }

    $referrer_id = get_user_meta($user_id, '_referral_user_id', true);
    if (empty($referrer_id)) {
        return;
    }

    $settings = get_option('woo_wallet_referrals_settings', []);
    $referral_percent = isset($settings['referring_signups_amount']) ? floatval($settings['referring_signups_amount']) : 1;
    $referral_text    = isset($settings['referring_signups_description']) ? $settings['referring_signups_description'] : 'Referral reward';

    $commission_percent = $referral_percent / 100;
    $order_total = (float) $order->get_total();
    $commission_amount = round($order_total * $commission_percent, 2);

    if (function_exists('woo_wallet')) {
        $wallet = woo_wallet()->wallet;

        $credit_note = $referral_text;
        $credit_transaction_id = $wallet->credit($referrer_id, $commission_amount, $credit_note);

        if ($credit_transaction_id) {
            do_action('woo_wallet_transfer_amount_credited', $credit_transaction_id, $referrer_id, $user_id);
        }
    }
}

add_filter('manage_woocommerce_page_wc-orders_columns', 'add_operation_type_column', 20);
function add_operation_type_column($columns) {
    $new_columns = array();

    foreach ($columns as $key => $column) {
        $new_columns[$key] = $column;

        if ($key === 'origin') {
            $new_columns['operation_type'] = __('Type', 'woocommerce');
        }
    }

    return $new_columns;
}

add_action('manage_woocommerce_page_wc-orders_custom_column', 'fill_operation_type_column', 20, 2);
function fill_operation_type_column($column, $order) {
    if ($column === 'operation_type') {
        $operation_type = '';

        foreach ($order->get_items() as $item) {
            $type = $item->get_meta('Operation Type');
            $product_name = $item->get_name();

            if ($type) {
                $operation_type = $type;
                break;
            }
            if($product_name == "Wallet Topup"){
                $operation_type = "Wallet Topup";
            }
        }

        // Переводим на русский
        switch ($operation_type) {
            case 'Prolongation':
                echo 'Prolongation';
                break;
            case 'Purchase':
                echo 'Purchase';
                break;
            case 'Wallet Topup':
                echo 'Wallet Topup';
                break;
            default:
                echo $operation_type ? $operation_type : 'Purchase';
        }
    }
}

//style lost password page
function add_text_to_lost_password_footer() {
    $current_url = $_SERVER['REQUEST_URI'];

    if ( strpos( $current_url, 'lost-password' ) !== false ) {
        ?>
        <style>
            .custom_woocommerce.account_page .woocommerce {
                flex-direction: column;
                justify-content: flex-start;
            }
        </style>
        <?php
    }
}
add_action( 'wp_footer', 'add_text_to_lost_password_footer' );
