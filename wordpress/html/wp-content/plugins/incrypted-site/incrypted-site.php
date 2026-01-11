<?php
/**
 * Plugin Name: Incrypted Site
 * Description: Site-specific hooks, shortcodes, and integrations for Incrypted.
 * Version: 0.1.0
 * Author: NodesPlus
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'INCR_SITE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once INCR_SITE_PLUGIN_DIR . 'includes/incr-helpers.php';
require_once INCR_SITE_PLUGIN_DIR . 'includes/incr-mock-nodes.php';
require_once INCR_SITE_PLUGIN_DIR . 'includes/incr-node-details.php';
require_once INCR_SITE_PLUGIN_DIR . 'includes/class-incr-nodes-store.php';
require_once INCR_SITE_PLUGIN_DIR . 'includes/class-incr-nodes-admin.php';
require_once INCR_SITE_PLUGIN_DIR . 'includes/class-incr-telegram-connect.php';
require_once INCR_SITE_PLUGIN_DIR . 'includes/class-incr-telegram-links.php';
require_once INCR_SITE_PLUGIN_DIR . 'includes/class-incr-telegram-notifications.php';
require_once INCR_SITE_PLUGIN_DIR . 'includes/class-incr-telegram-wallet.php';

function incr_nodes_store_activate() {
    if ( class_exists( 'INCR_Nodes_Store' ) ) {
        INCR_Nodes_Store::create_table();
    }
    if ( class_exists( 'INCR_Telegram_Connect' ) ) {
        INCR_Telegram_Connect::create_table();
    }
    if ( class_exists( 'INCR_Telegram_Links' ) ) {
        INCR_Telegram_Links::create_table();
    }
    if ( class_exists( 'INCR_Telegram_Notifications' ) ) {
        INCR_Telegram_Notifications::create_table();
        INCR_Telegram_Notifications::ensure_scheduled();
    }
    if ( class_exists( 'INCR_Telegram_Wallet' ) ) {
        INCR_Telegram_Wallet::create_table();
        INCR_Telegram_Wallet::ensure_scheduled();
    }
}
register_activation_hook( __FILE__, 'incr_nodes_store_activate' );
register_deactivation_hook( __FILE__, function () {
    if ( class_exists( 'INCR_Telegram_Notifications' ) ) {
        INCR_Telegram_Notifications::unschedule();
    }
    if ( class_exists( 'INCR_Telegram_Wallet' ) ) {
        INCR_Telegram_Wallet::unschedule();
    }
} );

if ( class_exists( 'INCR_Nodes_Admin' ) ) {
    INCR_Nodes_Admin::init();
}
if ( class_exists( 'INCR_Telegram_Connect' ) ) {
    INCR_Telegram_Connect::init();
}
if ( class_exists( 'INCR_Telegram_Links' ) ) {
    INCR_Telegram_Links::init();
}
if ( class_exists( 'INCR_Telegram_Notifications' ) ) {
    INCR_Telegram_Notifications::init();
}
if ( class_exists( 'INCR_Telegram_Wallet' ) ) {
    INCR_Telegram_Wallet::init();
}
