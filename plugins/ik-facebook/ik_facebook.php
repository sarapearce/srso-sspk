<?php
/*
Plugin Name: WP Social Plugin
Plugin URI: https://goldplugins.com/our-plugins/wp-social-pro/
Description: WP Social Plugin - A Social Feed Solution for WordPress
Author: Gold Plugins
Version: 3.0.3
Author URI: https://goldplugins.com
Text Domain: ik-facebook

This file is part of the WP Social Plugin.

The WP Social Plugin is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

The WP Social Plugin is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with the WP Social Plugin .  If not, see <http://www.gnu.org/licenses/>.
*/
require_once( plugin_dir_path( __FILE__ ) . 'include/gold-framework/plugin-base.php' );
require_once( plugin_dir_path( __FILE__ ) . 'include/widgets/ik_facebook_feed_widget.php' );
require_once( plugin_dir_path( __FILE__ ) . 'include/widgets/ik_facebook_like_button_widget.php' );
require_once( plugin_dir_path( __FILE__ ) . 'include/lib/ik_facebook_options.php' );
require_once( plugin_dir_path( __FILE__ ) . 'include/lib/lib.php' );
require_once( plugin_dir_path( __FILE__ ) . 'include/lib/BikeShed/bikeshed.php' );
require_once( plugin_dir_path( __FILE__ ) . 'include/lib/CachedCurl.php' );
require_once( plugin_dir_path( __FILE__ ) . 'include/lib/GP_CacheBox.php' );
require_once( plugin_dir_path( __FILE__ ) . 'include/lib/GP_Facebook.php' );
require_once( plugin_dir_path( __FILE__ ) . 'include/lib/GP_Media_Button/gold-plugins-media-button.class.php' );
require_once( plugin_dir_path( __FILE__ ) . 'include/lib/GP_Janus/gp-janus.class.php' );
require_once( plugin_dir_path( __FILE__ ) . 'include/lib/GP_Sajak/gp_sajak.class.php' );
require_once( plugin_dir_path( __FILE__ ) . 'include/lib/GP_MegaSeptember/mega.september.class.php' );

//use this to track if custom css has been output
global $ikfb_footer_css_output;

class ikFacebook extends GoldPlugin
{
	var $options;
	var $cache;
	var $gp_facebook;
	var $gp_twitter;
	var $is_pro;
	var $media_buttons;
	var $plugins_url;
	var $plugins_path;
	
	function __construct(){		
		$this->gp_facebook = new GP_Facebook($this);
		$this->options = new ikFacebookOptions($this);
		$this->cache = new GP_CacheBox();
		//$this->gp_twitter = new GP_Twitter($this);
		
		//set base URL
		$this->plugins_path = plugin_dir_path( __FILE__ );
		$this->plugins_url = plugin_dir_url( __FILE__ );
		
		//add editor widgets
		$this->editor_widgets();
		
		//run pro check
		$this->isPro();
		
		//create custom post type to store feed items
		$this->create_post_types();
		
		//add custom columns for post types
		add_filter( 'manage_edit-facebookpost_columns', array($this, 'gp_edit_facebookpost_columns') ) ;
		add_action( 'manage_facebookpost_posts_custom_column', array($this, 'gp_manage_facebookpost_columns'), 10, 2 );
		add_filter( 'manage_edit-facebookpost_sortable_columns', array($this, 'gp_facebookpost_sortable_columns') );
		
		//custom comments yang
		add_filter( 'admin_comment_types_dropdown', array($this, 'gp_custom_comment_filter') ) ;
		/* Only run our customization on the 'index.php' page in the admin. */
		add_action( 'load-index.php', array($this, 'gp_exclude_custom_comments_from_dashboard') );
		
		
		/* Only run our customization on the 'edit.php' page in the admin. */
		add_action( 'load-edit.php', array($this, 'gp_edit_facebookpost_load') );
		
		//hide add new buttons for custom post types
		$this->hide_new_buttons();
		
		//create shortcodes
		$this->add_shortcodes();

		//add CSS
		$this->add_css();

		//register sidebar widgets
		$this->add_widgets();	
		
		//run admin actions
		$this->setup_admin();
		
		//run steps for internationalization
		$this->internationalize();
		
		//run activation processes
		$this->run_activation_steps();
			
		//create media button object for use with editor widgets
		$this->media_buttons = new Gold_Plugins_Media_Button('WP Social', 'share-alt');		
		
		//ability to filter posts by custom field
		add_action( 'restrict_manage_posts', array($this,'ikfb_admin_posts_filter_restrict_manage_posts') );
		add_filter( 'parse_query', array($this,'ikfb_posts_filter') );
	}
	
