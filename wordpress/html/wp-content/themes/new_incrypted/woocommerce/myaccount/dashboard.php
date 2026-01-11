<?php
/**
 * My Account Dashboard
 *
 * Shows the first intro screen on the account dashboard.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/dashboard.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 4.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$allowed_html = array(
	'a' => array(
		'href' => array(),
	),
);
$current_user = wp_get_current_user();
$user_id      = get_current_user_id();
$discord_id   = get_field('discord_id', 'user_' . $user_id);
$discord_connected = ! empty( $discord_id );
$discord_status = $discord_connected ? __( 'Connected', 'incrypted' ) : __( 'Not connected', 'incrypted' );
$discord_action = $discord_connected ? __( 'Manage', 'incrypted' ) : __( 'Connect', 'incrypted' );
$discord_status_class = $discord_connected ? 'is-connected' : 'is-disconnected';
$telegram_connected = false;
 $telegram_username = '';
global $wpdb;
$telegram_links_table = $wpdb->prefix . 'incr_telegram_links';
$telegram_links_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $telegram_links_table ) );
if ( $telegram_links_exists === $telegram_links_table ) {
    $telegram_link_row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT telegram_chat_id, tg_username FROM {$telegram_links_table} WHERE wp_user_id = %d LIMIT 1",
            $user_id
        ),
        ARRAY_A
    );
    if ( is_array( $telegram_link_row ) ) {
        $telegram_connected = ! empty( $telegram_link_row['telegram_chat_id'] );
        $telegram_username = ! empty( $telegram_link_row['tg_username'] ) ? (string) $telegram_link_row['tg_username'] : '';
    }
}
$twitter_connected = false;
$telegram_status = $telegram_connected ? __( 'Connected', 'incrypted' ) : __( 'Not connected', 'incrypted' );
if ( $telegram_connected && $telegram_username !== '' ) {
    $telegram_status = sprintf( '%s (@%s)', $telegram_status, $telegram_username );
}
$twitter_status = $twitter_connected ? __( 'Connected', 'incrypted' ) : __( 'Not connected', 'incrypted' );
$telegram_action = $telegram_connected ? __( 'Manage', 'incrypted' ) : __( 'Connect', 'incrypted' );
$twitter_action = $twitter_connected ? __( 'Manage', 'incrypted' ) : __( 'Connect', 'incrypted' );
$telegram_status_class = $telegram_connected ? 'is-connected' : 'is-disconnected';
$twitter_status_class = $twitter_connected ? 'is-connected' : 'is-disconnected';
$telegram_connect_endpoint = rest_url( 'nodesplus/v1/telegram/connect' );
$telegram_connect_nonce = wp_create_nonce( 'wp_rest' );
$telegram_card_style = $telegram_connected ? ' style="background:#f0fdf4;border-color:#bbf7d0;box-shadow:0 8px 20px rgba(34,197,94,0.15);"' : '';
$overdue_count = 0;
$user_nodes = getNodesByUserID($user_id);
if ( is_array( $user_nodes ) && ! isset( $user_nodes['detail'] ) ) {
    $now = current_time( 'timestamp' );
    foreach ( $user_nodes as $node ) {
        if ( empty( $node['due_date'] ) ) {
            continue;
        }
        $due_timestamp = strtotime( $node['due_date'] );
        if ( $due_timestamp && $due_timestamp < $now ) {
            $overdue_count++;
        }
    }
}
?>

<?php
    $discord_oauth_url = add_query_arg([
        'client_id'     => DISCORD_CLIENT_ID,
        'response_type' => 'code',
        'redirect_uri'  => site_url('/discord-callback'),
        'scope'         => 'identify',
        'state'         => wp_create_nonce('discord_oauth'),
    ], 'https://discord.com/oauth2/authorize');
?>

<div class="dashboard-cards">
    <div class="dashboard-card dashboard-card--account">
        <div class="dashboard-card__header">
            <?php echo get_avatar( $current_user->ID, 48, '', $current_user->display_name, [ 'class' => 'dashboard-card__avatar' ] ); ?>
            <div class="dashboard-card__title">
                <span class="dashboard-card__name"><?php echo esc_html( $current_user->display_name ); ?></span>
            </div>
        </div>
        <div class="dashboard-card__action">
            <a class="dashboard-card__button dashboard-card__button--logout" href="<?php echo esc_url( wc_logout_url() ); ?>">
                <?php esc_html_e( 'Logout', 'incrypted' ); ?>
            </a>
        </div>
    </div>

    <div class="dashboard-card dashboard-card--discord <?php echo esc_attr( $discord_status_class ); ?>">
        <div class="dashboard-card__service">
            <span class="dashboard-card__icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 -28.5 256 256" aria-hidden="true" focusable="false">
                    <path d="M216.856339,16.5966031 C200.285002,8.84328665 182.566144,3.2084988 164.041564,0 C161.766523,4.11318106 159.108624,9.64549908 157.276099,14.0464379 C137.583995,11.0849896 118.072967,11.0849896 98.7430163,14.0464379 C96.9108417,9.64549908 94.1925838,4.11318106 91.8971895,0 C73.3526068,3.2084988 55.6133949,8.86399117 39.0420583,16.6376612 C5.61752293,67.146514 -3.4433191,116.400813 1.08711069,164.955721 C23.2560196,181.510915 44.7403634,191.567697 65.8621325,198.148576 C71.0772151,190.971126 75.7283628,183.341335 79.7352139,175.300261 C72.104019,172.400575 64.7949724,168.822202 57.8887866,164.667963 C59.7209612,163.310589 61.5131304,161.891452 63.2445898,160.431257 C105.36741,180.133187 151.134928,180.133187 192.754523,160.431257 C194.506336,161.891452 196.298154,163.310589 198.110326,164.667963 C191.183787,168.842556 183.854737,172.420929 176.223542,175.320965 C180.230393,183.341335 184.861538,190.991831 190.096624,198.16893 C211.238746,191.588051 232.743023,181.531619 254.911949,164.955721 C260.227747,108.668201 245.831087,59.8662432 216.856339,16.5966031 Z M85.4738752,135.09489 C72.8290281,135.09489 62.4592217,123.290155 62.4592217,108.914901 C62.4592217,94.5396472 72.607595,82.7145587 85.4738752,82.7145587 C98.3405064,82.7145587 108.709962,94.5189427 108.488529,108.914901 C108.508531,123.290155 98.3405064,135.09489 85.4738752,135.09489 Z M170.525237,135.09489 C157.88039,135.09489 147.510584,123.290155 147.510584,108.914901 C147.510584,94.5396472 157.658606,82.7145587 170.525237,82.7145587 C183.391518,82.7145587 193.761324,94.5189427 193.539891,108.914901 C193.539891,123.290155 183.391518,135.09489 170.525237,135.09489 Z" fill="#5865F2" fill-rule="nonzero"></path>
                </svg>
            </span>
            <span class="dashboard-card__service-name"><?php esc_html_e( 'Discord', 'incrypted' ); ?></span>
        </div>
        <div class="dashboard-card__status <?php echo esc_attr( $discord_status_class ); ?>">
            <?php echo esc_html( $discord_status ); ?>
        </div>
        <div class="dashboard-card__action">
            <?php if ( $discord_connected ) : ?>
                <form method="post" class="discord-disconnect-form">
                    <?php wp_nonce_field('discord_disconnect', 'discord_nonce'); ?>
                    <button type="submit" name="disconnect_discord" class="dashboard-card__button">
                        <?php echo esc_html( $discord_action ); ?>
                    </button>
                </form>
            <?php else : ?>
                <a href="<?php echo esc_url( $discord_oauth_url ); ?>" class="dashboard-card__button">
                    <?php echo esc_html( $discord_action ); ?>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="dashboard-card dashboard-card--telegram <?php echo esc_attr( $telegram_status_class ); ?>"<?php echo $telegram_card_style; ?>>
        <div class="dashboard-card__service">
            <span class="dashboard-card__icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="M22 3L2.6 11.1c-.9.4-.9 1.7.1 2l4.8 1.6 2 6.1c.3.9 1.5 1 2 .2l2.9-4 5.2 3.8c.8.6 1.9.1 2.1-.9L24 4.4c.2-1.1-.8-2-2-1.4Z" fill="#229ED9"></path>
                </svg>
            </span>
            <span class="dashboard-card__service-name"><?php esc_html_e( 'Telegram', 'incrypted' ); ?></span>
        </div>
        <div class="dashboard-card__status <?php echo esc_attr( $telegram_status_class ); ?>">
            <?php echo esc_html( $telegram_status ); ?>
        </div>
        <div class="dashboard-card__action">
            <button type="button" class="dashboard-card__button js-telegram-connect" data-endpoint="<?php echo esc_url( $telegram_connect_endpoint ); ?>" data-nonce="<?php echo esc_attr( $telegram_connect_nonce ); ?>">
                <?php echo esc_html( $telegram_action ); ?>
            </button>
        </div>
    </div>

    <div class="dashboard-card dashboard-card--twitter <?php echo esc_attr( $twitter_status_class ); ?>">
        <div class="dashboard-card__service">
            <span class="dashboard-card__icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="M18.9 2H22l-7.3 8.3L23.6 22h-7l-5.5-7.2L4.7 22H1.5l7.8-8.9L.7 2h7.2l4.9 6.4L18.9 2Zm-2 18h2.1L7.1 4h-2L16.9 20Z" fill="#0f172a"></path>
                </svg>
            </span>
            <span class="dashboard-card__service-name"><?php esc_html_e( 'Twitter', 'incrypted' ); ?></span>
        </div>
        <div class="dashboard-card__status <?php echo esc_attr( $twitter_status_class ); ?>">
            <?php echo esc_html( $twitter_status ); ?>
        </div>
        <div class="dashboard-card__action">
            <button type="button" class="dashboard-card__button">
                <?php echo esc_html( $twitter_action ); ?>
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('click', function(event) {
    var button = event.target.closest('.js-telegram-connect');
    if (!button) {
        return;
    }
    event.preventDefault();
    var endpoint = button.getAttribute('data-endpoint');
    var nonce = button.getAttribute('data-nonce');
    if (!endpoint || !nonce) {
        return;
    }
    button.disabled = true;
    fetch(endpoint, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': nonce
        }
    }).then(function(response) {
        return response.text().then(function(text) {
            var cleaned = text.replace(/^\uFEFF/, '');
            var data = {};
            try {
                data = JSON.parse(cleaned);
            } catch (err) {
                data = { parse_error: true };
            }
            return { ok: response.ok, data: data };
        });
    }).then(function(result) {
        if (result.ok && result.data && result.data.redirect_url) {
            window.location.href = result.data.redirect_url;
            return;
        }
        button.disabled = false;
        alert('Unable to connect Telegram.');
    }).catch(function() {
        button.disabled = false;
        alert('Unable to connect Telegram.');
    });
});
</script>

    <div class="stats_container">
        <div class="stat-card purple redirect_to" data-redirect_to="<?php echo site_url(); ?>/my-account/nodes/">
            <div class="icon">💻</div>
            <div class="value">
                <?php
                $userNodes = getNodesByUserID($current_user->ID);
                if(is_array($userNodes) && count($userNodes) > 0 && !isset($userNodes["detail"])){
                    echo count($userNodes);
                } else {
                    echo "0";
                }
                ?>
            </div>
            <div class="label"><?= __('Total Nodes','incrypted') ?></div>
        </div>
        <div class="stat-card orange redirect_to" data-redirect_to="<?php echo site_url(); ?>/my-account/my-wallet/">
            <div class="icon">💰</div>
            <div class="value">
                <?php
                $balance = woo_wallet()->wallet->get_wallet_balance(get_current_user_id());
                echo $balance;
                ?></div>
            <div class="label"><?= __('Your balance','incrypted') ?></div>
        </div>
        <div class="stat-card blue redirect_to" data-redirect_to="https://t.me/IncryptedPlus_WR">
            <div class="icon">👥</div>
            <div class="value">Incrypted+</div>
            <div class="label">
                <?php
                if( checkPlusSubscriptionWP($current_user->user_email)){

                    echo __('Subscribed', 'incrypted') . " ✅";
                    if (current_user_can('customer')) {
                        assign_customer_plus_role($current_user->ID);
                    }
                } else {
                    echo __('Unsubscribed', 'incrypted') . " ❌";
                    if (current_user_can('customer_plus')) {
                        assign_customer_role($current_user->ID);
                    }
                }
                ?>
            </div>
        </div>
    </div>

<?php if ( $overdue_count > 0 ) : ?>
    <?php
    // TODO: Add overdue filter handling on the nodes page (?status=overdue).
    $overdue_url = add_query_arg( 'status', 'overdue', wc_get_account_endpoint_url( 'nodes' ) );
    ?>
    <a class="dashboard-overdue" href="<?php echo esc_url( $overdue_url ); ?>">
        <span class="dashboard-overdue__icon" aria-hidden="true">⚠️</span>
        <span class="dashboard-overdue__title"><?php esc_html_e( 'Overdue', 'incrypted' ); ?></span>
        <span class="dashboard-overdue__text">
            <?php
            printf(
                esc_html__( 'You have %d overdue nodes. Please click to check', 'incrypted' ),
                (int) $overdue_count
            );
            ?>
        </span>
    </a>
<?php endif; ?>

<?php
	/**
	 * My Account dashboard.
	 *
	 * @since 2.6.0
	 */
	do_action( 'woocommerce_account_dashboard' );

	/**
	 * Deprecated woocommerce_before_my_account action.
	 *
	 * @deprecated 2.6.0
	 */
	do_action( 'woocommerce_before_my_account' );

	/**
	 * Deprecated woocommerce_after_my_account action.
	 *
	 * @deprecated 2.6.0
	 */
	do_action( 'woocommerce_after_my_account' );

/* Omit closing PHP tag at the end of PHP files to avoid "headers already sent" issues. */
