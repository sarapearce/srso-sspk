<?php
class OTW_Shortcode_Button extends OTW_Shortcodes{
	
	
	public function __construct(){
		
		$this->has_custom_options = true;
		
		parent::__construct();
	}
	
	/**
	 * apply settings
	 */
	public function apply_settings(){
	
		$this->settings = array(
			'sizes' => array(
				'tiny'   => $this->get_label( 'Tiny' ),
				'small'  => $this->get_label( 'Small' ),
				'medium' => $this->get_label( 'Medium' ),
				'large'  => $this->get_label( 'Large' ),
			),
			'default_size' => 'medium',
			'icon_types' => array(
			
				''                      => $this->get_label( 'none (Default)' ),
				'general foundicon-settings'      => $this->get_label( 'Settings' ),
				'general foundicon-heart'         => $this->get_label( 'Heart' ),
				'general foundicon-star'          => $this->get_label( 'Star' ),
				'general foundicon-plus'          => $this->get_label( 'Plus' ),
				'general foundicon-minus'         => $this->get_label( 'Minus' ),
				'general foundicon-checkmark'     => $this->get_label( 'Checkmark' ),
				'general foundicon-remove'        => $this->get_label( 'Remove' ),
				'general foundicon-mail'          => $this->get_label( 'Mail' ),
				'general foundicon-calendar'      => $this->get_label( 'Calendar' ),
				'general foundicon-page'          => $this->get_label( 'Page' ),
				'general foundicon-tools'         => $this->get_label( 'Tools' ),
				'general foundicon-globe'         => $this->get_label( 'Globe' ),
				'general foundicon-cloud'         => $this->get_label( 'Cloud' ),
				'general foundicon-error'         => $this->get_label( 'Error' ),
				'general foundicon-right-arrow'   => $this->get_label( 'Right arrow' ),
				'general foundicon-left-arrow'    => $this->get_label( 'Left arrow' ),
				'general foundicon-up-arrow'      => $this->get_label( 'Up arrow' ),
				'general foundicon-down-arrow'    => $this->get_label( 'Down arrow' ),
				'general foundicon-trash'         => $this->get_label( 'Trash' ),
				'general foundicon-add-doc'       => $this->get_label( 'Add Doc' ),
				'general foundicon-edit'          => $this->get_label( 'Edit' ),
				'general foundicon-lock'          => $this->get_label( 'Lock' ),
				'general foundicon-unlock'        => $this->get_label( 'Unlock' ),
				'general foundicon-refresh'       => $this->get_label( 'Refresh' ),
				'general foundicon-paper-clip'    => $this->get_label( 'Paper clip' ),
				'general foundicon-video'         => $this->get_label( 'Video' ),
				'general foundicon-photo'         => $this->get_label( 'Photo' ),
				'general foundicon-graph'         => $this->get_label( 'Graph' ),
				'general foundicon-idea'          => $this->get_label( 'Idea' ),
				'general foundicon-mic'           => $this->get_label( 'Mic' ),
				'general foundicon-cart'          => $this->get_label( 'Cart' ),
				'general foundicon-address-book'  => $this->get_label( 'Address book' ),
				'general foundicon-compass'       => $this->get_label( 'Compass' ),
				'general foundicon-flag'          => $this->get_label( 'Flag' ),
				'general foundicon-location'      => $this->get_label( 'Location' ),
				'general foundicon-clock'         => $this->get_label( 'Clock' ),
				'general foundicon-folder'        => $this->get_label( 'Folder' ),
				'general foundicon-inbox'         => $this->get_label( 'Inbox' ),
				'general foundicon-website'       => $this->get_label( 'Website' ),
				'general foundicon-smiley'        => $this->get_label( 'Smiley' ),
				'general foundicon-search'        => $this->get_label( 'Search' ),
				'general foundicon-phone'         => $this->get_label( 'Phone' ),
				
				'social foundicon-thumb-up'       => $this->get_label( 'Thumb up' ),
				'social foundicon-thumb-down'     => $this->get_label( 'Thumb down' ),
				'social foundicon-rss'            => $this->get_label( 'Rss' ),
				'social foundicon-facebook'       => $this->get_label( 'Facebook' ),
				'social foundicon-twitter'        => $this->get_label( 'Twitter' ),
				'social foundicon-pinterest'      => $this->get_label( 'Pinterest' ),
				'social foundicon-github'         => $this->get_label( 'Github' ),
				'social foundicon-path'           => $this->get_label( 'Path' ),
				'social foundicon-linkedin'       => $this->get_label( 'LinkedIn' ),
				'social foundicon-dribbble'       => $this->get_label( 'Dribbble' ),
				'social foundicon-stumble-upon'   => $this->get_label( 'Stumble upon' ),
				'social foundicon-behance'        => $this->get_label( 'Behance' ),
				'social foundicon-reddit'         => $this->get_label( 'Reddit' ),
				'social foundicon-google-plus'    => $this->get_label( 'Google plus' ),
				'social foundicon-youtube'        => $this->get_label( 'Youtube' ),
				'social foundicon-vimeo'          => $this->get_label( 'Vimeo' ),
				'social foundicon-clickr'         => $this->get_label( 'Clickr' ),
				'social foundicon-slideshare'     => $this->get_label( 'Slideshare' ),
				'social foundicon-picassa'        => $this->get_label( 'Picassa' ),
				'social foundicon-skype'          => $this->get_label( 'Skype' ),
				'social foundicon-instagram'      => $this->get_label( 'instagram' ),
				'social foundicon-foursquare'     => $this->get_label( 'Foursquare' ),
				'social foundicon-delicious'      => $this->get_label( 'Delicious' ),
				'social foundicon-chat'           => $this->get_label( 'Chat' ),
				'social foundicon-torso'          => $this->get_label( 'Torso' ),
				'social foundicon-tumblr'         => $this->get_label( 'Tumblr' ),
				'social foundicon-video-chat'     => $this->get_label( 'Video chat' ),
				'social foundicon-digg'           => $this->get_label( 'Digg' ),
				'social foundicon-wordpress'      => $this->get_label( 'Wordpress' )
			),
			'default_icon_type' => '',
			'color_classes' => array(
			
				''                      => $this->get_label( 'none (Default)' ),
				'otw-red'                   => $this->get_label( 'Red' ),
				'otw-orange'                => $this->get_label( 'Orange' ),
				'otw-green'                 => $this->get_label( 'Green' ),
				'otw-greenish'              => $this->get_label( 'Greenish' ),
				'otw-aqua'                  => $this->get_label( 'Aqua' ),
				'otw-blue'                  => $this->get_label( 'Blue' ),
				'otw-pink'                  => $this->get_label( 'Pink' ),
				'otw-silver'                => $this->get_label( 'Silver' ),
				'otw-brown'                 => $this->get_label( 'Brown' ),
				'otw-black'                 => $this->get_label( 'Black' )
			),
			'default_color_class' => '',
			'icon_positions' => array(
				'left' => $this->get_label( 'Left (default)'),
				'right' => $this->get_label( 'Right')
			),
			'default_icon_position' => 'left',
			'shapes' => array(
				'square'      =>     $this->get_label( 'Square (default)' ),
				'radius'      =>     $this->get_label( 'Radius' ),
				'round'       =>     $this->get_label( 'Round' )
			),
			'default_shape' => 'square'
			
		);
		
	}
	
