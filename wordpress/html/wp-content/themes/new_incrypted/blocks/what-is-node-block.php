<?php
$what_is_node = get_field('acf_what_is_node');

$webpImage = kama_thumb_src(array(
    'src'   => $what_is_node["image"]["url"],
    'width' => $what_is_node["image"]["width"],
    'height' => $what_is_node["image"]["height"],
    'force_format'  => 'webp'
));
?>

<section id="what_is_node" class="what_is_node">
    <div class="container">

        <picture>
            <source srcset="<?= $webpImage ?>" type="image/webp">
            <img src="<?= $what_is_node["image"]["url"] ?>" alt="<?= $what_is_node["image"]["alt"] ?>">
        </picture>

        <div class="about_the_node">
            <span><?= $what_is_node["small_text"]; ?></span>
            <h2><?= $what_is_node["header"]; ?></h2>
            <?= $what_is_node["description"]; ?>
        </div>

    </div>
</section>