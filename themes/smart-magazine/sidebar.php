<?php
/**
 * The sidebar containing the main widget area.
 *
 * @package Smart Magazine
 */
if (!is_active_sidebar('smart-magazine-sidebar-1')) {
    return;
}
?>

<div id="secondary" class="widget-area col-sm-4 col-md-4 sidebar container-fluid" role="complementary">
    <div class="row">
        <?php dynamic_sidebar('smart-magazine-sidebar-1'); ?>
    </div>
</div><!-- #secondary -->
<div class="clearfix"></div>