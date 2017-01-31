<?php
/*
This file is part of WP Social.

WP Social is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

WP Social is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with WP Social.  If not, see <http://www.gnu.org/licenses/>.

Shout out to http://www.makeuseof.com/tag/how-to-create-wordpress-widgets/ for the help
*/

class ikFacebookFeedWidget extends WP_Widget
{
	function __construct(){
		$widget_ops = array('classname' => 'ikFacebookWidget', 'description' => 'Displays the Facebook Feed' );
		parent::__construct('ikFacebookWidget', 'WP Social Facebook Feed', $widget_ops);
	}

	// PHP4 style constructor for backwards compatibility
	function ikFacebookFeedWidget(){
		$this->__construct();
	}

	function form($instance){
		$instance = wp_parse_args( (array) $instance, array( 'title' => '', 'use_thumbs' => true, 'width' => '', 'colorscheme' => false, 'page_id' => '', 'num_posts' => '' ) );
		$title = $instance['title'];
		$colorscheme = $instance['colorscheme'];
		$page_id = $instance['page_id'];
		$use_thumbs = $instance['use_thumbs'];
		$num_posts = $instance['num_posts'];
		$width = $instance['width'];
		
		?>
			<p><label for="<?php echo $this->get_field_id('title'); ?>">Title: </label><input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" /></p>
			<p><label for="<?php echo $this->get_field_id('page_id'); ?>">Page ID: </label><?php $this->output_feed_id_dropdown($page_id); ?></p>
			<p><label for="<?php echo $this->get_field_id('num_posts'); ?>">Number of Posts: </label><input class="widefat" id="<?php echo $this->get_field_id('num_posts'); ?>" name="<?php echo $this->get_field_name('num_posts'); ?>" type="text" value="<?php echo esc_attr($num_posts); ?>" /></p>
			<p><label for="<?php echo $this->get_field_id('use_thumbs'); ?>">Use Thumbs (width setting ignored, if checked): </label><input class="widefat" id="<?php echo $this->get_field_id('use_thumbs'); ?>" name="<?php echo $this->get_field_name('use_thumbs'); ?>" type="checkbox" value="1" <?php if($use_thumbs){ ?>checked="CHECKED"<?php } ?>/></p>
			<p><label for="<?php echo $this->get_field_id('width'); ?>">Image Width: </label><input class="widefat" id="<?php echo $this->get_field_id('width'); ?>" name="<?php echo $this->get_field_name('width'); ?>" type="text" value="<?php echo esc_attr($width); ?>" /></p>
			<p><label for="<?php echo $this->get_field_id('colorscheme'); ?>">Like Button Color Scheme: </label><select class="widefat" id="<?php echo $this->get_field_id('colorscheme'); ?>" name="<?php echo $this->get_field_name('colorscheme'); ?>"><option <?php if($colorscheme == "light"): ?> selected="SELECTED" <?php endif; ?> value="light" >Light</option><option <?php if($colorscheme == "dark"): ?> selected="SELECTED" <?php endif; ?> value="dark">Dark</option></select></p>
		<?php
	}
	
	function output_feed_id_dropdown($page_id){
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
		$values = array();
		foreach($feed_ids as $feed_id){
			$values[$feed_id] = $feed_id;
		}
		
		?>
		<select id="<?php echo $this->get_field_id('page_id'); ?>" data-shortcode-key="id" name="<?php echo $this->get_field_name('page_id'); ?>" >
		<?php
			foreach ($values as $label => $value) {
				printf
					(
						'<option value="%s"%s>%s</option>',
						$value,
						$value == $page_id? ' selected="selected"':'',
						$label
					);
				}
		?>
		</select>
		<?php
	}	

	function update($new_instance, $old_instance){
		$instance = $old_instance;
		$instance['title'] = $new_instance['title'];
		$instance['colorscheme'] = strip_tags( $new_instance['colorscheme'] );
		$instance['page_id'] = strip_tags( $new_instance['page_id'] );
		$instance['use_thumbs'] = strip_tags( $new_instance['use_thumbs'] );
		$instance['num_posts'] = strip_tags( $new_instance['num_posts'] );
		$instance['width'] = strip_tags( $new_instance['width'] );
		return $instance;
	}

	function widget($args, $instance){
		if (!isset($ik_fb)){
			$ik_fb = new ikFacebook();
		}
		
		extract($args, EXTR_SKIP);

		echo $before_widget;
		
		extract($attributes = array(
			'colorscheme' => 'light',
			'width' => '', /* old, should no longer appear */
			'height' => '', /* old, should no longer appear */
			'feed_image_width' => get_option('ik_fb_feed_image_width'),
			'feed_image_height' => get_option('ik_fb_feed_image_height'),
			'use_thumb' => !get_option('ik_fb_fix_feed_image_width') && !get_option('ik_fb_fix_feed_image_height'),
			'num_posts' => null,
			'id' => false,
			'show_errors' => false,
			'show_only_events' => get_option('ik_fb_show_only_events'),
			'header_bg_color' => strlen(get_option('ik_fb_header_bg_color')) > 2 && !get_option('ik_fb_use_custom_html') ? get_option('ik_fb_header_bg_color') : '',
			'window_bg_color' => strlen(get_option('ik_fb_window_bg_color')) > 2 && !get_option('ik_fb_use_custom_html') ? get_option('ik_fb_window_bg_color') : '',
			'character_limit' => '',
			'description_character_limit' => '',
			'hide_feed_images' => '',
			'show_like_button' => '',
			'show_profile_picture' => '',
			'show_page_title' => '',
			'show_posted_by' => '',
			'show_date' => '',
			'use_human_timing' => '',
			'date_format' => '',
			'show_avatars' => '',
			'show_reply_counts' => '',
			'show_replies' => '',
			'show_likes' => '',
			'only_show_page_owner' => '',
			'reverse_events' => '',
			'start_date_format' => '',
			'end_date_format' => '',
			'event_range_start_date' => '',
			'event_range_end_date' => '',
		));
		
		$title = empty($instance['title']) ? ' ' : apply_filters('widget_title', $instance['title']);
		$colorscheme = empty($instance['colorscheme']) ? 'light' : $instance['colorscheme'];
		$id = empty($instance['page_id']) ? get_option('ikfb_page_id') : $instance['page_id'];
		$use_thumb = empty($instance['use_thumbs']) ? false : $instance['use_thumbs'];
		$num_posts = empty($instance['num_posts']) ? get_option('ikfb_num_posts') : $instance['num_posts'];
		$feed_image_height = empty($instance['height']) ? get_option('ikfb_feed_window_height') : $instance['height'];
		$feed_image_width = empty($instance['width']) ? get_option('ik_fb_feed_image_width') : $instance['width'];
		
		// Initialize the feed options
		$show_only_events = ($show_only_events) ? 1 : 0;		
		$content_type = ($show_only_events) ? "events" : "";		
		
		if (!empty($title)){
			echo $before_title . $title . $after_title;
		}
		
		echo do_shortcode("[ik_fb_feed id='{$id}' height='{$height}' colorscheme='{$colorscheme}' use_thumb='{$use_thumb}' feed_image_width='{$feed_image_width}'  feed_image_height='{$feed_image_height}'  num_posts='{$num_posts}'  show_only_events='{$show_only_events}'  content_type='{$content_type}'  header_bg_color='{$header_bg_color}' window_bg_color='{$window_bg_color}']");

		echo $after_widget;
	} 
}
?>