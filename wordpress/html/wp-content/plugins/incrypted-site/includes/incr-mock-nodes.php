<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function incr_mock_nodes_enabled( $user_id ) {
    $flag = get_user_meta( $user_id, 'incr_mock_nodes_enabled', true );
    if ( $flag === '' ) {
        return true;
    }
    return $flag === '1';
}

function incr_handle_mock_nodes_toggle() {
    if ( ! incr_is_local_env() || ! ( defined( 'INCR_USE_MOCK_NODES' ) && INCR_USE_MOCK_NODES ) ) {
        return;
    }

    if ( ! is_user_logged_in() ) {
        return;
    }

    if ( empty( $_GET['state'] ) || empty( $_GET['_mock_nodes_nonce'] ) ) {
        return;
    }

    if ( ! wp_verify_nonce( $_GET['_mock_nodes_nonce'], 'incr_mock_nodes_toggle' ) ) {
        return;
    }

    $value = sanitize_text_field( wp_unslash( $_GET['state'] ) );
    $enabled = $value === '1';
    update_user_meta( get_current_user_id(), 'incr_mock_nodes_enabled', $enabled ? '1' : '0' );

    if ( ! $enabled ) {
        delete_transient( 'Incr_user_nodes_' . get_current_user_id() );
    }

    $redirect = wc_get_account_endpoint_url( 'nodes' );
    wp_safe_redirect( $redirect );
    exit;
}
add_action( 'admin_post_incr_toggle_mock_nodes', 'incr_handle_mock_nodes_toggle' );

function incr_render_mock_nodes_toggle() {
    if ( ! incr_is_local_env() || ! ( defined( 'INCR_USE_MOCK_NODES' ) && INCR_USE_MOCK_NODES ) ) {
        return;
    }

    if ( ! is_user_logged_in() ) {
        return;
    }

    $user_id = get_current_user_id();
    $enabled = incr_mock_nodes_enabled( $user_id );
    $nonce = wp_create_nonce( 'incr_mock_nodes_toggle' );
    $toggle_url = add_query_arg(
        [
            'action' => 'incr_toggle_mock_nodes',
            'state' => $enabled ? '0' : '1',
            '_mock_nodes_nonce' => $nonce,
        ],
        admin_url( 'admin-post.php' )
    );

    $label = $enabled ? 'Mock nodes: ON' : 'Mock nodes: OFF';
    $action = $enabled ? 'Hide test nodes' : 'Show test nodes';

    echo '<div class="mock-nodes-toggle" style="margin: 12px 0;">';
    echo '<strong>' . esc_html( $label ) . '</strong> ';
    echo '<a href="' . esc_url( $toggle_url ) . '">' . esc_html( $action ) . '</a>';
    echo '</div>';
}
// Mock nodes toggle UI removed; keep mock data active.

function incr_normalize_acf_node_names_local() {
    if ( ! incr_is_local_env() || ! ( defined( 'INCR_USE_MOCK_NODES' ) && INCR_USE_MOCK_NODES ) ) {
        return;
    }

    if ( get_option( 'incr_acf_node_name_trimmed' ) ) {
        return;
    }

    global $wpdb;
    $wpdb->query(
        "UPDATE {$wpdb->postmeta}
        SET meta_value = TRIM(meta_value)
        WHERE meta_key = 'acf_node_name'"
    );

    update_option( 'incr_acf_node_name_trimmed', 1, false );
}
add_action( 'init', 'incr_normalize_acf_node_names_local', 5 );

function incr_get_mock_nodes( $user_id ) {
    $now = current_time( 'mysql' );

    return [
        [
            'id' => 'NOD-GEN-1001',
            'node_type' => 'Gensyn',
            'status' => 'active',
            'created_at' => date( 'Y-m-d H:i:s', strtotime( '-40 days', strtotime( $now ) ) ),
            'due_date' => date( 'Y-m-d H:i:s', strtotime( '+20 days', strtotime( $now ) ) ),
            'price' => '49.00',
            'billing_period' => 'monthly',
            'user_id' => $user_id,
        ],
        [
            'id' => 'NOD-PIP-2034',
            'node_type' => 'Pipe',
            'status' => 'pending',
            'created_at' => date( 'Y-m-d H:i:s', strtotime( '-5 days', strtotime( $now ) ) ),
            'due_date' => date( 'Y-m-d H:i:s', strtotime( '+25 days', strtotime( $now ) ) ),
            'price' => '29.00',
            'billing_period' => 'monthly',
            'user_id' => $user_id,
        ],
        [
            'id' => 'NOD-DAT-3312',
            'node_type' => 'Dats',
            'status' => 'expired',
            'created_at' => date( 'Y-m-d H:i:s', strtotime( '-90 days', strtotime( $now ) ) ),
            'due_date' => date( 'Y-m-d H:i:s', strtotime( '-1 day', strtotime( $now ) ) ),
            'price' => '99.00',
            'billing_period' => 'quarterly',
            'user_id' => $user_id,
        ],
    ];
}

