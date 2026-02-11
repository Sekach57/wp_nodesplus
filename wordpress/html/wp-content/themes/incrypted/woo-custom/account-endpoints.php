<?php
/////////////////////////////////////////////////
//// ?????? ??????? "????" ? ??????? ???????????
//// ???????? ??? ???? ???????????
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
    echo '<h2 class="mb-5">' . __('Nodes', 'incrypted') . '</h2>';

    $user = wp_get_current_user();
    $user_id = $user->ID;
    $user_nodes = getNodesByUserID($user_id);

    if(!empty($user_nodes['detail'])){
        $is_empty = true;
        switch ($user_nodes['detail']) {
            case __('Client not found or has no nodes', 'incrypted'):
            default:
                echo __('Looks like you don\'t have any nodes yet', 'incrypted');
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
