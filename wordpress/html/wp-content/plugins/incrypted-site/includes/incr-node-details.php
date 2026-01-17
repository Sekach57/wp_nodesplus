<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Available tiers and categories
function incr_get_available_tiers() {
    return [
        '' => __( '— Select Tier —', 'incrypted' ),
        'tier-top' => 'Tier TOP',
        'tier-1' => 'Tier 1',
        'tier-2' => 'Tier 2',
        'tier-3' => 'Tier 3',
    ];
}

function incr_get_available_categories() {
    return [
        'depin' => 'DePIN',
        'rpc' => 'RPC',
        'validator' => 'Validator',
        'ai' => 'AI',
        'gaming' => 'Gaming',
        'defi' => 'DeFi',
        'infrastructure' => 'Infrastructure',
        'storage' => 'Storage',
    ];
}

function incr_get_node_details_ajax() {
    $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
    if ( $nonce && ! wp_verify_nonce( $nonce, 'np_node_details' ) ) {
        // Nonce is optional for public node details; ignore invalid values.
    }

    $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
    if ( ! $product_id ) {
        wp_send_json_error( [ 'message' => 'missing_product' ], 400 );
    }

    $product = wc_get_product( $product_id );
    if ( ! $product ) {
        wp_send_json_error( [ 'message' => 'product_not_found' ], 404 );
    }

    $details = (string) incr_get_meta_with_translation_fallback( $product_id, '_np_node_details' );
    if ( trim( wp_strip_all_tags( $details ) ) === '' ) {
        $details = $product->get_description();
    }
    if ( trim( wp_strip_all_tags( $details ) ) === '' ) {
        $details = $product->get_short_description();
    }
    if ( trim( wp_strip_all_tags( $details ) ) === '' ) {
        $details = get_the_excerpt( $product_id );
    }
    if ( trim( wp_strip_all_tags( $details ) ) === '' ) {
        $details = __( 'Details are coming soon.', 'incrypted' );
    }

    $image_id = $product->get_image_id();
    $image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'large' ) : '';

    // Get tier and categories
    $tier = incr_get_meta_with_translation_fallback( $product_id, 'np_project_tier' );
    $categories = incr_get_meta_with_translation_fallback( $product_id, 'np_project_categories' );
    if ( ! is_array( $categories ) ) {
        $categories = [];
    }

    // Get tier label
    $tiers = incr_get_available_tiers();
    $tier_label = isset( $tiers[ $tier ] ) ? $tiers[ $tier ] : '';

    // Get category labels
    $all_categories = incr_get_available_categories();
    $category_labels = [];
    foreach ( $categories as $cat ) {
        if ( isset( $all_categories[ $cat ] ) ) {
            $category_labels[] = [
                'slug' => $cat,
                'label' => $all_categories[ $cat ],
            ];
        }
    }

    wp_send_json_success( [
        'title' => $product->get_name(),
        'image' => $image_url,
        'price_html' => wp_kses_post( $product->get_price_html() ),
        'details' => wp_kses_post( $details ),
        'add_to_cart_url' => $product->add_to_cart_url(),
        'add_to_cart_text' => $product->add_to_cart_text(),
        'discord_url' => incr_get_meta_with_translation_fallback( $product_id, 'np_discord_url' ),
        'telegram_url' => incr_get_meta_with_translation_fallback( $product_id, 'np_telegram_url' ),
        'twitter_url' => incr_get_meta_with_translation_fallback( $product_id, 'np_twitter_url' ),
        'guide_url' => incr_get_meta_with_translation_fallback( $product_id, 'np_installation_guide_url' ),
        'tier' => $tier,
        'tier_label' => $tier_label,
        'categories' => $category_labels,
    ] );
}
add_action( 'wp_ajax_np_node_details', 'incr_get_node_details_ajax' );
add_action( 'wp_ajax_nopriv_np_node_details', 'incr_get_node_details_ajax' );

function incr_add_node_details_metabox() {
    add_meta_box(
        'incr_node_details',
        __( 'Node details (for popup)', 'incrypted' ),
        'incr_render_node_details_metabox',
        'product',
        'normal',
        'default'
    );
}
add_action( 'add_meta_boxes', 'incr_add_node_details_metabox' );

