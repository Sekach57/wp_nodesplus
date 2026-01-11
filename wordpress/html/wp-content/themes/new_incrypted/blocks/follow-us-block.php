<?php
$follow_us = get_field('acf_follow_us_block');
?>

<section class="follow_us">
    <div class="container">
        <h2><?= $follow_us["header"] ?></h2>
        <p><?= $follow_us["subheader"] ?></p>

        <div class="social_platforms">

            <?php foreach ($follow_us["platforms"] as $platform) { ?>
                <div class="platform_card">
                    <div class="icon <?php echo $platform["platform"]["verified"] ? "verified" : ''; ?>">
                        <img src="<?= $platform["platform"]["logo"]["url"] ?>"
                             alt="<?= $platform["platform"]["logo"]["alt"] ?>"/>
                    </div>
                    <a class="platform_title"
                       href="<?= $platform["platform"]["platform_link"]["url"] ?>"><?= $platform["platform"]["platform_link"]["title"] ?></a>
                    <div class="subscribers"><?= $platform["platform"]["subscribers"] ?></div>
                    <ul>
                        <?php
                        foreach ($platform["platform"]["list"] as $list_item)
                            echo "<li>" . $list_item['list_item'] . "</li>";
                        ?>
                    </ul>
                </div>
            <?php } ?>

        </div>
    </div>
</section>