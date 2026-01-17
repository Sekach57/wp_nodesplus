<?php

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

