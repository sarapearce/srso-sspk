<?php
/**
Template Name: archives page
 Written by Sara Pearce at Left Turn Only http://leftturnonly.tv
 */
get_header();
?>
<div class="container-fluid col-sm-12">
    <div class="row">
<div id="primary" class="col-sm-12">
    <main id="main" class="site-main" role="main">

        <?php while (have_posts()) : the_post(); ?>

            <?php get_template_part('template-parts/content', 'page'); ?>

            <?php
            // If comments are open or we have at least one comment, load up the comment template.
            if (comments_open() || get_comments_number()) :
                comments_template();
            endif;
            ?>

        <?php endwhile; // End of the loop. ?>

    </main><!-- #main -->
</div><!-- #primary -->
    </div>
</div>
<?php get_footer(); ?>

<style type="text/css">
div.inline { float:left; }
.clearBoth { clear:both; }
.entry-title { text-align:center; }
.entry-content { margin-left: 22%; }
</style>
