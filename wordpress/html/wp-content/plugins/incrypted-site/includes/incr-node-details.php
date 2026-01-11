<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function incr_get_node_details_ajax() {
    if ( ! check_ajax_referer( 'np_node_details', 'nonce', false ) ) {
        wp_send_json_error( [ 'message' => 'invalid_nonce' ], 403 );
    }

    $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
    if ( ! $product_id ) {
        wp_send_json_error( [ 'message' => 'missing_product' ], 400 );
    }

    $product = wc_get_product( $product_id );
    if ( ! $product ) {
        wp_send_json_error( [ 'message' => 'product_not_found' ], 404 );
    }

    $details = get_post_meta( $product_id, '_np_node_details', true );
    if ( $details === '' ) {
        $details = $product->get_description();
    }

    $image_id = $product->get_image_id();
    $image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'large' ) : '';

    wp_send_json_success( [
        'title' => $product->get_name(),
        'image' => $image_url,
        'price_html' => wp_kses_post( $product->get_price_html() ),
        'details' => wp_kses_post( $details ),
        'add_to_cart_url' => $product->add_to_cart_url(),
        'add_to_cart_text' => $product->add_to_cart_text(),
        'discord_url' => get_post_meta( $product_id, 'np_discord_url', true ),
        'telegram_url' => get_post_meta( $product_id, 'np_telegram_url', true ),
        'twitter_url' => get_post_meta( $product_id, 'np_twitter_url', true ),
        'guide_url' => get_post_meta( $product_id, 'np_installation_guide_url', true ),
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
    $editor_id = 'np_node_details';
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
}
add_action( 'save_post', 'incr_save_node_details_metabox' );
