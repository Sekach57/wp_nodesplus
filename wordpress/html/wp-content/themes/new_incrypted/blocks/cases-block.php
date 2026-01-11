<?php
$cases = get_field('acf_cases_block');
?>

<section class="cases">
    <div class="container">
        <h2><?= $cases["header"] ?></h2>

        <div class="cases_items">

            <?php foreach ($cases["cases"] as $case){ ?>
                <div class="case">
                    <div class="case_title">
                        <img src="<?= $case["case"]["logo"]["url"] ?>" alt="<?= $case["case"]["logo"]["alt"] ?>"/>
                        <?= $case["case"]["title"] ?>
                    </div>
                    <div class="case_description">
                        <?= $case["case"]["description"] ?>
                    </div>
                    <div class="case_result">
                        <span><?= $case["case"]["result"] ?></span>
                        <span class="btn"><?= $case["case"]["result_value"] ?></span>
                    </div>
                </div>
            <?php } ?>

        </div>
    </div>
</section>
