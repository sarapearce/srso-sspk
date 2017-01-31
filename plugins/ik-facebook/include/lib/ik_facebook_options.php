<?php
/*
This file is part of The WP Social Plugin .

The WP Social Plugin  is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

The WP Social Plugin  is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with The WP Social Plugin.  If not, see <http://www.gnu.org/licenses/>.
*/

class ikFacebookOptions
{
	var $root = false;
	var $is_pro = false;
	var $coupon_box;
	var $pro_string;
	var $update_feeds_now = false;
	var $messages = array();

	function __construct($root = false){
		//may be running in non WP mode (for example from a notification)
		if(function_exists('add_action')){
			//add a menu item
			add_action('admin_menu', array($this, 'add_admin_menu_items'));		
		}
		
		//setup root
		if ($root) {
			$this->root = $root;
		}
		
		//create the BikeShed object now, so that BikeShed can add its hooks
        $this->shed = new GoldPlugins_BikeShed();
		
		//check for pro and set class variable if so
		if(is_valid_key(get_option('ik_fb_pro_key'))){
			$this->is_pro = true;
		}
		
		//setup coupon box
		$coupon_box_settings = array(
			'plugin_name' 		=> 'WP Social Pro',
			'pitch' 			=> "When you upgrade, you'll instantly unlock advanced features including Custom HTML, Custom Event options, Themes, and more!",
			'learn_more_url' 	=> 'https://goldplugins.com/our-plugins/wp-social-pro/?utm_source=cpn_box&utm_campaign=upgrade&utm_banner=learn_more',
			'upgrade_url' 		=> 'https://goldplugins.com/our-plugins/wp-social-pro/upgrade-to-wp-social-pro/?utm_source=plugin_menu&utm_campaign=upgrade',
			'upgrade_url_promo' => 'https://goldplugins.com/purchase/wp-social-pro/single?promo=newsub10',
			'text_domain' => 'before-and-after',
			'testimonial' => array(
				'title' => 'Good, responsive support',
				'body' => 'I highly recommend this. The plug-in makers offer good, responsive support.',
				'name' => 'Carlton Smith<br>Flagstone Search Marketing',
			)
		);
		$coupon_box = new GP_MegaSeptember($coupon_box_settings);
		$this->coupon_box = $coupon_box;
		
		//instantiate Sajak so we get our JS and CSS enqueued
		new GP_Sajak();

		//call register settings function
		add_action( 'admin_init', array($this, 'register_settings'));	

		if ( get_option('ik_fb_app_id', '') == '' ) {
			add_action( 'admin_init', array($this, 'register_admin_notices'));
		}		
		
		//load additional label string based upon pro status
		$this->pro_string = $this->is_pro ? "" : " (Pro)";		
		
		//handle any changes in scheduling status and forced refreshes
		$this->process_cron_options();	
		
		//process feed refresh requests
		add_action( 'update_option_ik_fb_page_id_types', array($this, 'refresh_as_needed') );
	}
	
	//if flag is set, refresh feeds and clear flag
	function refresh_as_needed(){		
		if($this->update_feeds_now){
			$this->root->gp_facebook->force_reload = true;
			$this->root->gp_facebook->run_scheduled_feed_update();
		}
		
		$this->update_feeds_now = false;
		$this->messages[] = "Your feeds have been updated.";
	}
	
	//forced refresh and scheduling changes
	function process_cron_options(){
		//force feed update yang			
		//if the refresh feeds now button has been clicked
		if (isset($_GET['run-cron-now']) && $_GET['run-cron-now'] == 'true'){
			//go ahead and add the posts, too
			$this->root->gp_facebook->force_reload = true;
			add_action('admin_init', array($this->root->gp_facebook, 'run_scheduled_feed_update') );
		}
		
		//cron yang
		//schedule cron if enabled
		if(get_option('ik_fb_enable_cron', 0)){
			//and if the cron job hasn't already been scheduled
			if(!wp_get_schedule('scheduled_feed_update')){
				//schedule the cron job
				$this->root->gp_facebook->cron_activate();
			}
		} else {
			//else if the cron job option has been unchecked
			//clear the scheduled job
			$this->root->gp_facebook->cron_deactivate();
		}
	}
	
	function add_admin_menu_items(){		
		if(get_option('ik_fb_unbranded') && $this->is_pro){
			$title = __('Social Settings', 'ik-facebook');
		} else {
			$title = __('WP Social Settings', 'ik-facebook');
		}
		
		if(get_option('ik_fb_unbranded') && $this->is_pro){
			$page_title = __('Social Plugin Settings', 'ik-facebook');
		} else {
			$page_title = __('WP Social Plugin Settings', 'ik-facebook');
		}
		
		// TODO: Any reason not to change this to ik-facebook or something? see note on http://codex.wordpress.org/Function_Reference/add_submenu_page:
		// 		 "For $menu_slug please don't use __FILE__ it makes for an ugly URL, and is a minor security nuisance. "
		// $top_level_menu_slug = __FILE__;
		$this->top_level_menu_slug = 'ikfb_configuration_options';
		
		//create new top-level menu
		$this->hook_suffix = add_menu_page($page_title, $title, 'administrator', $this->top_level_menu_slug, array($this, 'configuration_options_page'));

		//create sub menus for each tab
		add_submenu_page( $this->top_level_menu_slug, 'Basic Configuration', 'Basic Configuration', 'manage_options', $this->top_level_menu_slug, array($this, 'configuration_options_page') ); 
		add_submenu_page( $this->top_level_menu_slug, 'Style Options', 'Style Options', 'manage_options', 'ikfb_style_options', array($this, 'style_options_page') ); 
		add_submenu_page( $this->top_level_menu_slug, 'Display Options', 'Display Options', 'manage_options', 'ikfb_display_options', array($this, 'display_options_page') ); 
		add_submenu_page( $this->top_level_menu_slug, 'Event Options', 'Event Options', 'manage_options', 'ikfb_event_options', array($this, 'event_options_page') ); 
		
		//only add for Pro users to prevent clutter in free version
		if($this->is_pro){
			add_submenu_page( $this->top_level_menu_slug, 'Custom HTML Options', 'Custom HTML Options', 'manage_options', 'ikfb_custom_html_options', array($this, 'custom_html_options_page') ); 
		}
		
		add_submenu_page( $this->top_level_menu_slug, 'Shortcode Generator', 'Shortcode Generator', 'manage_options', 'ikfb-shortcode-generator', array($this, 'shortcode_generator_page') ); 
		add_submenu_page( $this->top_level_menu_slug, 'Help &amp; Instructions', 'Help &amp; Instructions', 'manage_options', 'ikfb_plugin_status', array($this, 'plugin_status_page') );
	}
	
	/**
	 * If the user hasn't entered their Facebook App ID, show a notice
	 */
	function register_admin_notices() {
		add_action( 'admin_notices', array( $this, 'display_admin_notices' ) );
	}
	
	/**
	 * Function to output an admin notice when the plugin has not 
	 * been configured yet
	 */
	function display_admin_notices() {
		$screen = get_current_screen();
		if ( $screen->id == $this->hook_suffix ) { //$this->hook_suffix
			$fb_url = 'https://developers.facebook.com/apps';
			$tutorial_url = 'http://goldplugins.com/documentation/wp-social-pro-documentation/how-to-get-an-app-id-and-secret-key-from-facebook/?utm_source=ikfb_settings&utm_campaign=enter_app_id_and_secret';
			
			$this->messages[] = __( 'Please enter your Facebook App ID and Secret Key.', 'ik_facebook' );
			$this->messages[] = sprintf( __( 'To get your App ID and Secret Key from Facebook, please visit the <a href="%1$s">Facebook Developer portal</a>.', 'ik_facebook' ), $fb_url );
			$this->messages[] = sprintf( __( 'As this process can be somewhat confusing for new users, we have created a <a href="%1$s">video tutorial for you to follow</a>, which explains the process in detail</a>.', 'ik_facebook' ), $tutorial_url );						
		} else {
			$this->messages[] = __(  'WP Social is almost ready. ', 'ik_facebook' );						
			$this->messages[] = sprintf( __( 'You must <a href="%1$s">configure WP Social</a> for it to work.' ), menu_page_url($this->top_level_menu_slug , false ) );
		}
	}
		
	//function to produce tabs on admin screen
	function ik_fb_admin_tabs() {	
		$current = $_GET['page'];
	
		$tabs = array( 
			'ikfb_configuration_options' => __('Basic Configuration', 'ik-facebook'), 
			'ikfb_style_options' => __('Style Options', 'ik-facebook'), 
			'ikfb_display_options' => __('Display Options', 'ik-facebook'), 
			'ikfb_event_options' => __('Event Options', 'ik-facebook'), 
			//'ikfb-shortcode-generator' => __('Shortcode Generator', 'ik-facebook'), 
			//'ikfb_plugin_status' => __('Help &amp; Instructions', 'ik-facebook')
		);
		
		//add tabs for pro users
		if($this->is_pro){
			$tabs['ikfb_custom_html_options'] = __('Custom HTML Options', 'ik-facebook'); 
		}
		
		echo '<div id="icon-themes" class="icon32"><br></div>';
		echo '<h2 class="nav-tab-wrapper">';
			foreach( $tabs as $tab => $name ){
				$class = ( $tab == $current ) ? ' nav-tab-active' : '';
				echo "<a class='nav-tab$class' href='?page=$tab'>$name</a>";
			}
		echo '</h2>';
	}
	
