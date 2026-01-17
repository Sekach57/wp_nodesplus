<?php
/**
 * The template for displaying all pages
 *
 * This is the template that displays all pages by default.
 * Please note that this is the WordPress construct of pages
 * and that other 'pages' on your WordPress site may use a
 * different template.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package incrypted
 */

get_header();
?>
    <main class="<?php echo set_custom_main_class(); ?>">


        <?php if (str_contains(set_custom_main_class(), 'custom_woocommerce')) {
            echo '<button class="btn select_node" style="margin-bottom: 24px;">' . __('Select nodes','incrypted') .'</button>';
            echo '<div class="woocommerce_container">';
        } ?>

        <?php if (
            !str_contains(set_custom_main_class(), 'custom_woocommerce') &&
            !is_home() &&
            !is_front_page()
        ) {
            echo '<div class="inner_page">';
        } ?>


            <?php
            while (have_posts()) :
                the_post();

                get_template_part('template-parts/content', 'page');

            endwhile; // End of the loop.
            ?>

        <?php if (str_contains(set_custom_main_class(), 'custom_woocommerce')) {
            echo '</div>';
        } ?>

        <?php if ( is_home() || is_front_page() ) {
            echo '</div>';
        } ?>

    </main>
<?php
get_footer();