	//add editor widgets
	function editor_widgets(){
		// load Janus
		new GP_Janus();

		//add editor widgets
		add_action( 'admin_init', array($this, 'add_media_buttons') );
	}
	
	//add media buttons
	function add_media_buttons(){		
		// add media buttons to admin
		$cur_post_type = ( isset($_GET['post']) ? get_post_type(intval($_GET['post'])) : '' );
		if( is_admin() && ( empty($_REQUEST['post_type']) || $_REQUEST['post_type'] !== 'facebookpost' ) && ($cur_post_type !== 'facebookpost') )
		{					
			$this->media_buttons->add_button('Facebook Feed', 'ik_fb_feed', 'ikfacebookwidget', 'facebook');
			$this->media_buttons->add_button('Like Button', 'ik_fb_like_button', 'ikfacebooklikebuttonwidget', 'facebook');
		}
	}
	
	//add custom links
	//add admin css and js
	function setup_admin(){
		//run admin init actions
		add_action( 'admin_enqueue_scripts', array($this, 'ikfb_admin_init') );
		
		//add our custom links for Settings and Support to various places on the Plugins page
		$plugin = plugin_basename(__FILE__);
		add_filter( "plugin_action_links_{$plugin}", array($this, 'add_settings_link_to_plugin_action_links') );
		add_filter( 'plugin_row_meta', array($this, 'add_custom_links_to_plugin_description'), 10, 2 );	
	}
	
	//set timezone, language
	//other steps for internationalization
	function internationalize(){
		//load language
		add_action('plugins_loaded', array($this, 'ikfb_load_textdomain'));
		//set locale
		add_action('plugins_loaded', array($this, 'ikfb_set_locale'));
	}
	
	//collection of functions to be run once, upon activation
	function run_activation_steps(){
		//flush rewrite rules - only do this once!
		//TBD: Fix this somehow, probably the CPT changes req'd from some of the other plugins
		register_activation_hook( __FILE__, array($this, 'rewrite_flush') );
	}
	
	function add_widgets(){
		add_action( 'widgets_init', array($this, 'ik_fb_register_widgets' ));
	}
	
	//check if pro and set class var
	function isPro(){
		$this->is_pro = is_valid_key(get_option('ik_fb_pro_key'));
	}
	
	//only do this once
	function rewrite_flush() {
		$this->create_post_types();
		
		flush_rewrite_rules();
	}
	
	function add_css(){
		add_action( 'wp_enqueue_scripts', array($this, 'ik_fb_setup_css'));
		add_action( 'wp_head', array($this, 'ik_fb_setup_custom_css'));
		add_action( 'wp_enqueue_scripts', array($this, 'ik_fb_setup_custom_theme_css'));
		add_action( 'wp_enqueue_scripts', array($this, 'enqueue_webfonts'));
	}
	
	//sets the locale for things such as the date formatting when displaying event dates
	function ikfb_set_locale(){
		//use get_locale() instead of WPLANG, as WPLANG is now deprecated
		//https://codex.wordpress.org/Function_Reference/get_locale
		return setlocale(LC_TIME, get_locale());
	}
	
