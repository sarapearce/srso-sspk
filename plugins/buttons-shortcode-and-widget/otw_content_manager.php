<?php
/**
Plugin Name: Buttons Shortcode And Widgets
Plugin URI: http://OTWthemes.com
Description:  Create buttons. Nice and easy interface. Insert anywhere in your site - page/post editor, sidebars, template files. 
Author: OTWthemes.com
Version: 1.9

Author URI: http://themeforest.net/user/OTWthemes
*/

load_plugin_textdomain('otw_bsw',false,dirname(plugin_basename(__FILE__)) . '/languages/');

$wp_bsw_tmc_items = array(
	'page'              => array( array(), __( 'Pages', 'otw_bsw' ) ),
	'post'              => array( array(), __( 'Posts', 'otw_bsw' ) )
);

$wp_bsw_agm_items = array(
	'page'              => array( array(), __( 'Pages', 'otw_bsw' ) ),
	'post'              => array( array(), __( 'Posts', 'otw_bsw' ) )
);

$wp_bsw_cs_items = array(
	'page'              => array( array(), __( 'Pages', 'otw_bsw' ) ),
	'post'              => array( array(), __( 'Posts', 'otw_bsw' ) )
);

$otw_bsw_plugin_url = plugin_dir_url( __FILE__);
$otw_bsw_css_version = '1.0';

//include functons
require_once( plugin_dir_path( __FILE__ ).'/include/otw_bsw_functions.php' );

//otw components
$otw_bsw_shortcode_component = false;
$otw_bsw_form_component = false;
$otw_bsw_validator_component = false;

//load core component functions
@include_once( 'include/otw_components/otw_functions/otw_functions.php' );

if( !function_exists( 'otw_register_component' ) ){
	wp_die( 'Please include otw components' );
}

//register form component
otw_register_component( 'otw_form', dirname( __FILE__ ).'/include/otw_components/otw_form/', $otw_bsw_plugin_url.'/include/otw_components/otw_form/' );

//register validator component
otw_register_component( 'otw_validator', dirname( __FILE__ ).'/include/otw_components/otw_validator/', $otw_bsw_plugin_url.'/include/otw_components/otw_validator/' );

//register shortcode component
otw_register_component( 'otw_shortcode', dirname( __FILE__ ).'/include/otw_components/otw_shortcode/', $otw_bsw_plugin_url.'/include/otw_components/otw_shortcode/' );

/** 
 *call init plugin function
 */
add_action('init', 'otw_bsw_init' );
add_action('widgets_init', 'otw_bsw_widgets_init' );

?>