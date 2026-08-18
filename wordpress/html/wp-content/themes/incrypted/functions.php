<?php
if ( ! defined( '_S_VERSION' ) ) {
	define( '_S_VERSION', '1.0.0' );
}


function incrypted_setup() {
	/*
		* Make theme available for translation.
		* Translations can be filed in the /languages/ directory.
		* If you're building a theme based on incrypted, use a find and replace
		* to change 'incrypted' to the name of your theme in all the template files.
		*/
	load_theme_textdomain( 'incrypted', get_template_directory() . '/' );

	add_theme_support( 'title-tag' );

	add_theme_support( 'post-thumbnails' );

	add_theme_support(
		'html5',
		array(
			'style',
			'script',
		)
	);
}
add_action( 'after_setup_theme', 'incrypted_setup' );

function incrypted_content_width() {
	$GLOBALS['content_width'] = apply_filters( 'incrypted_content_width', 640 );
}
add_action( 'after_setup_theme', 'incrypted_content_width', 0 );

/**
 * Enqueue scripts and styles.
 */
function incrypted_scripts() {
    $theme_dir = get_template_directory();
    $app_js_version = file_exists( $theme_dir . '/js/app.js' ) ? filemtime( $theme_dir . '/js/app.js' ) : _S_VERSION;
    $nodes_css_version = file_exists( $theme_dir . '/css/nodes-cards.css' ) ? filemtime( $theme_dir . '/css/nodes-cards.css' ) : _S_VERSION;
    $theme_css_version = file_exists( $theme_dir . '/css/style.css' ) ? filemtime( $theme_dir . '/css/style.css' ) : _S_VERSION;

    wp_enqueue_style( 'incrypted-basic', get_stylesheet_uri(), array(), _S_VERSION );
    wp_enqueue_style( 'incrypted-normalize', get_template_directory_uri() . '/css/normalize.css', array(), _S_VERSION );
    wp_enqueue_style( 'incrypted-styles', get_template_directory_uri() . '/css/style.css', array(), $theme_css_version );
    wp_enqueue_style( 'incrypted-nodes-cards-css', get_template_directory_uri() . '/css/nodes-cards.css', array(), $nodes_css_version );

    wp_enqueue_script('jquery');
	wp_enqueue_script( 'incrypted-scripts', get_template_directory_uri() . '/js/app.js', array(), $app_js_version, true );
    wp_enqueue_script('custom-cart', get_stylesheet_directory_uri() . '/js/custom-cart.js', ['jquery'], _S_VERSION, true);

    wp_localize_script('custom-cart', 'custom_cart_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('custom_cart_nonce'),
        'renewal_nonce' => wp_create_nonce('renewal_nonce'),
        'promo_nonce' => wp_create_nonce('promo_code_nonce'),
        'checkout_url' => wc_get_checkout_url(),
        'cart_url' => wc_get_cart_url()
    ]);

    wp_localize_script( 'incrypted-scripts', 'npNodeDetails', [
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce' => wp_create_nonce( 'np_node_details' ),
        'rest_url' => esc_url_raw( rest_url() ),
        'rest_nonce' => wp_create_nonce( 'wp_rest' ),
    ] );
}
add_action( 'wp_enqueue_scripts', 'incrypted_scripts' );

/////////////////////////
add_filter( 'woocommerce_feature_flag_blockified_cart_checkout', '__return_false' );

/**
 * Restrict checkout to cryptocurrency payments through Whitepay.
 *
 * Filtering the available gateways also rejects attempts to submit a removed
 * payment method directly, while keeping all gateways visible in WooCommerce
 * settings for administrators.
 */
function np_crypto_only_payment_gateways( $gateways ) {
    if ( is_admin() && ! wp_doing_ajax() ) {
        return $gateways;
    }

    return isset( $gateways['whitepay'] )
        ? [ 'whitepay' => $gateways['whitepay'] ]
        : [];
}
add_filter( 'woocommerce_available_payment_gateways', 'np_crypto_only_payment_gateways', 100 );

/**
 * Show the crypto-only payment announcement for seven days after deployment.
 */
function np_crypto_payment_notice_is_active() {
    $started_at = get_option( 'np_crypto_payment_notice_started_at', false );

    if ( false === $started_at ) {
        $started_at = time();
        add_option( 'np_crypto_payment_notice_started_at', $started_at, '', false );
    }

    return time() < ( (int) $started_at + WEEK_IN_SECONDS );
}

function np_crypto_payment_notice_text() {
    $lang = function_exists( 'pll_current_language' ) ? pll_current_language() : 'uk';

    if ( 'en' === $lang ) {
        return 'Important: all payments are now accepted only in cryptocurrency.';
    }

    return 'Важливо: відтепер усі платежі приймаються лише в криптовалюті.';
}

