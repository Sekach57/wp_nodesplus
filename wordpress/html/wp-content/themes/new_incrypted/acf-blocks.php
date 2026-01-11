<?php
add_action('acf/init', 'register_custom_blocks');
function register_custom_blocks() {
    if( function_exists('acf_register_block_type') ) {

        acf_register_block_type(array(
            'name'              => 'hero-section',
            'title'             => 'Hero Section',
            'description'       => 'Головна секція',
            'render_template'   => 'blocks/hero-block.php',
            'category'          => 'formatting',
            'icon'              => 'cover-image',
            'keywords'          => array('hero', 'banner', 'main'),
            'supports'          => array(
                'align' => array('wide', 'full'),
                'anchor' => true,
            ),
            //'enqueue_style'     => get_template_directory_uri() . '/blocks/hero-section/style.css',
        ));

        acf_register_block_type(array(
            'name'              => 'what-is-node',
            'title'             => 'What is node',
            'description'       => 'Що таке нода',
            'render_template'   => 'blocks/what-is-node-block.php',
            'category'          => 'formatting',
            'icon'              => 'cover-image',
            'keywords'          => array('node'),
            'supports'          => array(
                'align' => array('wide', 'full'),
                'anchor' => true,
            ),
            //'enqueue_style'     => get_template_directory_uri() . '/blocks/hero-section/style.css',
        ));

        acf_register_block_type(array(
            'name'              => 'services_block',
            'title'             => 'Services',
            'description'       => 'Наші послуги',
            'render_template'   => 'blocks/services-block.php',
            'category'          => 'formatting',
            'icon'              => 'cover-image',
            'keywords'          => array('service'),
            'supports'          => array(
                'align' => array('wide', 'full'),
                'anchor' => true,
            ),
            //'enqueue_style'     => get_template_directory_uri() . '/blocks/hero-section/style.css',
        ));

        acf_register_block_type(array(
            'name'              => 'cases_block',
            'title'             => 'Cases',
            'description'       => 'Кейси Node+',
            'render_template'   => 'blocks/cases-block.php',
            'category'          => 'formatting',
            'icon'              => 'cover-image',
            'keywords'          => array('case'),
            'supports'          => array(
                'align' => array('wide', 'full'),
                'anchor' => true,
            ),
            //'enqueue_style'     => get_template_directory_uri() . '/blocks/hero-section/style.css',
        ));

        acf_register_block_type(array(
            'name'              => 'faq_block',
            'title'             => 'FAQ',
            'description'       => 'FAQ',
            'render_template'   => 'blocks/faq-block.php',
            'category'          => 'formatting',
            'icon'              => 'cover-image',
            'keywords'          => array('faq'),
            'supports'          => array(
                'align' => array('wide', 'full'),
                'anchor' => true,
            ),
            //'enqueue_style'     => get_template_directory_uri() . '/blocks/hero-section/style.css',
        ));

        acf_register_block_type(array(
            'name'              => 'follow_us_block',
            'title'             => 'Follow us',
            'description'       => 'Слідкуйте за нами',
            'render_template'   => 'blocks/follow-us-block.php',
            'category'          => 'formatting',
            'icon'              => 'cover-image',
            'keywords'          => array('follow'),
            'supports'          => array(
                'align' => array('wide', 'full'),
                'anchor' => true,
            ),
            //'enqueue_style'     => get_template_directory_uri() . '/blocks/hero-section/style.css',
        ));

        acf_register_block_type(array(
            'name'              => 'nodes_block',
            'title'             => 'Nodes',
            'description'       => 'Доступні ноди',
            'render_template'   => 'blocks/nodes-block.php',
            'category'          => 'formatting',
            'icon'              => 'cover-image',
            'keywords'          => array('nodes'),
            'supports'          => array(
                'align' => array('wide', 'full'),
                'anchor' => true,
            ),
            //'enqueue_style'     => get_template_directory_uri() . '/blocks/hero-section/style.css',
        ));

    }
}