	//load proper language pack based on current language
	function ikfb_load_textdomain() {
		$plugin_dir = basename(dirname(__FILE__));
		load_plugin_textdomain( 'ik-facebook', false, $plugin_dir . '/include/languages' );
	}
	
	//hides add new buttons for twitter and facebook CPTs
	//this is undesired functionality inside the wp admin
	//since the plugin has no ability to post out, currently
	function hide_new_buttons(){
		add_action('admin_menu', array($this,'hide_add_new_custom_type'));
		add_action('wp_before_admin_bar_render', array($this,'remove_admin_bar_links'));
	}
	
	//adds various shortcodes belonging to the plugin
	//TBD: allow customization of available shortcodes
	//		similar to easy t
	function add_shortcodes(){
		//facebook
		add_shortcode('ik_fb_feed', array($this->gp_facebook, 'ik_fb_output_feed_shortcode'));
		add_shortcode('ik_fb_gallery', array($this->gp_facebook, 'ik_fb_output_gallery_shortcode'));
		add_shortcode('ik_fb_like_button', array($this->gp_facebook, 'ik_fb_output_like_button_shortcode'));
		
		//twitter
		//add_shortcode('wp_twitter_feed', array($this->gp_twitter, 'wp_twitter_output_feed_shortcode'));
	}
	
	/**
	 * First create the dropdown
	 * make sure to change POST_TYPE to the name of your custom post type
	 * 
	 * @author Ohad Raz
	 * 
	 * @return void
	 */
	function ikfb_admin_posts_filter_restrict_manage_posts(){
		$type = 'post';
		if (isset($_GET['post_type'])) {
			$type = $_GET['post_type'];
		}

		//only add filter to post type you want
		if ('facebookpost' == $type){
			$feed_ids = get_option('ik_fb_page_id');
										
			//if not currently an array, make it one
			//should only happen the first time they use this screen
			if(!is_array($feed_ids)){
				$feed_ids = array(
					$feed_ids
				);							
			}
			
			//the list of values you want to show
			//in 'label' => 'value' format
			$values = array( 'All Feeds' => '');
			foreach($feed_ids as $feed_id){
				$values[$feed_id] = $feed_id;
			}
			
			?>
			<select name="gp_facebook_page_id_filter">
			<?php
				$current_v = isset($_GET['gp_facebook_page_id_filter'])? $_GET['gp_facebook_page_id_filter']:'';
				foreach ($values as $label => $value) {
					printf
						(
							'<option value="%s"%s>%s</option>',
							$value,
							$value == $current_v? ' selected="selected"':'',
							$label
						);
					}
			?>
			</select>
			<?php
		}
	}
	
	/**
	 * if submitted filter by post meta
	 * 
	 * @param  (wp_query object) $query
	 * 
	 * @return Void
	 */
	function ikfb_posts_filter( $query ){
		global $pagenow;
		$type = 'post';
		if (isset($_GET['post_type'])) {
			$type = $_GET['post_type'];
		}
		if ( 'facebookpost' == $type && is_admin() && $pagenow=='edit.php' && isset($_GET['gp_facebook_page_id_filter']) && $_GET['gp_facebook_page_id_filter'] != '') {
			$query->query_vars['meta_key'] = '_ikcf_feed_id';
			$query->query_vars['meta_value'] = $_GET['gp_facebook_page_id_filter'];
		}
	}
	