require get_template_directory() . '/acf-blocks.php';
require get_template_directory() . '/woo-custom/nodes-functionality.php';
require get_template_directory() . '/woo-custom/woo-helpers.php';

/**
 * Translate first-order banner/email strings (EN/UK).
 */
function np_t( $string ) {
    static $uk = [
        'Your node is being set up'
            => 'Ваша нода налаштовується',
        'Your node is being set up — NodesPlus'
            => 'Ваша нода налаштовується — NodesPlus',
        'Thank you for your purchase! Your node is now being configured.'
            => 'Дякуємо за покупку! Ваша нода зараз налаштовується.',
        'It usually takes up to 30 minutes for the node to appear in your profile, but sometimes it may take longer. Once it does, you will be able to connect Discord in your account settings.'
            => 'Зазвичай нода з\'являється у вашому профілі протягом 30 хвилин, але іноді це може зайняти більше часу. Після цього ви зможете підключити Discord у налаштуваннях акаунту.',
        'Please <a href="%s">link your Telegram</a> in your account to receive all updates and notifications.'
            => 'Будь ласка, <a href="%s">підключіть Telegram</a> у вашому акаунті, щоб отримувати всі оновлення та сповіщення.',
        'You can check the status in your <a href="%s">account dashboard</a>.'
            => 'Ви можете перевірити статус у вашій <a href="%s">панелі акаунту</a>.',
    ];

    $lang = function_exists( 'pll_current_language' ) ? pll_current_language() : 'uk';
    if ( $lang === 'uk' && isset( $uk[ $string ] ) ) {
        return $uk[ $string ];
    }
    return $string;
}

add_filter('pll_get_post_types', 'add_cpt_to_pll', 10, 2);
function add_cpt_to_pll($post_types, $is_settings) {
    if ($is_settings) {
        unset($post_types['acf-field-group']);
        unset($post_types['acf-field']);
    }
    return $post_types;
}

function custom_language_switcher() {
    if (function_exists('pll_the_languages')) {
        $languages = pll_the_languages(array('raw' => 1));

        $custom_names = array(
            'uk' => 'Ua',
            'en' => 'En'
        );

        if (!empty($languages) && count($languages) > 1) {
            echo '<ul class="languages">';
            foreach ($languages as $lang) {
                $class = $lang['current_lang'] ? 'active' : '';
                $display_name = isset($custom_names[$lang['slug']]) ? $custom_names[$lang['slug']] : $lang['slug'];

                echo '<li>';
                echo '<a class="' . $class . '" href="' . esc_url($lang['url']) . '">';
                echo esc_html($display_name);
                echo '</a>';
                echo '</li>';
            }
            echo '</ul>';
        }
    }
}

function enable_svg_support() {
    add_filter('upload_mimes', function($mimes) {
        $mimes['svg'] = 'image/svg+xml';
        $mimes['svgz'] = 'image/svg+xml';
        return $mimes;
    });

    add_filter('wp_check_filetype_and_ext', function($data, $file, $filename, $mimes) {
        $filetype = wp_check_filetype($filename, $mimes);
        return [
            'ext'             => $filetype['ext'],
            'type'            => $filetype['type'],
            'proper_filename' => $data['proper_filename']
        ];
    }, 10, 4);

    add_filter('wp_prepare_attachment_for_js', function($response, $attachment, $meta) {
        if ($response['mime'] == 'image/svg+xml') {
            $response['image'] = [
                'src' => $response['url'],
                'width' => 200,
                'height' => 200,
            ];
        }
        return $response;
    }, 10, 3);

    add_action('admin_head', function() {
        echo '<style>
            .media-icon img[src$=".svg"], 
            .attachment-preview img[src$=".svg"] {
                width: 100% !important;
                height: auto !important;
            }
        </style>';
    });
}
add_action('init', 'enable_svg_support');

function checkPlusSubscriptionWP($email) {
    $app_token = "z1oibj8KbvaeOU9gHgGe42BuRpijxIBS6b4gmdPKdDaXuW2ZDbpQSx5as1zwV1Kh";
    $url = "https://incrypted.com/api/?action=check_plus&application=" . urlencode($app_token) . "&email=" . urlencode($email);

    $cache_key = 'plus_subscription_' . md5($email);

    $cached_result = get_transient($cache_key);
    if ($cached_result !== false) {
        return $cached_result;
    }

    $response = wp_remote_get($url, [
        'timeout' => 30,
        'headers' => [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ]
    ]);

    if (is_wp_error($response)) {
        echo "Error: " . $response->get_error_message();
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "JSON decode error: " . json_last_error_msg();
        return false;
    }

    $result = false;

    if (isset($data['status']) && $data['status'] === 'success') {
        if (isset($data['has_plus'])) {
            if ($data['has_plus'] === true) {
                $result = true;
            }
        }
    }

    set_transient($cache_key, $result, HOUR_IN_SECONDS);
    return $result;
}

