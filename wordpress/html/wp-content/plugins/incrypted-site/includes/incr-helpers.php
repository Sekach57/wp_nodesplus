<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function incr_is_local_env() {
    if ( defined( 'WP_ENVIRONMENT_TYPE' ) && in_array( WP_ENVIRONMENT_TYPE, [ 'local', 'development' ], true ) ) {
        return true;
    }

    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ( $host === '' ) {
        return false;
    }

    if ( $host === 'localhost' || strpos( $host, 'localhost:' ) === 0 || $host === '127.0.0.1' ) {
        return true;
    }

    return ( substr( $host, -5 ) === '.test' || substr( $host, -6 ) === '.local' );
}

function incr_is_nodes_request() {
    if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'nodes' ) ) {
        return true;
    }

    $request = $_SERVER['REQUEST_URI'] ?? '';
    if ( $request === '' ) {
        return false;
    }

    $path = trim( parse_url( $request, PHP_URL_PATH ) ?: '', '/' );
    if ( $path === '' ) {
        return false;
    }

    $parts = explode( '/', $path );
    return in_array( 'nodes', $parts, true );
}
