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
?>
