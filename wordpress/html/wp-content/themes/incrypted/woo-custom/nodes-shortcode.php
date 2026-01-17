<?php
add_action('wp_ajax_add_to_cart_custom', 'handle_custom_add_to_cart');
add_action('wp_ajax_nopriv_add_to_cart_custom', 'handle_custom_add_to_cart');

function handle_custom_add_to_cart() {
    check_ajax_referer('custom_cart_nonce', 'nonce');

    $product_id = intval($_POST['product_id']);
    $quantity = intval($_POST['quantity']);

    if ($product_id && $quantity > 0) {
        $result = WC()->cart->add_to_cart($product_id, $quantity);

        if ($result) {
            wp_send_json_success([
                'message' => __('Added to cart', 'incrypted'),
                'cart_count' => WC()->cart->get_cart_contents_count(),
                'cart_total' => WC()->cart->get_cart_total()
            ]);
        } else {
            wp_send_json_error(['message' => 'Error']);
        }
    } else {
        wp_send_json_error(['message' => __('Incorrect parameters', 'incrypted')]);
    }
}

add_action('wp_ajax_get_cart_count', 'get_cart_count');
add_action('wp_ajax_nopriv_get_cart_count', 'get_cart_count');

function get_cart_count() {
    wp_send_json_success([
        'cart_count' => WC()->cart->get_cart_contents_count(),
        'cart_total' => WC()->cart->get_cart_total()
    ]);
}

add_shortcode('custom_nodes_list', 'display_custom_nodes');

