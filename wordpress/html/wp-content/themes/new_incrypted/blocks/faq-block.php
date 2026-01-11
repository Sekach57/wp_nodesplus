<?php
$faq = get_field('acf_faq_block');
$counter = 0;
?>

<section id="faq" class="faq">
    <div class="container">
        <h2><?= $faq["header"] ?></h2>
        <p><?= $faq["subheader"] ?></p>

        <div class="faq_items">

            <?php foreach ($faq["questions"] as $faq){ ?>
                <div class="faq_item <?php
                echo $counter == 0 ? 'active' : '';
                $counter++;
                ?>">
                    <div class="faq_question"><?= $faq["question"]["question"] ?></div>
                    <div class="faq_answer"><?= $faq["question"]["answer"] ?></div>
                </div>
            <?php } ?>

        </div>
    </div>
</section>