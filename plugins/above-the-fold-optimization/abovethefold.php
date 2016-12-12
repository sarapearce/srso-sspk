<?php
/**
 * Above the fold optimization for WordPress
 *
 * Above the fold optimization toolkit that enables to achieve a Google PageSpeed 100 Score. Supports most optimization, minification and full page cache plugins.
 *
 * @link              https://pagespeed.pro/
 * @since             1.0
 * @package           abovethefold
 *
 * @wordpress-plugin
 * Plugin Name:       Above The Fold Optimization
 * Plugin URI:        https://pagespeed.pro/
 * Description:       Above the fold optimization toolkit that enables to achieve a <a href="https://developers.google.com/speed/docs/insights/about" target="_blank">Google PageSpeed</a> 100 Score. Supports most optimization, minification and full page cache plugins.
 * Version:           2.6.17
 * Author:            PageSpeed.pro
 * Author URI:        https://pagespeed.pro/
 * Text Domain:       abovethefold
 * Domain Path:       /languages
 */

define('WPABTF_VERSION','2.6.17');
define('WPABTF_URI', plugin_dir_url( __FILE__ ));
define('WPABTF_PATH', plugin_dir_path( __FILE__ ));
define('WPABTF_SELF', __FILE__);
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * The core plugin class that is used to define internationalization,
 * admin dashboard hooks and optimization hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/abovethefold.class.php';

/**
 * Begins execution of optimization.
 *
 * The plugin is based on hooks, starting the plugin from here does not impact load speed.
 *
 * @since    1.0
 */
function run_Abovethefold() {
	$GLOBALS['Abovethefold'] = new Abovethefold();
	$GLOBALS['Abovethefold']->run();
}
run_Abovethefold();
