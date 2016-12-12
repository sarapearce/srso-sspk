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
            <div class="col-sm-3">
                <?php dynamic_sidebar('smart-magazine-footer-1'); ?>
            </div><!-- col-main -->
            <div class="col-sm-3 contact-info">
                <h3>Contact</h3>
                <h5>subscriber@shespark.com</h5>
            </div><!-- col-main -->
            <div class="col-sm-3">
               <?php // dynamic_sidebar('smart-magazine-footer-2'); ?>
            </div><!-- col-main -->
            <div class="col-sm-3"> 

<!--<img id="titos" src="http://client-3.sarapearce.net/wp-content/uploads/2016/12/Titos-Logo-Horizontal.png" alt="titos" />-->
            </div><!-- col-main -->
        </div>
        <div class="clearfix"></div>
        <script type="text/javascript">
            jQuery(".footer-widget-title").remove();
        </script>

        <style>
            .contact-info {
                padding-left: 121px;
                padding-top: 30px;
            }
        </style>

        <!-- site-footer -->

        <div class="clearfix"></div>
        <div class="col-sm-12 copyright text-center">
            <span class="col-sm-12"><a>SheSpark &copy; 2017</a> | <a href="./terms-privacy-policy/">Terms & Privacy Policy
                </a></span></div>
        <div class="clearfix"></div>
    </div>
</footer><!-- .site-footer-wrapper -->

<?php wp_footer(); ?>




</body>
</html>