	//creates cpts for use in plugin
	function create_post_types(){
		//facebook
		$postType = array('name' => 'Facebook Post', 'plural' => 'Facebook Posts', 'slug' => 'facebook-posts', 'menu_icon' => 'dashicons-facebook', 'public' => get_option('ik_fb_show_posts_on_site', false) );
		$customFields = array();
		$customFields[] = array('name' => 'like_count', 'title' => 'Like Count', 'description' => 'How many times this post has been Liked on Facebook.', 'type' => 'text', 'disabled' => true);	
		
		$customFields[] = array('name' => 'caption', 'title' => 'Caption', 'description' => 'This is the Caption associated with the Item.', 'type' => 'text', 'disabled' => true);	
		$customFields[] = array('name' => 'description', 'title' => 'Description', 'description' => 'This is the Description associated with the Item.', 'type' => 'text', 'disabled' => true);	
		$customFields[] = array('name' => 'link', 'title' => 'Link', 'description' => 'This is the Link associated with the Item.', 'type' => 'text', 'disabled' => true);	
		$customFields[] = array('name' => 'item_type', 'title' => 'Item Type', 'description' => 'The type of Content in the Item (e.g. Video)', 'type' => 'text', 'disabled' => true);
		$customFields[] = array('name' => 'content_source', 'title' => 'Content Source', 'description' => 'The path to the Source of the Content (e.g. the Video)', 'type' => 'text', 'disabled' => true);		
		$customFields[] = array('name' => 'status_type', 'title' => 'Status Type', 'description' => 'This is the Status Type of Item.', 'type' => 'text', 'disabled' => true);		
		
		$customFields[] = array('name' => 'comment_count', 'title' => 'Comment Count', 'description' => 'How many comments this post has on Facebook.', 'type' => 'text', 'disabled' => true);	
		$customFields[] = array('name' => 'fbid', 'title' => 'Facebook Post ID', 'description' => 'The ID of this post on Facebook.  Changing this value may prevent this post from updating.', 'type' => 'text', 'disabled' => true);
		$customFields[] = array('name' => 'feed_id', 'title' => 'Facebook Profile ID', 'description' => 'The ID of the Facebook Profile this post is from. This helps identify posts that come from different feeds.', 'type' => 'text', 'disabled' => true);
		$customFields[] = array('name' => 'updated_time', 'title' => 'Updated Time', 'description' => 'The timestamp of when this post was last updated on Facebook.  Changing this value may prevent this post from updating.', 'type' => 'text', 'disabled' => true);	
		$customFields[] = array('name' => 'created_time', 'title' => 'Created Time', 'description' => 'The timestamp of when this post was created on Facebook.', 'type' => 'text', 'disabled' => true);
		
		
		$customFields[] = array('name' => 'start_time', 'title' => 'Event Start Time', 'description' => 'When this event starts.', 'type' => 'text', 'disabled' => true);
		$customFields[] = array('name' => 'end_time', 'title' => 'Event End Time', 'description' => 'When this event ends.', 'type' => 'text', 'disabled' => true);
		$customFields[] = array('name' => 'location', 'title' => 'Event Location', 'description' => 'Where this event is occurring.', 'type' => 'text', 'disabled' => true);
		$customFields[] = array('name' => 'timezone', 'title' => 'Event Timezone', 'description' => 'The timezone of this event.', 'type' => 'text', 'disabled' => true);
		
		$this->add_custom_post_type($postType, $customFields);
		
		//load list of current posts that have featured images	
		$supportedTypes = get_theme_support( 'post-thumbnails' );
		
		//for the facebookpost image support in wordpress   
		//none set, add them just to our type
		if( $supportedTypes === false ){
			add_theme_support( 'post-thumbnails', array( 'facebookpost' ) );           
		}
		//specifics set, add ours to the array
		elseif( is_array( $supportedTypes ) ){
			$supportedTypes[0][] = 'facebookpost';
			add_theme_support( 'post-thumbnails', $supportedTypes[0] );
		}
		
		//if neither of the above hit, the theme in general supports them for everything.  that includes us!
				
		//twitter
		/*
		$postType = array('name' => 'Twitter Post', 'plural' => 'Twitter Posts', 'slug' => 'twitter-posts', 'menu_icon' => 'dashicons-twitter');
		$customFields = array();
		$customFields[] = array('name' => 'favorite_count', 'title' => 'Favorite Count', 'description' => 'How many times this post has been Favorited on Twitter.', 'type' => 'text');	
		$customFields[] = array('name' => 'retweet_count', 'title' => 'Retweet Count', 'description' => 'How many times this post has been Retweeted.', 'type' => 'text');	
		$this->add_custom_post_type($postType, $customFields);
		*/
	}
	
