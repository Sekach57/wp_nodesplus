<?php
$hero = get_field('acf_hero_block');

$desktop_webpImage = kama_thumb_src(array(
    'src'   => $hero["desktop_image"]["url"],
    'width' => $hero["desktop_image"]["width"],
    'height' => $hero["desktop_image"]["height"],
    'force_format'  => 'webp'
));
$mobile_webpImage = kama_thumb_src(array(
    'src'   => $hero["mobile_image"]["url"],
    'width' => $hero["mobile_image"]["width"],
    'height' => $hero["mobile_image"]["height"],
    'force_format'  => 'webp'
));
?>

<section class="hero">
    <div class="container">
        <h1><?= $hero["title_h1"] ?></h1>
        <?= $hero["description"] ?>

        <div class="btn select_node"><?= $hero["button_text"] ?></div>

        <div class="img_section">
            <picture>
                <source media="(min-width: 768px)" srcset="<?= $desktop_webpImage ?>" type="image/webp">
                <source media="(min-width: 768px)" srcset="<?= $hero["desktop_image"]["url"] ?>">

                <source srcset="<?= $mobile_webpImage ?>" type="image/webp">
                <img src="<?= $hero["mobile_image"]["url"] ?>" alt="<?= $hero["mobile_image"]["alt"] ?>">
            </picture>
        </div>
    </div>
    <ul class="features">
        <li>
            <svg xmlns="http://www.w3.org/2000/svg" width="31" height="30" viewBox="0 0 31 30" fill="none">
                <circle cx="15.4998" cy="15" r="15" fill="white"/>
                <path fill-rule="evenodd" clip-rule="evenodd"
                      d="M22.5278 9.9077C23.1311 10.4755 23.1598 11.4248 22.5921 12.0281L13.5324 21.654L8.4391 16.5607C7.85331 15.9749 7.85331 15.0251 8.4391 14.4393C9.02488 13.8536 9.97463 13.8536 10.5604 14.4393L13.4671 17.3461L20.4075 9.97196C20.9752 9.3687 21.9245 9.33993 22.5278 9.9077Z"
                      fill="black"/>
            </svg>
            <?= $hero["features"]["feature_1"] ?>
        </li>
        <li>
            <svg xmlns="http://www.w3.org/2000/svg" width="31" height="30" viewBox="0 0 31 30" fill="none">
                <circle cx="15.4998" cy="15" r="15" fill="white"/>
                <path fill-rule="evenodd" clip-rule="evenodd"
                      d="M22.5278 9.9077C23.1311 10.4755 23.1598 11.4248 22.5921 12.0281L13.5324 21.654L8.4391 16.5607C7.85331 15.9749 7.85331 15.0251 8.4391 14.4393C9.02488 13.8536 9.97463 13.8536 10.5604 14.4393L13.4671 17.3461L20.4075 9.97196C20.9752 9.3687 21.9245 9.33993 22.5278 9.9077Z"
                      fill="black"/>
            </svg>
            <?= $hero["features"]["feature_2"] ?>
        </li>
        <li>
            <svg xmlns="http://www.w3.org/2000/svg" width="31" height="30" viewBox="0 0 31 30" fill="none">
                <circle cx="15.4998" cy="15" r="15" fill="white"/>
                <path fill-rule="evenodd" clip-rule="evenodd"
                      d="M22.5278 9.9077C23.1311 10.4755 23.1598 11.4248 22.5921 12.0281L13.5324 21.654L8.4391 16.5607C7.85331 15.9749 7.85331 15.0251 8.4391 14.4393C9.02488 13.8536 9.97463 13.8536 10.5604 14.4393L13.4671 17.3461L20.4075 9.97196C20.9752 9.3687 21.9245 9.33993 22.5278 9.9077Z"
                      fill="black"/>
            </svg>
            <?= $hero["features"]["feature_3"] ?>
        </li>
    </ul>

</section>