function assign_customer_plus_role($user_id) {
    $user = new WP_User($user_id);
    $user->set_role('customer_plus');
}
function assign_customer_role($user_id) {
    $user = new WP_User($user_id);
    $user->set_role('customer');
}



// DISCORD CONNECT FN
add_action('init', function () {
    add_rewrite_rule('^discord-callback/?$', 'index.php?discord=1', 'top');
});

add_filter('query_vars', function ($vars) {
    $vars[] = 'discord';
    return $vars;
});



add_action('template_redirect', function () {
    if (!get_query_var('discord')) return;
    $redirect = site_url('/my-account/edit-account');
    if (!is_user_logged_in()) {
        wp_redirect(add_query_arg('discord_error', 'not_logged_in', $redirect));
        exit;
    }
    $user = wp_get_current_user();
    if (function_exists('incr_user_has_active_nodes') && !incr_user_has_active_nodes($user->ID)) {
        wp_redirect(add_query_arg('discord_error', 'no_active_nodes', $redirect));
        exit;
    }
    if (!isset($_GET['state']) || !wp_verify_nonce($_GET['state'], 'discord_oauth')) {
        wp_redirect(add_query_arg('discord_error', 'invalid_state', $redirect));
        exit;
    }
    if (!isset($_GET['code'])) {
        wp_redirect(add_query_arg('discord_error', 'no_code', $redirect));
        exit;
    }
    $token_response = wp_remote_post('https://discord.com/api/oauth2/token', [
        'timeout' => 15,
        'body'    => [
            'client_id'     => DISCORD_CLIENT_ID,
            'client_secret' => DISCORD_CLIENT_SECRET,
            'grant_type'    => 'authorization_code',
            'code'          => sanitize_text_field($_GET['code']),
            'redirect_uri'  => site_url('/discord-callback'),
        ],
    ]);
    if (is_wp_error($token_response)) {
        wp_redirect(add_query_arg('discord_error', 'token_failed', $redirect));
        exit;
    }
    $token = json_decode(wp_remote_retrieve_body($token_response), true)['access_token'] ?? null;
    if (!$token) {
        wp_redirect(add_query_arg('discord_error', 'no_token', $redirect));
        exit;
    }
    $user_response = wp_remote_get('https://discord.com/api/users/@me', [
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
        ],
    ]);
    if (is_wp_error($user_response)) {
        wp_redirect(add_query_arg('discord_error', 'user_failed', $redirect));
        exit;
    }
    $discord_user = json_decode(wp_remote_retrieve_body($user_response), true);
    if (empty($discord_user['id'])) {
        wp_redirect(add_query_arg('discord_error', 'no_discord_id', $redirect));
        exit;
    }
    update_field('discord_id', $discord_user['id'], 'user_' . $user->ID);
    send_user_update('Discord updated', [
        'user_id' => $user->ID,
        'user_email'   => $user->user_email,
        'discord_id' => $discord_user['id']
    ]);
    wp_redirect(add_query_arg('discord_success', 1, $redirect));
    exit;
});


add_action('template_redirect', function () {
    if (!is_user_logged_in()) return;
    if (!isset($_POST['disconnect_discord'])) return;
    if (
        !isset($_POST['discord_nonce']) ||
        !wp_verify_nonce($_POST['discord_nonce'], 'discord_disconnect')
    ) {
        wp_die('Error');
    }
    $user = wp_get_current_user();
    delete_field('discord_id', 'user_' . $user->ID);
    send_user_update('Discord updated', [
        'user_id' => $user->ID,
        'user_email'   => $user->user_email,
        'discord_id' => null
    ]);
    wp_redirect(add_query_arg('discord_disconnected', 1, wp_get_referer()));
    exit;
});


// --

// UPDATE USER DATA IN CRM
function send_user_update($message = '', $context = []) {
    $request_url = 'https://crm.incrypted.com/webhooks/nodes/customer';
    // write_logs_user_update($message, $context);
    $request = makeRequest("POST", $request_url, $context, defined('INCRYPTED_CRM_TOKEN') ? INCRYPTED_CRM_TOKEN : '');


}

/**
 * Send a welcome email to first-time node buyers.
 */