	//add custom columns to post list
	function gp_edit_facebookpost_columns( $columns ) {

		$columns = array(
			'cb' => '<input type="checkbox" />',
			'title' => __( 'Title' ),
			'type' => __( 'Type' ),
			'pageid' => __( 'Page ID' ),
			'date' => __( 'Date' )
		);

		return $columns;
	}
	
	//we are on the index page, so now we can apply our comment filtering function
	function gp_exclude_custom_comments_from_dashboard( ) {
		add_filter( 'comments_clauses', array($this, 'gp_exclude_custom_comments'), 10, 1);
	}
	
	//hide our custom comments from dashboard widgets
	function gp_exclude_custom_comments( $clauses ) {
		// Hide all those comments which aren't of type system_message
		$clauses['where'] .= ' AND comment_type != "ikfb_fb_comment"';   

		return $clauses;
	}
	
	//add custom comment type to filter list
	function gp_custom_comment_filter( $comment_types ){
		$comment_types = array(
			'comment' => __( 'Comments' ),
			'pings' => __( 'Pings' ),
			'ikfb_fb_comment' => __( 'Facebook Comments' )
		);		
		
		return $comment_types;
	}
	
	//add content to custom columns
	function gp_manage_facebookpost_columns( $column, $post_id ) {
		global $post;
		
		//output values for type
		$content_types = array(
			'events' => "Event",
			'default' => "Post"
		);

		switch( $column ) {

			/* If displaying the 'type' column. */
			case 'type' :
				/* Get the post meta. */
				$content_type = get_post_meta( $post_id, '_ikcf_content_type', true );

				/* If no type is found, output a default message. */
				if ( empty( $content_type ) ){
					echo __( 'Unknown' );
				} else {
					echo $content_types[$content_type];
				}
				break;

			/* If displaying the 'type' column. */
			case 'pageid' :
				/* Get the post meta. */
				$feed_id = get_post_meta( $post_id, '_ikcf_feed_id', true );

				/* If no type is found, output a default message. */
				if ( empty( $feed_id ) ){
					echo __( 'Unknown' );
				} else {
					echo $feed_id;
				}
				break;		

			/* Just break out of the switch statement for everything else. */
			default :
				break;
		}
	}
	
	//make custom columns sortable
	function gp_facebookpost_sortable_columns( $columns ) {

		$columns['type'] = 'type';
		$columns['pageid'] = 'pageid';

		return $columns;
	}

	function gp_edit_facebookpost_load() {
		add_filter( 'request', array($this, 'gp_sort_facebookposts') );
	}

	/* Sorts the facebookposts */
	function gp_sort_facebookposts( $vars ) {

		/* Check if we're viewing the 'facebookpost' post type. */
		if ( isset( $vars['post_type'] ) && 'facebookpost' == $vars['post_type'] ) {

			/* Check if 'orderby' is set to 'pageid'. */
			if ( isset( $vars['orderby'] ) && 'pageid' == $vars['orderby'] ) {

				/* Merge the query vars with our custom variables. */
				$vars = array_merge(
					$vars,
					array(
						'meta_key' => '_ikcf_feed_id',
						'orderby' => 'meta_value'
					)
				);
			}
			
			/* Check if 'orderby' is set to 'type'. */
			if ( isset( $vars['orderby'] ) && 'type' == $vars['orderby'] ) {

				/* Merge the query vars with our custom variables. */
				$vars = array_merge(
					$vars,
					array(
						'meta_key' => '_ikcf_content_type',
						'orderby' => 'meta_value'
					)
				);
			}
		}

		return $vars;
	}	
	
