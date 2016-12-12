<?php
/**
 * Template Name: Homepage
 */
get_header();
?>
<div class="col-md-8 col-sm-8">
    <?php while (have_posts()) : the_post(); ?>

        <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
            <div class="entry-content">
                <?php the_content(); ?>

            </div><!-- .entry-content -->

        </article><!-- #post-## -->

    <?php endwhile; // End of the loop. ?>
</div><!-- col-main -->
<div class="col-sm-4 col-md-4 sidebar">
    <?php dynamic_sidebar('smart-magazine-homepage-sidebar'); ?>
</div><!-- sidebar -->
<div class="clearfix"></div>
<div id="primary" class="content-area col-sm-12">
    <main id="main" class="site-main" role="main">
    </main><!-- #main -->
</div><!-- #primary -->
<div class="clearfix"></div>
<div id="sidebar_wrapper" class=" col-sm-12">
    <h3 class="text-center underline">MORE FROM SHESPARK</h3>
    <?php
    if (!is_active_sidebar('smart-magazine-sidebar-1')) {
        return;
    }
    ?>

    <div id="secondary" class="widget-area col-sm-12 col-md-12 sidebar container-fluid" role="complementary">
        <div class="row">
            <?php dynamic_sidebar('smart-magazine-sidebar-1'); ?>
        </div>
    </div><!-- #secondary -->
    <div class="clearfix"></div>
</div>

<script type="text/javascript">
    jQuery(".widget-title").remove();
    jQuery('.gum_sidebar_posts').children().css({float: 'left', height: '200px'})

</script>    
<?php get_footer(); ?>

