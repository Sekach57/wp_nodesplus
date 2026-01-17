<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function incr_is_local_env() {
    return np_env() === 'local';
}

function np_env() {
    $env = '';
    if ( defined( 'NP_ENV' ) ) {
        $env = NP_ENV;
    }
    if ( $env === '' ) {
        $value = getenv( 'NP_ENV' );
        if ( $value !== false ) {
            $env = $value;
        }
    }
    if ( $env === '' && defined( 'WP_ENVIRONMENT_TYPE' ) ) {
        $env = WP_ENVIRONMENT_TYPE;
    }

    $env = strtolower( trim( (string) $env ) );
    if ( $env === 'production' ) {
        $env = 'prod';
    }
    if ( $env === 'development' ) {
        $env = 'local';
    }
    if ( ! in_array( $env, [ 'local', 'staging', 'prod' ], true ) ) {
        $env = 'local';
    }

    return $env;
}

function incr_get_config( $const_name, $default = '', $env_key = '' ) {
    $value = '';
    if ( defined( $const_name ) ) {
        $value = constant( $const_name );
    }

    if ( $value === '' && $env_key !== '' ) {
        $env_value = getenv( $env_key );
        if ( $env_value !== false ) {
            $value = $env_value;
        }
    }

    if ( $value === '' ) {
        $env_value = getenv( $const_name );
        if ( $env_value !== false ) {
            $value = $env_value;
        }
    }

    $value = trim( (string) $value );
    return $value !== '' ? $value : $default;
}

function incr_missing_required_config() {
    $required = [
        'INCR_API_BASE',
        'INCR_IMPORT_URL',
        'INCR_AUTH_TOKEN',
        'INCR_TELEGRAM_BOT_TOKEN',
        'INCR_TELEGRAM_BOT_USERNAME',
        'NP_BOT_SECRET',
    ];

    $required = apply_filters( 'incr_required_config_keys', $required );
    $missing = [];

    foreach ( $required as $key ) {
        $env_key = $key === 'NP_BOT_SECRET' ? 'NP_BOT_SECRET' : '';
        if ( $key === 'NP_BOT_SECRET' ) {
            $value = incr_get_config( 'INCR_TELEGRAM_BOT_SECRET', '', $env_key );
        } else {
            $value = incr_get_config( $key );
        }
        if ( $value === '' ) {
            $missing[] = $key;
        }
    }

    return $missing;
}

function incr_config_admin_notice() {
    if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $missing = incr_missing_required_config();
    if ( empty( $missing ) ) {
        return;
    }

    $env = np_env();
    $label = implode( ', ', array_map( 'esc_html', $missing ) );
    echo '<div class="notice notice-warning"><p>';
    echo 'NodesPlus configuration missing for environment "' . esc_html( $env ) . '": ' . $label . '.';
    echo '</p></div>';
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