	//hide "add new" buttons for Twitter and FB posts
	function hide_add_new_custom_type(){
		global $submenu;
		unset($submenu['edit.php?post_type=facebookpost'][10]);
		//unset($submenu['edit.php?post_type=twitterpost'][10]);
	}
	
	//hide "add new" button from Admin bar for Twitter and FB posts
	function remove_admin_bar_links() {
		global $wp_admin_bar;
		
		//$wp_admin_bar->remove_menu('new-twitterpost');
		$wp_admin_bar->remove_menu('new-facebookpost');
	}
	
	//register admin css and js
	//perform other admin steps, if needed
    function ikfb_admin_init($hook) {
		$screen = get_current_screen();
		
		//enqueue admin js and css on admin and widget / preview pages
		if  (strpos($hook,'ikfb') !== false || 
			 $screen->id === "widgets" || 
			(function_exists('is_customize_preview') && is_customize_preview())
		) {
			wp_register_style( 'ikfb_admin_stylesheet', plugins_url('include/css/admin_style.css', __FILE__) );
			wp_enqueue_style( 'ikfb_admin_stylesheet' );
			wp_enqueue_script( 'ik_fb_pro_options', plugins_url('include/js/js.js', __FILE__), array( 'farbtastic', 'jquery' ) );
			
			wp_enqueue_script(
				'gp-admin_v2',
				plugins_url('include/js/gp-admin_v2.js', __FILE__),
				array( 'jquery' ),
				false,
				true
			);
			
			wp_enqueue_script(
				'ikfb-admin',
				plugins_url('include/js/ikfb-admin.js', __FILE__),
				array( 'jquery' ),
				false,
				true
			); 

			// register the shortcode generator script
			wp_register_script( 'gp_shortcode_generator', plugins_url('include/js/shortcode-generator.js', __FILE__), array( 'jquery') );
			
			wp_enqueue_script( 'jquery-ui-datepicker' );
		}
		
		if ( (strpos($hook,'ikfb') !== false || 
			 $screen->id === "widgets" || 
			(function_exists('is_customize_preview') && is_customize_preview()) ) &&
			strpos($hook,'shortcode-generator') === false	
		) {
			wp_enqueue_style( 'farbtastic' );
			wp_enqueue_script( 'farbtastic' );
			wp_enqueue_style('jquery-style', plugins_url('include/css/jquery-ui.css', __FILE__));
		}
    }

	//register any widgets here
	function ik_fb_register_widgets() {
		register_widget( 'ikFacebookFeedWidget' );
		register_widget( 'ikFacebookLikeButtonWidget' );
	}
	
	//add Basic CSS
	function ik_fb_setup_css() {						
		$ikfb_themes = array(
			'ik_facebook_style' => 'include/css/style.css',
			'ik_facebook_dark_style' => 'include/css/dark_style.css',
			'ik_facebook_light_style' => 'include/css/light_style.css',
			'ik_facebook_blue_style' => 'include/css/blue_style.css',
			'ik_facebook_no_style' => 'include/css/no_style.css',
			'ik_facebook_gallery_style' => 'include/css/gallery.css',
			'ik_facebook_video_style' => 'include/css/video.css'
		);
		
		if($this->is_pro){
			$ikfb_themes['ik_facebook_cobalt_style'] = 'include/css/cobalt_style.css';
			$ikfb_themes['ik_facebook_green_gray_style'] = 'include/css/green_gray_style.css';
			$ikfb_themes['ik_facebook_halloween_style'] = 'include/css/halloween_style.css';
			$ikfb_themes['ik_facebook_indigo_style'] = 'include/css/indigo_style.css';
			$ikfb_themes['ik_facebook_orange_style'] = 'include/css/orange_style.css';			
		}
	
		foreach($ikfb_themes as $name => $path){
			wp_register_style( $name, plugins_url($path, __FILE__) );
		}
		
		wp_enqueue_style( 'ik_facebook_' . get_option('ik_fb_feed_theme'));
		//css for videos
		wp_enqueue_style( 'ik_facebook_video_style');
		wp_enqueue_style( 'ik_facebook_gallery_style' );
	}

