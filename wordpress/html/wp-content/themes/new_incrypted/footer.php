<?php
/**
 * The template for displaying the footer
 *
 * Contains the closing of the #content div and all content after.
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package incrypted
 */

?>


<?php
$current_lang = pll_current_language();

switch ($current_lang) {
    case "en":
        $footer = get_field('acf_footer_en', 'option');
        $header = get_field('acf_header_settings_en', 'option');
        break;

    default:
        $footer = get_field('acf_footer_ua', 'option');
        $header = get_field('acf_header_settings_en', 'option');
        break;
}
?>

<footer>
    <div class="container">
        <div class="footer_left">
            <img src="<?= $header["logo"]["url"]; ?>" alt="Nodes plus"/>
            <p><?= $footer["have_a_questions"] ?? '<span>Залишились питання?</span> Напиши нам <a href="https://t.me/Incrypted_supportBot" target="_blank">Телеграм</a>'?></p>
        </div>
        <div class="footer_right">
            <h3 style="width: 100%;"><?= $footer["text"] ?? "Придбайте перспективні ноди вже сьогодні!"?></h3>
            <button class="btn select_node"><?= $footer["button_text"] ?? "Обрати ноду зараз" ?></button>
            <a href="<?= $footer["link_mobile"]["url"] ?? "#nodes" ?>" class="red_button_link"><?= $footer["link_mobile"]["title"] ?? "Приєднатися зараз"?></a>
        </div>

        <p class="copyright"><?= $footer["copyright"] ?? "© 2025 Incrypted. All rights reserved"?></p>
    </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