function display_custom_nodes($atts) {
    $atts = shortcode_atts([
        'category' => '',
        'ids' => '',
    ], $atts);

    $args = [
        'post_type' => 'product',
        'posts_per_page' => -1,
        'post_status' => 'publish'
    ];

    if ($atts['category']) {
        $args['tax_query'] = [
            [
                'taxonomy' => 'product_cat',
                'field' => 'slug',
                'terms' => $atts['category']
            ]
        ];
    }

    if ($atts['ids']) {
        $args['post__in'] = explode(',', $atts['ids']);
    }

    $products = new WP_Query($args);

    $current_lang = pll_current_language();

    switch ($current_lang) {
        case "en":
            $piece = "pc";
            $pieces = "pcs";
            $per_month = "/month";
            $more = "More...";
            break;

        default:
            $piece = "&#1096;&#1090;.";
            $pieces = "&#1096;&#1090;.";
            $per_month = "/&#1084;&#1110;&#1089;&#1103;&#1094;&#1100;";
            $more = "&#1044;&#1077;&#1090;&#1072;&#1083;&#1100;&#1085;&#1110;&#1096;&#1077;...";
            break;
    }

    $nodes = get_field('acf_nodes_block');

    ob_start();
    ?>
    <section id="nodes" class="nodes">
        <div class="container">
            <h2><?= $nodes["header"] ?></h2>
            <div class="node_items">
                <?php while ($products->have_posts()) : $products->the_post();
                    $product = wc_get_product(get_the_ID());
                    $price = $product->get_price_html();
                    $image = wp_get_attachment_image_src(get_post_thumbnail_id(), 'full');
                    ?>
                    <div class="node_item" data-product-id="<?php echo get_the_ID(); ?>">
                        <div class="node_item_info">
                            <div class="node_item_title">
                                <?php if ($image): ?>
                                    <img src="<?php echo esc_url($image[0]); ?>" alt="<?php echo esc_attr(get_the_title()); ?>"/>
                                <?php endif; ?>
                                <span class="node_item_title-text"><?php the_title(); ?></span>
                                <?php echo incr_render_product_pills( get_the_ID() ); ?>
                            </div>
                            <div class="node_item_description">
                                <?php echo wp_trim_words(get_the_excerpt(), 20); ?>
                            </div>
                        </div>
                        <div class="node_item_price"><?php echo $price; ?><?= $per_month ?></div>
                        <div class="node_item_action_btns">
                            <div class="custom_quantity">
                                <div class="minus">-</div>
                                <input type="text" class="node_quantity" placeholder="1" value="1" />
                                <div class="plus">+</div>
                            </div>
                            <button class="btn_2 quantity-btn" data-quantity="1">1 <?= $piece ?></button>
                            <button class="btn_2 quantity-btn" data-quantity="5">5 <?= $pieces ?></button>
                            <button class="btn_2 quantity-btn" data-quantity="10">10 <?= $pieces ?></button>
                            <button class="btn_2 quantity-btn" data-quantity="25">25 <?= $pieces ?></button>

                            <button class="btn add-to-cart-btn"><?= $nodes["buy_now_button_text"] ?></button>
                        </div>
                    </div>
                <?php endwhile; wp_reset_postdata(); ?>
            </div>

                <!-- Node Details Modal -->
                <div class="np-modal" id="np-node-modal" aria-hidden="true">
                    <div class="np-modal__backdrop" data-np-modal-close></div>
                    <div class="np-modal__panel" role="dialog" aria-modal="true" aria-labelledby="np-node-modal-title">
                        <div class="np-modal__header">
                            <div class="np-modal__brand">
                                <img class="np-modal__logo" src="" alt="" aria-hidden="true" />
                                <h3 class="np-modal__title" id="np-node-modal-title">Node details</h3>
                            </div>
                            <button type="button" class="np-modal__close" data-np-modal-close aria-label="<?php esc_attr_e( 'Close', 'incrypted' ); ?>">?</button>
                        </div>
                        <div class="np-modal__body">
                            <div class="np-modal__column np-modal__column--details">
                                <div class="np-modal__card np-modal__details-panel">
                                    <div class="np-modal__content"></div>
                                </div>
                            </div>
                            <div class="np-modal__column np-modal__column--actions">
                                <div class="np-modal__card np-modal__actions-panel">
                                    <div class="np-modal__price"></div>
                                    <div class="np-modal__social">
                                        <a class="np-modal__social-button" href="#" target="_blank" rel="noopener" data-np-modal-discord aria-label="<?php esc_attr_e( 'Discord', 'incrypted' ); ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 -28.5 256 256" aria-hidden="true" focusable="false"><path d="M216.856339,16.5966031 C200.285002,8.84328665 182.566144,3.2084988 164.041564,0 C161.766523,4.11318106 159.108624,9.64549908 157.276099,14.0464379 C137.583995,11.0849896 118.072967,11.0849896 98.7430163,14.0464379 C96.9108417,9.64549908 94.1925838,4.11318106 91.8971895,0 C73.3526068,3.2084988 55.6133949,8.86399117 39.0420583,16.6376612 C5.61752293,67.146514 -3.4433191,116.400813 1.08711069,164.955721 C23.2560196,181.510915 44.7403634,191.567697 65.8621325,198.148576 C71.0772151,190.971126 75.7283628,183.341335 79.7352139,175.300261 C72.104019,172.400575 64.7949724,168.822202 57.8887866,164.667963 C59.7209612,163.310589 61.5131304,161.891452 63.2445898,160.431257 C105.36741,180.133187 151.134928,180.133187 192.754523,160.431257 C194.506336,161.891452 196.298154,163.310589 198.110326,164.667963 C191.183787,168.842556 183.854737,172.420929 176.223542,175.320965 C180.230393,183.341335 184.861538,190.991831 190.096624,198.16893 C211.238746,191.588051 232.743023,181.531619 254.911949,164.955721 C260.227747,108.668201 245.831087,59.8662432 216.856339,16.5966031 Z M85.4738752,135.09489 C72.8290281,135.09489 62.4592217,123.290155 62.4592217,108.914901 C62.4592217,94.5396472 72.607595,82.7145587 85.4738752,82.7145587 C98.3405064,82.7145587 108.709962,94.5189427 108.488529,108.914901 C108.508531,123.290155 98.3405064,135.09489 85.4738752,135.09489 Z M170.525237,135.09489 C157.88039,135.09489 147.510584,123.290155 147.510584,108.914901 C147.510584,94.5396472 157.658606,82.7145587 170.525237,82.7145587 C183.391518,82.7145587 193.761324,94.5189427 193.539891,108.914901 C193.539891,123.290155 183.391518,135.09489 170.525237,135.09489 Z" fill="#5865F2" fill-rule="nonzero"></path></svg>
                                        </a>
                                        <a class="np-modal__social-button" href="#" target="_blank" rel="noopener" data-np-modal-telegram aria-label="<?php esc_attr_e( 'Telegram', 'incrypted' ); ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M22 3L2.6 11.1c-.9.4-.9 1.7.1 2l4.8 1.6 2 6.1c.3.9 1.5 1 2 .2l2.9-4 5.2 3.8c.8.6 1.9.1 2.1-.9L24 4.4c.2-1.1-.8-2-2-1.4Z" fill="#229ED9"></path></svg>
                                        </a>
                                        <a class="np-modal__social-button" href="#" target="_blank" rel="noopener" data-np-modal-twitter aria-label="<?php esc_attr_e( 'Twitter', 'incrypted' ); ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M18.9 2H22l-7.3 8.3L23.6 22h-7l-5.5-7.2L4.7 22H1.5l7.8-8.9L.7 2h7.2l4.9 6.4L18.9 2Zm-2 18h2.1L7.1 4h-2L16.9 20Z" fill="#0f172a"></path></svg>
                                        </a>
                                    </div>
                                    <a class="np-modal__secondary" href="#" target="_blank" rel="noopener" data-np-modal-guide><?php esc_html_e( 'Installation guide', 'incrypted' ); ?></a>
                                    <a class="np-modal__action" href="#" data-np-modal-add-to-cart><?php esc_html_e( 'Add to cart', 'incrypted' ); ?></a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <a href="<?php echo wc_get_checkout_url(); ?>" class="btn start_order" id="checkout-btn"><?= $nodes["start_order_button_text"] ?></a>
        </div>
    </section>
    <?php
    return ob_get_clean();
}