function incr_render_node_details_metabox( $post ) {
    wp_nonce_field( 'incr_save_node_details', 'incr_node_details_nonce' );
    $value = get_post_meta( $post->ID, '_np_node_details', true );
    $discord_url = get_post_meta( $post->ID, 'np_discord_url', true );
    $telegram_url = get_post_meta( $post->ID, 'np_telegram_url', true );
    $twitter_url = get_post_meta( $post->ID, 'np_twitter_url', true );
    $guide_url = get_post_meta( $post->ID, 'np_installation_guide_url', true );

    // Get tier and categories
    $current_tier = get_post_meta( $post->ID, 'np_project_tier', true );
    $current_categories = get_post_meta( $post->ID, 'np_project_categories', true );
    if ( ! is_array( $current_categories ) ) {
        $current_categories = [];
    }

    $tiers = incr_get_available_tiers();
    $categories = incr_get_available_categories();

    $editor_id = 'np_node_details';
    ?>

    <h4 style="margin-top: 0;"><?php esc_html_e( 'Project Classification', 'incrypted' ); ?></h4>
    <p>
        <label for="np_project_tier"><strong><?php esc_html_e( 'Project Tier', 'incrypted' ); ?></strong></label><br>
        <select id="np_project_tier" name="np_project_tier" style="width: 200px;">
            <?php foreach ( $tiers as $value => $label ) : ?>
                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_tier, $value ); ?>>
                    <?php echo esc_html( $label ); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </p>
    <p>
        <label><strong><?php esc_html_e( 'Project Categories', 'incrypted' ); ?></strong> <small>(<?php esc_html_e( 'select up to 3', 'incrypted' ); ?>)</small></label><br>
        <span class="np-categories-checkboxes" style="display: flex; flex-wrap: wrap; gap: 10px; margin-top: 5px;">
        <?php foreach ( $categories as $value => $label ) : ?>
            <label style="display: inline-flex; align-items: center; gap: 4px; background: #f0f0f0; padding: 4px 10px; border-radius: 4px; cursor: pointer;">
                <input type="checkbox" name="np_project_categories[]" value="<?php echo esc_attr( $value ); ?>"
                    <?php checked( in_array( $value, $current_categories, true ) ); ?>
                    class="np-category-checkbox">
                <?php echo esc_html( $label ); ?>
            </label>
        <?php endforeach; ?>
        </span>
    </p>
    <script>
    (function() {
        var checkboxes = document.querySelectorAll('.np-category-checkbox');
        var maxCategories = 3;

        function updateCheckboxes() {
            var checked = document.querySelectorAll('.np-category-checkbox:checked').length;
            checkboxes.forEach(function(cb) {
                if (!cb.checked && checked >= maxCategories) {
                    cb.disabled = true;
                    cb.parentElement.style.opacity = '0.5';
                } else {
                    cb.disabled = false;
                    cb.parentElement.style.opacity = '1';
                }
            });
        }

        checkboxes.forEach(function(cb) {
            cb.addEventListener('change', updateCheckboxes);
        });

        updateCheckboxes();
    })();
    </script>

    <hr style="margin: 20px 0;">
    <h4><?php esc_html_e( 'Node Details Content', 'incrypted' ); ?></h4>

    <?php
    wp_editor(
        $value,
        $editor_id,
        [
            'textarea_name' => '_np_node_details',
            'media_buttons' => true,
            'textarea_rows' => 8,
            'teeny' => false,
            'tinymce' => [
                'toolbar1' => 'bold,italic,underline,bullist,numlist,link,unlink,undo,redo',
                'toolbar2' => '',
            ],
        ]
    );
    ?>

    <hr style="margin: 20px 0;">
    <h4><?php esc_html_e( 'Social Links', 'incrypted' ); ?></h4>

    <p>
        <label for="np_discord_url"><?php esc_html_e( 'Discord URL', 'incrypted' ); ?></label><br>
        <input type="url" class="widefat" id="np_discord_url" name="np_discord_url" value="<?php echo esc_attr( $discord_url ); ?>">
    </p>
    <p>
        <label for="np_telegram_url"><?php esc_html_e( 'Telegram URL', 'incrypted' ); ?></label><br>
        <input type="url" class="widefat" id="np_telegram_url" name="np_telegram_url" value="<?php echo esc_attr( $telegram_url ); ?>">
    </p>
    <p>
        <label for="np_twitter_url"><?php esc_html_e( 'Twitter (X) URL', 'incrypted' ); ?></label><br>
        <input type="url" class="widefat" id="np_twitter_url" name="np_twitter_url" value="<?php echo esc_attr( $twitter_url ); ?>">
    </p>
    <p>
        <label for="np_installation_guide_url"><?php esc_html_e( 'Installation guide URL', 'incrypted' ); ?></label><br>
        <input type="url" class="widefat" id="np_installation_guide_url" name="np_installation_guide_url" value="<?php echo esc_attr( $guide_url ); ?>">
    </p>
    <?php
}

