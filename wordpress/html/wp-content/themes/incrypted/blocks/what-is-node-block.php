<?php
$what_is_node = get_field('acf_what_is_node');

if (empty($what_is_node) || (
    empty($what_is_node['header'])
    && empty($what_is_node['description'])
    && empty($what_is_node['small_text'])
    && empty($what_is_node['image'])
)) {
    return;
}

$image = $what_is_node['image'] ?? null;
$image_url = $image['url'] ?? '';
$image_alt = $image['alt'] ?? '';
$image_width = $image['width'] ?? '';
$image_height = $image['height'] ?? '';

$webpImage = '';
if ($image_url && $image_width && $image_height) {
    $webpImage = kama_thumb_src(array(
        'src' => $image_url,
        'width' => $image_width,
        'height' => $image_height,
        'force_format' => 'webp'
    ));
}
?>

<section id="what_is_node" class="what_is_node">
    <div class="container">
        <?php if ($image_url) : ?>
            <picture>
                <?php if ($webpImage) : ?>
                    <source srcset="<?= $webpImage ?>" type="image/webp">
                <?php endif; ?>
                <img src="<?= $image_url ?>" alt="<?= $image_alt ?>">
            </picture>
        <?php endif; ?>

        <div class="about_the_node">
            <?php if (!empty($what_is_node['small_text'])) : ?>
                <span><?= $what_is_node['small_text']; ?></span>
            <?php endif; ?>
            <?php if (!empty($what_is_node['header'])) : ?>
                <h2><?= $what_is_node['header']; ?></h2>
            <?php endif; ?>
            <?= $what_is_node['description'] ?? ''; ?>
        </div>
    </div>
</section>