function incr_get_mock_nodes_data( $user_id ) {
    $nodes = incr_get_mock_nodes( $user_id );
    $saved = get_user_meta( $user_id, 'incr_mock_nodes_data', true );

    if ( is_array( $saved ) && ! empty( $saved ) ) {
        foreach ( $nodes as $index => $node ) {
            if ( empty( $saved[ $index ] ) || ! is_array( $saved[ $index ] ) ) {
                continue;
            }
            $override = $saved[ $index ];
            $nodes[ $index ]['id'] = $override['id'] ?? $node['id'];
            $nodes[ $index ]['node_type'] = $override['node_type'] ?? $node['node_type'];
            $nodes[ $index ]['status'] = $override['status'] ?? $node['status'];
            $nodes[ $index ]['created_at'] = $override['created_at'] ?? $node['created_at'];
            $nodes[ $index ]['due_date'] = $override['due_date'] ?? $node['due_date'];
            $nodes[ $index ]['price'] = $override['price'] ?? $node['price'];
            $nodes[ $index ]['billing_period'] = $override['billing_period'] ?? $node['billing_period'];
        }
    }

    return $nodes;
}

function incr_save_mock_nodes() {
    if ( ! incr_is_local_env() || ! ( defined( 'INCR_USE_MOCK_NODES' ) && INCR_USE_MOCK_NODES ) ) {
        wp_safe_redirect( wp_get_referer() ?: home_url( '/' ) );
        exit;
    }

    if ( ! is_user_logged_in() ) {
        wp_safe_redirect( wp_get_referer() ?: home_url( '/' ) );
        exit;
    }

    if ( empty( $_POST['incr_mock_nodes_nonce'] ) || ! wp_verify_nonce( $_POST['incr_mock_nodes_nonce'], 'incr_save_mock_nodes' ) ) {
        wp_safe_redirect( wp_get_referer() ?: home_url( '/' ) );
        exit;
    }

    $nodes = $_POST['mock_nodes'] ?? [];
    $clean = [];
    if ( is_array( $nodes ) ) {
        foreach ( $nodes as $index => $node ) {
            if ( ! is_array( $node ) ) {
                continue;
            }
            $clean[ $index ] = [
                'id' => sanitize_text_field( wp_unslash( $node['id'] ?? '' ) ),
                'node_type' => sanitize_text_field( wp_unslash( $node['node_type'] ?? '' ) ),
                'status' => sanitize_text_field( wp_unslash( $node['status'] ?? '' ) ),
                'created_at' => sanitize_text_field( wp_unslash( $node['created_at'] ?? '' ) ),
                'due_date' => sanitize_text_field( wp_unslash( $node['due_date'] ?? '' ) ),
                'price' => sanitize_text_field( wp_unslash( $node['price'] ?? '' ) ),
                'billing_period' => sanitize_text_field( wp_unslash( $node['billing_period'] ?? '' ) ),
            ];
        }
    }

    update_user_meta( get_current_user_id(), 'incr_mock_nodes_data', $clean );
    if ( class_exists( 'INCR_Nodes_Store' ) ) {
        INCR_Nodes_Store::upsert_nodes( get_current_user_id(), incr_get_mock_nodes_data( get_current_user_id() ), 'mock' );
    }
    delete_transient( 'Incr_user_nodes_' . get_current_user_id() );

    wp_safe_redirect( wp_get_referer() ?: home_url( '/' ) );
    exit;
}
add_action( 'admin_post_incr_save_mock_nodes', 'incr_save_mock_nodes' );

function incr_seed_mock_nodes_for_local() {
    if ( ! incr_is_local_env() || ! ( defined( 'INCR_USE_MOCK_NODES' ) && INCR_USE_MOCK_NODES ) || ! is_user_logged_in() ) {
        return;
    }

    if ( ! incr_is_nodes_request() ) {
        return;
    }

    $user_id = get_current_user_id();
    if ( ! incr_mock_nodes_enabled( $user_id ) ) {
        delete_transient( 'Incr_user_nodes_' . $user_id );
        return;
    }
    $nodes = incr_get_mock_nodes_data( $user_id );
    $nodes = apply_filters( 'incrypted_mock_nodes', $nodes, $user_id );

    set_transient( 'Incr_user_nodes_' . $user_id, $nodes, HOUR_IN_SECONDS );
}
add_action( 'template_redirect', 'incr_seed_mock_nodes_for_local' );