function incr_save_node_details_metabox( $post_id ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! isset( $_POST['incr_node_details_nonce'] ) || ! wp_verify_nonce( $_POST['incr_node_details_nonce'], 'incr_save_node_details' ) ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }
    if ( get_post_type( $post_id ) !== 'product' ) {
        return;
    }
    if ( isset( $_POST['_np_node_details'] ) ) {
        $value = wp_kses_post( wp_unslash( $_POST['_np_node_details'] ) );
        update_post_meta( $post_id, '_np_node_details', $value );
    }
    if ( isset( $_POST['np_discord_url'] ) ) {
        update_post_meta( $post_id, 'np_discord_url', esc_url_raw( wp_unslash( $_POST['np_discord_url'] ) ) );
    }
    if ( isset( $_POST['np_telegram_url'] ) ) {
        update_post_meta( $post_id, 'np_telegram_url', esc_url_raw( wp_unslash( $_POST['np_telegram_url'] ) ) );
    }
    if ( isset( $_POST['np_twitter_url'] ) ) {
        update_post_meta( $post_id, 'np_twitter_url', esc_url_raw( wp_unslash( $_POST['np_twitter_url'] ) ) );
    }
    if ( isset( $_POST['np_installation_guide_url'] ) ) {
        update_post_meta( $post_id, 'np_installation_guide_url', esc_url_raw( wp_unslash( $_POST['np_installation_guide_url'] ) ) );
    }

    // Save tier
    if ( isset( $_POST['np_project_tier'] ) ) {
        $tier = sanitize_text_field( wp_unslash( $_POST['np_project_tier'] ) );
        $valid_tiers = array_keys( incr_get_available_tiers() );
        if ( in_array( $tier, $valid_tiers, true ) ) {
            update_post_meta( $post_id, 'np_project_tier', $tier );
        }
    }

    // Save categories (max 3)
    if ( isset( $_POST['np_project_categories'] ) && is_array( $_POST['np_project_categories'] ) ) {
        $categories = array_map( 'sanitize_text_field', wp_unslash( $_POST['np_project_categories'] ) );
        $valid_categories = array_keys( incr_get_available_categories() );
        $categories = array_intersect( $categories, $valid_categories );
        $categories = array_slice( $categories, 0, 3 ); // Max 3
        update_post_meta( $post_id, 'np_project_categories', $categories );
    } else {
        update_post_meta( $post_id, 'np_project_categories', [] );
    }
}
add_action( 'save_post', 'incr_save_node_details_metabox' );

// Helper function to render pills for a product
function incr_get_meta_with_translation_fallback( $post_id, $meta_key ) {
    $value = get_post_meta( $post_id, $meta_key, true );
    $has_value = false;

    if ( is_array( $value ) ) {
        $has_value = ! empty( $value );
    } else {
        $has_value = trim( (string) $value ) !== '';
    }

    if ( $has_value || ! function_exists( 'pll_get_post_translations' ) ) {
        return $value;
    }

    $translations = pll_get_post_translations( $post_id );
    if ( empty( $translations ) || ! is_array( $translations ) ) {
        return $value;
    }

    foreach ( $translations as $translated_id ) {
        if ( ! $translated_id || $translated_id === $post_id ) {
            continue;
        }
        $candidate = get_post_meta( $translated_id, $meta_key, true );
        if ( is_array( $candidate ) ) {
            if ( ! empty( $candidate ) ) {
                return $candidate;
            }
        } elseif ( trim( (string) $candidate ) !== '' ) {
            return $candidate;
        }
    }

    return $value;
}

function incr_render_product_pills( $product_id ) {
    if ( ! function_exists( 'incr_get_available_tiers' ) || ! function_exists( 'incr_get_available_categories' ) ) {
        return '';
    }

    $tier = incr_get_meta_with_translation_fallback( $product_id, 'np_project_tier' );
    $categories = incr_get_meta_with_translation_fallback( $product_id, 'np_project_categories' );
    if ( ! is_array( $categories ) ) {
        $categories = [];
    }

    $tiers = incr_get_available_tiers();
    $all_categories = incr_get_available_categories();

    $pills = [];

    // Add tier pill
    if ( $tier && isset( $tiers[ $tier ] ) && $tier !== '' ) {
        $pills[] = sprintf(
            '<span class="np-pill np-pill--tier np-pill--%s">%s</span>',
            esc_attr( $tier ),
            esc_html( $tiers[ $tier ] )
        );
    }

    // Add category pills (max 3)
    $count = 0;
    foreach ( $categories as $cat ) {
        if ( $count >= 3 ) break;
        if ( isset( $all_categories[ $cat ] ) ) {
            $pills[] = sprintf(
                '<span class="np-pill np-pill--category np-pill--%s">%s</span>',
                esc_attr( $cat ),
                esc_html( $all_categories[ $cat ] )
            );
            $count++;
        }
    }

    if ( empty( $pills ) ) {
        return '';
    }

    return '<div class="np-pills">' . implode( '', $pills ) . '</div>';
}