	//register our settings
	function register_settings(){
		//register our config settings
		register_setting( 'ik-fb-config-settings-group', 'ik_fb_page_id', array($this, 'feed_id_change') );
		register_setting( 'ik-fb-config-settings-group', 'ik_fb_page_id_types', array($this, 'feed_type_change') );		
		register_setting( 'ik-fb-config-settings-group', 'ik_fb_app_id' );
		register_setting( 'ik-fb-config-settings-group', 'ik_fb_secret_key' );
		register_setting( 'ik-fb-config-settings-group', 'wpsp_fb_upload_path', array($this, 'clean_upload_dir') );
		register_setting( 'ik-fb-config-settings-group', 'ik_fb_pro_key' );
		register_setting( 'ik-fb-config-settings-group', 'ik_fb_pro_url' );
		register_setting( 'ik-fb-config-settings-group', 'ik_fb_pro_email' );
		register_setting( 'ik-fb-config-settings-group', 'ik_fb_enable_cron' );
		register_setting( 'ik-fb-config-settings-group', 'ik_fb_update_timeframe' );
		register_setting( 'ik-fb-config-settings-group', 'ik_fb_pro_options_mixer', array($this, 'update_options_mixer') );
		
		// register pro config settings		
		register_setting( 'ik-fb-config-settings-group', 'wp_social_pro_registered_email' );
		register_setting( 'ik-fb-config-settings-group', 'wp_social_registered_url' );
		register_setting( 'ik-fb-config-settings-group', 'wp_social_pro_registered_key' );		
		
		//register our style settings
		register_setting( 'ik-fb-style-settings-group', 'ik_fb_custom_css' );
		register_setting( 'ik-fb-style-settings-group', 'ik_fb_fix_feed_image_width' );
		register_setting( 'ik-fb-style-settings-group', 'ik_fb_feed_image_width' );
		register_setting( 'ik-fb-style-settings-group', 'ik_fb_fix_feed_image_height' );
		register_setting( 'ik-fb-style-settings-group', 'ik_fb_feed_image_height' );
		register_setting( 'ik-fb-style-settings-group', 'ik_fb_feed_theme' );		
		register_setting( 'ik-fb-style-settings-group', 'ik_fb_header_bg_color' );
		register_setting( 'ik-fb-style-settings-group', 'ik_fb_window_bg_color' );		
		register_setting( 'ik-fb-style-settings-group', 'ik_fb_posted_by_font_color' );
		register_setting( 'ik-fb-style-settings-group', 'ik_fb_posted_by_font_size' );
		register_setting( 'ik-fb-style-settings-group', 'ik_fb_posted_by_font_style' );
		register_setting( 'ik-fb-style-settings-group', 'ik_fb_posted_by_font_family' );
		register_setting( 'ik-fb-style-settings-group', 'ik_fb_date_font_color' );
		register_setting( 'ik-fb-style-settings-group', 'ik_fb_date_font_size' );
		register_setting( 'ik-fb-style-settings-group', 'ik_fb_date_font_style' );
		register_setting( 'ik-fb-style-settings-group', 'ik_fb_date_font_family' );
		register_setting( 'ik-fb-style-settings-group', 'ik_fb_description_font_color' );
		register_setting( 'ik-fb-style-settings-group', 'ik_fb_description_font_size' );
		register_setting( 'ik-fb-style-settings-group', 'ik_fb_description_font_style' );
		register_setting( 'ik-fb-style-settings-group', 'ik_fb_description_font_family' );
		register_setting( 'ik-fb-style-settings-group', 'ik_fb_link_font_color' );
		register_setting( 'ik-fb-style-settings-group', 'ik_fb_link_font_size' );
		register_setting( 'ik-fb-style-settings-group', 'ik_fb_link_font_style' );
		register_setting( 'ik-fb-style-settings-group', 'ik_fb_link_font_family' );
		register_setting( 'ik-fb-style-settings-group', 'ik_fb_feed_window_height' );
		register_setting( 'ik-fb-style-settings-group', 'ik_fb_feed_window_width' );
		register_setting( 'ik-fb-style-settings-group', 'ik_fb_font_color' );
		register_setting( 'ik-fb-style-settings-group', 'ik_fb_font_size' );
		register_setting( 'ik-fb-style-settings-group', 'ik_fb_font_family' );
		register_setting( 'ik-fb-style-settings-group', 'ik_fb_font_style' );
		register_setting( 'ik-fb-style-settings-group', 'ik_fb_story_font_color' );
		register_setting( 'ik-fb-style-settings-group', 'ik_fb_story_font_size' );
		register_setting( 'ik-fb-style-settings-group', 'ik_fb_story_font_family' );
		register_setting( 'ik-fb-style-settings-group', 'ik_fb_story_font_style' );
		register_setting( 'ik-fb-style-settings-group', 'ik_fb_sidebar_feed_window_height' );
		register_setting( 'ik-fb-style-settings-group', 'ik_fb_sidebar_feed_window_width' );
		register_setting( 'ik-fb-style-settings-group', 'other_ik_fb_feed_window_width' );
		register_setting( 'ik-fb-style-settings-group', 'other_ik_fb_feed_image_width' );
		register_setting( 'ik-fb-style-settings-group', 'other_ik_fb_feed_image_height' );
		register_setting( 'ik-fb-style-settings-group', 'other_ik_fb_feed_window_height' );
		register_setting( 'ik-fb-style-settings-group', 'other_ik_fb_sidebar_feed_window_height' );
		register_setting( 'ik-fb-style-settings-group', 'other_ik_fb_sidebar_feed_window_width' );
		register_setting( 'ik-fb-style-settings-group', 'ik_fb_pro_options_mixer', array($this, 'update_options_mixer') );
		
		//register our display settings
		register_setting( 'ik-fb-display-settings-group', 'ik_fb_hide_feed_images' );
		register_setting( 'ik-fb-display-settings-group', 'ik_fb_show_feed_videos' );
		register_setting( 'ik-fb-display-settings-group', 'ik_fb_show_like_button' );
		register_setting( 'ik-fb-display-settings-group', 'ik_fb_show_profile_picture' );
		register_setting( 'ik-fb-display-settings-group', 'ik_fb_show_page_title' );
		register_setting( 'ik-fb-display-settings-group', 'ik_fb_show_posted_by' );
		register_setting( 'ik-fb-display-settings-group', 'ik_fb_show_stories' );
		register_setting( 'ik-fb-display-settings-group', 'ik_fb_show_date' );
		register_setting( 'ik-fb-display-settings-group', 'ik_fb_date_format' );
		register_setting( 'ik-fb-display-settings-group', 'ik_fb_use_human_timing' );
		register_setting( 'ik-fb-display-settings-group', 'ik_fb_feed_limit' );
		register_setting( 'ik-fb-display-settings-group', 'ik_fb_photo_feed_limit' );
		register_setting( 'ik-fb-display-settings-group', 'ik_fb_character_limit' );
		register_setting( 'ik-fb-display-settings-group', 'ik_fb_description_character_limit' );
		register_setting( 'ik-fb-display-settings-group', 'ik_fb_caption_character_limit' );
		register_setting( 'ik-fb-display-settings-group', 'ik_fb_link_photo_to_feed_item' );
		register_setting( 'ik-fb-display-settings-group', 'ik_fb_show_posts_on_site' );
		register_setting( 'ik-fb-display-settings-group', 'ik_fb_onsite_or_facebook' );		
		register_setting( 'ik-fb-display-settings-group', 'ik_fb_pro_options_mixer', array($this, 'update_options_mixer') );
		
		//register any pro settings		
		//in 2.13 update, "branding" settings were moved under the Display Options and grouped
		register_setting( 'ik-fb-branding-settings-group', 'ik_fb_only_show_page_owner' );
		register_setting( 'ik-fb-branding-settings-group', 'ik_fb_unbranded' );
		
		//in 2.13 update, Pro Display Options were moved under Standard Display Options and grouped
		register_setting( 'ik-fb-display-settings-group', 'ik_fb_show_avatars' );
		register_setting( 'ik-fb-display-settings-group', 'ik_fb_show_replies' );
		register_setting( 'ik-fb-display-settings-group', 'ik_fb_show_reply_counts' );
		register_setting( 'ik-fb-display-settings-group', 'ik_fb_show_likes' );
		
		//in 2.13 update, Event Settings were moved out of Pro sub-tab (Pro sub-tab has been removed) and given their own tab
		register_setting( 'ik-fb-pro-event-settings-group', 'ik_fb_show_only_events' );
		register_setting( 'ik-fb-pro-event-settings-group', 'ik_fb_reverse_events' );
		register_setting( 'ik-fb-pro-event-settings-group', 'ik_fb_start_date_format' );
		register_setting( 'ik-fb-pro-event-settings-group', 'ik_fb_end_date_format' );
		register_setting( 'ik-fb-pro-event-settings-group', 'ik_fb_event_image_size' );
		register_setting( 'ik-fb-pro-event-settings-group', 'ik_fb_event_range_start_date' );
		register_setting( 'ik-fb-pro-event-settings-group', 'ik_fb_event_range_end_date' );
		register_setting( 'ik-fb-pro-event-settings-group', 'ik_fb_range_or_manual' );
		register_setting( 'ik-fb-pro-event-settings-group', 'ik_fb_event_range_past_days' );	
		register_setting( 'ik-fb-pro-event-settings-group', 'ik_fb_event_range_future_days' );			
		
		//in 2.13 update, Custom HTML Settings were moved out of the Pro sub-tab and into their own tab
		register_setting( 'ik-fb-html-settings-group', 'ik_fb_feed_item_html' );
		register_setting( 'ik-fb-html-settings-group', 'ik_fb_message_html' );
		register_setting( 'ik-fb-html-settings-group', 'ik_fb_image_html' );
		register_setting( 'ik-fb-html-settings-group', 'ik_fb_description_html' );
		register_setting( 'ik-fb-html-settings-group', 'ik_fb_caption_html' );
		register_setting( 'ik-fb-html-settings-group', 'ik_fb_feed_html' );
		register_setting( 'ik-fb-html-settings-group', 'ik_fb_use_custom_html' );
		register_setting( 'ik-fb-html-settings-group', 'ik_fb_show_picture_before_message' );
	}
	
	function clean_upload_dir($upload_dir){
		$ds = DIRECTORY_SEPARATOR;
		$upload_dir = str_replace( '..' . $ds, '', $upload_dir );
		$upload_dir = rtrim( $upload_dir, $ds );
		$upload_dir = ltrim( $upload_dir, $ds );
		$upload_dir = $ds . $upload_dir;
		
		return $upload_dir;
	}
	
	function update_options_mixer($v = '')
	{
		return md5(rand());
	}
	
	//when the feed IDs change
	//make sure they are valid IDs
	//toggle the refresh so we grab new data
	function feed_id_change($input){
		$retData = $this->extract_facebook_id($input);
		
		$new_values = md5(serialize($input));
		$current_values = md5(serialize(get_option('ik_fb_page_id')));
		
		//if options have changed
		//refresh the feed
		if($new_values != $current_values){	
			//set flag to refresh feed
			$this->update_feeds_now = true;
		}
		
		return $retData;
	}
	
	//when the feed types change
	//toggle the refresh so we grab new data
	function feed_type_change($input){		
		$new_values = md5(serialize($input));
		$current_values = md5(serialize(get_option('ik_fb_page_id_types')));
		
		//if options have changed
		//refresh the feed
		if($new_values != $current_values){
			//set flag to refresh feed
			$this->update_feeds_now = true;
		}
		
		return $input;
	}
	
	//loop through array of fb id's
	//any that match pattern are filtered and that value is set back in place on the array
	function extract_facebook_id($input)
	{
		//https://www.facebook.com/The-Trees-Network-1476662195972809/
		if(is_array($input)){
			foreach($input as $key => $input_item){
				$input_item = trim($input_item, ' /');
				if (strpos($input_item, 'facebook.com/') !== FALSE) {
					$pieces = explode('/', $input_item); // divides the string in pieces where '/' is found
					$last_piece = end($pieces);										
					$last_piece = $this->maybe_extract_facebook_id($last_piece);					
					$input[$key] = $last_piece;
				}
				
				// delete invalid chars
				$input[$key] = preg_replace('/[^0-9A-Za-z\-_]+/', '', $input[$key]);
			}
		}
		
		return $input;
	}
	
	/*
	 * Tests for Facebook IDs of the form My-Facebook-Page-1476662195972809
	 * If an ID like this is found, the numeric part is extracted and returned.
	 * Otherwise, $str is returned as-is.
	 *
	 * @param String $str The Facebook ID to consider
	 * @return String Either the extracted numeric ID, or $str unchanged.	 
	 */
	function maybe_extract_facebook_id($str)
	{
		$pattern = '/(\-[0-9]{12,20})\w+/';
		preg_match($pattern, $str, $matches);
		if ( !empty($matches) ) {
			usort( $matches, array($this, 'sort_by_strlen') );
			$str = trim($matches[0], '- ');
		}		
		return $str;
	}
	
	/*
	 * Custom sorting function for use with usort(), to sort a list of values
	 * by their length (longest first).
	 *
	 * @param String $a The first string to consider
	 * @param String $b The second string to consider
	 * @return Integer -1 if $a is shorter than $b, 1 if $b is longer than a, 
	 * or 0 if they are the same length.
	 */
	function sort_by_strlen($a,$b)
	{
		return strlen($b)-strlen($a);
	}