	public function register_external_libs(){
	
		$this->add_external_lib( 'css', 'otw-shortcode-general_foundicons', $this->component_url.'css/general_foundicons.css', 'all', 10 );
		$this->add_external_lib( 'css', 'otw-shortcode-social_foundicons', $this->component_url.'css/social_foundicons.css', 'all', 20 );
		$this->add_external_lib( 'css', 'otw-shortcode', $this->component_url.'css/otw_shortcode.css', 'all', 100 );
	
	}
	
	/**
	 * Shortcode button admin interface
	 */
	public function build_shortcode_editor_options(){
		
		$html = '';
		
		$source = array();
		if( isset( $_POST['shortcode_object'] ) ){
			$source = $_POST['shortcode_object'];
		}
		
		$html .= OTW_Form::text_input( array( 'id' => 'otw-shortcode-element-title', 'label' => $this->get_label( 'Title' ), 'description' => $this->get_label( 'The button title.' ), 'parse' => $source )  );
		
		$html .= OTW_Form::text_input( array( 'id' => 'otw-shortcode-element-href', 'label' => $this->get_label( 'Link' ), 'description' => $this->get_label( 'Optional link (e.g. http://google.com).' ), 'parse' => $source )  );
		
		$html .= OTW_Form::select( array( 'id' => 'otw-shortcode-element-target', 'label' => $this->get_label( 'Open in a new window' ), 'description' => $this->get_label( 'Optionally open this link in a new window.' ), 'parse' => $source, 'options' => array( '' => $this->get_label( 'no (Default)' ), '_blank' => $this->get_label( 'yes' ) ), 'value' => '' )  );
		
		$html .= OTW_Form::select( array( 'id' => 'otw-shortcode-element-size', 'label' => $this->get_label( 'Size' ), 'description' => $this->get_label( 'The size for the button.' ), 'parse' => $source, 'options' => $this->settings['sizes'], 'value' => $this->settings['default_size'] )  );
		
		$html .= OTW_Form::select( array( 'id' => 'otw-shortcode-element-icon_type', 'label' => $this->get_label( 'Icon Type' ), 'description' => $this->get_label( 'The icons here are based on foundation icon fonts. The size and the color apply from the button title color and button size.' ), 'parse' => $source, 'options' => $this->settings['icon_types'], 'value' => $this->settings['default_icon_type'] )  );
		
		$html .= OTW_Form::select( array( 'id' => 'otw-shortcode-element-icon_position', 'label' => $this->get_label( 'Icon Position' ), 'description' => $this->get_label( 'Icon can be position left or right of the button title.' ), 'parse' => $source, 'options' => $this->settings['icon_positions'], 'value' => $this->settings['default_icon_position'] )  );
		
		$html .= OTW_Form::select( array( 'id' => 'otw-shortcode-element-shape', 'label' => $this->get_label( 'Shape' ), 'description' => $this->get_label( 'The shape of the button.' ), 'parse' => $source, 'options' => $this->settings['shapes'], 'value' => $this->settings['default_shape'] )  );
		
		$html .= OTW_Form::select( array( 'id' => 'otw-shortcode-element-color_class', 'label' => $this->get_label( 'Color' ), 'description' => $this->get_label( 'Button predefined color.' ), 'parse' => $source, 'options' => $this->settings['color_classes'], 'value' => $this->settings['default_color_class'] )  );
		
		
		return $html;
	}
	
