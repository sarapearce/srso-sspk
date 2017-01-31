<?php
//
/**
 * The header for our theme.
 *
 * Displays all of the <head> section and everything up till <div id="content">
 *
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo('charset'); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="profile" href="http://gmpg.org/xfn/11">
        <link rel="pingback" href="<?php bloginfo('pingback_url'); ?>">

        <?php wp_head(); ?>
    </head>

    <body <?php body_class(); ?>>
        <!--do NOT tweak this section, it took forever to center and everything is hinged on its location-->
        <header class="main-header">
            <!-- container -->
            <div class="clearfix"></div>
            <div class="container-fluid right col-md-12 col-sm-12">
                <div class="row">
                    <div class="span8 center-block">
                        <?php
                        $logo = esc_url(get_theme_mod("logo-upload"));
                        ?>
                        <a href="<?php echo esc_url(home_url('/')); ?>">
                            <?php echo (strlen($logo) > 0) ? '<img align="middle" src="' . $logo . '" alt="logo" class="custom-logo center-block" />' : get_bloginfo('name'); ?>
                        </a>
                    </div>

                </div>
            </div><!-- container -->
        </header>
        <div class="clearfix"></div>
        <div class="nav_wrapper">
            <div class="container-fluid">
                <div class="row">
                    <nav class="main_nav">
                    </nav>
                </div>
         
                <!--top_bar-->
                <div class="top_bar container-fluid">
                    <div class="row">
<!--                        <div class="top_nav col-sm-4">
                            <a href="https://madmimi.com/signups/315876/join" title="Subscribe" data-toggle="popover" data-placement="right" 
                               data-content="" class="btn subscribe-btn">Subscribe</a>
                        </div>-->
                        <div class="top_nav col-sm-4 right top_bar_nav">

                            <?php
                            $arg = array(
                                'theme_location' => 'top',
                                'container_class' => 'menu-top',
                                'container_id' => '',
                                'menu_class' => 'sf-menu',
                                'menu_id' => 'top-menu',
                            );

                            wp_nav_menu($arg);
                            ?>
                        </div>


                        <div class="social col-sm-4">
                            <ul>
                                <?php
                                $facebook_link = esc_url(get_theme_mod("facebook_link"));
                                $twitter_link = esc_url(get_theme_mod("twitter_link"));
                                $g_link = esc_url(get_theme_mod("googleplus_link"));
                                $youtube_link = esc_url(get_theme_mod("youtube_link"));
                                $linkedin_link = esc_url(get_theme_mod("linkedin_link"));
                                $pinterest_link = esc_url("pinterest.com/shesparkover40");
                                $instagram_link = esc_url("instagram.com/shesparkmag");


                                if (strlen($facebook_link) > 0) {
                                    echo '<li class="facebook"><a href="' . $facebook_link . '"><i class="fa fa-facebook-square"></i></a></li>';
                                }
                                if (strlen($twitter_link) > 0) {
                                    echo '<li class="twitter"><a href="' . $twitter_link . '"><i class="fa fa-twitter-square"></i></a></li>';
                                }
                                if (strlen($g_link) > 0) {
                                    echo '<li class="gplus"><a href="' . $g_link . '"><i class="fa fa-google-plus-square"></i></a></li>';
                                }
                                if (strlen($youtube_link) > 0) {
                                    echo '<li class="youtube"><a href="' . $youtube_link . '"><i class="fa fa-youtube-square"></i></a></li>';
                                }
                                if (strlen($linkedin_link) > 0) {
                                    echo '<li class="linkedin"><a href="' . $linkedin_link . '"><i class="fa fa-linkedin-square"></i></a></li>';
                                }
                                if (strlen($pinterest_link) > 0) {
                                    echo '<li class="pinterest"><a href="' . $pinterest_link . '"><i class="fa fa-pinterest-square"></i></a></li>';
                                }
                                if (strlen($instagram_link) > 0) {
                                    echo '<li class="instagram"><a href="' . $instagram_link . '"><i class="fa fa-instagram"></i></a></li>';
                                }
                                ?>
                            </ul>
                        </div>
                        <div class="clearfix"></div>
                    </div>
                </div>
                
                <!-- top_bar -->
          
            <div class="container-fluid content_wrapper" id="content_wrapper">


                <script type="text/javascript">
                    jQuery(".gum_breadcrumb").remove();
                </script>