function incr_send_first_order_email( $order ) {
    $to = $order->get_billing_email();
    if ( ! $to ) {
        $user = get_userdata( $order->get_user_id() );
        $to   = $user ? $user->user_email : '';
    }
    if ( ! $to ) {
        return;
    }

    $subject   = np_t( 'Your node is being set up — NodesPlus' );
    $heading   = np_t( 'Your node is being set up' );
    $nodes_url = site_url( '/my-account/nodes/' );

    ob_start();
    wc_get_template( 'emails/email-header.php', [ 'email_heading' => $heading ] );
    ?>
    <p><?php echo esc_html( np_t( 'Thank you for your purchase! Your node is now being configured.' ) ); ?></p>
    <p><?php echo esc_html( np_t( 'It usually takes up to 30 minutes for the node to appear in your profile, but sometimes it may take longer. Once it does, you will be able to connect Discord in your account settings.' ) ); ?></p>
    <p><?php
        printf(
            wp_kses_post( np_t( 'Please <a href="%s">link your Telegram</a> in your account to receive all updates and notifications.' ) ),
            esc_url( site_url( '/my-account/' ) )
        );
    ?></p>
    <p><?php
        printf(
            wp_kses_post( np_t( 'You can check the status in your <a href="%s">account dashboard</a>.' ) ),
            esc_url( $nodes_url )
        );
    ?></p>
    <?php
    wc_get_template( 'emails/email-footer.php' );
    $message = ob_get_clean();

    $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
    wp_mail( $to, $subject, $message, $headers );
}

function write_logs_user_update($message = '', $context = []) {
    $file = 'user_update.log';
    $log_dir = WP_CONTENT_DIR . '/logs';
    if (!is_dir($log_dir)) {
        wp_mkdir_p($log_dir);
    }
    $log_file = $log_dir . '/' . $file;
    if (is_array($message) || is_object($message)) {
        $message = wp_json_encode($message, JSON_UNESCAPED_UNICODE);
    }
    if (!empty($context)) {
        $message .= ' | context: ' . wp_json_encode($context, JSON_UNESCAPED_UNICODE);
    }
    $line = sprintf(
        "[%s] %s\n",
        current_time('Y-m-d H:i:s'),
        $message
    );
    error_log($line, 3, $log_file);
}


add_action('profile_update', function ($user_id, $old_user_data, ) {
    static $processed = [];
    if (isset($processed[$user_id])) {
        return;
    }
    if (empty($old_user_data->user_email)) {
        return;
    }

    $user = get_userdata($user_id);

    if (!$user) return;

    if ($old_user_data->user_email === $user->user_email) {
        return;
    }
    $processed[$user_id] = true;
    $user = get_userdata($user_id);
    $discord = get_field('discord_id', 'user_' . $user_id);
    send_user_update('User updated', [
        'user_id' => $user_id,
        'user_email'   => $user->user_email,
        'discord_id' => $discord ? $discord : null
    ]);
}, 10, 2);

add_action( 'user_register', function($user_id) {
    $user = get_userdata($user_id);
    $discord = get_field('discord_id', 'user_' . $user_id);
    send_user_update('User created', [
        'user_id' => $user_id,
        'user_email'   => $user->user_email,
        'discord_id' => $discord ? $discord : null
    ]);
},  10, 2);


/**
 * Check if the given order is the user's very first node purchase.
 */
function incr_is_first_purchase_order( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return false;
    }

    $user_id = $order->get_user_id();
    if ( ! $user_id ) {
        return false;
    }

    $is_purchase = false;
    foreach ( $order->get_items() as $item ) {
        $op_type      = $item->get_meta( 'Operation Type' );
        $product_name = $item->get_name();
        if ( $product_name === 'Wallet Topup' ) {
            continue;
        }
        if ( $op_type === 'Prolongation' ) {
            continue;
        }
        $is_purchase = true;
        break;
    }

    if ( ! $is_purchase ) {
        return false;
    }

    $previous = wc_get_orders( [
        'customer_id' => $user_id,
        'status'      => [ 'processing', 'completed' ],
        'exclude'     => [ $order_id ],
        'limit'       => 1,
        'return'      => 'ids',
    ] );

    return empty( $previous );
}

add_action( 'woocommerce_order_status_changed', 'check_order_status_update', 10, 3 );

function check_order_status_update( $order_id, $old_status, $new_status ){
    // if( $new_status == 'processing' || $new_status == 'completed' ) {
    if( $new_status == 'processing' ) {
        $order = wc_get_order( $order_id );
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
        $operation_type = $operation_type ? $operation_type : 'Purchase';
        $available_types = ['Purchase', 'Prolongation'];
        if (in_array($operation_type, $available_types, true)) {
            $user_id = $order->get_user_id();
            $user = get_userdata($user_id);
            $discord = get_field('discord_id', 'user_' . $user_id);
            send_user_update('Order complete', [
                'user_id' => $user_id,
                'user_email'   => $user->user_email,
                'discord_id' => $discord ? $discord : null,
                'user_nodes_expiration_date' => time() + (30 * DAY_IN_SECONDS)
            ]);

            if ( incr_is_first_purchase_order( $order_id ) ) {
                incr_send_first_order_email( $order );
            }
        }
    };
};





// --
