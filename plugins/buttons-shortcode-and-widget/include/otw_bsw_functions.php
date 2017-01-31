<?php
/**
 * Init function
 */
if( !function_exists( 'otw_bsw_widgets_init' ) ){
	
	function otw_bsw_widgets_init(){
		
		global $otw_components;
		
		if( isset( $otw_components['registered'] ) && isset( $otw_components['registered']['otw_shortcode'] ) ){
			
			$shortcode_components = $otw_components['registered']['otw_shortcode'];
			arsort( $shortcode_components );
			
			foreach( $shortcode_components as $shortcode ){
				if( is_file( $shortcode['path'].'/widgets/otw_shortcode_widget.class.php' ) ){
					
					include_once( $shortcode['path'].'/widgets/otw_shortcode_widget.class.php' );
					break;
				}
			}
		}
		register_widget( 'OTW_Shortcode_Widget' );
	}
}
/**
 * Init function
 */
if( !function_exists( 'otw_bsw_init' ) ){
	
	function otw_bsw_init(){
		
		global $otw_bsw_plugin_url, $otw_bsw_plugin_options, $otw_bsw_shortcode_component, $otw_bsw_shortcode_object, $otw_bsw_form_component, $otw_bsw_validator_component, $otw_bsw_form_object, $otw_bsw_skin, $wp_bsw_cs_items;
		
		if( is_admin() ){
			
			add_action('admin_menu', 'otw_bsw_init_admin_menu' );
			
			add_action('admin_print_styles', 'otw_bsw_enqueue_admin_styles' );
			
			add_action('admin_enqueue_scripts', 'otw_bsw_enqueue_admin_scripts');
			
		}
		otw_bsw_enqueue_styles();
		
		include_once( plugin_dir_path( __FILE__ ).'otw_bsw_dialog_info.php' );
		
		//shortcode component
		$otw_bsw_shortcode_component = otw_load_component( 'otw_shortcode' );
		$otw_bsw_shortcode_object = otw_get_component( $otw_bsw_shortcode_component );
		$otw_bsw_shortcode_object->editor_button_active_for['page'] = true;
		$otw_bsw_shortcode_object->editor_button_active_for['post'] = true;
		
		$otw_bsw_shortcode_object->add_default_external_lib( 'css', 'style', get_stylesheet_directory_uri().'/style.css', 'live_preview', 10 );
		
		$otw_bsw_shortcode_object->shortcodes['button'] = array( 'title' => __('Button', 'otw_bsw'),'enabled' => true,'children' => false, 'parent' => false, 'order' => 0,'path' => dirname( __FILE__ ).'/otw_components/otw_shortcode/', 'url' => $otw_bsw_plugin_url.'/include/otw_components/otw_shortcode/', 'dialog_text' => $otw_bsw_dialog_text );
		
		include_once( plugin_dir_path( __FILE__ ).'otw_labels/otw_bsw_shortcode_object.labels.php' );
		$otw_bsw_shortcode_object->init();
		
		//form component
		$otw_bsw_form_component = otw_load_component( 'otw_form' );
		$otw_bsw_form_object = otw_get_component( $otw_bsw_form_component );
		include_once( plugin_dir_path( __FILE__ ).'otw_labels/otw_bsw_form_object.labels.php' );
		$otw_bsw_form_object->init();
		
		//validator component
		$otw_bsw_validator_component = otw_load_component( 'otw_validator' );
		$otw_bsw_validator_object = otw_get_component( $otw_bsw_validator_component );
		$otw_bsw_validator_object->init();
	}
}

/**
 * include needed styles
 */
if( !function_exists( 'otw_bsw_enqueue_styles' ) ){
	function otw_bsw_enqueue_styles(){
		global $otw_bsw_plugin_url, $otw_bsw_css_version;
	}
}


/**
 * Admin styles
 */
if( !function_exists( 'otw_bsw_enqueue_admin_styles' ) ){
	
	function otw_bsw_enqueue_admin_styles(){
		
		global $otw_bsw_plugin_url, $otw_bsw_css_version;
		
		$currentScreen = get_current_screen();
		
		switch( $currentScreen->id ){
			
			case 'widgets':
			case 'page':
			case 'post':
					wp_enqueue_style( 'otw_bsw_admin', $otw_bsw_plugin_url.'/css/otw_bsw_admin.css', array( 'thickbox' ), $otw_bsw_css_version );
				break;
		}
	}
}


/**
 * Admin scripts
 */
if( !function_exists( 'otw_bsw_enqueue_admin_scripts' ) ){
	
	function otw_bsw_enqueue_admin_scripts( $requested_page ){
		
		global $otw_bsw_plugin_url, $otw_bsw_js_version;
		
		switch( $requested_page ){
			
			case 'widgets.php':
					wp_enqueue_script("otw_shotcode_widget_admin", $otw_bsw_plugin_url.'/include/otw_components/otw_shortcode/js/otw_shortcode_widget_admin.js'  , array( 'jquery', 'thickbox' ), $otw_bsw_js_version );
					
					if(function_exists( 'wp_enqueue_media' )){
						wp_enqueue_media();
					}else{
						wp_enqueue_style('thickbox');
						wp_enqueue_script('media-upload');
						wp_enqueue_script('thickbox');
					}
				break;
		}
	}
	
}

/**
 * Init admin menu
 */
if( !function_exists( 'otw_bsw_init_admin_menu' ) ){
	
	function otw_bsw_init_admin_menu(){
		
		global $otw_bsw_plugin_url;
		
		add_menu_page(__('Buttons Shortcode And Widget', 'otw_bsw'), __('Buttons Shortcode And Widget', 'otw_bsw'), 'manage_options', 'otw-bsw-settings', 'otw_bsw_settings', $otw_bsw_plugin_url.'/images/otw-sbm-icon.png');
		add_submenu_page( 'otw-bsw-settings', __('Settings', 'otw_bsw'), __('Settings', 'otw_bsw'), 'manage_options', 'otw-bsw-settings', 'otw_bsw_settings' );
	}
}

/**
 * Settings page
 */
if( !function_exists( 'otw_bsw_settings' ) ){
	
	function otw_bsw_settings(){
		require_once( 'otw_bsw_settings.php' );
	}
}
?>