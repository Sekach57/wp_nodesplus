<?php
/////////////////////////////////////////////////
//// ????? ?????????? ?? ???????????? woo
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
    if ( $translated_text === '??? ????????? ? ????' ) {
        $translated_text = '??? ????? ?????? ? ??? ?? ??????';
    }
    if ( $translated_text === '????????? ???, ??? ??????' ) {
        $translated_text = '?????? ? ??????';
    }
    if ( $translated_text === '?????????' ) {
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
 * ????????????? ????????? ?????? ??? ????????? ?????
 * ? ??????????? ?????????? Terra Wallet.
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
    if ( stripos($message, '?????') !== false || stripos($message, 'coupon') !== false ) {
        return '';
    }
    return $message;
}, 10, 1);

add_filter('woocommerce_add_error', function($message) {
    if ( stripos($message, '?????') !== false || stripos($message, 'coupon') !== false ) {
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

        // ????????? ?? ???????
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