	function start_settings_page($wrap_with_form = true, $show_newsletter_form = true, $before_title = '', $show_tabs = true )
	{
		global $pagenow;
			
		if (isset($_GET['settings-updated']) && $_GET['settings-updated'] == 'true'){		
			if(get_option('ik_fb_unbranded') && $this->is_pro){
				$message = __("Facebook Settings Updated.", 'ik-facebook');
			} else {
				$message = __("WP Social Plugin Settings Updated.", 'ik-facebook');
			}
			
			$this->messages[] = $message;
		}
		
		if(!$this->is_pro): ?><style>div.disabled,div.disabled th,div.disabled label,div.disabled .description{color:#999999;}</style><?php endif;
		
		?>
			<script type="text/javascript">
				jQuery(function () {
					if (typeof(gold_plugins_init_coupon_box) == 'function') {
						gold_plugins_init_coupon_box();
					}
				});
			</script>
			<?php if($this->is_pro): ?>	
			<div class="wrap ikfb_settings gold_plugins_settings">
			<?php else: ?>
			<div class="wrap ikfb_settings not-pro gold_plugins_settings">			
			<?php endif; ?>
			
			<?php
				if( !empty($this->messages) ){
					foreach($this->messages as $message){
						echo '<div id="messages" class="gp_updated fade">';
						echo '<p>' . $message . '</p>';
						echo '</div>';
					}
					
					$this->messages = array();
				}
			?>
			
				<?php echo $before_title; ?>	
				
				<?php 
					if($show_tabs){
						//output tabs at top of options screen
						$this->ik_fb_admin_tabs();
					}
				?>
		<?php
	}
	
	function end_settings_page($wrap_with_form = true)
	{		
			  if( !$this->is_pro ): ?>
		<?php $this->output_newsletter_signup_form(); ?>
		<?php endif; ?>
		<?php
	}
	
	/*
	 * Outputs the Basic Configuration page
	 */
	function configuration_options_page()
	{				
		//add upgrade button if free version
		$extra_buttons = array();
		if(!$this->is_pro){
			$extra_buttons = array(
				array(
					'class' => 'btn-purple',
					'label' => 'Upgrade To Pro',
					'url' => 'https://goldplugins.com/our-plugins/wp-social-pro/upgrade-to-wp-social-pro/'
				)
			);
		}
		
		//instantiate tabs object for output basic settings page tabs
		$tabs = new GP_Sajak( array(
			'header_label' => 'Basic Configuration',
			'settings_field_key' => 'ik-fb-config-settings-group', // can be an array	
			'show_save_button' => true, // hide save buttons for all panels   		
			'extra_buttons_header' => $extra_buttons, // extra header buttons
			'extra_buttons_footer' => $extra_buttons // extra footer buttons
		) );
		
		$this->start_settings_page();
	
		$tabs->add_tab(
			'facebook-api-settings', // section id, used in url fragment
			'Facebook API Settings', // section label
			array($this, 'output_facebook_api_settings'), // display callback
			array(
				'class' => 'extra_li_class', // extra classes to add sidebar menu <li> and tab wrapper <div>
				'icon' => 'life-buoy' // icons here: http://fontawesome.io/icons/
			)
		);
	
		$tabs->add_tab(
			'facebook-feed-settings', // section id, used in url fragment
			'Facebook Synced Feeds', // section label
			array($this, 'output_facebook_synced_feed_ids'), // display callback
			array(
				'class' => 'extra_li_class', // extra classes to add sidebar menu <li> and tab wrapper <div>
				'icon' => 'life-buoy' // icons here: http://fontawesome.io/icons/
			)
		);
	
		$tabs->add_tab(
			'facebook-api-status', // section id, used in url fragment
			'Facebook API Status', // section label
			array($this, 'output_plugin_status'), // display callback
			array(
				'class' => 'extra_li_class', // extra classes to add sidebar menu <li> and tab wrapper <div>
				'icon' => 'life-buoy' // icons here: http://fontawesome.io/icons/
			)
		);
	
		$tabs->add_tab(
			'registration-settings', // section id, used in url fragment
			'WP Social Pro Registration', // section label
			array($this, 'output_registration_options'), // display callback
			array(
				'class' => 'extra_li_class', // extra classes to add sidebar menu <li> and tab wrapper <div>
				'icon' => 'life-buoy' // icons here: http://fontawesome.io/icons/
			)
		);
		
		$tabs->display();
		
		$this->end_settings_page();		
	}
	
	function output_facebook_synced_feed_ids(){
		?>
		<h3><?php _e("Facebook Feed IDs", 'ik-facebook');?></h3>
			<p><?php _e("These options tell the plugin what Facebook accounts to keep synced.", 'ik-facebook');?></p>
			<?php 
			$needs_app_id = (get_option('ik_fb_app_id', '') == '');
			$needs_secret = (get_option('ik_fb_secret_key', '') == '');
			if ( $needs_app_id ):
			?>
			<p><?php _e("<strong>Important:</strong> You'll need to <a href=\"http://goldplugins.com/documentation/wp-social-pro-documentation/how-to-get-an-app-id-and-secret-key-from-facebook/\">create a free Facebook app</a> so that your plugin can access your feed. Don't worry - it only takes 2 minutes, and we've even got <a href=\"http://goldplugins.com/documentation/wp-social-pro-documentation/how-to-get-an-app-id-and-secret-key-from-facebook/\">a video explaining the process</a>.", 'ik-facebook');?></p>
			<?php endif; ?>
			<div id="ikfb_multi_feed_ids">
				<table class="form-table">
					<tbody>
						<tr valign="top">
							<th scope="row">
								<label>Facebook Page IDs</label>
							</th>
							<td>			
								<div class="bikeshed bikeshed_text">
									<div class="text_wrapper">
										<?php
										// Facebook Page ID
										$feed_ids = get_option('ik_fb_page_id');
										$feed_types_by_id = get_option('ik_fb_page_id_types');
										
										//if not currently an array, make it one
										//should only happen the first time they use this screen
										if(!is_array($feed_ids)){
											$feed_ids = array(
												$feed_ids
											);							
										}
										
										foreach($feed_ids as $key => $feed_id){
											$select_value = isset($feed_types_by_id[$feed_id]) ? $feed_types_by_id[$feed_id] : "default";
											
											?><div class="ikfb_feed_id_wrap"><input type="text" value="<?php echo $feed_id; ?>" id="ik_fb_page_id[]" class="ik_fb_page_id" name="ik_fb_page_id[]"> <select id="ik_fb_page_id_types[<?php echo $feed_id; ?>]" name="ik_fb_page_id_types[<?php echo $feed_id; ?>]" class="ik_fb_page_id_type"><option value="default" <?php if($select_value == "default"): ?>selected=SELECTED<?php endif; ?>>News Feed</option><option value="events" <?php if($select_value == "events"): ?>selected=SELECTED<?php endif; ?>>Event Feed</option></select> <a href="" class="remove_this_id">X</a><br></div>
											<?php
										}
									?>										
									</div>
									<p class="description">Your Facebook Username or Page ID you want to keep synced. This can be a username (like IlluminatiKarate) or a number (like 189090822).<br>Tip: You can find it by visiting your Facebook profile and copying the entire URL into the box above.</p>
								</div>
							</td>	
						</tr>
						<?php
							//load gmt offset
							$gmt_offset = get_option('gmt_offset');
							//load last update time
							$last_update_time = get_option('ik_fb_last_scheduled_update', 0);
							//adjust last update time for GMT offset (offset * minutes * seconds)
							$adjusted_last_update_time = $last_update_time + ($gmt_offset * 60 * 60);	
						?>
						<tr valign="top">
							<th scope="row">Force Feed Reload</th>
							<td>		
								<a class="button" id="ik_fb_force_feed_reload" href="?page=ikfb_configuration_options&run-cron-now=true#tab-facebook-feed-settings" class="button-primary" title="<?php _e('Refresh Feeds Now', 'ik-facebook') ?>"><?php _e('Refresh Feeds Now', 'ik-facebook') ?></a>&nbsp;&nbsp;<span class="description" style="display: inline-block; line-height: 28px;">Last update: <?php echo strftime( "%B %e, %G at %l:%M%P",$adjusted_last_update_time ); ?> (<?php echo strftime( "%B %e, %G at %l:%M%P",$last_update_time ); ?> server time)</span>
								<p class="description">If clicked, we will refresh all of your synced feeds now.  Note: this may take a minute to process.</p>
							</td>
						</tr>	
					</tbody>
					
				</table>
			</div>	
		<?php
		
	}
	
	function output_plugin_status(){			
		// output the Status Widget with the results of our diagnostics
		echo '<h2>';
		_e('Plugin Status', 'ik-facebook');
		echo'</h2>';
		
		if(isset($_GET['run_status_test'])){		
			//load profile id(s)
			$profile_ids = get_option('ik_fb_page_id');
			
			//if not currently an array, make it one
			//should only happen the first time they use this screen
			if(!is_array($profile_ids)){
				$profile_ids = array(
					$profile_ids
				);							
			}
			
			echo '<p>';
			_e('We\'re running some quick tests, to help you troubleshoot any issues you might be running into while setting up your Facebook feed.', 'ik-facebook');
			echo '</p>';
			
			$diagnostics_results = $this->run_diagnostics($profile_ids);
			$graph_api_warning = '';
			
			// if their App ID and Secret work, but we can't load their profile, 
			// let the user know that they need to make their profile public
			if ($diagnostics_results['loaded_demo_profile'] && !$diagnostics_results['loaded_own_profile']) {
				$graph_api_warning = '<p class="alert_important"><strong>Your Facebook page (' . $profile_id .') cannot be accessed via the Graph API</strong>Please verify that your Facebook page is Public, that it is not a Personal account, and that it has no Country, Age or other restrictions enabled.  If you have verified all of the above, then the Graph API could be rate-limiting you or it could be slow to respond - in either case, the issues typically clear up within 20 minutes!</p>';
			}
			$this->output_status_box($diagnostics_results, $profile_ids);
			?>
			<p>
				<a id="ik_fb_perform_status_test" onclick="window.location.reload()" class="button button-primary" title="<?php _e('Run Status Test Again', 'ik-facebook') ?>"><?php _e('Run Status Test Again', 'ik-facebook') ?></a>
				<span class="description">If clicked, we will run a test to verify that the plugin is functioning with the API.</span>
			</p>
			<?php
		} else {
			?>
			<p>
				<a id="ik_fb_perform_status_test" href="?page=ikfb_configuration_options&run_status_test=true#tab-facebook-api-status" class="button button-primary" title="<?php _e('Run Status Test', 'ik-facebook') ?>"><?php _e('Run Status Test', 'ik-facebook') ?></a>
				<span class="description">If clicked, we will run a test to verify that the plugin is functioning with the API.</span>
			</p>
			<?php
		}
	}
	
	function output_facebook_api_settings(){
		?>
			<h3><?php _e("Facebook API Settings", 'ik-facebook');?></h3>
			<p><?php _e("These options tell the plugin how to access your Facebook Page. They are required for the plugin to work.", 'ik-facebook');?></p>
			<?php 
			$needs_app_id = (get_option('ik_fb_app_id', '') == '');
			$needs_secret = (get_option('ik_fb_secret_key', '') == '');
			if ( $needs_app_id ):
			?>
			<p><?php _e("<strong>Important:</strong> You'll need to <a href=\"http://goldplugins.com/documentation/wp-social-pro-documentation/how-to-get-an-app-id-and-secret-key-from-facebook/\">create a free Facebook app</a> so that your plugin can access your feed. Don't worry - it only takes 2 minutes, and we've even got <a href=\"http://goldplugins.com/documentation/wp-social-pro-documentation/how-to-get-an-app-id-and-secret-key-from-facebook/\">a video explaining the process</a>.", 'ik-facebook');?></p>
			<?php endif; ?>
			<table class="form-table">
			
			<?php
				// Facebook App ID
				$desc = 'This is the App ID you acquired when you <a href="http://goldplugins.com/documentation/wp-social-pro-documentation/how-to-get-an-app-id-and-secret-key-from-facebook/" target="_blank" title="How To Get An App ID and Secret Key From Facebook">setup your Facebook app</a>.';
				$desc = $needs_app_id ? '<div class="app_id_callout">' . $desc . '</div>' : $desc;
				$this->shed->text( array('name' => 'ik_fb_app_id', 'label' =>'Facebook App ID', 'value' => get_option('ik_fb_app_id'), 'description' => $desc) );

				// Facebook Secret Key
				$desc = 'This is the App Secret you acquired when you <a href="http://goldplugins.com/documentation/wp-social-pro-documentation/how-to-get-an-app-id-and-secret-key-from-facebook/" target="_blank" title="How To Get An App ID and Secret Key From Facebook">setup your Facebook app</a>.';
				$desc = $needs_secret ? '<div class="app_id_callout">' . $desc . '</div>' : $desc;
				$this->shed->text( array('name' => 'ik_fb_secret_key', 'label' =>'Facebook Secret Key', 'value' => get_option('ik_fb_secret_key'), 'description' => $desc) );
				
				// Uploads Directory
				$desc = 'This is directory inside your WordPress uploads directory where Facebook photos will be stored.  Be sure this directory is writeable!';
				$this->shed->text( array('name' => 'wpsp_fb_upload_path', 'label' =>'Facebook Uploads Directory', 'value' => get_option('wpsp_fb_upload_path', '/wp-social/facebook'), 'description' => $desc) );
							
				$checked = (get_option('ik_fb_enable_cron') == '1');
				$this->shed->checkbox( array('name' => 'ik_fb_enable_cron', 'label' =>'Enable Scheduled Syncing', 'value' => 1, 'checked' => $checked, 'description' => 'If checked, new Facebook posts will be periodically loaded from your Facebook account and synced to your Feed.  The schedule will use the Profile set in the Facebook Synced Feeds panel.') ); 
				
				$ikfb_timeframe_choices = array(
					1	=> '1 hour',
					2	=> '2 hours', 
					3	=> '3 hours',
					4	=> '4 hours', 
					5	=> '5 hours', 
					6	=> '6 hours', 
					7	=> '7 hours', 
					8	=> '8 hours', 
					9	=> '9 hours', 
					10 	=> '10 hours',
					11 	=> '11 hours',
					12 	=> '12 hours',
					13 	=> '13 hours',
					14 	=> '14 hours',
					15 	=> '15 hours',
					16 	=> '16 hours',
					17 	=> '17 hours',
					18 	=> '18 hours',
					19 	=> '19 hours',
					20 	=> '20 hours',
					21 	=> '21 hours',
					22 	=> '22 hours',
					23 	=> '23 hours',
					24 	=> '24 hours',
				);
				
				$this->shed->select( array('name' => 'ik_fb_update_timeframe', 'options' => $ikfb_timeframe_choices, 'label' =>'Keep Updated For', 'value' => get_option('ik_fb_update_timeframe'), 'description' => 'Select the number of hours to keep items up-to-date for.  Decrease this if you are experiencing issues with load times.') );
				?>		
			</table>	
		<?php
	}
	
	/* Outputs the Registration Options */
	function output_registration_options(){
		?>
		<h3><?php _e('WP Social Pro Registration', 'ik-facebook'); ?></h3>			
		<?php if($this->is_pro): ?>
		<p class="plugin_is_registered">&#x2713; WP Social Pro is registered and activated. Thank you!</p>
		<?php else: ?>
		<p class="plugin_is_not_registered">&#x2718; Pro features not available. Upgrade to WP Social Pro to unlock all features. <a class="button" href="http://goldplugins.com/our-plugins/wp-social-pro/upgrade-to-wp-social-pro/?utm_source=api_key_reminder" target="_blank">Click here to upgrade now!</a></p>
		<p>Enter your Email Address and API Key here to activate additional features such as Custom HTML, Unbranded Admin Screens, Comments, Avatars, and more!</p>
		<p><a class="button" href="http://goldplugins.com/our-plugins/wp-social-pro/upgrade-to-wp-social-pro/?utm_source=plugin&utm_campaign=api_key_reminder_2">Get An API Key</a></p>
		<?php endif; ?>

		<?php if(!wpsp_is_valid_multisite_key()): ?>
		<table class="form-table">
			<?php
				// Registration Email
				$this->shed->text( array('name' => 'wp_social_pro_registered_email', 'label' =>'Email Address', 'value' => get_option('wp_social_pro_registered_email'), 'description' => 'This is the e-mail address that you used when you registered the plugin.') );

				// API Key
				$this->shed->text( array('name' => 'wp_social_pro_registered_key', 'label' =>'API Key', 'value' => get_option('wp_social_pro_registered_key'), 'description' => 'This is the API Key that you received after registering the plugin.') );
			?>
		</table>	
		<?php endif; ?>
		<?php
	}
	
	/*
	 * Outputs the Style Options page
	 */
	function style_options_page()
	{
		//add upgrade button if free version
		$extra_buttons = array();
		if(!$this->is_pro){
			$extra_buttons = array(
				array(
					'class' => 'btn-purple',
					'label' => 'Upgrade To Pro',
					'url' => 'https://goldplugins.com/our-plugins/wp-social-pro/upgrade-to-wp-social-pro/'
				)
			);
		}
		
		//instantiate tabs object for output basic settings page tabs
		$tabs = new GP_Sajak( array(
			'header_label' => 'Style Options',
			'settings_field_key' => 'ik-fb-style-settings-group', // can be an array	
			'show_save_button' => true, // hide save buttons for all panels   		
			'extra_buttons_header' => $extra_buttons, // extra header buttons
			'extra_buttons_footer' => $extra_buttons // extra footer buttons
		) );
		
		$this->start_settings_page();
	
		$tabs->add_tab(
			'style-options', // section id, used in url fragment
			'Style Options', // section label
			array($this, 'output_style_options'), // display callback
			array(
				'class' => 'extra_li_class', // extra classes to add sidebar menu <li> and tab wrapper <div>
				'icon' => 'life-buoy' // icons here: http://fontawesome.io/icons/
			)
		);
	
		$tabs->add_tab(
			'image-options', // section id, used in url fragment
			'Feed Images', // section label
			array($this, 'output_image_options'), // display callback
			array(
				'class' => 'extra_li_class', // extra classes to add sidebar menu <li> and tab wrapper <div>
				'icon' => 'life-buoy' // icons here: http://fontawesome.io/icons/
			)
		);
	
		$tabs->add_tab(
			'feed-window-options', // section id, used in url fragment
			'Feed Window Color and Dimensions', // section label
			array($this, 'output_feed_window_options'), // display callback
			array(
				'class' => 'extra_li_class', // extra classes to add sidebar menu <li> and tab wrapper <div>
				'icon' => 'life-buoy' // icons here: http://fontawesome.io/icons/
			)
		);
	
		$tabs->add_tab(
			'font-style-options', // section id, used in url fragment
			'Font Styling', // section label
			array($this, 'output_font_options'), // display callback
			array(
				'class' => 'extra_li_class', // extra classes to add sidebar menu <li> and tab wrapper <div>
				'icon' => 'life-buoy' // icons here: http://fontawesome.io/icons/
			)
		);
		
		$tabs->display();
		
		$this->end_settings_page();		
	}
	
	function output_font_options(){
			?>
			<h3><?php _e('Font Styling', 'ik-facebook');?></h3>
			<p class="description">Choose a font size, family, style, and color for each post item's parts.</p>
			<p class="section_intro"><strong>Tip:</strong> try out the <a href="http://www.google.com/fonts/" target="_blank">Google Web Fonts</a> for more exotic font options!</p>
			<table class="form-table">			
				<?php
					$values = array(
								'font_size' => get_option('ik_fb_description_font_size'),
								'font_family' => get_option('ik_fb_description_font_family'),
								'font_style' => get_option('ik_fb_description_font_style'),
								'font_color' => get_option('ik_fb_description_font_color'),
							);
					$this->shed->typography( array('name' => 'ik_fb_description_*', 'label' =>'Description Font', 'description' => '', 'google_fonts' => true, 'default_color' => '#878787', 'values' => $values) );
				?>

				<?php
					$values = array(
								'font_size' => get_option('ik_fb_font_size'),
								'font_family' => get_option('ik_fb_font_family'),
								'font_style' => get_option('ik_fb_font_style'),
								'font_color' => get_option('ik_fb_font_color'),
							);
					$this->shed->typography( array('name' => 'ik_fb_*', 'label' =>'Message Font', 'description' => '', 'google_fonts' => true, 'default_color' => '#878787', 'values' => $values) );
				?>

				<?php
					$values = array(
								'font_size' => get_option('ik_fb_story_font_size'),
								'font_family' => get_option('ik_fb_story_font_family'),
								'font_style' => get_option('ik_fb_story_font_style'),
								'font_color' => get_option('ik_fb_story_font_color'),
							);
					$this->shed->typography( array('name' => 'ik_fb_story_*', 'label' =>'Story Font', 'description' => '', 'google_fonts' => true, 'default_color' => '#878787', 'values' => $values) );
				?>

				<?php
					$values = array(
								'font_size' => get_option('ik_fb_link_font_size'),
								'font_family' => get_option('ik_fb_link_font_family'),
								'font_style' => get_option('ik_fb_link_font_style'),
								'font_color' => get_option('ik_fb_link_font_color'),
							);
					$this->shed->typography( array('name' => 'ik_fb_link_*', 'label' =>'Link Font', 'description' => '', 'google_fonts' => true, 'default_color' => '#878787', 'values' => $values) );
				?>
			
				<?php
					$values = array(
								'font_size' => get_option('ik_fb_posted_by_font_size'),
								'font_family' => get_option('ik_fb_posted_by_font_family'),
								'font_style' => get_option('ik_fb_posted_by_font_style'),
								'font_color' => get_option('ik_fb_posted_by_font_color'),
							);
					$this->shed->typography( array('name' => 'ik_fb_posted_by_*', 'label' =>'Posted By Font', 'description' => '', 'google_fonts' => true, 'default_color' => '#878787', 'values' => $values) );
				?>

				<?php
					$values = array(
								'font_size' => get_option('ik_fb_date_font_size'),
								'font_family' => get_option('ik_fb_date_font_family'),
								'font_style' => get_option('ik_fb_date_font_style'),
								'font_color' => get_option('ik_fb_date_font_color'),
							);
					$this->shed->typography( array('name' => 'ik_fb_date_*', 'label' =>'Date Font', 'description' => '', 'google_fonts' => true, 'default_color' => '#878787', 'values' => $values) );
				?>
			
			</table>
		<?php	
	}
	
	function output_feed_window_options(){
			?>
			<h3><?php _e('Feed Window Color and Dimensions', 'ik-facebook');?></h3>
			<table class="form-table">				
				<?php $this->shed->color( array('name' => 'ik_fb_header_bg_color', 'label' =>'Feed Header Background Color', 'value' => get_option('ik_fb_header_bg_color'), 'description' => 'Input your hex color code, by clicking and using the Colorpicker or typing it in.  Erase the contents of this field to use the default color.') ); ?>				
				<?php $this->shed->color( array('name' => 'ik_fb_window_bg_color', 'label' =>'Feed Window Background Color', 'value' => get_option('ik_fb_window_bg_color'), 'description' => 'Input your hex color code, by clicking and using the Colorpicker or typing it in.  Erase the contents of this field to use the default color.') ); ?>
				
				<?php
					$radio_options = array(
						'' => 'Default',
						'auto' => 'Auto',
						'100%' => '100%',
						'OTHER' => sprintf('Other Pixel Value {{text|other_ik_fb_feed_window_height|%s}}', get_option('other_ik_fb_feed_window_height')),
					);				
					$this->shed->radio( array('name' => 'ik_fb_feed_window_height', 'value' => get_option('ik_fb_feed_window_height'), 'options' => $radio_options, 'label' =>'Feed Window Height', 'description' => "Choose 'Auto', '100%', or 'Other' and type in an integer number of pixels. The effect of this setting may vary, based upon your theme's CSS. This option does not apply to the sidebar widget.") );
				?>
				
				<?php
					$radio_options = array(
						'' => 'Default',
						'auto' => 'Auto',
						'100%' => '100%',
						'OTHER' => sprintf('Other Pixel Value {{text|other_ik_fb_feed_window_width|%s}}', get_option('other_ik_fb_feed_window_width')),
					);				
					$this->shed->radio( array('name' => 'ik_fb_feed_window_width', 'value' => get_option('ik_fb_feed_window_width'), 'options' => $radio_options, 'label' =>'Feed Window Width', 'description' => "Choose 'Auto', '100%', or 'Other' and type in an integer number of pixels. The effect of this setting may vary, based upon your theme's CSS. This option does not apply to the sidebar widget.") );
				?>
				
				<?php
					$radio_options = array(
						'' => 'Default',
						'auto' => 'Auto',
						'100%' => '100%',
						'OTHER' => sprintf('Other Pixel Value {{text|other_ik_fb_sidebar_feed_window_height|%s}}', get_option('other_ik_fb_sidebar_feed_window_height')),
					);				
					$this->shed->radio( array('name' => 'ik_fb_sidebar_feed_window_height', 'value' => get_option('ik_fb_sidebar_feed_window_height'), 'options' => $radio_options, 'label' =>'Sidebar Feed Window Height', 'description' => "Choose 'Auto', '100%', or 'Other' and type in an integer number of pixels. The effect of this setting may vary, based upon your theme's CSS. This option does not apply to the sidebar widget.") );
				?>
				
				<?php
					$radio_options = array(
						'' => 'Default',
						'auto' => 'Auto',
						'100%' => '100%',
						'OTHER' => sprintf('Other Pixel Value {{text|other_ik_fb_sidebar_feed_window_width|%s}}', get_option('other_ik_fb_sidebar_feed_window_width')),
					);				
					$this->shed->radio( array('name' => 'ik_fb_sidebar_feed_window_width', 'value' => get_option('ik_fb_sidebar_feed_window_width'), 'options' => $radio_options, 'label' =>'Sidebar Feed Window Width', 'description' => "Choose 'Auto', '100%', or 'Other' and type in an integer number of pixels. The effect of this setting may vary, based upon your theme's CSS. This option does not apply to the sidebar widget.") );
				?>
			</table>
		<?php
	}
	
	function output_image_options(){
		?>
			<h3><?php _e('Feed Images', 'ik-facebook');?></h3>
			<table class="form-table">				
				<?php
					$checked = (get_option('ik_fb_fix_feed_image_width') == '1');
					$this->shed->checkbox( array('name' => 'ik_fb_fix_feed_image_width', 'label' =>'Fix Feed Image Width', 'value' => 1, 'checked' => $checked, 'description' => 'If checked, images inside the feed will all be displayed at the width set below. If both this and \'Fix Feed Image Height\' are unchecked, feed will display image thumbnails.', 'inline_label' => 'Display images at the width selected below') ); 
				?>
				
				<?php
					$radio_options = array(
						'100%' => '100%',
						'OTHER' => sprintf('Other Pixel Value {{text|other_ik_fb_feed_image_width|%s}}', get_option('other_ik_fb_feed_image_width')),
					);				
					$this->shed->radio( array('name' => 'ik_fb_feed_image_width', 'value' => get_option('ik_fb_feed_image_width'), 'options' => $radio_options, 'label' =>'Feed Image Width', 'description' => "If 'Fix Feed Image Width' is checked, the images will be set to this width.  Choose '100%' or 'Other' and type in an integer number of pixels.  The effect of this setting may vary, based upon your theme's CSS.") );
				?>

				<?php
					$checked = (get_option('ik_fb_fix_feed_image_height') == '1');
					$this->shed->checkbox( array('name' => 'ik_fb_fix_feed_image_height', 'label' =>'Fix Feed Image Height', 'value' => 1, 'checked' => $checked, 'description' => 'If checked, images inside the feed will all be displayed at the height set below.  If both this and \'Fix Feed Image Width\' are unchecked, feed will display image thumbnails.', 'inline_label' => 'Display images at the height selected below') ); 
				?>
				<?php
					$radio_options = array(
						'100%' => '100%',
						'OTHER' => sprintf('Other Pixel Value {{text|other_ik_fb_feed_image_height|%s}}', get_option('other_ik_fb_feed_image_height')),
					);				
					$this->shed->radio( array('name' => 'ik_fb_feed_image_height', 'value' => get_option('ik_fb_feed_image_height'), 'options' => $radio_options, 'label' =>'Feed Image Height', 'description' => "If 'Fix Feed Image Height' is checked, the images will be set to this height.  Choose '100%' or 'Other' and type in an integer number of pixels.  The effect of this setting may vary, based upon your theme's CSS.") );
				?>
			</table>
		<?php
	}
	
	function output_style_options(){
		$ikfb_themes = array(
							'no_style' => 'No Theme',
							'style' => 'Default Theme',
							'dark_style' => 'Dark Theme',
							'light_style' => 'Light Theme',
							'blue_style' => 'Blue Theme',
						);
						
		if($this->is_pro){
			$ikfb_themes['cobalt_style'] = 'Cobalt Theme';
			$ikfb_themes['green_gray_style'] = 'Green Gray Theme';
			$ikfb_themes['halloween_style'] = 'Halloween Theme';
			$ikfb_themes['indigo_style'] = 'Indigo Theme';
			$ikfb_themes['orange_style'] = 'Orange Theme';			
		}
		?>
			
			<h3><?php _e('Style Options', 'ik-facebook');?></h3>
			<p><?php _e('These options control the style of the Facebook Feed displayed on your website. You can change fonts, colors, image sizes, and even add your own custom CSS.', 'ik-facebook');?></p>
		
			<table class="form-table">
			<?php 
				$desc = 'Select which theme you want to use.  If \'No Theme\' is selected, only your own theme\'s CSS, and any Custom CSS you\'ve added, will be used.  The settings below will override the defaults set in your selected theme.';
				if (!$this->is_pro) {
					$desc .= '<br /><br /><a href="http://goldplugins.com/our-plugins/wp-social-pro/upgrade-to-wp-social-pro/?utm_source=plugin&utm_campaign=unlock_more_themes">Tip: Upgrade to WP Social Pro to unlock more themes!</a>';
				}
				$this->shed->select( array('name' => 'ik_fb_feed_theme', 'options' => $ikfb_themes, 'label' =>'Feed Theme', 'value' => get_option('ik_fb_feed_theme'), 'description' => $desc) );
			?>				
				<?php $this->shed->textarea( array('name' => 'ik_fb_custom_css', 'label' =>'Custom CSS', 'value' => get_option('ik_fb_custom_css'), 'description' => 'Input any Custom CSS you want to use here.  You can also include a file in your theme\'s folder called \'ik_fb_custom_style.css\' - any styles in that file will be loaded with the plugin.  The plugin will work without you placing anything here - this is useful in case you need to edit any styles for it to work with your theme, though.') ); ?>
			</table>
		<?php
	}
	
	/*
	 * Outputs the Display Options page
	 */
	function display_options_page()
	{
		//add upgrade button if free version
		$extra_buttons = array();
		if(!$this->is_pro){
			$extra_buttons = array(
				array(
					'class' => 'btn-purple',
					'label' => 'Upgrade To Pro',
					'url' => 'https://goldplugins.com/our-plugins/wp-social-pro/upgrade-to-wp-social-pro/'
				)
			);
		}
		
		//instantiate tabs object for output basic settings page tabs
		$tabs = new GP_Sajak( array(
			'header_label' => 'Display Options',
			'settings_field_key' => 'ik-fb-display-settings-group', // can be an array	
			'show_save_button' => true, // hide save buttons for all panels   		
			'extra_buttons_header' => $extra_buttons, // extra header buttons
			'extra_buttons_footer' => $extra_buttons // extra footer buttons
		) );
		
		$this->start_settings_page();
	
		$tabs->add_tab(
			'display-fields', // section id, used in url fragment
			'Fields to Display', // section label
			array($this, 'output_display_field_options'), // display callback
			array(
				'class' => 'extra_li_class', // extra classes to add sidebar menu <li> and tab wrapper <div>
				'icon' => 'life-buoy' // icons here: http://fontawesome.io/icons/
			)
		);
	
		$tabs->add_tab(
			'advanced-display-options', // section id, used in url fragment
			'Advanced Display Options', // section label
			array($this, 'output_advanced_display_options'), // display callback
			array(
				'class' => 'extra_li_class', // extra classes to add sidebar menu <li> and tab wrapper <div>
				'icon' => 'life-buoy' // icons here: http://fontawesome.io/icons/
			)
		);
		
		$tabs->display();
		
		$this->end_settings_page();		
	}
	
	function output_advanced_display_options(){
		?>
			<h3><?php _e('Advanced Display Options', 'ik-facebook');?></h3>
			<table class="form-table">
				<?php
				// Limit the total number of posts in the feed (number)
				$this->shed->text( array('name' => 'ik_fb_feed_limit', 'label' =>'Number of Posts', 'value' => get_option('ik_fb_feed_limit', 25), 'description' => 'The number of posts to show in the Standard Facebook Feed.  The default number of posts displayed is 25 - set higher numbers to display more.  If set, the feed will be limited to this number of posts.  This can be overridden via the shortcode.') );
			
				//Limit the number of photos in the feed (number)
				$this->shed->text( array('name' => 'ik_fb_photo_feed_limit', 'label' =>'Number of Photo Album Photos', 'value' => get_option('ik_fb_photo_feed_limit', 25), 'description' => 'The number of photos to show in a Photo Album Feed.  The default number of photos displayed is 25 - set higher numbers to display more.  If set, the photo feed will be limited to this number of photos.  This can be overridden via the shortcode.') );

				// Feed Item Message Word limit (number)
				$this->shed->text( array('name' => 'ik_fb_character_limit', 'label' =>'Post Message Word Limit', 'value' => get_option('ik_fb_character_limit', 55), 'description' => 'The Message is the primary text block of a post.  If set, the Message will be limited to this number of words.  The default word limit is 55.  If the Message is shortened, a Read More link will be displayed that takes the user to the full length post.') );

				// Feed Item Description Word Limit (number)
				$this->shed->text( array('name' => 'ik_fb_description_character_limit', 'label' =>'Photo Description Word Limit', 'value' => get_option('ik_fb_description_character_limit', 55), 'description' => 'The Description is the block of text that appears below Photos in the Standard Feed.  If set, the Description will be limited to this number of words.  The default word limit is 5. If a Description is shortened, a Read More link will be displayed that takes the user to the full length post.') );
			
				// Link Photo To Feed Item (checkbox)
				$checked = (get_option('ik_fb_link_photo_to_feed_item') == '1');
				$this->shed->checkbox( array('name' => 'ik_fb_link_photo_to_feed_item', 'label' =>'Link Photo to Facebook Post', 'value' => 1, 'checked' => $checked, 'description' => 'If checked, the Photos in the Feed will link to the same location that the Read More text does.  If unchecked, the Photos in the Feed will link to the Full Sized version of themselves.', 'inline_label' => 'Link Photos to \'Read More\'') ); 

				// Link Read More to Facebook or Onsite
				$radio_options = array(
					'read-more-onsite' => 'Onsite',
					'read-more-onfacebook' => 'Facebook',
				);				
				$this->shed->radio( array('name' => 'ik_fb_onsite_or_facebook', 'value' => get_option('ik_fb_onsite_or_facebook','read-more-onfacebook'), 'options' => $radio_options, 'label' =>'Link to Facebook or Onsite', 'description' => "Depending on selection, we will either send users to the post on Facebook or the post on your website.  Be sure you check Show Onsite Facebook posts if you're linking to Onsite posts."));
								
				//Hide CPTs from being viewed onsite
				$checked = (get_option('ik_fb_show_posts_on_site', false) == true);
				$this->shed->checkbox( array('name' => 'ik_fb_show_posts_on_site', 'label' =>'Show Onsite Facebook Posts', 'value' => 1, 'checked' => $checked, 'description' => 'If checked, the Facebook Posts stored onsite will be available for public view.  If you have your Read More text linked to Onsite posts, be sure this option is set accordingly.', 'inline_label' => 'Show Onsite Facebook Posts') ); 				
				
				// Date Format (text)
				$this->shed->text( array('name' => 'ik_fb_date_format', 'label' =>'Date Format', 'value' => get_option('ik_fb_date_format', '%B %d'), 'description' => 'The format string to be used for the Post Date.  This follows the standard used for <a href="http://php.net/manual/en/function.strftime.php">PHP strfrtime()</a>.') );

				// Disable "Human Timing" (checkbox)
				$checked = (get_option('ik_fb_use_human_timing') == '1');
				$this->shed->checkbox( array('name' => 'ik_fb_use_human_timing', 'label' =>'Disable "Human Timing" For Timestamps', 'value' => 1, 'checked' => $checked, 'description' => 'Check this box to always show normal timestamps, such as "August 9th", instead of "2 hours ago"', 'inline_label' => 'Disable Human Timing for Timestamps') );					
				
				//advance pro options
				if(!$this->is_pro): ?></table><div class="disabled"><table class="form-table"><?php endif;
				
				echo $this->pro_upgrade_link();	
				
				// Only Show Page Owner's Posts (checkbox)
				$checked = (get_option('ik_fb_only_show_page_owner') == '1');
				$this->shed->checkbox( array('name' => 'ik_fb_only_show_page_owner', 'label' =>'Only Show Page Owner\'s Posts', 'value' => 1, 'checked' => $checked, 'description' => 'If checked, the only posts shown will be those made by the Page Owner.  This is a good way to prevent random users from posting things to your FB Wall that will then show up on your website.', 'inline_label' => 'Only show posts made by the page owner', 'disabled' => !$this->is_pro) );

				// Hide Branding (checkbox)
				$checked = (get_option('ik_fb_unbranded') == '1');
				$this->shed->checkbox( array('name' => 'ik_fb_unbranded', 'label' =>'Hide Branding', 'value' => 1, 'checked' => $checked, 'description' => 'If checked, our branding will be hidden from the Dashboard.', 'inline_label' => 'Hide WP Social Branding', 'disabled' => !$this->is_pro) );
				
				if(!$this->is_pro): ?></table></div><?php endif; ?>	
			</table><!-- end original table, non pro options -->				
		<?php	
	}
	
	function output_display_field_options(){
			?>
			<h3><?php _e('Fields to Display', 'ik-facebook');?></h3>
			<p><?php _e('These options control the type and amount of content that is displayed in your Facebook Feed.', 'ik-facebook');?></p>
						
			<table class="form-table">
			<?php	

				// Show Page Title (checkbox)
				$checked = (get_option('ik_fb_show_page_title') == '1');
				$this->shed->checkbox( array('name' => 'ik_fb_show_page_title', 'label' =>'Show Page Title', 'value' => 1, 'checked' => $checked, 'description' => 'If checked, the Title of the feed will be shown.', 'inline_label' => 'Show my Page Title above my feed') );			

				// Show Profile Photo (checkbox)
				$checked = (get_option('ik_fb_show_profile_picture') == '1');
				$this->shed->checkbox( array('name' => 'ik_fb_show_profile_picture', 'label' =>'Show Profile Picture', 'value' => 1, 'checked' => $checked, 'description' => 'If checked, the Profile Picture will be shown next to the Title of the feed.', 'inline_label' => 'Show my Profile Picture above my feed ') );
				
				// Show the Like Button (checkbox)
				$checked = (get_option('ik_fb_show_like_button') == '1');
				$this->shed->checkbox( array('name' => 'ik_fb_show_like_button', 'label' =>'Show Like Button', 'value' => 1, 'checked' => $checked, 'description' => 'If checked, the Like Button and number of people who like your page will be displayed above the Feed.', 'inline_label' => 'Show the Like Button above my feed') ); 
				
				// Show Images in Feed (checkbox)
				// RWG: this was originally Hide Feed Images, so things are opposite
				//		ie, before checking this option would hide images.  now checking this option will display images (codepaths throughout plugin updated to match)
				$checked = (get_option('ik_fb_hide_feed_images') == '1');
				$this->shed->checkbox( array('name' => 'ik_fb_hide_feed_images', 'label' =>'Show Feed Images', 'value' => 1, 'checked' => $checked, 'description' => 'If checked, images will be shown in your feed.', 'inline_label' => 'Show Images In My Feed') ); 

				// Show Videos in Feed (checkbox)
				$checked = (get_option('ik_fb_show_feed_videos') == '1');
				$this->shed->checkbox( array('name' => 'ik_fb_show_feed_videos', 'label' =>'Show Feed Videos', 'value' => 1, 'checked' => $checked, 'description' => 'If checked, videos will be shown in your feed.', 'inline_label' => 'Show Videos In My Feed') ); 
				
				// Show 'Stories' text (checkbox)
				$checked = (get_option('ik_fb_show_stories',1) == '1');
				$this->shed->checkbox( array('name' => 'ik_fb_show_stories', 'label' =>'Show Stories', 'value' => 1, 'checked' => $checked, 'description' => 'If checked, the Story text will be displayed in the feed.', 'inline_label' => 'Show \'Story\' for each item') );
				
				// Show 'Posted By' text (checkbox)
				$checked = (get_option('ik_fb_show_posted_by') == '1');
				$this->shed->checkbox( array('name' => 'ik_fb_show_posted_by', 'label' =>'Show Posted By', 'value' => 1, 'checked' => $checked, 'description' => 'If checked, the text Posted By PosterName will be displayed in the feed.', 'inline_label' => 'Show \'Posted by PosterName\' for each item') );

				// Show Posted Date (checkbox)
				$checked = (get_option('ik_fb_show_date') == '1');
				$this->shed->checkbox( array('name' => 'ik_fb_show_date', 'label' =>'Show Posted Date', 'value' => 1, 'checked' => $checked, 'description' => 'If checked, the date of the post will be displayed in the Feed.', 'inline_label' => 'Show the date posted for each item') );
				
				//pro display options
				if(!$this->is_pro): ?></table><div class="disabled"><table class="form-table"><?php endif;
				
				echo $this->pro_upgrade_link();
				
				// Show Avatars
				$checked = (get_option('ik_fb_show_avatars') == '1');
				$this->shed->checkbox( array('name' => 'ik_fb_show_avatars', 'label' => 'Show Avatars', 'value' => 1, 'checked' => $checked, 'description' => 'If checked, user avatars will be shown in the feed.', 'inline_label' => 'Show user avatars in my feed', 'disabled' => !$this->is_pro) );
				
				// Show Comment Count (checkbox)
				$checked = (get_option('ik_fb_show_reply_counts') == '1');
				$this->shed->checkbox( array('name' => 'ik_fb_show_reply_counts', 'label' => 'Show Comment Counts', 'value' => 1, 'checked' => $checked, 'description' => 'If checked, user comment counts will be shown in the feed, with a link to the Facebook page.', 'inline_label' => 'Show Comment Counts', 'disabled' => !$this->is_pro) );

				// Show Comments (checkbox)
				$checked = (get_option('ik_fb_show_replies') == '1');
				$this->shed->checkbox( array('name' => 'ik_fb_show_replies', 'label' => 'Show Comments', 'value' => 1, 'checked' => $checked, 'description' => 'If checked, user comments will be shown in the feed.  If Show Avatars is also checked, user avatars will be shown in the replies.  If Show Date is is also checked, the comment date will be shown in the replies. If Show Likes is also checked, the number of likes for each comment will be displayed.', 'inline_label' => 'Show Comments', 'disabled' => !$this->is_pro) );

				// Show Likes (checkbox)
				$checked = (get_option('ik_fb_show_likes') == '1');
				$this->shed->checkbox( array('name' => 'ik_fb_show_likes', 'label' => 'Show Likes', 'value' => 1, 'checked' => $checked, 'description' => 'If checked, user like counts will be shown in the feed, with a link to the Facebook page.', 'inline_label' => 'Show Likes', 'disabled' => !$this->is_pro) );
							
				if(!$this->is_pro): ?></table></div><?php endif; ?>
			</table>
		<?php
	}
	
	/*
	 * Outputs the Event Options page
	 */
	function event_options_page()
	{		
		//add upgrade button if free version
		$extra_buttons = array();
		if(!$this->is_pro){
			$extra_buttons = array(
				array(
					'class' => 'btn-purple',
					'label' => 'Upgrade To Pro',
					'url' => 'https://goldplugins.com/our-plugins/wp-social-pro/upgrade-to-wp-social-pro/'
				)
			);
		}
		
		//instantiate tabs object for output basic settings page tabs
		$tabs = new GP_Sajak( array(
			'header_label' => 'Event Options',
			'settings_field_key' => 'ik-fb-pro-event-settings-group', // can be an array	
			'show_save_button' => true, // hide save buttons for all panels   		
			'extra_buttons_header' => $extra_buttons, // extra header buttons
			'extra_buttons_footer' => $extra_buttons // extra footer buttons
		) );
		
		$this->start_settings_page();
	
		$tabs->add_tab(
			'event-display-options', // section id, used in url fragment
			'Event Display Options', // section label
			array($this, 'output_event_display_options'), // display callback
			array(
				'class' => 'extra_li_class', // extra classes to add sidebar menu <li> and tab wrapper <div>
				'icon' => 'life-buoy' // icons here: http://fontawesome.io/icons/
			)
		);
	
		$tabs->add_tab(
			'event-order-options', // section id, used in url fragment
			'Event Order Options' . $this->pro_string, // section label
			array($this, 'output_event_order_options'), // display callback
			array(
				'class' => 'extra_li_class', // extra classes to add sidebar menu <li> and tab wrapper <div>
				'icon' => 'life-buoy' // icons here: http://fontawesome.io/icons/
			)
		);
	
		$tabs->add_tab(
			'event-date-format-options', // section id, used in url fragment
			'Event Date Format Options' . $this->pro_string, // section label
			array($this, 'output_event_date_format_options'), // display callback
			array(
				'class' => 'extra_li_class', // extra classes to add sidebar menu <li> and tab wrapper <div>
				'icon' => 'life-buoy' // icons here: http://fontawesome.io/icons/
			)
		);
	
		$tabs->add_tab(
			'event-date-range-options', // section id, used in url fragment
			'Event Date Range Options' . $this->pro_string, // section label
			array($this, 'output_event_date_range_options'), // display callback
			array(
				'class' => 'extra_li_class', // extra classes to add sidebar menu <li> and tab wrapper <div>
				'icon' => 'life-buoy' // icons here: http://fontawesome.io/icons/
			)
		);
	
		$tabs->add_tab(
			'event-image-size-options', // section id, used in url fragment
			'Event Image Size Options' . $this->pro_string, // section label
			array($this, 'output_event_image_size_options'), // display callback
			array(
				'class' => 'extra_li_class', // extra classes to add sidebar menu <li> and tab wrapper <div>
				'icon' => 'life-buoy' // icons here: http://fontawesome.io/icons/
			)
		);
		
		$tabs->display();
		
		$this->end_settings_page();		
	}			
	
	function output_event_image_size_options(){
		?>
		<h3>Event Image Size:</h3>	
		<?php echo $this->pro_upgrade_link(); ?>
		<table class="form-table">	
		<?php
			$ikfb_event_image_sizes = array(
				'normal' => 'Normal',
				'small' => 'Small',
				'large' => 'Large',
				'square' => 'Square'
			);			
			$this->shed->select( array('name' => 'ik_fb_event_image_size', 'options' => $ikfb_event_image_sizes, 'label' =>'Event Feed Image Size', 'value' => get_option('ik_fb_event_image_size'), 'description' => 'Select which size of image to display with Events in your Feed.', 'disabled' => !$this->is_pro) );
		?>
		</table>	
		<?php
	}
	
	function output_event_date_range_options(){
		?>
		<h3>Event Date Range Options<h3>
		<?php echo $this->pro_upgrade_link(); ?>
		<p>This options control which events will shop up in your feed.</p>
		<fieldset>	
			<legend>Floating or Manual Range:</legend>	
			<table class="form-table">	
			<?php
				// Use Event Date Range Window option or Use Event Start Date and Event End Date options
				// Depending on selection, we will either calculate the Date Range using the Window selected, or will use the manually selected dates in the Event Start Date and Event End Date options
				$radio_options = array(
					'event-date-range-window' => 'Floating Date Range',
					'event-start-end-date-options' => 'Manual Event Start and End Date Options',
				);				
				$this->shed->radio( array('name' => 'ik_fb_range_or_manual', 'value' => get_option('ik_fb_range_or_manual','event-start-end-date-options'), 'options' => $radio_options, 'label' =>'Use Floating or Manual Selection', 'description' => "Depending on selection, we will either calculate the Date Range using the time frame selected, or will will use the manually selected dates in the Event Start Date and Event End Date options.", 'disabled' => !$this->is_pro) );
			?>
			</table>
		</fieldset>		
		<fieldset>
			<legend>Floating Date Range:</legend>	
			<table class="form-table">	
			<?php
				// Floating Event Range - Days Into Future
				$this->shed->text( array('name' => 'ik_fb_event_range_future_days', 'label' =>'Days Into Future', 'value' => get_option('ik_fb_event_range_future_days',365), 'description' => 'How many days into the future to look for Upcoming Events. Defaults to 365 days.', 'disabled' => !$this->is_pro) );
				
				// Floating Event Range - Days Into Past
				$this->shed->text( array('name' => 'ik_fb_event_range_past_days', 'label' =>'Days Into Past', 'value' => get_option('ik_fb_event_range_past_days',14), 'description' => 'How many days into the past to show Old Events. Defaults to 14 days.', 'disabled' => !$this->is_pro) );
			?>
			</table>
		</fieldset>
		<fieldset>
			<legend>Manual Date Range:</legend>	
			<table class="form-table">	
			<?php
				// Event Range - Start Date (text / datepicker)
				$this->shed->text( array('name' => 'ik_fb_event_range_start_date', 'label' =>'Event Range Start Date', 'value' => get_option('ik_fb_event_range_start_date'), 'description' => 'The Start Date of Events you want shown.  Events that start before this date will not be shown in the feed - even if their End Date is after this date.', 'class' => 'datepicker', 'disabled' => !$this->is_pro) );
			
				// Event Range - End Date (text / datepicker)
				$this->shed->text( array('name' => 'ik_fb_event_range_end_date', 'label' =>'Event Range End Date', 'value' => get_option('ik_fb_event_range_end_date'), 'description' => 'The End Date of Events you want shown.  Events that end after this date will not be shown in the feed - even if their Start Date is before this date.', 'class' => 'datepicker', 'disabled' => !$this->is_pro) );
			
			?>
			</table>
		</fieldset>
		<?php
	}
		
	function output_event_date_format_options(){
		?>		
		<h3>Event Date Format:</h3>	
		<?php echo $this->pro_upgrade_link(); ?>
		<table class="form-table">	
		<?php
			// Start Date Format (text)
			$value = get_option('ik_fb_start_date_format', 'l, F jS, Y h:i:s a');
			if(empty($value)){
				$value = 'l, F jS, Y h:i:s a';
			}
			
			$this->shed->text( array('name' => 'ik_fb_start_date_format', 'label' =>'Start Date Format', 'value' => $value, 'description' => 'The format string to be used for the Event Start Date.  This follows the standard used for PHP date.  Warning: this is an advanced feature - do not change this value if you do not know what you are doing! The default setting is l, F jS, Y h:i:s a', 'disabled' => !$this->is_pro) );

			// End Date Format (text)
			$value = get_option('ik_fb_end_date_format', 'l, F jS, Y h:i:s a');
			if(empty($value)){
				$value = 'l, F jS, Y h:i:s a';
			}
			
			$this->shed->text( array('name' => 'ik_fb_end_date_format', 'label' =>'End Date Format', 'value' => $value, 'description' => 'The format string to be used for the Event End Date.  This follows the standard used for PHP date.  Warning: this is an advanced feature - do not change this value if you do not know what you are doing! The default setting is l, F jS, Y h:i:s a', 'disabled' => !$this->is_pro) );
		?>			
		</table>
		<?php
	}
	
	function output_event_order_options(){
		?>
		<h3>Event Feed Order:</h3>
		<?php echo $this->pro_upgrade_link(); ?>
		<table class="form-table">	
		<?php			
			// Reverse Event Feed Order (checkbox)
			$checked = (get_option('ik_fb_reverse_events') == '1');
			$this->shed->checkbox( array('name' => 'ik_fb_reverse_events', 'label' =>'Reverse Event Feed Order', 'value' => 1, 'checked' => $checked, 'description' => 'If checked, the order of the events feed will be reversed.', 'inline_label' => 'Reverse the order of the events feed', 'disabled' => !$this->is_pro) );
		?>
		</table>
		<?php
	}
	
	function output_event_display_options(){
		?>
			<h3>Only Display Events:</h3>	
			<p class="description">This option has moved to the <a href="?page=ikfb_configuration_options#tab-facebook-feed-settings">Synced Feeds</a> panel, where it is set on a per feed basis.</p>
		<?php
	}
	
	/*
	 * Outputs the Custom HTML Options page
	 */
	function custom_html_options_page()
	{
		//add upgrade button if free version
		$extra_buttons = array();
		if(!$this->is_pro){
			$extra_buttons = array(
				array(
					'class' => 'btn-purple',
					'label' => 'Upgrade To Pro',
					'url' => 'https://goldplugins.com/our-plugins/wp-social-pro/upgrade-to-wp-social-pro/'
				)
			);
		}
		
		//instantiate tabs object for output basic settings page tabs
		$tabs = new GP_Sajak( array(
			'header_label' => 'Custom HTML Options',
			'settings_field_key' => 'ik-fb-html-settings-group', // can be an array	
			'show_save_button' => true, // hide save buttons for all panels   		
			'extra_buttons_header' => $extra_buttons, // extra header buttons
			'extra_buttons_footer' => $extra_buttons // extra footer buttons
		) );
	
		$tabs->add_tab(
			'custom-html-options', // section id, used in url fragment
			'Custom HTML Options' . $this->pro_string, // section label
			array($this, 'output_custom_html_options'), // display callback
			array(
				'class' => 'extra_li_class', // extra classes to add sidebar menu <li> and tab wrapper <div>
				'icon' => 'life-buoy' // icons here: http://fontawesome.io/icons/
			)
		);
		
		$this->start_settings_page();
		
		$tabs->display();
		
		$this->end_settings_page();		
	}
	
	function output_custom_html_options(){
		?>
			<h3><?php _e('Custom HTML', 'ik-facebook');?></h3>
			<?php if(!$this->is_pro): ?><div class="disabled"><?php endif; ?>
			
			<?php echo $this->pro_upgrade_link(); ?>
			<table class="form-table">
			<?php
				// Use Custom HTML (checkbox)
				$checked = (get_option('ik_fb_use_custom_html') == '1');
				$this->shed->checkbox( array('name' => 'ik_fb_use_custom_html', 'label' => 'Use Custom HTML', 'value' => 1, 'checked' => $checked, 'description' => 'If checked, this will disable the Style Options in the first tab and will instead use the HTML from below.', 'inline_label' => 'Use Custom HTML', 'disabled' => !$this->is_pro) );

				// Hide Branding (checkbox)
				$checked = (get_option('ik_fb_show_picture_before_message') == '1');
				$this->shed->checkbox( array('name' => 'ik_fb_show_picture_before_message', 'label' => 'Show Media Before Message', 'value' => 1, 'checked' => $checked, 'description' => 'If checked, the Picture or Video HMTL will be output before the Message HTML.', 'inline_label' => 'Output the Picture or Video HTML before the Message HTML', 'disabled' => !$this->is_pro) );
				
				// Custom Feed Item Wrapper HTML (textarea)
				$desc = 'Input any Custom Feed Item HTML you want to use here.  The plugin will work without you placing anything here - this is useful in case you need to edit any styles for it to work with your theme, though. Accepts the following shortcodes: {ikfb:feed_item}';
				$desc .= '<br />Example: <code>' . htmlentities('<li class="ik_fb_feed_item">{ikfb:feed_item}</li>') . '</code>';
				$this->shed->textarea( array('name' => 'ik_fb_feed_item_html', 'label' => 'Custom Feed Item Wrapper HTML', 'value' => get_option('ik_fb_feed_item_html','<li class="ik_fb_feed_item">{ikfb:feed_item}</li>'), 'description' => $desc, 'disabled' => !$this->is_pro) );
				
				// Custom Feed Message HTML (textarea)
				$desc = 'Input any Custom Feed Item HTML you want to use here.  The plugin will work without you placing anything here - this is useful in case you need to ed,it any styles for it to work with your theme, though. Accepts the following shortcodes: {ikfb:feed_item:message}';
				$desc .= '<br />Example: <code>' . htmlentities('<p>{ikfb:feed_item:message}</p>') . '</code>';
				$this->shed->textarea( array('name' => 'ik_fb_message_html', 'label' => 'Custom Feed Message HTML', 'value' => get_option('ik_fb_message_html','<p>{ikfb:feed_item:message}</p>'), 'description' => $desc, 'disabled' => !$this->is_pro) );
				
				// Custom Feed Image HTML (textarea)
				$desc = 'Input any Custom Feed Item HTML you want to use here.  The plugin will work without you placing anything here - this is useful in case you need to edit any styles for it to work with your theme, though. Accepts the following shortcodes: {ikfb:feed_item:image}';
				$desc .= '<br />Example: <code>' . htmlentities('<p class="ik_fb_facebook_image">{ikfb:feed_item:image}</p>') . '</code>';
				$this->shed->textarea( array('name' => 'ik_fb_image_html', 'label' => 'Custom Feed Image HTML', 'value' => get_option('ik_fb_image_html','<p class="ik_fb_facebook_image">{ikfb:feed_item:image}</p>'), 'description' => $desc, 'disabled' => !$this->is_pro) );
				
				// Custom Feed Description HTML (textarea)
				$desc = 'Input any Custom Feed Item HTML you want to use here.  The plugin will work without you placing anything here - this is useful in case you need to edit any styles for it to work with your theme, though. Accepts the following shortcodes: {ikfb:feed_item:description}';
				$desc .= '<br />Example: <code>' . htmlentities('<p class="ik_fb_facebook_description">{ikfb:feed_item:description}</p>') . '</code>';
				$this->shed->textarea( array('name' => 'ik_fb_description_html', 'label' => 'Custom Feed Description HTML', 'value' => get_option('ik_fb_description_html','<p class="ik_fb_facebook_description">{ikfb:feed_item:description}</p>'), 'description' => $desc, 'disabled' => !$this->is_pro) );
				
				// Custom Feed Caption HTML (textarea)
				$desc = 'Input any Custom Feed Item HTML you want to use here.  The plugin will work without you placing anything here - this is useful in case you need to edit any styles for it to work with your theme, though. Accepts the following shortcodes: {ikfb:feed_item:link}';
				$desc .= '<br />Example: <code>' . htmlentities('<p class="ik_fb_facebook_link">{ikfb:feed_item:link}</p>') . '</code>';
				$this->shed->textarea( array('name' => 'ik_fb_caption_html', 'label' => 'Custom Feed Caption HTML', 'value' => get_option('ik_fb_caption_html','<p class="ik_fb_facebook_link">{ikfb:feed_item:link}</p>'), 'description' => $desc, 'disabled' => !$this->is_pro) );
				
				// Custom Feed Wrapper HTML (textarea)
				$desc = 'Input any Custom Feed Item HTML you want to use here.  The plugin will work without you placing anything here - this is useful in case you need to edit any styles for it to work with your theme, though.  Accepts the following shortcodes: {ikfb:image},{ikfb:link},{ikfb:like_button}, and {ikfb:feed}.';
				$desc .= '<br />Example: <code>' . htmlentities('<div id="ik_fb_widget"><div class="ik_fb_profile_picture">{ikfb:image}{ikfb:link}</div>{ikfb:like_button}<ul class="ik_fb_feed_window">{ikfb:feed}</ul></div>') . '</code>';
				$this->shed->textarea( array('name' => 'ik_fb_feed_html', 'label' => 'Custom Feed Wrapper HTML', 'value' => get_option('ik_fb_feed_html','<div id="ik_fb_widget"><div class="ik_fb_profile_picture">{ikfb:image}{ikfb:link}</div>{ikfb:like_button}<ul class="ik_fb_feed_window">{ikfb:feed}</ul></div>'), 'description' => $desc, 'disabled' => !$this->is_pro) );
			?>
			</table>		
			<?php if(!$this->is_pro): ?></div><?php endif; ?>
		<?php	
	}
	
	/* outputs Upgrade to Pro text, if Pro is not registered. */
	function pro_upgrade_link($text = 'Upgrade To WP Social Pro To Unlock These Features')
	{
		if(!$this->is_pro) {
			return '<p class="plugin_is_not_registered">&#x2718; Pro features not available. Upgrade to WP Social Pro to unlock all features. <a class="button" href="http://goldplugins.com/our-plugins/wp-social-pro/upgrade-to-wp-social-pro/?utm_source=plugin_dash&utm_campaign=upgrade_to_unlock" target="_blank">Click here to upgrade now!</a></p>';
		} else {
			return '';
		}
	}
	
	/*
	 * Outputs the Shortcode Generator page
	 */
	function shortcode_generator_page()
	{
		// start the settings page, outputting a notice about the Graph API if needed
		$this->start_settings_page(false, true, '', false);
			
		$this->is_pro = $this->is_pro;
		
		?>		
		<p>Using the buttons below, select your desired method and options for displaying social feeds.</p>
		<p>Instructions:</p>
		<ol>
			<li>Click the WP Social button, below,</li>
			<li>Pick from the available display methods listed, such as Facebook Feed.</li>
			<li>Set the options for your desired method of display,</li>
			<li>Click "Insert Now" to generate the shortcode.</li>
			<li>The generated shortcode will appear in the textarea below - simply copy and paste this into the Page or Post where you would like feed to appear!</li>
		</ol>
		
		<div id="wp-social-shortcode-generator">
		
		<?php 
			$content = "";//initial content displayed in the editor_id
			$editor_id = "wp_social_shortcode_generator";//HTML id attribute for the textarea NOTE hyphens will break it
			$settings = array(
				//'tinymce' => false,//don't display tinymce
				'quicktags' => false,
			);
			wp_editor($content, $editor_id, $settings); 
		?>
		</div>
		<?php
		
		$this->end_settings_page(false);
	}
	
	/*
	 * Outputs the Plugin Status page
	 */
	function plugin_status_page()
	{
		//add upgrade button if free version
		$extra_buttons = array();
		if(!$this->is_pro){
			$extra_buttons = array(
				array(
					'class' => 'btn-purple',
					'label' => 'Upgrade To Pro',
					'url' => 'https://goldplugins.com/our-plugins/wp-social-pro/upgrade-to-wp-social-pro/'
				)
			);
		}
		
		//instantiate tabs object for output basic settings page tabs
		$tabs = new GP_Sajak( array(
			'header_label' => 'Help &amp; Instructions',
			'settings_field_key' => 'ik-fb-help-instructions-group', // can be an array	
			'show_save_button' => true, // hide save buttons for all panels   		
			'extra_buttons_header' => $extra_buttons, // extra header buttons
			'extra_buttons_footer' => $extra_buttons // extra footer buttons
		) );
	
		$tabs->add_tab(
			'help-center', // section id, used in url fragment
			'Help Center', // section label
			array($this, 'output_help_center'), // display callback
			array(
				'class' => 'extra_li_class', // extra classes to add sidebar menu <li> and tab wrapper <div>
				'icon' => 'life-buoy' // icons here: http://fontawesome.io/icons/
			)
		);
	
		$tabs->add_tab(
			'contact-support', // section id, used in url fragment
			'Contact Support' . $this->pro_string, // section label
			array($this, 'output_contact_support'), // display callback
			array(
				'class' => 'extra_li_class', // extra classes to add sidebar menu <li> and tab wrapper <div>
				'icon' => 'life-buoy' // icons here: http://fontawesome.io/icons/
			)
		);
		
		$this->start_settings_page(false, true, '', false);
		
		$tabs->display();
		
		$this->end_settings_page(false);				
	}
	
	function output_help_center(){
		?>
		<h3>Help Center</h3>
		<div class="help_box">
			<h4>Have a Question?  Check out our FAQs!</h4>
			<p>Our FAQs contain answers to our most frequently asked questions.  This is a great place to start!</p>
			<p><a class="wp_social_support_button" target="_blank" href="https://goldplugins.com/documentation/wp-social-pro-documentation/frequently-asked-questions/?utm_source=help_page">Click Here To Read FAQs</a></p>
		</div>
		<div class="help_box">
			<h4>Looking for Instructions? Check out our Documentation!</h4>
			<p>For a good start to finish explanation of how to add Facebook Feeds and then display them on your site, check out our Documentation!</p>
			<p><a class="wp_social_support_button" target="_blank" href="https://goldplugins.com/documentation/wp-social-pro-documentation/?utm_source=help_page">Click Here To Read Our Docs</a></p>
		</div>
		<?php			
	}
	
	function output_contact_support(){
		if($this->is_pro){		
			//load all plugins on site
			$all_plugins = get_plugins();
			//load current theme object
			$the_theme = wp_get_theme();
			//load current easy t options
			$the_options = '';//$this->load_all_options();
			//load wordpress area
			global $wp_version;
			
			$site_data = array(
				'plugins'	=> $all_plugins,
				'theme'		=> $the_theme,
				'wordpress'	=> $wp_version,
				'options'	=> $the_options
			);
			
			$current_user = wp_get_current_user();
			?>
			<h3>Contact Support</h3>
			<p>Would you like personalized support? Use the form below to submit a request!</p>
			<p>If you aren't able to find a helpful answer in our Help Center, go ahead and send us a support request!</p>
			<p>Please be as detailed as possible, including links to example pages with the issue present and what steps you've taken so far.  If relevant, include any shortcodes or functions you are using.</p>
			<p>Thanks!</p>
			<div class="gp_support_form_wrapper">
				<div class="gp_ajax_contact_form_message"></div>
				
				<div data-gp-ajax-form="1" data-ajax-submit="1" class="gp-ajax-form" method="post" action="https://goldplugins.com/tickets/galahad/catch.php">
					<div style="display: none;">
						<textarea name="your-details" class="gp_galahad_site_details">
							<?php
								echo htmlentities(json_encode($site_data));
							?>
						</textarea>
						
					</div>
					<div class="form_field">
						<label>Your Name (required)</label>
						<input type="text" aria-invalid="false" aria-required="true" size="40" value="<?php echo (!empty($current_user->display_name) ?  $current_user->display_name : ''); ?>" name="your_name">
					</div>
					<div class="form_field">
						<label>Your Email (required)</label>
						<input type="email" aria-invalid="false" aria-required="true" size="40" value="<?php echo (!empty($current_user->user_email) ?  $current_user->user_email : ''); ?>" name="your_email"></span>
					</div>
					<div class="form_field">
						<label>URL where problem can be seen:</label>
						<input type="text" aria-invalid="false" aria-required="false" size="40" value="" name="example_url">
					</div>
					<div class="form_field">
						<label>Your Message</label>
						<textarea aria-invalid="false" rows="10" cols="40" name="your_message"></textarea>
					</div>
					<div class="form_field">
						<input type="hidden" name="include_wp_info" value="0" />
						<label for="include_wp_info">
							<input type="checkbox" id="include_wp_info" name="include_wp_info" value="1" />Include information about my WordPress environment (server information, installed plugins, theme, and current version)
						</label>
					</div>					
					<p><em>Sending this data will allow the Gold Plugins can you help much more quickly. We strongly encourage you to include it.</em></p>
					<input type="hidden" name="registered_email" value="<?php echo htmlentities(get_option('wp_social_pro_registered_name')); ?>" />
					<input type="hidden" name="site_url" value="<?php echo htmlentities(site_url()); ?>" />
					<input type="hidden" name="challenge" value="<?php echo substr(md5(sha1('bananaphone' . get_option('wp_social_pro_registered_key') )), 0, 10); ?>" />
					<div class="submit_wrapper">
						<input type="submit" class="button submit" value="Send">			
					</div>
				</div>
			</div>
			<?php
		} else {
			?>
			<h3>Contact Support</h3>
			<p>Would you like personalized support? Upgrade to Pro today to receive hands on support and access to all of our Pro features!</p>
			<p><a class="button upgrade" href="https://goldplugins.com/our-plugins/wp-social-pro/">Click Here To Learn More</a></p>			
			<?php
		}		
	}
	
	/* Diagnostics for Graph Help Page */
	function run_diagnostics($page_ids)
	{
		// required settings for Graph API		
		$app_id = get_option('ik_fb_app_id', '');
		$secret_key = get_option('ik_fb_secret_key', '');
		
		// default all flags to false
		$results = array( 'keys_present' => false,
						  'keys_work' => false,
						  'loaded_demo_profile' => false,
						  'loaded_own_profile' => array(),
					);
					
		/* run tests! */
		
		// Test #1: make sure the keys are present
		if ( empty($app_id) || empty($secret_key) ) {
			$results['keys_present'] = false;
		} else {
			$results['keys_present'] = true;
		}

		// Test #2: See if we can connect to the Graph API and generate an Access Token
		$access_token = $this->root->gp_facebook->generateAccessToken();
		
		if ( empty($access_token)){
			$results['keys_work'] = false;
		} else {
			$results['keys_work'] = true;
			$results['access_token'] = $access_token;
		}
		
		// Test #3: See if we can load the demo profile
		$demo_feed = $this->root->gp_facebook->loadFacebookFeed('IlluminatiKarate', 1, true);//IK feed, 1 post, all types of posts, status_test true	

		if ( empty($demo_feed['feed']) ) {
			$results['loaded_demo_profile'] = false;
		} else {
			$results['loaded_demo_profile'] = true;
		}
				  
		// Test #4: See if we can load the owner's profile(s)
		foreach($page_ids as $page_id){
			$own_feed = $this->root->gp_facebook->loadFacebookFeed($page_id, 1, true);//user set feed, 1 post, all types of posts, status_test true	

			if ( empty($own_feed['feed']) ) {			
				$results['loaded_own_profile'][$page_id] = false;
			} else {
				$results['loaded_own_profile'][$page_id] = true;
			}						
		}	
		
		return $results;		
	}
	
	/*
	 * Outputs a plugin status box
	 */
	function output_status_box($diagnostics_results, $profile_ids)
	{		
?>
	<table class="table" id="plugin_status_table" cellpadding="0" cellspacing="0">
		<tbody>
			<!-- Page ID -->
			<?php if ( $diagnostics_results['keys_present'] ): ?>
			<tr class="success">
				<td><img src="<?php echo $this->root->plugins_url . 'include/img/check-button.png'; ?>" alt="SUCCESS" /></td>
				<td>App ID and Secret Key Present</td>
			</tr>
			<?php else: ?>
			<tr class="fail">
				<td><img src="<?php echo $this->root->plugins_url . 'include/img/x-button.png'; ?>" alt="FAIL" /></td>
				<td>App ID and Secret Key Present</td>
			</tr>
			<?php endif; ?>
			
			<!-- Connected To Graph API -->
			<?php if ( $diagnostics_results['keys_work'] ): ?>
			<tr class="success">
				<td><img src="<?php echo $this->root->plugins_url . 'include/img/check-button.png'; ?>" alt="SUCCESS" /></td>
				<td>Connected To Facebook Graph API, Access Token <?php echo $diagnostics_results['access_token']; ?> has been granted.</td>
			</tr>
			<?php else: ?>
			<tr class="fail">
				<td><img src="<?php echo $this->root->plugins_url . 'include/img/x-button.png'; ?>" alt="FAIL" /></td>
				<td>Connected To Facebook Graph API</td>
			</tr>
			<?php endif; ?>
			
			<?php 		
				//loop through IDs and output results 
				foreach($profile_ids as $profile_id):
			?>
			
			<!-- Load Their Page Data -->
			<?php if ( $diagnostics_results['loaded_own_profile'][$profile_id] ): ?>
			<tr class="success">
				<td><img src="<?php echo $this->root->plugins_url . 'include/img/check-button.png'; ?>" alt="SUCCESS" /></td>
				<td>Loaded Profile ID: <?php echo $profile_id; ?></td>
			</tr>			
			<?php else: ?>
			<tr class="fail">
				<td><img src="<?php echo $this->root->plugins_url . 'include/img/x-button.png'; ?>" alt="FAIL" /></td>
				<td>Loaded Profile ID: <?php echo $profile_id; ?></td>
			</tr>
			<?php endif; ?>
			
			<?php endforeach; ?>
			
			<!-- Load Our Page Data -->
			<?php if ( $diagnostics_results['loaded_demo_profile'] ): ?>
			<tr class="success">
				<td><img src="<?php echo $this->root->plugins_url . 'include/img/check-button.png'; ?>" alt="SUCCESS" /></td>
				<td>Loaded Test Profile ID: IlluminatiKarate</td>
			</tr>			
			<?php else: ?>
			<tr class="fail">
				<td><img src="<?php echo $this->root->plugins_url . 'include/img/x-button.png'; ?>" alt="FAIL" /></td>
				<td>Loaded Test Profile ID: IlluminatiKarate</td>
			</tr>
			<?php endif; ?>
			
			<!-- PRO Version Activated -->
			<?php if ($this->is_pro): ?>
			<tr class="success">
				<td><img src="<?php echo $this->root->plugins_url . 'include/img/check-button.png'; ?>" alt="SUCCESS" /></td>
				<td>PRO Features Activated</td>
			</tr>
			<?php else: ?>
			<tr class="fail">
				<td><img src="<?php echo $this->root->plugins_url . 'include/img/x-button.png'; ?>" alt="FAIL" /></td>
				<td>PRO Features Unlocked</td>
			</tr>
			<?php endif; ?>
		</tbody>
	</table>
<?php	
	}
	
	
	/*
	 * Outputs a Mailchimp signup form
	 */
	function output_newsletter_signup_form()
	{		
		$this->coupon_box->form();
	} // end output_newsletter_signup_form function
} // end class
?>