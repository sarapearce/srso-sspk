<?php
/**
 * Theme Footer Section for our theme.
 *
 * Displays all of the footer section and starting from <footer> tag.
 *
 */
?>

<footer id="colophon">
    <?php get_sidebar('footer'); ?>
</footer>
<a href="#" class="scrollup"><i class="fa fa-angle-up"> </i> </a>
</div>
<div class="clearfix"></div>
<div class="bottom-footer">
    <span class="col-sm-12"><a>SheSpark &copy; 2017</a> | <a href="./terms-privacy-policy/">Terms & Privacy Policy
        </a></span></div>
<!-- Page end -->

<script type="text/javascript">
    jQuery(".page-header").remove();
    jQuery(".posted-on").remove();
    jQuery(".category-toggle").on("click", function () {
        var win = window.open("https://madmimi.com/signups/315876/join", "_blank");
        win.focus();
    });
    jQuery(".footer-btn").css({'margin-left': '24%'});
    jQuery(".category-toggle i").remove();
    jQuery(".otw-button").css({ 'background-image': 'none' });
    jQuery("#ess-wrap-inline-networks").before("<h5 id='share-now'>Share It Now On</h5>");
    jQuery("#reply-title").html("Leave a Comment");
    
//grab iframe element and reset the style width and height
    
//    jQuery("iframe").css({ 'height': '540px', 'width': '500px' });
    jQuery(".social-icon").attr("target", "_blank");
    jQuery(".widget-title + ul .rsswidget").attr("target", "_blank");
</script>

</body>

</html>