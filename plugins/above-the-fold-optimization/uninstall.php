<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * @link       https://pagespeed.pro/
 * @since      2.5.0
 *
 * @package    abovethefold
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove settings
 */
delete_option( 'abovethefold' );

/**
 * Remove above the fold cache directory
 */
$dir = wp_upload_dir();
$path = trailingslashit($dir['basedir']) . 'abovethefold/';
if (is_dir($path)) {

	// Recursive delete
	function __rmdir_recursive($dir) {
		$files = array_diff(scandir($dir), array('.','..')); 
		foreach ($files as $file) { 
			(is_dir("$dir/$file")) ? __rmdir_recursive("$dir/$file") : @unlink("$dir/$file"); 
		} 
		return @rmdir($dir); 
	}
	__rmdir_recursive($path);
}