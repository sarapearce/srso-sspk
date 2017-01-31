<?php
/**
 * Theme Footer Section for our theme.
 *
 * Displays all of the footer section and starting from <footer> tag.
 *
 * @package ThemeGrill
 * @subpackage eStore
 * @since eStore 0.1
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
        window.open("https://madmimi.com/signups/315876/join");
    });
    jQuery(".footer-btn").css({'margin-left': '24%'});

//    jQuery(".toggle").on("click", function() {
//        
//                var menuVisible = false;
//                console.log('here');
//                jQuery('.toggle-wrap').click(function () {
//                    if (menuVisible) {
//                        jQuery('#primary-menu').css({'display': 'none'});
//                        menuVisible = false;
//                        return;
//                    }
//                   jQuery('.toggle-wrap').css({'display': 'block'});
//                    menuVisible = true;
//                });
//                jQuery('.toggle-wrap').click(function () {
//                    jQuery(this).css({'display': 'none'});
//                    menuVisible = false;
//                });
//             
//            });
//   jQuery(".toggle-wrap .toggle").click(function(){jQuery("#primary-menu").slideToggle("slow"); console.log('here')});

//jQuery('.toggle-wrap .toggle').click(function(event) {
//		jQuery('#primary-menu').slideToggle('slow');
//	});
</script>

</body>

</html>