	//add Custom CSS
	function ik_fb_setup_custom_css() {
		//use this to track if css has been output
		global $ikfb_footer_css_output;
		
		if($ikfb_footer_css_output){
			return;
		} else {
			echo '<!--IKFB CSS--> <style type="text/css" media="screen">' . get_option('ik_fb_custom_css') . "</style>";
			$ikfb_footer_css_output = true;
		}
	}
	
	//add Custom CSS from Theme
	function ik_fb_setup_custom_theme_css() {
		//only enqueue CSS if it's there
		if(file_exists(get_stylesheet_directory() . '/ik_fb_custom_style.css' )){
			wp_register_style( 'ik_facebook_custom_style', get_stylesheet_directory_uri() . '/ik_fb_custom_style.css' );
			wp_enqueue_style( 'ik_facebook_custom_style' );
		}
	}
	
	// Enqueue any needed Google Web Fonts
	function enqueue_webfonts(){
		$font_list = $this->list_required_google_fonts();
		$font_list_encoded = array_map('urlencode', $this->list_required_google_fonts());
		$font_str = implode('|', $font_list_encoded);
		
		//don't register this unless a font is set to register
		if(strlen($font_str)>2){
			$protocol = is_ssl() ? 'https:' : 'http:';
			$font_url = $protocol . '//fonts.googleapis.com/css?family=' . $font_str;
			wp_register_style( 'ik_facebook_webfonts', $font_url);
			wp_enqueue_style( 'ik_facebook_webfonts' );
		}
	}
	
	function list_required_google_fonts(){
		// check each typography setting for google fonts, and build a list
		$option_keys = array(	'ik_fb_font_family',
								'ik_fb_posted_by_font_family',
								'ik_fb_date_font_family',
								'ik_fb_description_font_family',
								'ik_fb_link_font_family',
								'ik_fb_font_family',
						);
		$fonts = array();		
		foreach ($option_keys as $option_key) {
			$option_value = get_option($option_key);
			if (strpos($option_value, 'google:') !== FALSE) {
				$option_value = str_replace('google:', '', $option_value);
				
				//only add the font to the array if it was in fact a google font
				$fonts[$option_value] = $option_value;				
			}
		}		
		return $fonts;
	}
	
	//check to see time elapsed since given datetime
	//credit to http://stackoverflow.com/questions/2915864/php-how-to-find-the-time-elapsed-since-a-date-time
	function humanTiming ($time){
		$time = time() - $time; // to get the time since that moment
	
		$tokens = array (
			31536000 => __('year', 'ik-facebook'),
			2592000 => __('month', 'ik-facebook'),
			604800 => __('week', 'ik-facebook'),
			86400 => __('day', 'ik-facebook'),
			3600 => __('hour', 'ik-facebook'),
			60 => __('minute', 'ik-facebook'),
			1 => __('second', 'ik-facebook')
		);

		foreach ($tokens as $unit => $text) {
			if ($time < $unit) continue;
			$numberOfUnits = floor($time / $unit);
			return $numberOfUnits.' '.$text.(($numberOfUnits>1)?'s':'');
		}

	}
	