	/**
	 * Shortcode button admin interface custom options
	 */
	public function build_shortcode_editor_custom_options(){
		
		$html = '';
		
		$source = array();
		if( isset( $_POST['shortcode_object'] ) ){
			$source = $_POST['shortcode_object'];
		}
		
		$html .= OTW_Form::color_picker( array( 'id' => 'otw-shortcode-element-bgcolor', 'label' => $this->get_label( 'Background Color' ), 'description' => $this->get_label( 'Button custom color.' ), 'parse' => $source )  );
		
		$html .= OTW_Form::color_picker( array( 'id' => 'otw-shortcode-element-border_color', 'label' => $this->get_label( 'Border color' ), 'description' => $this->get_label( 'Custom color for the border.' ), 'parse' => $source )  );
		
		$html .= OTW_Form::color_picker( array( 'id' => 'otw-shortcode-element-text_color', 'label' => $this->get_label( 'Title color' ), 'description' => $this->get_label( 'Custom color for the title.' ), 'parse' => $source )  );
		
		$html .= OTW_Form::uploader( array( 'id' => 'otw-shortcode-element-icon_url', 'label' => $this->get_label( 'Icon URL' ), 'description' => $this->get_label( 'Url to a custom icon.' ), 'preview_label' => $this->get_label( 'Preview:' ), 'parse' => $source )  );
		
		$html .= OTW_Form::text_input( array( 'id' => 'otw-shortcode-element-css_class', 'label' => $this->get_label( 'CSS Class' ), 'description' => $this->get_label( 'If you\'d like to style this element separately enter a name here. A CSS class with this name will be available for you to style this particular element..' ), 'parse' => $source )  );
		
		return $html;
	}
	
