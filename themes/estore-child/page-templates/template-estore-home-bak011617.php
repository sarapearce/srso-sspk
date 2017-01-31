<?php
/**
 * Template Name: eStore Home
 *
 * @package ThemeGrill
 * @subpackage eStore
 * @since 1.0
 */
?>
<?php get_header(); ?>

<div id="content" class="clearfix">

    <section id="top_slider_section" class="clearfix">
        <div class="tg-container">
            <div>
                <?php // while (have_posts()) : the_post(); ?>
                <?php // get_template_part('template-parts/content', 'page'); ?>

                <?php // endwhile; // End of the loop. 
                ?>

                <div class="big-slider">
                    <!--there is a metabox that in the UI that updates the onclick and image source via js-->
                    <a id="mag-cover" alt="SheSpark Magazine" onclick= "window.open('<?php echo rwmb_meta('mag-link') ?>', '_blank', 'toolbar=0,location=0,menubar=0')">
                        <img class="mag-cover-img img-responsive" src='<?php echo rwmb_meta('mag-cover') ?>' alt="SheSpark Magazine" /> 
                    </a>   
                    <div class="sidebar_slider">
                        <?php
                        if (is_active_sidebar('estore_sidebar_slider')) {
                            if (!dynamic_sidebar('estore_sidebar_slider')):
                            endif;
                        }
                        ?>
                    </div>
                    <br>
                    <br>


                    <!--close big slider-->       
                </div> 



                <div class="small-slider-wrapper">
                    <?php
                    if (is_active_sidebar('estore_sidebar_slider_beside')) {
                        if (!dynamic_sidebar('estore_sidebar_slider_beside')):
                        endif;
                    }
                    ?>
                </div>
                </section>

                <div style="width: 100%">
                    <div><h3 class="post-grid-title widget-title">FEATURED</h3></div>
                    <br>
                    <br>
                    <div class="clearfix"></div>
                    <?php echo do_shortcode('[the-post-grid id="2438" title="Home Page Grid"]'); ?>
                </div>

                <?php
                if (is_active_sidebar('estore_sidebar_front')) {
                    if (!dynamic_sidebar('estore_sidebar_front')):
                    endif;
                }
                ?>

            </div>

            <?php get_footer(); ?>
