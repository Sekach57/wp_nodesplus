<?php
$services = get_field('acf_services_block');
?>
<section id="services" class="services">
    <div class="container">
        <h2><?= $services["header"] ?></h2>

        <div class="services_list">

            <div class="service_item">
                <?= $services["services"]["service_1"]["svg"] ?>

                <h3><?= $services["services"]["service_1"]["header"] ?></h3>
                <p><?= $services["services"]["service_1"]["description"] ?></p>
            </div>

            <div class="service_item">
                <?= $services["services"]["service_2"]["svg"] ?>

                <h3><?= $services["services"]["service_2"]["header"] ?></h3>
                <p><?= $services["services"]["service_2"]["description"] ?></p>
            </div>

            <div class="service_item">
                <?= $services["services"]["service_3"]["svg"] ?>

                <h3><?= $services["services"]["service_3"]["header"] ?></h3>
                <p><?= $services["services"]["service_3"]["description"] ?></p>
            </div>

        </div>
    </div>
</section>