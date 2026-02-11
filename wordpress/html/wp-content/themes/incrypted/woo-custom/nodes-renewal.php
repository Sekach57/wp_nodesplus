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
        <div id="<?php echo $form_id; ?>" class="nodes-renewal">
            <h3><?= __('Your nodes','incrypted') ?></h3>
            <form id="renewal-form-submit">
                <div class="nodes-renewal__grid">
                    <?php foreach ($products as $product_data):
                        $product = $product_data['product'];
                        $node_id = $product_data['node_id'];
                        $node_name = $product_data['node_name'];
                        $unique_key = $product_data['unique_key'];
                        $created_at = !empty($product_data['created_at']) ? new DateTime($product_data['created_at']) : null;
                        $due_at = !empty($product_data['due_date']) ? new DateTime($product_data['due_date']) : null;
                        $created_date = $created_at ? $created_at->format('d.m.Y') : '-';
                        $due_date = $due_at ? $due_at->format('d.m.Y') : '-';
                        $is_overdue = $due_at ? ($due_at->getTimestamp() < time()) : false;
                        $status_label = $is_overdue ? __('Overdue', 'incrypted') : __('Active', 'incrypted');
                        $card_classes = 'node-card' . ($is_overdue ? ' node-card--status-overdue' : '');

                        // Progress bar percentage
                        if ($created_at && $due_at) {
                            $total_days = max(1, ($due_at->getTimestamp() - $created_at->getTimestamp()) / 86400);
                            $elapsed_days = (time() - $created_at->getTimestamp()) / 86400;
                            $progress_pct = max(0, min(100, ($elapsed_days / $total_days) * 100));
                        } else {
                            $progress_pct = 0;
                        }
                        $help_id = 'node-help-' . preg_replace('/[^a-zA-Z0-9_-]/', '', $unique_key);
                        ?>
                        <div class="<?php echo esc_attr($card_classes); ?>" data-product-id="<?php echo $product->get_id(); ?>">
                            <div class="node-card__header">
                                <div class="node-card__title">
                                    <span class="node-card__project"><?php echo esc_html($node_name); ?></span>
                                    <button type="button" class="node-card__help" aria-expanded="false" aria-controls="<?php echo esc_attr($help_id); ?>">?</button>
                                    <span id="<?php echo esc_attr($help_id); ?>" class="node-card__help-text" hidden><?php esc_html_e('Node ID', 'incrypted'); ?>: <?php echo esc_html($node_id); ?></span>
                                </div>
                                <span class="node-card__status"><?php echo esc_html($status_label); ?></span>
                            </div>
                            <div class="node-card__body">
                                <div class="node-card__meta">
                                    <div class="node-card__meta-row">
                                        <span class="node-card__meta-label"><?php esc_html_e('Created at', 'incrypted'); ?></span>
                                        <span class="node-card__meta-value"><?php echo esc_html($created_date); ?></span>
                                    </div>
                                    <div class="node-card__meta-row">
                                        <span class="node-card__meta-label"><?php esc_html_e('Price', 'incrypted'); ?></span>
                                        <span class="node-card__meta-value"><?php echo wp_kses_post($product->get_price_html()); ?></span>
                                    </div>
                                </div>
                                <div class="node-card__due-block">
                                    <span class="node-card__label"><?php esc_html_e('Due date', 'incrypted'); ?></span>
                                    <span class="node-card__due"><?php echo esc_html($due_date); ?></span>
                                </div>
                            </div>
                            <div class="node-card__progress">
                                <div class="node-card__progress-bar<?php echo $is_overdue ? ' node-card__progress-bar--overdue' : ''; ?>" style="width: <?php echo esc_attr(round($progress_pct, 1)); ?>%"></div>
                            </div>
                            <input type="checkbox"
                                   class="node-card__checkbox"
                                   name="renewal_products[]"
                                   value="<?php echo esc_attr($unique_key); ?>"
                                   data-product-id="<?php echo $product->get_id(); ?>"
                                   data-node="<?php echo esc_attr($node_id); ?>"/>
                        </div>
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