	//thanks to Alix Axel, http://stackoverflow.com/questions/2762061/how-to-add-http-if-its-not-exists-in-the-url
	function addhttp($url){
		if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
			$url = "http://" . $url;
		}
		return $url;
	}
	
	//fetches an URL
	function fetchUrl($url,$decode=false){		
		//caching
		$ch = new CachedCurl();
		$retData = $ch->load_url($url);
		
		if($decode){
			$retData = json_decode($retData);
		}
		
		return $retData;
	}

	/* Styling Functions */
	
	/*
	 * Builds a CSS string corresponding to the values of a typography setting
	 *
	 * @param	$prefix		The prefix for the settings. We'll append font_name,
	 *						font_size, etc to this prefix to get the actual keys
	 *
	 * @returns	string		The completed CSS string, with the values inlined
	 */
	function build_typography_css($prefix){
		$css_rule_template = ' %s: %s;';
		$output = '';
		
		/* 
		 * Font Family
		 */
		$option_val = get_option($prefix . 'font_family', '');
		if (!empty($option_val)) {
			// strip off 'google:' prefix if needed
			$option_val = str_replace('google:', '', $option_val);

		
			// wrap font family name in quotes
			$option_val = '\'' . $option_val . '\'';
			$output .= sprintf($css_rule_template, 'font-family', $option_val);
		}
		
		/* 
		 * Font Size
		 */
		$option_val = get_option($prefix . 'font_size', '');
		if (!empty($option_val)) {
			// append 'px' if needed
			if ( is_numeric($option_val) ) {
				$option_val .= 'px';
			}
			$output .= sprintf($css_rule_template, 'font-size', $option_val);
		}		
		
		/* 
		 * Font Color
		 */
		$option_val = get_option($prefix . 'font_color', '');
		if (!empty($option_val)) {
			$output .= sprintf($css_rule_template, 'color', $option_val);
		}

		/* 
		 * Font Style - add font-style and font-weight rules
		 * NOTE: in this special case, we are adding 2 rules!
		 */
		$option_val = get_option($prefix . 'font_style', '');

		// Convert the value to 2 CSS rules, font-style and font-weight
		// NOTE: we lowercase the value before comparison, for simplification
		switch(strtolower($option_val))
		{
			case 'regular':
				// not bold not italic
				$output .= sprintf($css_rule_template, 'font-style', 'normal');
				$output .= sprintf($css_rule_template, 'font-weight', 'normal');
			break;
		
			case 'bold':
				// bold, but not italic
				$output .= sprintf($css_rule_template, 'font-style', 'normal');
				$output .= sprintf($css_rule_template, 'font-weight', 'bold');
			break;

			case 'italic':
				// italic, but not bold
				$output .= sprintf($css_rule_template, 'font-style', 'italic');
				$output .= sprintf($css_rule_template, 'font-weight', 'normal');
			break;
		
			case 'bold italic':
				// bold and italic
				$output .= sprintf($css_rule_template, 'font-style', 'italic');
				$output .= sprintf($css_rule_template, 'font-weight', 'bold');
			break;
			
			default:
				// empty string or other invalid value, ignore and move on
			break;			
		}			

		// return the completed CSS string
		return trim($output);		
	}

	// add inline links to our plugin's description area on the Plugins page
	function add_custom_links_to_plugin_description($links, $file) { 

		/** Get the plugin file name for reference */
		$plugin_file = plugin_basename( __FILE__ );
	 
		/** Check if $plugin_file matches the passed $file name */
		if ( $file == $plugin_file )
		{		
			$new_links['settings_link'] = '<a href="admin.php?page=ikfb_configuration_options">Settings</a>';
			$new_links['support_link'] = '<a href="http://goldplugins.com/contact/?utm-source=plugin_menu&utm_campaign=support&utm_banner=ikfb_settings_links" target="_blank">Pro Support</a>';
				
			if(!$this->is_pro){
				$new_links['upgrade_to_pro'] = '<a href="http://goldplugins.com/our-plugins/wp-social-pro/upgrade-to-wp-social-pro/?utm_source=plugin_menu&utm_campaign=upgrade" target="_blank">Upgrade to Pro</a>';
			}
			
			$links = array_merge( $links, $new_links);
		}
		return $links; 
	}

	//add an inline link to the settings page, before the "deactivate" link
	function add_settings_link_to_plugin_action_links($links) { 
		$settings_link = '<a href="admin.php?page=ikfb_configuration_options">Settings</a>';
		array_unshift($links, $settings_link); 
		return $links; 
	}
	
}//end ikFacebook

if (!isset($ik_fb)){
	$ik_fb = new ikFacebook();
}