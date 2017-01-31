<?php

add_action('wp_enqueue_scripts', 'theme_enqueue_styles');

function theme_enqueue_styles() {
    //need to hard code the template directory path the style.css, make style.css be pointed to by sass
    wp_enqueue_style('parent-style', get_template_directory_uri() . '/style.css');
}

function rss_post_thumbnail($content) {

    global $post;

    if (has_post_thumbnail($post->ID)) {
        $content = '<p>' . get_the_post_thumbnail($post->ID) .
                '</p>' . get_the_content();
    }
    return $content;
}

add_filter('the_excerpt_rss', 'rss_post_thumbnail');
add_filter('the_content_feed', 'rss_post_thumbnail');

add_filter('rwmb_meta_boxes', 'sspk_meta_boxes');

function sspk_meta_boxes($meta_boxes) {

    $meta_boxes[] = array(
        'title' => __('Magazine Updates (Only Update on the Home Page!)', 'textdomain'),
        'post_types' => 'page',
        'fields' => array(
            array(
                'id' => 'mag-link',
                'name' => __('Magazine Embed Link (Currently MagTitan)', 'textdomain'),
                'type' => 'text',
            ),
            array(
                'id' => 'mag-cover',
                'name' => __('Magazine Cover Image Path (PNG)', 'textdomain'),
                'type' => '',
            ),
//            array(
//                'name' => esc_html__('File Upload', 'sspk'),
//                'id' => "sspkfile",
//                'type' => 'file',
//            ),
        ),
    );
    return $meta_boxes;
}
