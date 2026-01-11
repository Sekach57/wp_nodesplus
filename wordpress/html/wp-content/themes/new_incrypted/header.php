<?php
/**
 * The header for our theme
 *
 * This is the template that displays all of the <head> section and everything up until <div id="content">
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package incrypted
 */
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="https://gmpg.org/xfn/11">

	<?php wp_head(); ?>

    <meta name="theme-color" content="#1E1E1E">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter+Tight:wght@500;600&family=Onest:wght@300;400;500&display=swap"
          rel="stylesheet">
</head>

<body>
<?php wp_body_open(); ?>

<header class="header">
    <div class="container">

        <?php
        $current_lang = pll_current_language();

        switch ($current_lang) {
            case "en":
                $header = get_field('acf_header_settings_en', 'option');
                $footer = get_field('acf_footer_en', 'option');
                break;

            default:
                $header = get_field('acf_header_settings_ua', 'option');
                $footer = get_field('acf_footer_ua', 'option');
                break;
        }

        if (is_front_page() || is_home()) {
            $home_url = '';
        } else {
            if (function_exists('pll_home_url')) {
                $current_lang = pll_current_language();
                $home_url = pll_home_url($current_lang);
            } else {
                $home_url = home_url('/');
            }
        }
        ?>

        <div class="logo">
            <a href="/"><img src="<?= $header["logo"]["url"]; ?>" alt="<?= $header["logo"]["alt"]; ?>"/></a>
        </div>

        <div class="menu_and_languages">
            <ul class="menu">
                <?php foreach ($header["menu"] as $menu_item) { ?>
                    <li>
                        <a href="<?= $home_url ?><?= $menu_item["menu_item"]["url"] ?>"><?= $menu_item["menu_item"]["title"] ?></a>
                    </li>
                <?php } ?>
            </ul>

            <?php custom_language_switcher(); ?>

            <a href="<?= $footer["link_mobile"]["url"] ?? "#nodes" ?>"
               class="red_button_link"><?= $footer["link_mobile"]["title"] ?? "Приєднатися зараз" ?></a>
        </div>

        <?php if ($header['discord_url']) : ?>
            <div class="dis-link">
                <a href="<?= $header['discord_url'] ?>" target="_blank">
                    <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="20" height="20" viewBox="0 -28.5 256 256" version="1.1" preserveAspectRatio="xMidYMid">
                        <g>
                            <path d="M216.856339,16.5966031 C200.285002,8.84328665 182.566144,3.2084988 164.041564,0 C161.766523,4.11318106 159.108624,9.64549908 157.276099,14.0464379 C137.583995,11.0849896 118.072967,11.0849896 98.7430163,14.0464379 C96.9108417,9.64549908 94.1925838,4.11318106 91.8971895,0 C73.3526068,3.2084988 55.6133949,8.86399117 39.0420583,16.6376612 C5.61752293,67.146514 -3.4433191,116.400813 1.08711069,164.955721 C23.2560196,181.510915 44.7403634,191.567697 65.8621325,198.148576 C71.0772151,190.971126 75.7283628,183.341335 79.7352139,175.300261 C72.104019,172.400575 64.7949724,168.822202 57.8887866,164.667963 C59.7209612,163.310589 61.5131304,161.891452 63.2445898,160.431257 C105.36741,180.133187 151.134928,180.133187 192.754523,160.431257 C194.506336,161.891452 196.298154,163.310589 198.110326,164.667963 C191.183787,168.842556 183.854737,172.420929 176.223542,175.320965 C180.230393,183.341335 184.861538,190.991831 190.096624,198.16893 C211.238746,191.588051 232.743023,181.531619 254.911949,164.955721 C260.227747,108.668201 245.831087,59.8662432 216.856339,16.5966031 Z M85.4738752,135.09489 C72.8290281,135.09489 62.4592217,123.290155 62.4592217,108.914901 C62.4592217,94.5396472 72.607595,82.7145587 85.4738752,82.7145587 C98.3405064,82.7145587 108.709962,94.5189427 108.488529,108.914901 C108.508531,123.290155 98.3405064,135.09489 85.4738752,135.09489 Z M170.525237,135.09489 C157.88039,135.09489 147.510584,123.290155 147.510584,108.914901 C147.510584,94.5396472 157.658606,82.7145587 170.525237,82.7145587 C183.391518,82.7145587 193.761324,94.5189427 193.539891,108.914901 C193.539891,123.290155 183.391518,135.09489 170.525237,135.09489 Z" fill="#fff" fill-rule="nonzero"></path>
                        </g>
                    </svg>
                </a>
            </div>
        <?php endif; ?>

        <a href="#" class="cart_icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                <path d="M4 19C4 19.5304 4.21071 20.0391 4.58579 20.4142C4.96086 20.7893 5.46957 21 6 21C6.53043 21 7.03914 20.7893 7.41421 20.4142C7.78929 20.0391 8 19.5304 8 19C8 18.4696 7.78929 17.9609 7.41421 17.5858C7.03914 17.2107 6.53043 17 6 17C5.46957 17 4.96086 17.2107 4.58579 17.5858C4.21071 17.9609 4 18.4696 4 19ZM15 19C15 19.5304 15.2107 20.0391 15.5858 20.4142C15.9609 20.7893 16.4696 21 17 21C17.5304 21 18.0391 20.7893 18.4142 20.4142C18.7893 20.0391 19 19.5304 19 19C19 18.4696 18.7893 17.9609 18.4142 17.5858C18.0391 17.2107 17.5304 17 17 17C16.4696 17 15.9609 17.2107 15.5858 17.5858C15.2107 17.9609 15 18.4696 15 19Z" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M17 17H6V3H4" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M6 5L20 6L19 13H6" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <div class="items_in_cart" style="display: none;">0</div>
        </a>

        <?php if ( function_exists( 'incr_is_local_env' ) && incr_is_local_env() && defined( 'INCR_USE_MOCK_NODES' ) && INCR_USE_MOCK_NODES && is_user_logged_in() ) : ?>
            <?php
            $mock_nodes = function_exists( 'incr_get_mock_nodes_data' ) ? incr_get_mock_nodes_data( get_current_user_id() ) : [];
            ?>
            <div class="mock-nodes-menu">
                <button type="button" class="mock-nodes-menu__toggle">Mock nodes</button>
                <div class="mock-nodes-menu__panel">
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="incr_save_mock_nodes">
                        <?php wp_nonce_field( 'incr_save_mock_nodes', 'incr_mock_nodes_nonce' ); ?>

                        <?php foreach ( $mock_nodes as $index => $node ) : ?>
                            <?php
                            $created_value = '';
                            if ( ! empty( $node['created_at'] ) ) {
                                $timestamp = strtotime( $node['created_at'] );
                                $created_value = $timestamp ? gmdate( 'Y-m-d', $timestamp ) : '';
                            }
                            $due_value = '';
                            if ( ! empty( $node['due_date'] ) ) {
                                $timestamp = strtotime( $node['due_date'] );
                                $due_value = $timestamp ? gmdate( 'Y-m-d', $timestamp ) : '';
                            }
                            ?>
                            <div class="mock-nodes-menu__item">
                                <div class="mock-nodes-menu__row">
                                    <label for="mock-node-id-<?php echo esc_attr( $index ); ?>">Node ID</label>
                                    <input type="text" id="mock-node-id-<?php echo esc_attr( $index ); ?>" name="mock_nodes[<?php echo esc_attr( $index ); ?>][id]" value="<?php echo esc_attr( $node['id'] ?? '' ); ?>">
                                </div>
                                <div class="mock-nodes-menu__row">
                                    <label for="mock-node-type-<?php echo esc_attr( $index ); ?>">Name</label>
                                    <input type="text" id="mock-node-type-<?php echo esc_attr( $index ); ?>" name="mock_nodes[<?php echo esc_attr( $index ); ?>][node_type]" value="<?php echo esc_attr( $node['node_type'] ?? '' ); ?>">
                                </div>
                                <div class="mock-nodes-menu__row">
                                    <label for="mock-node-price-<?php echo esc_attr( $index ); ?>">Price</label>
                                    <input type="text" id="mock-node-price-<?php echo esc_attr( $index ); ?>" name="mock_nodes[<?php echo esc_attr( $index ); ?>][price]" value="<?php echo esc_attr( $node['price'] ?? '' ); ?>">
                                </div>
                                <div class="mock-nodes-menu__row">
                                    <label for="mock-node-created-<?php echo esc_attr( $index ); ?>">Created at</label>
                                    <input type="date" id="mock-node-created-<?php echo esc_attr( $index ); ?>" name="mock_nodes[<?php echo esc_attr( $index ); ?>][created_at]" value="<?php echo esc_attr( $created_value ); ?>">
                                </div>
                                <div class="mock-nodes-menu__row">
                                    <label for="mock-node-due-<?php echo esc_attr( $index ); ?>">Due date</label>
                                    <input type="date" id="mock-node-due-<?php echo esc_attr( $index ); ?>" name="mock_nodes[<?php echo esc_attr( $index ); ?>][due_date]" value="<?php echo esc_attr( $due_value ); ?>">
                                </div>
                                <div class="mock-nodes-menu__row">
                                    <label for="mock-node-status-<?php echo esc_attr( $index ); ?>">Status</label>
                                    <input type="text" id="mock-node-status-<?php echo esc_attr( $index ); ?>" name="mock_nodes[<?php echo esc_attr( $index ); ?>][status]" value="<?php echo esc_attr( $node['status'] ?? '' ); ?>">
                                </div>
                                <div class="mock-nodes-menu__row">
                                    <label for="mock-node-period-<?php echo esc_attr( $index ); ?>">Billing</label>
                                    <input type="text" id="mock-node-period-<?php echo esc_attr( $index ); ?>" name="mock_nodes[<?php echo esc_attr( $index ); ?>][billing_period]" value="<?php echo esc_attr( $node['billing_period'] ?? '' ); ?>">
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <div class="mock-nodes-menu__actions">
                            <button type="submit" class="mock-nodes-menu__save">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <a href="<?php echo wc_get_account_endpoint_url('dashboard'); ?>" class="profile-link">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 18 18" fill="none">
                <path fill-rule="evenodd" clip-rule="evenodd"
                      d="M3.34835 11.5984C4.05161 10.8951 5.00544 10.5 6 10.5H12C12.9946 10.5 13.9484 10.8951 14.6517 11.5984C15.3549 12.3016 15.75 13.2554 15.75 14.25V15.75C15.75 16.1642 15.4142 16.5 15 16.5C14.5858 16.5 14.25 16.1642 14.25 15.75V14.25C14.25 13.6533 14.0129 13.081 13.591 12.659C13.169 12.2371 12.5967 12 12 12H6C5.40326 12 4.83097 12.2371 4.40901 12.659C3.98705 13.081 3.75 13.6533 3.75 14.25V15.75C3.75 16.1642 3.41421 16.5 3 16.5C2.58579 16.5 2.25 16.1642 2.25 15.75V14.25C2.25 13.2554 2.64509 12.3016 3.34835 11.5984Z"
                      fill="white"></path>
                <path fill-rule="evenodd" clip-rule="evenodd"
                      d="M9 3C7.75736 3 6.75 4.00736 6.75 5.25C6.75 6.49264 7.75736 7.5 9 7.5C10.2426 7.5 11.25 6.49264 11.25 5.25C11.25 4.00736 10.2426 3 9 3ZM5.25 5.25C5.25 3.17893 6.92893 1.5 9 1.5C11.0711 1.5 12.75 3.17893 12.75 5.25C12.75 7.32107 11.0711 9 9 9C6.92893 9 5.25 7.32107 5.25 5.25Z"
                      fill="white"></path>
            </svg>
        </a>

        <div class="hidden_menu_btn"></div>

    </div>
</header>