	/** build button shortcode
	 *
	 *  @param array
	 *  @return string
	 */
	public function build_shortcode_code( $attributes ){
		
		$code = '';
		
		if( !isset( $attributes['title'] ) || !strlen( trim( $attributes['title'] ) ) ){
			$this->add_error( $this->get_label( 'Title is required field' ) );
		}
		
		if( !$this->has_error ){
		
			$code = '[otw_shortcode_button';
			
			$code .= $this->format_attribute( 'href', 'href', $attributes );
			$code .= $this->format_attribute( 'size', 'size', $attributes );
			$code .= $this->format_attribute( 'bgcolor', 'bgcolor', $attributes );
			$code .= $this->format_attribute( 'icon_type', 'icon_type', $attributes );
			$code .= $this->format_attribute( 'icon_position', 'icon_position', $attributes );
			$code .= $this->format_attribute( 'shape', 'shape', $attributes );
			$code .= $this->format_attribute( 'icon_url', 'icon_url', $attributes );
			$code .= $this->format_attribute( 'color_class', 'color_class', $attributes );
			$code .= $this->format_attribute( 'border_color', 'border_color', $attributes );
			$code .= $this->format_attribute( 'text_color', 'text_color', $attributes );
			$code .= $this->format_attribute( 'css_class', 'css_class', $attributes, false, '', true  );
			$code .= $this->format_attribute( 'target', 'target', $attributes );
			
			$code .= ']';
			
			$code .= $attributes['title'];
			
			$code .= '[/otw_shortcode_button]';
		}
		
		return $code;
	}
	
	
	/**
	 * Process shortcode Button
	 */
	public function display_shortcode( $attributes, $content ){
		
		$html = '<a';
		
		$html .= $this->format_attribute( 'href', 'href', $attributes );
		
		/*class attribute*/
		$class = '';
		
		$class .= $this->format_attribute( '', 'size', $attributes, false, $class );
		$class .= $this->format_attribute( '', 'color_class', $attributes, false, $class );
		$class .= $this->format_attribute( '', 'css_class', $attributes, false, $class );
		$class .= $this->format_attribute( '', 'shape', $attributes, false, $class );
		
		if( $icon_position = $this->format_attribute( '', 'icon_position', $attributes, false, '' ) ){
			if( $icon_position == 'right' ){
				$class = $this->append_attribute( $class, 'right-icon' );
			}
		}
		
		
		if( strlen( $class ) ){
			$html .= ' class="'.$class.' otw-button"';
		}
		
		/*style attribute*/
		$style = '';
		
		if( $bgcolor = $this->format_attribute( '', 'bgcolor', $attributes, false, '' ) ){
		
			$style = $this->append_attribute( $style, 'background-color: '.$bgcolor.';' );
		}
		
		if( $border_color = $this->format_attribute( '', 'border_color', $attributes, false, '' ) ){
		
			$style = $this->append_attribute( $style, 'border-color: '.$border_color.';' );
		}
		
		if( $text_color = $this->format_attribute( '', 'text_color', $attributes, false, '' ) ){
		
			$style = $this->append_attribute( $style, 'color: '.$text_color.' !important;' );
		}
		
		if( strlen( $style ) ){
			$html .= ' style="'.$style.'"';
		}
		
		$html .= $this->format_attribute( 'target', 'target', $attributes );
		
		$html .= '>';
		
		
		if( $icon_type = $this->format_attribute( '', 'icon_type', $attributes, false, '' ) ){
			$html .= '<i class="'.$icon_type.'"></i>';
		}
		
		if( $icon_url = $this->format_attribute( '', 'icon_url', $attributes, false, '' ) ){
			$html .= '<img src="'.$icon_url.'" alt="icon" title="icon" />';
		}
		
		$html .= $content.'</a>';
		
		return $html;
	}
	

}