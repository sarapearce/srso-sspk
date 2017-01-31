<?php
/**
 * The template for displaying the footer.
 *
 * Contains the closing of the #content div and all content after
 *
 */
?>

</div><!-- content_border-->
</div><!-- content_wrapper-->


<footer class="site-footer-wrapper container-fluid" role="contentinfo">
    <div class="row">
        <div class="site-footer col-sm-12">
            <div class="col-sm-4">
                <?php dynamic_sidebar('smart-magazine-footer-1'); ?>
            </div><!-- col-main -->
            <div class="col-sm-4 center-block contact-info">
                <h3>Contact</h3>
                <h4>subscriber@shespark.com</h4>
                <a class="btn subscribe-btn" onclick="window.open('https://madmimi.com/signups/315876/join')" target='_blank'>Subscribe</a>
            </div><!-- col-main -->
<!--            <div class="col-sm-3">
                <?php // dynamic_sidebar('smart-magazine-footer-3'); ?>
            </div>-->
            <div class="col-sm-4">
                <a href="http://titosvodka.com" target="_blank"><img id="titos" class="center-block img-responsive" height="80" src="http://client-3.sarapearce.net/wp-content/uploads/2016/12/Titos-Logo-Horizontal_white.png" alt="titos vodka" /></a>
            </div><!-- col-main -->
        </div>
        <div class="clearfix"></div>
        <script type="text/javascript">
            jQuery(".footer-widget-title").remove();
        </script>
        <!-- site-footer -->
        <div class="clearfix"></div>
        <div class="col-sm-12 text-center copyright">
            <span class="col-sm-12"><a>SheSpark &copy; 2017</a> | <a href="./terms-privacy-policy/">Terms & Privacy Policy
                </a></span></div>
        <div class="clearfix"></div>
    </div>
</footer><!-- .site-footer-wrapper -->

<?php wp_footer(); ?>


</body>
</html>
