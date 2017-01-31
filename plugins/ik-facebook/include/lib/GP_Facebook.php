<?php
class GP_Facebook{	
	var $root;
	var $access_token;
	var $is_pro;
	var $doing_cron = false;
	var $force_reload = false;
	var $uploads_subfolder;
		
	function __construct($root){
		$this->root = $root;
		
		//generate an access token to use for api calls
		$this->generateAccessToken();
		
		//run pro check
		$this->isPro();
		
		//setup scheduling
		$this->setup_schedule();
		
		//create upload directory, if needed
		$this->create_upload_dir();
	}	
	
	//create upload directory using our formatting
	function create_upload_dir(){
		add_filter( 'upload_dir', array($this, 'gp_facebook_upload_dir') );		
		wp_upload_dir( null, true );
		remove_filter( 'upload_dir', array($this, 'gp_facebook_upload_dir') );
	}
	
	//check if pro and set class var
	function isPro(){
		$this->is_pro = is_valid_key(get_option('ik_fb_pro_key'));
	}
	
	//loads app id and secret key from settings
	//generates an access_token via the graph api and sets it
	function generateAccessToken(){
		if(!isset($this->access_token)){
			$app_id = get_option('ik_fb_app_id');
			$app_secret = get_option('ik_fb_secret_key');
				
			$this->access_token = $this->root->fetchUrl("https://graph.facebook.com/oauth/access_token?type=client_cred&client_id={$app_id}&client_secret={$app_secret}");
		}
		
		return $this->access_token;
	}
	
	
	/* Load and Display Functions */
	
	//loads facebook feed based on current id
	//can handle a regular page feed or an event's feed
	/*
		when in cron
		foreach through loaded data and store a facebook custom post type for each one
		store the timestamp of the last post (most recent) in the feed as a custom meta values
		future "new load" queries will use that timestamp and query for posts "since" that time
		secondarily, wp_cron jobs will iterate through already stored posts and make sure 
			that their meta data stays up to date
			will have an option to determine how long to keep posts up to date for
		set status_test to true to not store items and just load 1, for testing purposes
		
		when not in cron
		loads specified feed items from wp database and returns them according to the set options
		
		determines the content type itself, no longer needs it passed in
	*/
	function loadFacebookFeed($profile_id = false, $num_posts = false, $status_test = false, $forced = false){			
		//look for stored facebook posts
		//if found, load the timestamp of the last query and query for posts since that time
		//if not found, load all of the posts (taking into account relevant options / attributes)!	
		$retData = array();	
	
		//this might take a while on a large feed or slow server
		//TBD: make this an option, or find a better way to handle the issue of timeout
		ini_set('max_execution_time',0);
	
		//used for tracking if the passed ID is a new ID for the system
		$new_id = false;
		
		//if none passed, load first (default) ID from settings
		if(!$profile_id){
			$profile_ids = get_option('ik_fb_page_id');			
			
			//if not currently an array, make it one
			//should only happen after updating from 2.0
			if(!is_array($profile_ids)){
				$profile_ids = array(
					$profile_ids
				);							
			}
			
			$profile_id = $profile_ids[0];
			
			//no profile id set on options
			if(empty($profile_id)){
				return false;
			}
		}else{
			//check to see if this is a new ID
			//and that it isn't a status test
			$profile_ids = get_option('ik_fb_page_id');					
			
			//if not currently an array, make it one
			//should only happen after updating from 2.0
			if(!is_array($profile_ids)){
				$profile_ids = array(
					$profile_ids
				);							
			}
			
			//convert ids to lowercase for searching case insensitive
			$profile_ids = array_map('strtolower', $profile_ids);
			if( !in_array( strtolower($profile_id), $profile_ids ) && !$status_test ){
				//this is a new ID, set the flag and add to our options
				$new_id = true;				
				$profile_ids[] = $profile_id;
				update_option( 'ik_fb_page_id', $profile_ids );
			}
		}
		
		// create upload directory for this profile if none exists yet
		$this->set_uploads_subfolder($profile_id);
		
		//determine the content type of this feed
		$feed_types_by_id = get_option('ik_fb_page_id_types');
		$content_type = isset($feed_types_by_id[$profile_id]) ? $feed_types_by_id[$profile_id] : "default";
				
		//determine number of posts to load
		if(!is_numeric($num_posts)){
			$limit = get_option('ik_fb_feed_limit', 25);
			
			//option might be set to an empty string
			if(strlen($limit)<1){
				$limit = 25;
			}
		} else {
			$limit = $num_posts;
		}
		
		//determine if scheduled syncing is disabled
		$scheduled_syncing_disabled = !get_option( 'ik_fb_enable_cron', false );
	
		//if the profile id is set and appears correct
		//and if we are in cron job
		//or this is a new facebook ID
		//or this is a status test
		//or scheduled syncing is disabled so we load each time
		//fetch the feed from facebook and store the data
		if( ( (isset($profile_id) && strlen($profile_id)>0) && $this->doing_cron ) ||
			$new_id ||
			$status_test ||
			$scheduled_syncing_disabled){
			
			//initialize page data
			$retData['page_data'] = "";
			
			//load feed's page data from facebook 
			$page_data = $this->root->fetchUrl("https://graph.facebook.com/{$profile_id}?summary=1&{$this->access_token}", true);//the page data
			
			if(!empty($page_data)){ //check to see if page data is set
				$retData['page_data'] = $page_data;
			}
			
			//load the timestamp of the last time this specific feed was fetched
			//if status test is being performed, set last timestamp to 0
			//if force reload is set, set last timestamp to 0
			//if last_timestamp is not set, feed has never been loaded so set last timestamp to 0
			$last_timestamp = ($status_test || $forced) ? 0 : get_transient( $profile_id . $content_type . '_timestamp' );
			
			//if setting "keep posts up to date for XX time" is set, then subtract that time from the timestamp so we re-query those items
			$update_timeframe = get_option('ik_fb_update_timeframe', 24);
			$update_timeframe = $update_timeframe * 60 *60; //convert to seconds for timestamp
			$last_timestamp = ($last_timestamp > 0) ? $last_timestamp - $update_timeframe : $last_timestamp;
			
			if(!$last_timestamp){ // no transient set, feed never loaded before
				$last_timestamp = 0; // set timestamp to dawn of time to load all posts
			}
			
			//check to see if we are within the store interval (to prevent calling the API too frequently)
			$api_fields = implode( ',', $this->get_api_field_list() );
			
			//handle events queries
			if($content_type == "events") {
				$date_range = $this->get_event_data_range();
				
				//	Take into account the event date range
				$feed = $this->root->fetchUrl("https://graph.facebook.com/{$profile_id}/events?summary=1&limit={$limit}&since={$date_range['event_start_date']}&until={$date_range['event_end_date']}&{$this->access_token}", true);//the feed data
				
				//update last timestamp to the created time of the most recent item in the feed
				$new_last_timestamp = isset($feed->data[0]->created_time) ? strtotime($feed->data[0]->created_time) : $last_timestamp;
				
				set_transient( $profile_id . $content_type . '_timestamp', $new_last_timestamp, 0);
			} else {				
				//if showing only page owner posts
				if(get_option('ik_fb_only_show_page_owner') && $this->is_pro){
					$feedURL = "https://graph.facebook.com/{$profile_id}/posts?fields={$api_fields}&summary=1&limit={$limit}&since={$last_timestamp}&{$this->access_token}";
					//only load page owner's posts
					$feed = $this->root->fetchUrl($feedURL, true);//the feed data					
					
				} else {
					$feedURL = "https://graph.facebook.com/{$profile_id}/feed?fields={$api_fields}&summary=1&limit={$limit}&since={$last_timestamp}&{$this->access_token}";
					//if showing everything on the feed (3rd party and page owner)
					$feed = $this->root->fetchUrl($feedURL, true);//the feed data						
				}
				
				//update last timestamp to the created time of the most recent item in the feed
				//if there are no new items in the feed, set the timestamp to the most recently used timestamp
				$new_last_timestamp = isset($feed->data[0]->created_time) ? strtotime($feed->data[0]->created_time) : $last_timestamp;
				
				set_transient( $profile_id . $content_type . '_timestamp', $new_last_timestamp, 0);
			}
			
			if(isset($feed->data)){ //check to see if feed data is set							
				$retData['feed'] = $feed->data;
				
				//if this isn't a status test, store the data
				if(!$status_test){
					//foreach through this feed data and get to storing!
					foreach($retData['feed'] as $feed_item){
						$feed_item->like_count = $this->loadLikeCount($feed_item->id);
						$feed_item->comment_count = $this->loadCommentCount($feed_item->id);
						
						//if this is an event, we have to load a bit more data for each event before storing
						//as the graph api doesn't return enough data, for events, on the initial request					
						if($content_type == "events"){
							$feed_item = (object) array_merge( (array) $feed_item, (array) $this->loadFacebookEvent($feed_item->id) );
						}
												
						//store $feed_item
						$this->storeFacebookPost($feed_item, $profile_id, $content_type);
					}
					
					//store the page data
					$this->storeFacebookPageData($profile_id, $retData['page_data']);
				}
			//in this case, something didn't load correctly.  lets try and see what it is...
			} else {
				$retData['feed_dump'] = $feed;
				
				//don't store anything here -- something went wrong!
			}
			
		}//end check to load fresh feed
				
		//if we are in cron, we can go ahead and stop
		if($this->doing_cron){
			return;
		}
		
		//set the feed data to the stored fb data (which is now up to date, as we just ran our latest fetch)
		//if this is a status test, use the feed data that was just requested without loading from wordpress
		if($status_test){
			//if the feed didn't load (e.g. if the page ID is invalid) then $retData['feed'] won't be set
			$retData['feed'] = isset($retData['feed']) ? $retData['feed'] : '';
		} else {
			$retData['feed'] = $this->loadFbPostsFromWp($profile_id, $limit, $content_type);
			$retData['page_data'] = $this->loadFacebookPageData($profile_id);
		}
		
		return $retData;
	}
	
	function set_uploads_subfolder($subfolder = '')
	{
		$this->uploads_subfolder = sanitize_file_name($subfolder);
		$this->create_upload_dir(); // make sure the subfolder has been created
	}
	
	function get_api_field_list($context = 'feed')
	{
		return array(
			'picture',
			'caption',
			'created_time',
			'description',
			'from',
			'link',
			'message',
			'name',
			'object_id',
			'source',
			'type',
			'status_type',
			'story',
			'story_tags',
			'updated_time'
		);
	}
	
	//loads the event date range based on our event options
	function get_event_data_range(){
		//set a date range from now until a year in the future, to grab all upcoming events and no past events
		//these are the default values used if an override isn't set
		$now = time();
		$end_date = $now + 31557600;
		
		//check to see if we are using the manually selected event dates
		//or the automatically generated dates from the floating event dates
		$manual = get_option('ik_fb_range_or_manual','event-start-end-date-options') == 'event-date-range-window' ? false : true;
		
		if($manual){
			//if using manually selected options
			//load the event start date from options
			//if the event start date isn't set in the options, use now as the start date
			$event_start_date = get_option('ik_fb_event_range_start_date', $now);		

			//load the event end date from options
			//if the event end date isn't set in the options, use now + 1 year as the end date				
			$event_end_date = get_option('ik_fb_event_range_end_date', $end_date);
		} else {
			//if using automatically generated options
			$days_into_past = get_option('ik_fb_event_range_past_days', 1209600); //1209600 is 14 days in seconds
			$days_into_future = get_option('ik_fb_event_range_future_days', 31536000); //31536000 is 365 days in seconds
			$event_start_date = $now - ($days_into_past * 86400);		
			$event_end_date = $now + ($days_into_future * 86400);					
		}
		
		$retData['event_start_date'] = $event_start_date;
		$retData['event_end_date'] = $event_end_date;
		
		return $retData;		
	}
	
	//passed a profile id and array of data
	//stores the data for later retrieval
	//updates current data if already exists
	function storeFacebookPageData($profile_id, $page_data){
		if(update_option('ik_fb_profile_data_' . $profile_id, serialize($page_data))){
			return true;
		} else {
			return false;
		}
	}
	
	//passed an event ID
	//returns an object full of event data
	//single event
	//TBD: store and retrieve
	function loadFacebookEvent($event_id){
		$event_data = $this->root->fetchUrl("https://graph.facebook.com/{$event_id}?summary=1&{$this->access_token}", true);//the event data
		
		return $event_data;
	}
	
	//passed a profile id, loads that profiles data
	//if no profile id is passed, used global setting
	function loadFacebookPageData($profile_id){		
		$page_data = unserialize( get_option('ik_fb_profile_data_' . $profile_id, "") );
		
		//could be the first time we've seen this one, so go ahead and try and load it
		if( empty($page_data) ){			
			//load feed's page data from facebook 
			$page_data = $this->root->fetchUrl("https://graph.facebook.com/{$profile_id}?summary=1&{$this->access_token}", true);//the page data
		}
		
		return $page_data;
	}
		
	//loads the like count for this feed item from the graph api
	//only runs if Pro is active
	//returns the value (or 0 if Pro is inactive)
	function loadLikeCount($fb_item_id){				
		$num_likes = 0;
		
		if($this->is_pro){								
			$data = $this->root->fetchUrl("https://graph.facebook.com/{$fb_item_id}/likes?summary=1&{$this->access_token}", true);
			
			if(isset($data->summary->total_count)){
				$num_likes = $data->summary->total_count;
			}	
		}
		
		return $num_likes;
	}
	
	//loads the comment count for this feed item from the graph api
	//only runs if pro is active
	//returns the value (or 0 if Pro is inactive)
	function loadCommentCount($fb_item_id){
		$num_comments = 0;
		
		if($this->is_pro){								
			$data = $this->root->fetchUrl("https://graph.facebook.com/{$fb_item_id}/comments?summary=1&{$this->access_token}", true);
			
			if(isset($data->summary->total_count)){
				$num_comments = $data->summary->total_count;
			}	
		}
				
		return $num_comments;
	}	
		
	//passed a photo gallery ID and a number of photos (limit)
	//returns an object full of photo data
	//TBD: store and retrieve
	function loadFacebookGallery($id, $limit){	
		//load the timestamp of the last time this specific gallery was fetched
		//if last_timestamp is not set, feed has never been loaded so set last timestamp to 0
		$last_timestamp = get_transient( $id . '_ikfb_gallery_timestamp' );
		
		if(!$last_timestamp){ // no transient set, feed never loaded before
			$last_timestamp = 0; // set timestamp to dawn of time to load all posts
		}		
				
		//load photos in this gallery, only querying those added since the last time we loaded this gallery
		//10150205173893951/photos?fields=images,id,name,picture&limit=999
		//$gallery = $this->root->fetchUrl("https://graph.facebook.com/{$id}/photos?limit=999&since={$last_timestamp}&summary=1&{$this->access_token}", true);//the gallery data
		$gallery = $this->root->fetchUrl("https://graph.facebook.com/{$id}/photos?fields=images,id,name,picture&limit=999&since={$last_timestamp}&summary=1&{$this->access_token}", true);//the gallery data
		
		//update last timestamp to the created time of the most recent item in the feed
		//if there are no new items in the feed, set the timestamp to the most recently used timestamp
		//move to position of last item in array to find created time
		$position = isset($gallery->data) ? count($gallery->data)-1 : 0;
		$new_last_timestamp = isset($gallery->data[$position]->created_time) ? strtotime($gallery->data[$position]->created_time) : $last_timestamp;
		
		//set new last_timestamp
		//TBD: ability to flush gallery loads
		set_transient( $id . '_ikfb_gallery_timestamp', $new_last_timestamp, 0 );
		
		if(!empty($gallery->data)){
			//loop through newly loaded data and store photos
			foreach($gallery->data as $gallery_item){		
				$gallery_item->gallery_id = $id;//set the gallery id, for storage and retrieval
				//store $gallery_item
				$this->storePhotoOnPost(0, $gallery_item);
			}
		}
		
		//load all photos, including those from this gallery that were previously loaded
		$gallery = $this->loadFbGalleryPhotosFromWp($id, $limit);
		
		return $gallery;
	}
	
	//passed an item ID
	//loads a photo and returns its data
	//TBD: store and retrieve
	function loadFacebookPhoto($item_id){
		$photo_data = $this->root->fetchUrl("https://graph.facebook.com/{$item_id}/picture?summary=1&{$this->access_token}&redirect=false", true);	
		
		return $photo_data;
	}
	
	/* End Load and Display Functions */
	
	/* Internal Storage and Retrieval */
	
	//passed a valid facebook post object
	//parses the object and  s it as a custom post type
	//comments become comments with meta
	//likes, shares, etc become post meta
	function storeFacebookPost($item, $feed_id, $content_type){		
		$existing_post_id = $this->findFbPostInWp($feed_id, $item->id);
		
		$title = isset($item->name) ? $item->name : 'FB Post ' . $item->id;
		$body = isset($item->message) ? $item->message : '';
		
		//if this is an event, set the body to the event description
		//TBD: clean this up and maybe make it a function
		if($content_type == "events"){
			$event_date_string = $this->build_event_date_string($item);
			$body = $event_date_string;
			$body .= isset($item->description) ? $item->description : '';
		}
		
		//snag custom fields		
		$fbid = isset($item->id) ? $item->id : '';
		$like_count = $item->like_count;
		$comment_count = $item->comment_count;
		$caption = isset($item->caption) ? $item->caption : '';
		$description = isset($item->description) ? $item->description : '';
		$link = isset($item->link) ? $item->link : '';
		$item_type = isset($item->type) ? $item->type : '';//will be set to 'video' if this status has a video
		$content_source = isset($item->source) ? $item->source : '';//will contain the source of the video, if this status has a video
		$status_type = isset($item->status_type) ? $item->status_type : '';	
		$created_time = '';		
		$updated_time = isset($item->updated_time) ? $item->updated_time : '';
		
		//only process created time for non-events
		if($content_type != "events"){
			//load gmt offset
			$gmt_offset = get_option('gmt_offset');
			//load created time
			$created_time = isset($item->created_time) ? $item->created_time : '';
			//adjust created time for GMT offset (offset * minutes * seconds)
			$created_time = strtotime($created_time) + ($gmt_offset * 60 * 60);
			//format created time
			$created_time = date("Y-m-d H:i:s", $created_time);
		}
		
		
		//if this is an event and there is an existing post
		//then use that posts created time 
		//so we aren't constantly updating the created time of the post
		if( $content_type == "events" && !empty($existing_post_id) ){
			$current_post = get_post($existing_post_id);
			
			if( !empty($current_post) ){
				$created_time = $current_post->post_date;
			}
		}
		
		//event specific custom fields
		$end_time = isset($item->end_time) ? $item->end_time : '';
		$start_time = isset($item->start_time) ? $item->start_time : '';
		$location = isset($item->location) ? $item->location : '';
		$timezone = isset($item->timezone) ? $item->timezone : '';
		
		//add video source to end of content body for embedding, if appropriate
		$body = $this->addVideoToBody($body, $content_source);
		
		$tags = array(); 
		
	   
		$post = array(
			'ID'			=> $existing_post_id, // empty, if no pre-existing post is found (ie, inserts new post), otherwise is the WP ID of the post (thus updating that post, instead of adding a new one)
			'post_title'    => $title,
			'post_content'  => $body,
			'post_category' => array(),  // custom taxonomies too, needs to be an array
			'tags_input'    => $tags,
			'post_status'   => 'publish',
			'post_type'     => 'facebookpost',
			'post_date'		=> $created_time,
			'post_author'	=> 1,//TBD: allow user control over author?
		);
	
		$new_id = wp_insert_post($post);
		
		//no matching post found, so this is new and we can insert its photo
		if(!$existing_post_id){
			//store Photos as Media Items
			if(isset($item->picture) || $content_type == "events"){
				$this->storePhotoOnPost($new_id, $item, $content_type);
			}
		}
		
		//store FB comments as WP comments on this FBCPT
		if(isset($item->comments->data)){
			$this->storeFbCommentsOnPost($new_id, $item->comments->data);
		}
		
		//set the custom fields
		update_post_meta( $new_id, '_ikcf_fbid', $fbid );
		update_post_meta( $new_id, '_ikcf_like_count', $like_count );
		update_post_meta( $new_id, '_ikcf_comment_count', $comment_count );
		update_post_meta( $new_id, '_ikcf_caption', $caption );
		update_post_meta( $new_id, '_ikcf_description', $description );
		update_post_meta( $new_id, '_ikcf_link', $link );
		update_post_meta( $new_id, '_ikcf_item_type', $item_type );
		update_post_meta( $new_id, '_ikcf_content_source', $content_source );
		update_post_meta( $new_id, '_ikcf_status_type', $status_type );
		update_post_meta( $new_id, '_ikcf_created_time', $created_time );
		update_post_meta( $new_id, '_ikcf_updated_time', $updated_time );
		
		//event fields
		update_post_meta( $new_id, '_ikcf_start_time', $start_time );
		update_post_meta( $new_id, '_ikcf_end_time', $end_time );
		update_post_meta( $new_id, '_ikcf_location', $location );
		update_post_meta( $new_id, '_ikcf_timezone', $timezone );
		
		//identifier fields
		update_post_meta( $new_id, '_ikcf_feed_id', $feed_id );
		update_post_meta( $new_id, '_ikcf_content_type', $content_type );
	}
	
	//passed a facebook event item
	//returns a properly formatted event date range string
	function build_event_date_string($item = false){
		$event_date_content = "";
		
		if(!$item){
			return $event_date_content;
		}
		
		$start_time = isset($item->start_time) ? $item->start_time : '';
		$end_time = isset($item->end_time) ? $item->end_time : '';			
		
		//default time format
		$start_time_format = 'l, F jS, Y h:i:s a';
		$end_time_format = 'l, F jS, Y h:i:s a';
		
		if($this->is_pro){
			$start_time_format = get_option('ik_fb_start_date_format', 'l, F jS, Y h:i:s a');
			$end_time_format = get_option('ik_fb_end_date_format', 'l, F jS, Y h:i:s a');
		}
		
		//TBD: Allow user control over date formatting				
		$time_object = new DateTime($start_time);
		$start_time = $time_object->format($start_time_format);	
		
		//TBD: Allow user control over date formatting
		if(strlen($end_time)>2){
			$time_object = new DateTime($end_time);
			$end_time = $time_object->format($end_time_format);						
		}
		
		//event start time - event end time					
		$event_start_time = isset($item->start_time) ? $start_time : '';					
		$event_end_time = isset($item->end_time) ? $end_time : '';
		
		$event_date_content .= '<p class="ikfb_event_date">';
		$event_had_start = false;
		if(strlen($event_start_time)>2){
			$event_date_content .= $event_start_time;
			$event_had_start = true;
		}
		if(strlen($event_end_time)>2){
			
			if($event_had_start){
				$event_date_content .= ' - ';
			}
		
			$event_date_content .= $event_end_time; 
		}
		$event_date_content .= '</p>';
		
		return $event_date_content;
	}
	
	//add the video source to the facebook post body
	//there is a good chance that WordPress oEmbed will pick this up
	//and render the video in the Single Post view
	//currently allows YouTube and FBCDN videos	
	//this is done as part of the storing facebook post process
	function addVideoToBody($body, $content_source){
		$body_content_source = "";
		
		//look to see if this is a youtube video
		$youtube = strpos($content_source, 'youtube');
		//look to see if this is a FB hosted video (ie, an mp4)
		$fbcdn = strpos($content_source, 'fbcdn');
		
		if($youtube !== false){//youtube found within string
			if ($url = parse_url($content_source)) {
				$body_content_source = sprintf(' %s://%s%s', $url['scheme'], $url['host'], $url['path']);
			}
		} elseif($fbcdn !== false){
			//TBD: figure out a way to allow control or detection of the height and width
			$width = 'width="100%"';
			$height = 'height="100%"';
		
			//need to turn this into <video {$width} {$height} preload=\"auto\" controls=\"1\" muted=\"1\" src=\"{$content_source}\"></video>
			$body_content_source = " <video class=\"embedded_ikfb_video\" {$width} {$height} preload=\"auto\" controls=\"1\" muted=\"1\" src=\"{$content_source}\"></video> ";
		}
		
		$body .= "{$body_content_source}";
		
		return $body;
	}
	
	//passed a new fb post's id and data
	//load associated image into media library and attach to post
	//could be called from a feed building function or a gallery building function
	//TBD: prevent storing duplicate photos
	function storePhotoOnPost($post_id = 0, $item, $content_type = ""){	
		//used for overriding specific attributes inside media_handle_sideload
		$post_data = array();
		
		//used for tracking the gallery this photo is associated with, if this came from a gallery call
		$gallery_id = '';
		
		//if this is an event, set the picture accordingly
		if($content_type == "events"){
			//load event image source
			//acceptable parameters for type are: small, normal, large, square
			//default is small
			$event_image_size = get_option('ik_fb_event_image_size', 'small');
			$event_image = "http://graph.facebook.com/" . $item->id . "/picture?type={$event_image_size}";
			
			$item->picture = $event_image;
		}
		
		//if post id is 0, this has been called from a gallery function, not a feed function
		//therefore we need to reformat some data
		if($post_id == 0){			

			$item->description = !empty($item->name) ? $item->name : "FB Image ID: " . $item->id;
			
			usort($item->images, array($this, 'sort_by_image_width') );
			$item->picture = $item->images[0]->source; //photo galleries have already loaded the full sized image source, so place it here to avoid unneeded calls, below
			
			//set post id to default value of none for media_handle_sideload
			$post_id = '';
			
			//set attributes in override array
			$post_data = array(
				'post_title' => '', //photo title
				'post_content' => $item->description, //photo description
				'post_excerpt' => '', //photo caption
			);
			
			$gallery_id = $item->gallery_id;
		}
	
		require_once( ABSPATH . 'wp-admin/includes/image.php');
		require_once( ABSPATH . 'wp-admin/includes/media.php' );//need this for media_handle_sideload
		require_once( ABSPATH . 'wp-admin/includes/file.php' );//need this for the download_url function
		
		$desc = isset($item->description) ? $item->description : '';
		$object_id = isset($item->object_id) ? $item->object_id : '-999';
		
		//if full sized photo is available
		//use it
		if($object_id != -999){
			$photo = $this->loadFacebookPhoto($object_id);
			
			//if a photo was found
			//grab the path
			//otherwise return
			if(!empty($photo->data)){
				$picture = urldecode($photo->data->url);
			} else {
				return;
			}
		} else { //else use thumbnail				
			//load arguments into array for use below
			$parsed_url = parse_url($item->picture);
			
			if(isset($parsed_url['query'])){
				parse_str($parsed_url['query'], $params);               
			}
			
			//handle images loaded by fb's scripts
			if(isset($params['url'])) {
				$picture = urldecode($params['url']);
			} else {
				$picture = urldecode($item->picture);
			}
		}
		
		//before downloading etc,
		//make sure this photo doesn't already existing
		//to prevent duplicates from extra queries being run		
		$args = array(
			'posts_per_page'   => -1,
			'offset'           => 0,
			'category'         => '',
			'category_name'    => '',
			'orderby'          => 'date',
			'order'            => 'DESC',
			'include'          => '',
			'exclude'          => '',
			'meta_query' => array(
				array(
					'key'     => '_download_url',
					'value'   => $picture
				),
			),
			'post_type'        => 'attachment',
			'post_mime_type'   => '',
			'post_parent'      => '',
			'post_status'      => 'any',
			'suppress_filters' => true 
		);
		
		$exists_check = get_posts($args);
		
		//no duplicates found, proceed!
		if(empty($exists_check)){		
			// Download file to temp location
			$tmp = download_url( $picture);
			
			// Set variables for storage
			// fix file filename for query strings
			preg_match('/[^\?]+\.(jpg|JPG|jpe|JPE|jpeg|JPEG|gif|GIF|png|PNG)/', $picture, $matches);
			$file_array['name'] = isset($matches[0]) ? basename($matches[0]) : basename($picture) . ".jpg";//RWG: added file type extension if missing, to allow images rendered by fb safe image scripts through
			$file_array['tmp_name'] = $tmp;

			// If error storing temporarily, unlink
			if ( is_wp_error( $tmp ) ) {
				//$error_string = $tmp->get_error_message();
				//echo '<div id="message" class="error"><p>' . $error_string . '</p></div>';
				
				@unlink($file_array['tmp_name']);
				$file_array['tmp_name'] ='';
			}
			
			add_filter( 'upload_dir', array($this, 'gp_facebook_upload_dir') );
			
			$id = media_handle_sideload( $file_array, $post_id, $desc, $post_data );

			remove_filter( 'upload_dir', array($this, 'gp_facebook_upload_dir') );
			
			// If error storing permanently, unlink
			if ( is_wp_error($id) ) {
				//$error_string = $id->get_error_message();
				//echo '<div id="message" class="error"><p>' . $error_string . '</p></div>';
				
				@unlink($file_array['tmp_name']);
			}
			
			//add as the post thumbnail
			//if a facebook-post id is passed
			if($post_id > 0){
				add_post_meta($post_id, '_thumbnail_id', $id, true);
			}
			
			//add the gallery ID to the photo, if this is a gallery call
			if($gallery_id > 0){
				add_post_meta($id, '_gallery_id', $gallery_id, true);
			}
			
			//add a flag so we can look this up easy, later
			add_post_meta($id, '_download_url', $picture, true);
		}
	}
	
	//customize upload directory for storing facebook photos
	function gp_facebook_upload_dir( $dir ){
		$ds = DIRECTORY_SEPARATOR;
		$default_path = $ds . 'wp-social' . $ds . 'facebook';
		$gp_fb_upload_path = get_option( 'wpsp_fb_upload_path', $default_path );
		$gp_fb_upload_path = str_replace( '..' . $ds, '', $gp_fb_upload_path );
		$gp_fb_upload_path = rtrim( $gp_fb_upload_path, $ds );
		$gp_fb_upload_path = ltrim( $gp_fb_upload_path, $ds );
		$gp_fb_upload_path = $ds . $gp_fb_upload_path;
		
		// add subfolder if one is set
		if ( !empty($this->uploads_subfolder) ) {
			$gp_fb_upload_path .= $ds . $this->uploads_subfolder;
		}		
		
		$dir['path'] = $dir['basedir'] . $gp_fb_upload_path . $dir['subdir'];
		$dir['url'] = $dir['baseurl'] . $gp_fb_upload_path . $dir['subdir'];
		$dir['subdir']	= $gp_fb_upload_path . $dir['subdir'];
		return $dir;
	}
	
	function sort_by_image_width($image_a, $image_b)
	{
		if ($image_a->width > $image_b->width) {
			return -1;
		} else if ($image_a->width < $image_b->width) {
			return 1;		
		} else {
			return 0;
		}
	}
	
	//passed a feed item ID and a Comments Object
	//iterate through the comments and store them
	//on the items FB CPT
	//Pro Only
	//TBD: nested comments (maybe need to issue additional requests?)
	function storeFbCommentsOnPost($post_id, $comments){		
		if($this->is_pro){
			foreach($comments as $comment){		
				
				//load gmt offset
				$gmt_offset = get_option('gmt_offset');
				//load created time
				$created_time = isset($comment->created_time) ? $comment->created_time : '';
				//adjust created time for GMT offset (offset * minutes * seconds)
				$created_time = strtotime($created_time) + ($gmt_offset * 60 * 60);
				//format created time
				$created_time = date("Y-m-d H:i:s", $created_time);
				
				$commentdata = array(
					'comment_post_ID' => $post_id, // to which post the comment will show up
					'comment_author' => $comment->from->name, //fixed value - can be dynamic 
					'comment_author_email' => '', //fixed value - can be dynamic 
					'comment_author_url' => '', //fixed value - can be dynamic 
					'comment_content' => $comment->message, //fixed value - can be dynamic 
					'comment_type' => 'ikfb_fb_comment', //empty for regular comments, 'pingback' for pingbacks, 'trackback' for trackbacks
					'comment_parent' => 0, //0 if it's not a reply to another comment; if it's a reply, mention the parent comment ID here
					'comment_date' => $created_time,
					'user_id' => '', //leave blank, any ID used has to match a real WordPress User ID
					'comment_approved' => 1,
				);

				//look for a duplicate, insert if none found
				if(!$this->detectDuplicateComment($commentdata)){				
					//Insert new comment and get the comment ID
					$comment_id = wp_insert_comment( $commentdata );
					
					//load url of the avatar						
					$picture = "https://graph.facebook.com/{$comment->from->id}/picture";
					
					//Add the URL of the avatar to the comment
					add_comment_meta($comment_id, 'wpsp_avatar', $picture);
					
					//Add the number of Likes to the comment				
					add_comment_meta($comment_id, 'wpsp_num_likes', $comment->like_count);
				}
			}
		}
	}
	
	//passed a comment's dat
	//looks for a duplicate comment
	//returns true if duplicate found
	//sourced from wp_allow_comment in wp-includes/comment.php
	function detectDuplicateComment($commentdata){
		global $wpdb;
	
		// Simple duplicate check
		// expected_slashed ($comment_post_ID, $comment_author, $comment_author_email, $comment_content)
		$dupe = $wpdb->prepare(
			"SELECT comment_ID FROM $wpdb->comments WHERE comment_post_ID = %d AND comment_parent = %s AND comment_approved != 'trash' AND ( comment_author = %s ",
			wp_unslash( $commentdata['comment_post_ID'] ),
			wp_unslash( $commentdata['comment_parent'] ),
			wp_unslash( $commentdata['comment_author'] )
		);
		if ( $commentdata['comment_author_email'] ) {
			$dupe .= $wpdb->prepare(
				"OR comment_author_email = %s ",
				wp_unslash( $commentdata['comment_author_email'] )
			);
		}
		$dupe .= $wpdb->prepare(
			") AND comment_content = %s LIMIT 1",
			wp_unslash( $commentdata['comment_content'] )
		);
		if ( $wpdb->get_var( $dupe ) ) {
			return true;
		}
		
		return false;
	}
	
	//passed a Feed ID and item ID
	//looks for an existing FB CPT that matches
	//returns wordpress ID of FB CPT, if found
	//else returns false
	function findFbPostInWp($id, $fb_post_id){
		$args = array(
			'offset'           => 0,
			'category'         => '',
			'category_name'    => '',
			'orderby'          => 'date',
			'order'            => 'DESC',
			'include'          => '',
			'exclude'          => '',
			'meta_query' => array(
				array(
					'key'     => '_ikcf_feed_id',
					'value'   => $id
				),
				array(
					'key'     => '_ikcf_fbid',
					'value'   => $fb_post_id
				),
			),
			'post_type'        => 'facebookpost',
			'post_mime_type'   => '',
			'post_parent'      => '',
			'post_status'		=> 'any',
			'suppress_filters' => true 
		);
	
		
		$results = get_posts($args);
		
		if(isset($results[0]->ID)){
			//we found a match! return the ID of the match
			return $results[0]->ID;
		} else {
			//no match found! return false
			return false;
		}
	}
	
	//passed a gallery ID
	//loads media items with that gallery id as a custom field
	//returns wordpress gallery items
	function loadFbGalleryPhotosFromWp($id, $limit = -1){		
		$gallery = '';
		
		$args = array(
			'posts_per_page'   => $limit,
			'offset'           => 0,
			'category'         => '',
			'category_name'    => '',
			'orderby'          => 'date',
			'order'            => 'DESC',
			'include'          => '',
			'exclude'          => '',
			'meta_query' => array(
				array(
					'key'     => '_gallery_id',
					'value'   => $id
				),
			),
			'post_type'        => 'attachment',
			'post_mime_type'   => '',
			'post_parent'      => '',
			'post_status'      => 'any',
			'suppress_filters' => true 
		);
		
		$gallery = get_posts($args);
		
		//probably need to format the array now
			
		return $gallery;
	}
	
	//passed the loadFacebookFeed options
	//loads Facebook Feed Custom Post types
	//returns wordpress post object
	//if requested, returns data in same format as Facebook Graph API
	function loadFbPostsFromWp($id, $num_posts, $content_type, $graph_format = true){		
		$feed = '';
		
		if(!is_numeric($num_posts)){
			$num_posts = get_option('ik_fb_feed_limit', 25);
		}
		
		//if this is an events feed, we need to order our query according to the event options
		if($content_type == "events"){					
			//default is DESC
			$order = "DESC";
			
			//load date range from options
			//takes into account manual vs floating date ranges
			$date_range = $this->get_event_data_range();
		
			//reverse the order of the events feed, if the option is checked.
			if(get_option('ik_fb_reverse_events', 0) && $content_type == "events" && $this->is_pro){
				$order = "ASC";
			}
			
			//build query that loads events and sorts them by their event dates
			//if we sort this by wordpress publish dates, the events appear in a wonky order
			$args = array(
				'posts_per_page'	=> $num_posts,
				'offset'			=> 0,
				'category'			=> '',
				'category_name'		=> '',
				'orderby'			=> 'meta_value',
				'meta_key'			=> '_ikcf_start_time',
				'order'				=> $order,
				'include'			=> '',
				'exclude'			=> '',
				'meta_query' 		=> array(
					array(
						'key'     => '_ikcf_feed_id',
						'value'   => $id
					),
					array(
						'key'     => '_ikcf_content_type',
						'value'   => $content_type
					),
					array(
						'key'     => '_ikcf_start_time',
						'compare' => '>=',
						'value'   => date("Y-m-d H:i:s", $date_range['event_start_date'])
					),
					array(
						'key'     => '_ikcf_end_time',
						'compare' => '<=',
						'value'   => date("Y-m-d H:i:s", $date_range['event_end_date'])
					)					
				),
				'post_type'			=> 'facebookpost',
				'post_mime_type'	=> '',
				'post_parent'		=> '',
				'post_status'		=> 'publish',
				'suppress_filters'	=> true 
			);
		} else {		
			$args = array(
				'posts_per_page'   => $num_posts,
				'offset'           => 0,
				'category'         => '',
				'category_name'    => '',
				'orderby'          => 'date',
				'order'            => 'DESC',
				'include'          => '',
				'exclude'          => '',
				'meta_query' => array(
					array(
						'key'     => '_ikcf_feed_id',
						'value'   => $id
					),
					array(
						'key'     => '_ikcf_content_type',
						'value'   => $content_type
					),
				),
				'post_type'        => 'facebookpost',
				'post_mime_type'   => '',
				'post_parent'      => '',
				'post_status'      => 'publish',
				'suppress_filters' => true 
			);
		}
		
		$feed = get_posts($args);
		
		//add comments to feed
		$feed = $this->loadFbCommentsFromWp($feed);
		
		if($graph_format){
			$feed = $this->formatAsGraphObject($feed);
		}
				
		return $feed;
	}
	
	//passed the array of facebook posts from get_posts
	//loops through posts and loads comments for each post
	//adds comment data to feed array and returns array
	function loadFbCommentsFromWp($feed = array()){		
		foreach($feed as $key => $feed_item){
			$args = array(
				'post_id' => $feed_item->ID,
				'orderby' => 'comment_date',
				'order' => 'ASC',
				'type' => 'ikfb_fb_comment'
			);
			$comments = get_comments($args);
			$feed[$key]->comments_array = $comments;
		}
		
		return $feed;
	}
	
	//passed an array of wordpress facebook custom post types
	//formats them as an array of graph api objects and returns that
	//this will preserve functionality with the 2.0 way of IKFB
	//TBD: Events, Comments, Photo Albums, Shares, From Data
	function formatAsGraphObject($feed = array()){		
		$formatted_feed = array();
		$i = 0;
		
		foreach($feed as $feed_item){
			$post_id = $feed_item->ID;
			$post_meta = get_post_meta($post_id);
			
			$formatted_feed[$i]['id'] = $post_meta['_ikcf_fbid'][0];
			$formatted_feed[$i]['from'] = (object) array(
				'name' => '',
				'category' => '',
				'id' => '',
			);
			
			$formatted_feed[$i]['name'] = $feed_item->post_title;
			$formatted_feed[$i]['message'] = $feed_item->post_content;
			$formatted_feed[$i]['picture'] = array_key_exists("_thumbnail_id", $post_meta) ? wp_get_attachment_url($post_meta['_thumbnail_id'][0]) : "";//convert attachment ID into URL
			$formatted_feed[$i]['link'] = $post_meta['_ikcf_link'][0];
			$formatted_feed[$i]['name'] = $feed_item->post_title;
			$formatted_feed[$i]['caption'] = $post_meta['_ikcf_caption'][0];
			$formatted_feed[$i]['description'] = $post_meta['_ikcf_description'][0];
			$formatted_feed[$i]['type'] = $post_meta['_ikcf_content_type'][0];//ie Event
			$formatted_feed[$i]['item_type'] = $post_meta['_ikcf_item_type'][0];//ie Video or nothing
			$formatted_feed[$i]['item_source'] = $post_meta['_ikcf_content_source'][0];//ie Path to Video or nothing
			$formatted_feed[$i]['status_type'] = $post_meta['_ikcf_status_type'][0];
			$formatted_feed[$i]['created_time'] = $post_meta['_ikcf_created_time'][0];
			$formatted_feed[$i]['updated_time'] = $post_meta['_ikcf_updated_time'][0];
			$formatted_feed[$i]['comments'] = $feed_item->comments_array;			
			$formatted_feed[$i]['num_likes'] = $post_meta['_ikcf_like_count'][0];	
			$formatted_feed[$i]['num_comments'] = $post_meta['_ikcf_comment_count'][0];
			
			//pass back content type
			$formatted_feed[$i]['content_type'] = $post_meta['_ikcf_content_type'][0];
			
			//event data
			$formatted_feed[$i]['start_time'] = $post_meta['_ikcf_start_time'][0];
			$formatted_feed[$i]['end_time'] = $post_meta['_ikcf_end_time'][0];
			$formatted_feed[$i]['location'] = $post_meta['_ikcf_location'][0];
			$formatted_feed[$i]['timezone'] = $post_meta['_ikcf_timezone'][0];
			
			//retained WP data
			$formatted_feed[$i]['wp_post_id'] = $post_id;
			
			//convert array to object
			$formatted_feed[$i] = (object) $formatted_feed[$i];
			
			$i++;
		}			
		
		return $formatted_feed;
	}
	
	/* End Internal Storage and Retrieval */
	
	/* IK Social Pro */
	//returns true if current item is written by the page owner
	function is_page_owner($item,$page_data){
		//only hide items if the option is toggled
		if(get_option('ik_fb_only_show_page_owner') && $this->is_pro){
			if($item->from->id == $page_data->id){
				return true;
			}
			return false;
		} else {
			return true;
		}
	}
	
	//inserts avatars into the message content, if option is enabled
	function pro_user_avatars($content = "", $item = array()){			
		if($this->is_pro && get_option('ik_fb_show_avatars') && isset($item->from->id) && strlen($item->from->id) > 2){				
			//$picture = $this->root->fetchUrl("https://graph.facebook.com/{$item->from->id}/picture", true);
			
			$content .= "<img src=\"https://graph.facebook.com/{$item->from->id}/picture\" class=\"ikfb_user_avatar\" alt=\"avatar\"/>";
		}
		
		return $content;
	}
	
	//insert comment info into feed, if enabled
	function pro_comments($item, $the_link){	
		//initial option calls
		$show_avatars = get_option('ik_fb_show_avatars', false);
		$show_date = get_option('ik_fb_show_date', false);
		$show_likes = get_option('ik_fb_show_likes', false);
	
		$comment_output = "";

		if($this->is_pro){	
			if(get_option('ik_fb_show_reply_counts')){					
				$num_comments = 0;			
			
				if(!empty($item->num_comments)){
					$num_comments = $item->num_comments;
				}	
				
				if($num_comments > 0){				
					$comment_string = "comment";
					
					if($num_comments > 1){
						$comment_string = "comments";
					}
					
					$comment_output = '<a href="'.$the_link.'" target="_blank" class="ikfb_comments" title="Click To Read On Facebook">' . $num_comments . ' ' . $comment_string . '</a>';
				}
			}

			if(get_option('ik_fb_show_replies')){	
				$has_comments = false;
				
				if(isset($item->comments)){
					$comment_list = '<ul class="ikfb_comment_list">';
					
					//list of comments per feed item
					foreach($item->comments as $comment){
						if(isset($comment->comment_content)){
							$comment_list .= '<li class="ikfb_comment">';
							//show avatars, if enabled
							
							if($show_avatars){								
								$picture = get_comment_meta($comment->comment_ID, 'wpsp_avatar', true);
							
								$comment_avatar = "<img src=\"{$picture}\" class=\"ikfb_user_comment_avatar\" alt=\"avatar\"/>";
								
								$comment_list .= $comment_avatar;
							}
							
							$comment_list .= isset($comment->comment_author) ? '<p class="ikfb_comment_message"><span class="ikfb_comment_author">' . $comment->comment_author . ' says:</span> ' : '';
							$comment_list .= nl2br($comment->comment_content,true) . '</p>';
							
							//output date, if option to display it is enabled
							if($show_date){
								if(strtotime($comment->comment_date) >= strtotime('-1 day')){
									$date = $this->root->humanTiming(strtotime($comment->comment_date)). " ago";
								}else{
									$date = date('F jS', strtotime($comment->comment_date));
								}
							
								if(strlen($date)>2){
									$comment_list .= '<p class="ikfb_comment_date">' . $date . '</p>';
								}
							}	
							
							//ouput number of likes, if option to show them are enabled
							if($show_likes){	
								$like_count = get_comment_meta($comment->comment_ID, 'wpsp_num_likes', true);
								
								if($like_count > 0){
									$like_string = "person likes";
									if($like_count > 1){
										$like_string = "people like";
									}
									$comment_list .= '<p class="ikfb_comment_likes">' . $like_count . ' ' . $like_string . ' this.</p>';
								}
							}
							
							$comment_list .= '<span class="ikfb_clear"></span>';
							
							$comment_list .= '</li>';
							
							$has_comments = true;
						}
					}
					
					$comment_list .= '</ul>';
				}
				
				if($has_comments){
					$comment_output .= $comment_list;
				}
			}
		}
		
		return $comment_output;
	}
	
	//insert like info into feed, if enabled
	function pro_likes($item, $the_link){	
		
		$likes = "";
		if(get_option('ik_fb_show_likes')){	
			
			//load likes from item object			
			$num_likes = 0;
			
			if(!empty($item->num_likes)){	
				$num_likes = $item->num_likes;
			}	
			
			if($num_likes > 0){				
				$like_string = "like";
				
				if($num_likes > 1){
					$like_string = "likes";
				}
				
				$likes = '<a href="'.$the_link.'" target="_blank" class="ikfb_likes">' . $num_likes . ' ' . $like_string . '</a> ';
			}
		}
		
		return $likes;
	}
	
	/* End IK Social Pro */
	
	/* Shortcode Functions */
	
	//output the like button, shortcode
	function ik_fb_output_like_button_shortcode($atts){		
		//load shortcode attributes into an array
		extract( shortcode_atts( array(
			'url' => site_url(),
			'height' => '45',
			'colorscheme' => 'light'
		), $atts ) );
		
		return $this->ik_fb_like_button($url,$height,$colorscheme);
	}
	
	//output the feed, shortcode
	function ik_fb_output_feed_shortcode($atts){
		//load shortcode attributes into an array
		$merged_atts = shortcode_atts( array(
			'colorscheme' => 'light',
			'width' => '', /* old, should no longer appear */
			'height' => '', /* old, should no longer appear */
			'feed_image_width' => get_option('ik_fb_feed_image_width'),
			'feed_image_height' => get_option('ik_fb_feed_image_height'),
			'use_thumb' => !get_option('ik_fb_fix_feed_image_width') && !get_option('ik_fb_fix_feed_image_height'),
			'num_posts' => null,
			'id' => false,
			'show_errors' => false,
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
		), $atts );
		
		extract( $merged_atts );
		
		/* Previously, width and height referred to feed_image_width and feed_image_height, respectively.
		 * Check for those attributes here, to maintain compatibility */
		if (!empty($width)) {
			$feed_image_width = $width;
		}		
		if (!empty($height)) {
			$feed_image_height = $height;
		}

		return $this->ik_fb_output_feed($colorscheme, $use_thumb, $feed_image_width, false, $feed_image_height, $num_posts, $id, $show_errors, $header_bg_color, $window_bg_color);
	}
	
	//output the photo gallery, shortcode
	function ik_fb_output_gallery_shortcode($atts){			
		//load shortcode attributes into an array
		extract( shortcode_atts( array(
			'id' => '',
			'size' => '320x180',
			'show_name' => true,
			'title' => null,
			'num_photos' => false
		), $atts ));
		
		return $this->ik_fb_output_gallery($id, $size, $show_name, $title, $num_photos);				
	}
	
	/* End Shortcode Functions */
	
	/* IKFB HTML Building Functions */
	
	//generates the photo gallery HTML
	//caches the output in a transient
	//also tracks the last created time 
	//	so future requests don't reload previous photos
	//TBD: option to flush this cache
	//TBD: handle photo manually deleted from before the timestamp
	public function ik_fb_output_gallery($id = '', $size = '320x180', $show_name = true, $the_title = null, $num_photos = false){
		//enqueue thickbox css and javascript
		add_thickbox();	
		
		$output = '';
		
		// It wasn't there, so regenerate it					
		//see if a limit is set in the options, if one wasn't passed via shortcode
		if(!$num_photos){
			$limit = get_option('ik_fb_photo_feed_limit');
	
		} else {
			$limit = $num_photos;
		}
		
		//make sure its really a number, otherwise we default to 25
		if(!is_numeric($limit)){				
			$limit = 25;
		}
		
		$gallery = $this->loadFacebookGallery($id, $limit);
		
		ob_start();
		
		echo '<div class="ik_fb_gallery_standard">';
		
		if(isset($the_title)){
			echo '<span class="ik_fb_gallery_standard_title">' . $the_title . '</span>';
		}
		
		//if its empty the first time, try resetting the timestamp and loading again
		if(empty($gallery)){			
			//set new last_timestamp
			//TBD: ability to flush gallery loads
			set_transient( $id . '_ikfb_gallery_timestamp', 0, 0 );
		
			$gallery = $this->loadFacebookGallery($id, $limit);
		}
		
		//gallery succesfully loaded, build and output
		if(!empty($gallery)){			
			foreach($gallery as $gallery_item){				
				if(!empty($gallery_item->guid)){	
					echo '<div class="ik_fb_gallery_item ik_fb_gallery_'.$size.'">';
					
					//the "name" of the image is actually used in a caption/description context
					//the caption/description is stored in the body
					$name = "";
					if(!empty($gallery_item->post_content)){
						$name = $gallery_item->post_content;
					}
					//adjust length based on settings
					$name = wp_trim_words($name, get_option('ik_fb_description_character_limit', 55));
					
					echo '<a href="'.htmlentities($gallery_item->guid).'?TB_iframe=true" class="thickbox" rel="ikfb_photo_gallery_'.$id.'" target="_blank" title="' . htmlentities($name) . '"><img class="ik_fb_standard_image" src="'.$gallery_item->guid.'" alt="' . htmlentities($name) . '" /></a>';
				
					if($show_name){
						echo '<p class="ik_fb_standard_image_name">'.$name.'</p>';
					}
					
					echo '</div>';
				}
				
			}
		} else {
			//we were unable to load any photos
			//this could mean there is a bad ID
			//it could mean we couldn't connect to the API
			//it could mean the photos were deleted
			//wipe the last_timestamp and output a message.
			
			//set new last_timestamp
			//TBD: ability to flush gallery loads
			set_transient( $id . '_ikfb_gallery_timestamp', 0, 0 );
			echo '<p class="ik_fb_error">'.__('WP Social: Album Empty.  Please check again in a few minutes.', 'ik-facebook').'</p>';
		}
		
		echo '</div>';
		
		//encode the output so we can store it as a transient
		$output = ob_get_contents();
		
		ob_end_clean();
			
		//be sure to decode the output before returning!
		return $output;
	}

	//generates the like button HTML
	function ik_fb_like_button($url, $height = "45", $colorscheme = "light"){
		return '<iframe id="like_button" src="//www.facebook.com/plugins/like.php?href='.htmlentities(urlencode($url).'&layout=standard&show_faces=false&action=like&colorscheme='.$colorscheme.'&height='.$height).'"></iframe>';//add facebook like button
	}
		
	//generates the Feed HTML
	//for either an Event Feed
	//or for a comprehensive feed
	public function ik_fb_output_feed($colorscheme = "light", $use_thumb = true, $width = "", $is_sidebar_widget = false, $height = "", $num_posts = null, $id = false, $show_errors = false, $ik_fb_header_bg_color, $ik_fb_window_bg_color){
		// load the width and height settings for the feed. 
		// NOTE: the plugin uses different settings for the sidebar feed vs the normal (in-page) feed
		list($ik_fb_feed_width, $ik_fb_feed_height) = $this->get_feed_width_and_height($is_sidebar_widget);
		
		// Load the profile's feed items from the Graph API
		//RWG: removed caching from here as the feed data caching is handled inside GP_Facebook now
		$api_response = $this->loadFacebookFeed($id, $num_posts);
					
		$feed = isset($api_response['feed']) ? $api_response['feed'] : array();
		
		$page_data = isset($api_response['page_data']) ? $api_response['page_data'] : false;
		
		// feed could not be loaded. (likely cause: no page ID specified, or bad FB API ID/key)
		if ( empty($feed) ) {
			return '';
		}

		// setup the cache for the rest of the items
		$first_item_id = isset($feed[0]->id) ? $feed[0]->id : 'empty_feed';
		
		$feed = isset($api_response['feed']) ? $api_response['feed'] : array();		
		$the_link = $this->get_profile_link($page_data); // save a permalink to the Facebook profile. We'll need it in several places.
		$is_event = isset($page_data->start_time);

		// If there were no items and show_errors is OFF, we'll need to hide the feed
		$hide_the_feed = !$show_errors && !(count($feed) > 0);
	
		/** Start building the feed HTML now **/		
		// start with a template which contains the merge tag '{ik:feed}'
		$output = $this->build_feed_template($ik_fb_feed_width, $ik_fb_feed_height, $ik_fb_window_bg_color, $ik_fb_header_bg_color, $hide_the_feed);

		$image_html = $this->get_profile_photo_html($page_data); 
		$output = str_replace('{ikfb:image}', $image_html, $output);

		// {ikfb:link} merge tag - adds the profile title to the top of the feed (which is linked to the profile on Facebook)
		// NOTE: this is controlled by the "title" setting in the options, and can be disabled (meaning we would merge in a blank string)
		$title_html = $this->get_profile_title_html($page_data);
		$output = str_replace('{ikfb:link}', $title_html, $output);

		// {ikfb:like_button} merge tag - adds the Like button (for the profile itself, not the individual feed items)
		// NOTE: events cannot have like buttons, so they'll get a string with the event's Start/End Times and location instead
		$like_button_html = $this->get_feed_like_button_html($page_data, $is_event, $the_link, $colorscheme);
		$output = str_replace('{ikfb:like_button}', $like_button_html, $output);	

		// {ikfb:feed} merge tag - adds the actual line items
		// NOTE: this can be an error message, if the feed is empty and $show_errors = true
		$feed_items_html = $this->get_feed_items_html($feed, $page_data, $use_thumb, $width, $height, $the_link, $show_errors);
		$output = str_replace('{ikfb:feed}', $feed_items_html, $output);

		// All done! Return the HTML we've built.
		// TODO: add a hookable filter on $output
		return $output;		
	}

	public function get_feed_width_and_height($is_sidebar_widget = false){
		//use different heigh/width styling options, if this is the sidebar widget
		if($is_sidebar_widget) {
			// This is the sidebar widget, so load the sidebar feed settings
			$ik_fb_feed_height = strlen(get_option('ik_fb_sidebar_feed_window_height')) > 0 && !get_option('ik_fb_use_custom_html') ? get_option('ik_fb_sidebar_feed_window_height') : '';
			$ik_fb_feed_width = strlen(get_option('ik_fb_sidebar_feed_window_width')) > 0 && !get_option('ik_fb_use_custom_html') ? get_option('ik_fb_sidebar_feed_window_width') : '';
				
			if($ik_fb_feed_width == "OTHER"){
				$ik_fb_feed_width = str_replace("px", "", get_option('other_ik_fb_sidebar_feed_window_width')) . "px";
			}			
			
			if($ik_fb_feed_height == "OTHER"){
				$ik_fb_feed_height = str_replace("px", "", get_option('other_ik_fb_sidebar_feed_window_height')) . "px";
			}
		}
		else {
			// this is the normal (non-widget) feed, so load the normal settings
			$ik_fb_feed_height = strlen(get_option('ik_fb_feed_window_height')) > 0 && !get_option('ik_fb_use_custom_html') ? get_option('ik_fb_feed_window_height') : '';
			$ik_fb_feed_width = strlen(get_option('ik_fb_feed_window_width')) > 0 && !get_option('ik_fb_use_custom_html') ? get_option('ik_fb_feed_window_width') : '';
				
			if($ik_fb_feed_width == "OTHER"){
				$ik_fb_feed_width = str_replace("px", "", get_option('other_ik_fb_feed_window_width')) . "px";
			}
			
			if($ik_fb_feed_height == "OTHER"){
				$ik_fb_feed_height = str_replace("px", "", get_option('other_ik_fb_feed_window_height')) . "px";
			}		
		}
		return array($ik_fb_feed_width, $ik_fb_feed_height);		
	}
	
	public function build_feed_template($ik_fb_feed_width, $ik_fb_feed_height, $ik_fb_window_bg_color, $ik_fb_header_bg_color, $is_error = false){
		//feed window width
		$custom_styling_1 = ' style="';
		if(strlen($ik_fb_feed_width)>0){
			$custom_styling_1 .= "width: {$ik_fb_feed_width};";
		}	
		if(strlen($ik_fb_feed_height)>0){		
			$custom_styling_1 .= "height: auto; ";
		}
		$custom_styling_1 .= '"';
		
		//feed window height, feed window bg color
		$custom_styling_2 = ' style="';
		if(strlen($ik_fb_feed_height)>0){		
			$custom_styling_2 .= "height: {$ik_fb_feed_height}; ";
		}
		if(strlen($ik_fb_window_bg_color)>0){
			$custom_styling_2 .= " background-color: {$ik_fb_window_bg_color};";
		}	
		
		$custom_styling_2 .= '"';
		
		//feed heading bg color
		$custom_styling_3 = ' style="';
		if(strlen($ik_fb_header_bg_color)>0){
			$custom_styling_3 .= "background-color: {$ik_fb_header_bg_color};";
		}
		$custom_styling_3 .= '"';	
		
		// if the user has specified custom feed HTML, use that. Else, use our default HTML
		$use_custom_html = strlen(get_option('ik_fb_feed_html')) > 2 && get_option('ik_fb_use_custom_html');
		if ($use_custom_html) {
			// use custom HTML as specified in the options panel
			$template_html = get_option('ik_fb_feed_html');
		} else {
			// use default HTML
			$template_html = '<div id="ik_fb_widget" {custom_styling_1} ><div id="ik_fb_widget_top" {custom_styling_3} ><div class="ik_fb_profile_picture">{ikfb:image}{ikfb:link}</div>{ikfb:like_button}</div><ul class="ik_fb_feed_window" {custom_styling_2} >{ikfb:feed}</ul></div>';
		}
		
		// if there was an error, force custom_styling_2 to display: none
		if ($is_error) {
			$custom_styling_2 = 'style="display:none;"';
		}
		
		// replace the the custom styling merge tags (if present) and return the output
		$output = $template_html;
		$output = str_replace('{custom_styling_1}', $custom_styling_1, $output);
		$output = str_replace('{custom_styling_2}', $custom_styling_2, $output);
		$output = str_replace('{custom_styling_3}', $custom_styling_3, $output);
		return $output;
	}
	
	//generates the Profile Photo HTML
	public function get_profile_photo_html($page_data){
		if(get_option('ik_fb_show_profile_picture')){
			//use the username if available, otherwise fallback to page ID
			if(isset($page_data->username)){
				$replace = "<img src=\"https://graph.facebook.com/{$page_data->username}/picture\" alt=\"profile picture\"/>";
			} else if(isset($page_data->id)){
				$replace = "<img src=\"https://graph.facebook.com/{$page_data->id}/picture\" alt=\"profile picture\"/>";
			} else { //bad ID has been input, lets try not to crap out
				$replace = '';
			}
		} else {
			$replace = '';
		}	
		return $replace;
	}
	
	//generates the Profile Title HTML, for the Feed
	public function get_profile_title_html($page_data){
		$url = $this->get_profile_link($page_data);
		if(get_option('ik_fb_show_page_title') && isset($page_data->name))
		{
			// return a link to the profile, with the text inside wrapped in span.ik_fb_name
			return '<a target="_blank" href="' . $url . '"><span class="ik_fb_name">' . $page_data->name . '</span></a>';	
		} else {
			// user has disabled the feed title in the settings, or no page name was set, so return a blank string
			return '';
		}
	}
	
	//generates the HTML for the Like Button (if a Normal feed)
	//generates Time and Date of the Event, if this is an Event feed
	public function get_feed_like_button_html($page_data, $is_event, $the_link, $colorscheme){
		if(!$is_event){
			// This is a normal feed! (not an event)
			// only show like button if enabled in settings
			if(get_option('ik_fb_show_like_button')){
				return $this->ik_fb_like_button($the_link, "45", $colorscheme);
			} else {
				return '';
			}
		} else {
			// This is an event! Events don't allow like buttons, so output the date and time instead
			// TODO: allow the Date Formatting to be controlled by user
			return '<p class="ikfb_event_meta">' . $page_data->location . ', ' . date('M d, Y',strtotime($page_data->start_time)) . '<br/>' . $page_data->venue->street . ', ' . $page_data->venue->city . ', ' . $page_data->venue->country . '</p>';
		}		
	}
	
	//generates the HTML for the Feed
	//loops through feed object and builds HTML for each line item
	public function get_feed_items_html($feed, $page_data, $use_thumb, $width, $height, $the_link, $show_errors){
		$feed_html = '';
		// if the feed contains items, build the HTML for each one and add it to $feed_html
		if(count($feed)>0) {
			foreach($feed as $item){//$item is the feed object				
				$feed_html .= $this->buildFeedLineItem($item, $use_thumb, $width, $page_data, $height, $the_link, $page_data->id);
			}
		} else {
			// if there was nothing in the feed, show an error instead (if error display is enabled)
			if($show_errors){
				$feed_html = "<p class='ik_fb_error'>" . __('WP Social: Unable to load feed.', 'ik-facebook') . "</p>";
			}
		}
		return $feed_html;
	}

	/**
	 * Returns a permalink to the Facebook page. 
	 *
	 * @param  object $page_data The Facebook page's profile data, which contains its name and id
	 *
	 * @return string Permalink to the Facebook profile, or 'https://www.facebook.com/' if bad $page_data was passed
	 *
	 */
	public function get_profile_link($page_data){
		if(isset($page_data->name)) {
			$the_link = "https://www.facebook.com/pages/".urlencode($page_data->name)."/".urlencode($page_data->id);
		} else { //bad ID has been input, lets try not to crap out
			$the_link = "https://www.facebook.com/";
		}
		return $the_link;
	}
	
	//passed a FB Feed Item, builds the appropriate HTML
	//determines whether to build a Normal Feed item or an Event Feed item
	function buildFeedLineItem($item, $use_thumb, $width, $page_data, $height, $the_link = false){
		// load feed item template
		$default_feed_item_html = '<li class="ik_fb_feed_item">{ikfb:feed_item}</li>';		
		$feed_item_html = strlen(get_option('ik_fb_feed_item_html')) > 2 && get_option('ik_fb_use_custom_html') ? get_option('ik_fb_feed_item_html') : $default_feed_item_html;
		
		// Format the line item as either an Event or as a Normal Item (which could be a text post, photo, whatever)
		if(	( isset($item->link) && strpos($item->link,'http://www.facebook.com/events/') !== false ) || 
			( isset($item->content_type) && $item->content_type == "events" ) ) {			
			// Output this line item as an event
			$line_item_html = $this->build_event_feed_line_item_html($item);
		}
		else {
			// Output this line item normally (not an event)
			$line_item_html = $this->build_normal_feed_line_item_html($item, $page_data->id, $use_thumb, $width, $height, $the_link);
		}

		// replace the {ikfb:feed_item} merge tag with the line item html
		$output = str_replace('{ikfb:feed_item}', $line_item_html, $feed_item_html);	
		
		// TODO: Add a hookable filter?
		return $output;
	}
	
	//passed an FB Event Feed Item, builds the appropriate HTML
	public function build_event_feed_line_item_html($item){		
		//default to empty
		$line_item = '';	
		
		$event_id = $item->id;
		
		//for content type not explicitly set to an event
		//yet we've previously detected the event link pattern in (which is why we are inside this function)
		//parse the event ID from the link
		if(	isset($item->link) &&
			$item->content_type != "events" ) 
		{		
			$event_id = explode('/',$item->link);
			$event_id = $event_id[4];
		}
		
		if($event_id) {			
			$event_data = $item;
			
			//add avatar for pro users
			if($this->is_pro){		
				$line_item = $this->pro_user_avatars($line_item, $item) . " ";
			}
			
			//load event image source
			//acceptable parameters for type are: small, normal, large, square
			//default is small
			$event_image_size = get_option('ik_fb_event_image_size', 'small');
			$event_image = "http://graph.facebook.com/" . $event_id . "/picture?type={$event_image_size}";
			
			if(isset($item->name)){
				//event name
				$line_item = '<p class="ikfb_event_title">' . $line_item . $item->name . '</p>';
				
				$line_item .= $this->build_event_date_string($item);
				
				//event image					
				$line_item .= '<img class="ikfb_event_image" src="' . $event_image . '" alt="Event Image"/>';					
				
				//event description
				if(isset($item->description)){	
					//use mb_substr, if available, for 2 byte character support
					if(function_exists('mb_substr')){
						$event_description = mb_substr($item->description, 0, 250);
					} else {
						$event_description = substr($item->description, 0, 250);
					}
					$event_description .= __('... ', 'ik-facebook');
						
					$line_item .= '<p class="ikfb_event_description">' . $event_description . '</p>';
				}
				
				//event read more link
				$line_item .= '<p class="ikfb_event_link"><a href="http://facebook.com/events/'.urlencode($event_id).'" title="Click Here To Read More" target="_blank">Read More...</a></p>';
			}
		}
		return $line_item;	
	}
	
	//passed a "normal" (ie, non-event) Feed Item, builds the appropriate HTML
	public function build_normal_feed_line_item_html($item, $page_id, $use_thumb, $width, $height, $the_link){
		$default_story_html = '<p class="ik_fb_item_story">{ikfb:feed_item:story}</p>';
		$default_message_html = '<p class="ik_fb_item_message">{ikfb:feed_item:message}</p>';
		$default_image_html = '<p class="ik_fb_facebook_image">{ikfb:feed_item:image}</p>';	
		$default_video_html = '<p class="ik_fb_facebook_video">{ikfb:feed_item:video}</p>';	
		$default_caption_html = '<p class="ik_fb_facebook_link">{ikfb:feed_item:link}</p>';	
		
		$story_html = $default_story_html;//TBD: allow customization
		$message_html = strlen(get_option('ik_fb_message_html')) > 2 && get_option('ik_fb_use_custom_html') ? get_option('ik_fb_message_html') : $default_message_html;
		$image_html = strlen(get_option('ik_fb_image_html')) > 2 && get_option('ik_fb_use_custom_html') ? get_option('ik_fb_image_html') : $default_image_html;
		$video_html = strlen(get_option('ik_fb_video_html')) > 2 && get_option('ik_fb_use_custom_html') ? get_option('ik_fb_image_html') : $default_video_html;
		$caption_html = strlen(get_option('ik_fb_caption_html')) > 2 && get_option('ik_fb_use_custom_html') ? get_option('ik_fb_caption_html') : $default_caption_html;
			
		// capture the post's date (if one is set)
		$date = isset($item->created_time) ? $item->created_time : "";		

		$line_item = '';
		$replace = $message_output = $picture_output = "";
		$message_truncated = false;
		$photo_caption_truncated = false;
		$video_caption_truncated = false;
		
		//output the item message
		if(isset($item->message)){		
			list($message_output, $message_truncated) = $this->ikfb_build_message($item,$replace,$message_html);
		}
		
		//output the item story
		if(isset($item->story)){
			//parse story
			$story = nl2br(make_clickable(htmlspecialchars($item->story)));
			
			//add custom story styling from pro options
			//building story html
			if(!get_option('ik_fb_use_custom_html')){		
				$story_html = $this->ikfb_story_styling($story_html);
			}		
			
			//build story output
			$story_output = str_replace('{ikfb:feed_item:story}', $story, $story_html);		
			
			//attach the story to the front of the message
			$message_output = $story_output . $message_output;
		}
		
		//check for a video, and the option to display video, and output it, otherwise output the photo
		//currently can only do one or the other (not both!)
		if(isset($item->item_type) && $item->item_type == 'video' && get_option('ik_fb_show_feed_videos', 0)){
			list($media_output, $video_caption_truncated) = $this->ikfb_build_video($item, $replace, $video_html, $width, $height, $page_id);
		} else {//output the item photo
			list($media_output, $photo_caption_truncated) = $this->ikfb_build_photo($item, $replace, $image_html, $caption_html, $use_thumb, $width, $height, $page_id);
		}			
		
		//if set, show the picture and it's content before you show the message
		if(get_option('ik_fb_show_picture_before_message')){
			$line_item .= $media_output;
			$line_item .= $message_output;
		} else {
			$line_item .= $message_output;
			$line_item .= $media_output;				
		}

		//output a Read More link if either the photo caption, video caption, or the message body was truncated
		if($message_truncated || $photo_caption_truncated || $video_caption_truncated){
			$item_id = explode("_",$item->id);
		
			//determine if the link is onsite or offsite
			if(get_option("ik_fb_onsite_or_facebook", "read-more-onfacebook") == "read-more-onsite"){
				//links go onsite, use permalink
				$the_link = get_permalink($item->wp_post_id);	
			} else {
				//links go offsite, use facebook style link
				$the_link = "https://www.facebook.com/permalink.php" . htmlentities("?id=".urlencode($page_id)."&story_fbid=".urlencode($item_id[1]));				
			}
			
			$line_item .= ' <a href="'.$the_link.'" class="ikfb_read_more" target="_blank">'.__('Read More...', 'ik-facebook').'</a>';
		}	
		
		//output the item link	
		$link_html = $this->get_feed_item_link_html($item);
		$line_item .= str_replace('{ikfb:feed_item:link}', $link_html, $caption_html);	
		
		//only add the line item if there is content (i.e., a message, video, or a photo) to display
		if((strlen($line_item)>2))
		{
			
			//output Posted By... text, if option to display it is enabled
			$line_item .= $this->get_feed_item_posted_by_html($item);
			
			//output Posted By date, if option to display it is enabled
			$line_item .= $this->get_feed_item_post_date_html($date);
		
			//add likes, if pro and enabled
			if($this->is_pro){
				$line_item .= $this->pro_likes($item, $the_link);
			}
			
			//add comments, if pro and enabled
			if($this->is_pro){
				$line_item .= $this->pro_comments($item, $the_link);
			}	
		} 			
		return $line_item;
	}
	
	//passed a Feed Item, builds the Link HTML based on settings and content
	//this could go to the full sized photo, or to the item on Facebook
	//or it could link directly to the shared content (if shared from a website)
	public function get_feed_item_link_html($item){		
		$link_html = '';
		if(isset($item->link))
		{				
			if(!empty($item->caption) && !empty($item->picture)){
				$link_text = $item->caption; //some items have a caption	
			} else if(!empty($item->description) && empty($item->item_source)){//make sure this doesn't have a video, as those already display descriptions
				$link_text = $item->description; //some items have a description	
			} else {				
				$link_text = isset($item->name) ? $item->name : '';  //others might just have a name
			}
			
			//if the link text is using our auto-generated item titles (such as FB Post 29194481004_10153557745256005)
			//replace the text with the link address, as that looks slightly less bizarre
			$pos = strpos($link_text, 'FB Post');
			if($pos !== false){//FB Post found within string
				$link_text = $item->link;
			}
			
			// don't add the link if the link text isn't set
			if(strlen($link_text) > 1){
				// prevent validation errors
				$item->link = str_replace("&","&amp;",$item->link);
				
				$start_link = '<a href="'.htmlentities($item->link).'" target="_blank">';
				$end_link = '</a>';				
			
				// add custom link styling from pro options
				if(!get_option('ik_fb_use_custom_html')){
					$start_link = $this->ikfb_link_styling($item->link);
				}	
				
				$link_html = $start_link . $link_text. $end_link;	
			}
		}
		return $link_html;
	}

	//output "posted by" text (if it is enabled)
	public function get_feed_item_posted_by_html($item){
		$posted_by_text = '';
		if(get_option('ik_fb_show_posted_by'))
		{
			if(isset($item->from)){ //output the author of the item
				if(isset($item->from->name)){
					$from_text = $item->from->name;
				}
				
				if(strlen($from_text) > 1){
					$posted_by_text = '<p class="ikfb_item_author">' . __('Posted By ', 'ik-facebook') . $from_text . '</p>';
		
					//add custom posted by styling from pro options
					if(!get_option('ik_fb_use_custom_html')){		
						$posted_by_text = $this->ikfb_posted_by_styling($posted_by_text);
					}			
				}
			}
		}
		return $posted_by_text;
	}
	
	//output Posted By date, if option to display it is enabled
	//TBD: Allow user control over date formatting	
	public function get_feed_item_post_date_html($date) {
		if(get_option('ik_fb_show_date')){
			$ik_fb_use_human_timing = get_option('ik_fb_use_human_timing');
			if(strtotime($date) >= strtotime('-1 day') && !$ik_fb_use_human_timing){
				$date = $this->root->humanTiming(strtotime($date)). __(' ago', 'ik-facebook');
			}else{
				$ik_fb_date_format = get_option('ik_fb_date_format');
				$ik_fb_date_format = strlen($ik_fb_date_format) > 2 ? $ik_fb_date_format : "%B %d";
				$date = strftime($ik_fb_date_format, strtotime($date));
			}
		
			if(strlen($date)>2){
				$date = '<p class="date">' . $date . '</p>';
				
				//add custom date styling from  options
				if(!get_option('ik_fb_use_custom_html')){		
					$date = $this->ikfb_date_styling($date);
				}
			}
			return $date;
		}	
		else {
			return '';
		}
	}	
	
	//builds the video HTML, including caption
	function ikfb_build_video($item, $replace, $video_html, $width, $height, $page_id){
		//output to return
		$output = '';
		//whether or not we are using a short version of anything (ie, are character limits set in the options)
		$shortened = false;
		
		//videos are not hidden, get it together man!
		//video height/width will match the photo height / width, for consistency
		//maybe change labels to make more sense?
		if($width == "OTHER"){
			$width = get_option('other_ik_fb_feed_image_width');
		}
		
		if($height == "OTHER"){
			$height = get_option('other_ik_fb_feed_image_height');
		}
		
		$width = strlen($width)>0 ? 'width="'.$width.'"' : '';
		$height = strlen($height)>0 ? 'height="'.$height.'"' : '';
		
		$limit = get_option('ik_fb_description_character_limit');
	
		//look for youtube videos or fbcdn videos
		$youtube = strpos($item->item_source, 'youtube');
		//look to see if this is a FB hosted video (ie, an mp4)
		$fbcdn = strpos($item->item_source, 'fbcdn');
		
		if($youtube !== false){//youtube found within string		
			if ($url = parse_url($item->item_source)) {
				$item->item_source = sprintf(' %s://%s%s', $url['scheme'], $url['host'], $url['path']);
			}
			$replace = "<iframe {$width} {$height} src=\"{$item->item_source}\"></iframe>";
		} elseif($fbcdn !== false){
			$replace = "<video {$width} {$height} preload=\"auto\" controls=\"1\" muted=\"1\" src=\"{$item->item_source}\" poster=\"{$item->picture}\"></video>";
		} else {//not a usable video, fallback to a picture
			if(isset($item->picture) && strlen($item->picture) > 2){
				$photo_source = $item->picture;			
				$photo_link = $item->picture;
				
				if(get_option('ik_fb_link_photo_to_feed_item')){
					$item_id = explode("_",$item->id);
					$photo_link = "https://www.facebook.com/permalink.php?id=".urlencode($page_id)."&story_fbid=".urlencode($item_id[1]);
				}
				
				$replace = "<a href=\"{$photo_link}\" target=\"_blank\"><img {$width} {$height} src=\"{$photo_source}\" /></a>";
			}
		}
		
		$output .= str_replace('{ikfb:feed_item:video}', $replace, $video_html);
	
		//add the text for description
		if(isset($item->description)){
			$output .= $this->buildDescription($item->description, $shortened);
		}	
				
		return array( $output, $shortened);
	}
	
	//passed the description
	//builds the description based on settings
	//tracks whether or not we shortened the description
	function buildDescription($description, &$shortened){		
		$default_description_html = '<p class="ik_fb_facebook_description">{ikfb:feed_item:description}</p>';	
		$description_html = strlen(get_option('ik_fb_description_html')) > 2 && get_option('ik_fb_use_custom_html') ? get_option('ik_fb_description_html') : $default_description_html;	
		
		$replace = $description;	

		//if a character limit is set, here is the logic to handle that
		$limit = get_option('ik_fb_description_character_limit');
		if(is_numeric($limit)){
			//only perform changes on posts longer than the character limit
			if(strlen($replace) > $limit){
				//remove words beyond limit						
				$replace = wp_trim_words($replace,$limit);
			
				$shortened = true;
			}
		}					
	
		//add custom image styling from pro options
		if(!get_option('ik_fb_use_custom_html')){		
			$description_html = $this->ikfb_description_styling($description_html);
		}	
		
		return str_replace('{ikfb:feed_item:description}', $replace, $description_html);
	}
		
	//builds the photo html, including caption
	function ikfb_build_photo($item, $replace="", $image_html, $caption_html, $use_thumb, $width, $height, $page_id = ''){
		//output to return
		$output = '';
		//whether or not we are using a short version of anything (ie, are character limits set in the options)
		$shortened = false;		
			
		//check to see if the option to display feed images is set
		//if not, skip doing any of the below
		//this option was originally created as 'hide feed images', but has been updated to be used as 'show feed images'
		//as a result, the option name is still 'hide feed images' but we treat it as meaning 'show feed images', throughout the cod
		if(!get_option('ik_fb_hide_feed_images')){
			$replace = '';
		} else {					
			$page_id = strlen($page_id) > 2 ? $page_id : $item->from->id;
			
			//need info about full sized photo for linking purposes
			//get the item id
			$item_id = isset($item->object_id) ? $item->object_id : -999;
			
			//set the vars
			$photo_link = $item->picture;
			$photo_source = $item->picture;
			
			//don't add photos with a bad src
			if(strlen($photo_source) < 2){
				return;
			}
			
			if(get_option('ik_fb_link_photo_to_feed_item')){
				$item_id = explode("_",$item->id);
				$photo_link = "https://www.facebook.com/permalink.php?id=".urlencode($page_id)."&story_fbid=".urlencode($item_id[1]);
			}

			//output the images
			//if set, load the custom image width from the options page
			if(!$use_thumb){			
				if($width == "OTHER"){
					$width = get_option('other_ik_fb_feed_image_width');
				}
				
				if($height == "OTHER"){
					$height = get_option('other_ik_fb_feed_image_height');
				}
				
				//source: tim morozzo
				if (isset($item->description) && strlen($item->description) >5){
					$title = $item->description;
				}elseif(isset($item->message)){
					$title = $item->message;
				}else{ 
					$title = __('Click for fullsize photo', 'ik-facebook');
				}
				
				$limit = get_option('ik_fb_description_character_limit');
				
				if(is_numeric($limit)){
					if(strlen($title) > $limit){
						//remove characters beyond limit							
						//use mb_substr, if available, for 2 byte character support
						if(function_exists('mb_substr')){
							$title = mb_substr($title, 0, $limit);
						} else {
							$title = substr($title, 0, $limit);
						}
						$title .= __('... ', 'ik-facebook');

						$shortened = true;
					}
				}
				
				//replace ampersands and equal signs, and whatever else
				$photo_link = str_replace("&","&amp;",$photo_link);
				
				$width = strlen($width)>0 ? 'width="'.$width.'"' : '';
				$height = strlen($height)>0 ? 'height="'.$height.'"' : '';
				
				$replace = '<a href="'.$photo_link.'" title="'.htmlspecialchars($title, ENT_QUOTES).'" target="_blank"><img '.$width.' '.$height.' src="'.$photo_source.'" alt="'.htmlspecialchars($title, ENT_QUOTES).'"/></a>';
					
				$output .= str_replace('{ikfb:feed_item:image}', $replace, $image_html);						
			} else {						
				//ampersands!
				$item->picture = str_replace("&","&amp;",$item->picture);

				//courtesy of tim morozzo
				if (isset($item->description) && strlen($item->description) >5){
					$title = $item->description;
				} elseif (isset($item->message)){
					$title = make_clickable($item->message);
				} else { 
					$title = __('Click for fullsize photo', 'ik-facebook');
				}
				//otherwise, use thumbnail
				$replace = '<a href="'.$photo_link.'" target="_blank"><img src="'.$item->picture.'" title="'.$title.'"></a>';
						
				$output .= str_replace('{ikfb:feed_item:image}', $replace, $image_html);	
			}

			//add the text for photo description
			if(isset($item->description)){
				$output .= $this->buildDescription($item->description, $shortened);	
			}
		}//end show feed images check
		
		return array( $output, $shortened);
	}
	
	//builds the feed message, which could be a text post the user typed
	//or it could be the caption that appears below a Photo
	//returns the HTML for display
	function ikfb_build_message($item,$replace="",$message_html){
		$shortened = false;		
	
		//add avatar for pro users
		if($this->is_pro){		
			$replace = $this->pro_user_avatars($replace, $item) . " ";
		}		
		
		$message = $item->message;
		
		//if a character limit is set, here is the logic to handle that
		$limit = get_option('ik_fb_character_limit');
		if(is_numeric($limit)){
			//only perform changes on posts longer than the character limit
			if(strlen($message) > $limit){
				//remove characters beyond limit
				$message = wp_trim_words($message, $limit);
				
				$shortened = true;
			}
		}
		
		$message = make_clickable($message);
		
		$replace = $replace . $message;
		
		//add custom message styling from pro options
		if(!get_option('ik_fb_use_custom_html')){		
			$message_html = $this->ikfb_message_styling($message_html);
		}	
		
		$output = str_replace('{ikfb:feed_item:message}', $replace, $message_html);			
		
		return array($output, $shortened);
	}
	
	//takes a feed object and removes various items
	//based on settings, items such as Stories may or may not be shown
	//returns an object that only contains valid items
	function trim_feed($feed_items, $limit){
		$valid_items = array();
		
		// sometimes $limit comes in as -1, meaning unlimited
		if ($limit < 0) {
			$limit = count($feed_items);
		}
		
		foreach ($feed_items as $item)
		{
			// see if this item is a "keeper"; if so, add it to our list
			if ($this->feed_item_is_valid($item)) {
				$valid_items[] = $item;
			}
			
			// if we have enough vaild items by now, stop the loop early
			if (count($valid_items) >= $limit) {
				break;
			}		
		}			
					
		// return whatever we have (somewhere between 0 and $limit items)
		return $valid_items;
	}
	
	//determines if a specific item is valid
	function feed_item_is_valid($item){
		// throw out anything that's a "story" (i.e., "John Doe liked a photo")
		// only do this if option is set to hide them
		if(!get_option('ik_fb_show_stories',1)){
			if (isset($item->story)) {
				return false;
			}
		}
		
		//  TODO: add other validation rules based on the user's settings (i.e., "Hide Photos" or "Show Only Events")
		
		// passed all rules, so return true
		return true;
	}
	
	/*
	 * Looks for $tag in $haystack, and inserts $replacement in its place
	 * NOTE: this function currently assumes an HTML tag preceeds $tag
	 *
	 * @param	$tag		The string to match. $replace is inserted here
	 * @param	$replace	The string to match. $replace is inserted here
	 * @param	$haystack	The string to search, and to insert $replace into
	 *
	 * @returns	string		$haystack, with $tag replaced by $replace
	 */
	 function replace_ikfb_merge_tag($tag, $replace, $haystack){	
		//find the position of the search string in the haystack
		$position = strpos($haystack, $tag);
		
		// if we don't find it, return the original string
		// NOTE: we would usually use === here, but in this case 0 would 
		//		 be invalid as well, so we use == instead
		if ($position == FALSE) {
			return $haystack;
		}
		
		// Move back one character from that position, and insert our string
		// NOTE: we are assuming a closing bracket to some HTML tag here
		// TODO: Let's not assume an HTML tag! (maybe add a <span> instead?)
		return substr_replace($haystack, $replace, $position - 1, 0);
	}
	
	/* End HTML Building Functions */
	
	/* Styling */
	
	//inserts any selected custom styling options into the feed's message html
	//load custom style options from Pro Plugin, if available
	function ikfb_message_styling($message_html = ""){
		$css = sprintf(' style="%s"', $this->root->build_typography_css('ik_fb_'));
		$tag = '{ikfb:feed_item:message}';
		return $this->replace_ikfb_merge_tag($tag, $css, $message_html);
	}
	
	//inserts any selected custom styling options into the feed's message html
	//load custom style options from Pro Plugin, if available
	function ikfb_story_styling($story_html = ""){
		$css = sprintf(' style="%s"', $this->root->build_typography_css('ik_fb_story_'));
		$tag = '{ikfb:feed_item:story}';
		return $this->replace_ikfb_merge_tag($tag, $css, $story_html);
	}
	
	//inserts any selected custom styling options into the feed's link
	//$replace = <p class="ik_fb_facebook_link">{ikfb:feed_item:link}</p>
	function ikfb_link_styling($item_link = "") {		
		//load our custom styling, to insert
		$css = $this->root->build_typography_css('ik_fb_link_');
		$style_attr = sprintf(' style="%s"', $css);
		$template = '<a href="%s" target="_blank" %s>';
		return sprintf($template, $item_link, $style_attr);
	}
	
	//inserts any selected custom styling options into the feed's posted by attribute
	//$line_item .= '<p class="ikfb_item_author">Posted By '.$from_text.'</p>';		
	function ikfb_posted_by_styling($line_item = ""){	
		$css = $this->root->build_typography_css('ik_fb_posted_by_');
		$style_attr = sprintf(' style="%s"', $css);		
		$tag = 'Posted By';
		return $this->replace_ikfb_merge_tag($tag, $style_attr, $line_item);
	}
	
	//inserts any selected custom styling options into the feed's date attribute
	function ikfb_date_styling($line_item = ""){	
		$css = $this->root->build_typography_css('ik_fb_date_');
		$style_attr = sprintf(' style="%s"', $css);		
		$tag = 'class="date"';
		return $this->replace_ikfb_merge_tag($tag, $style_attr, $line_item);
	}
	
	//inserts any selected custom styling options into the feed's description
	//$replace = $item->description;				
	function ikfb_description_styling($replace = ""){	
		$css = $this->root->build_typography_css('ik_fb_description_');
		$style_attr = sprintf(' style="%s"', $css);		
		$tag = '{ikfb:feed_item:description}';
		return $this->replace_ikfb_merge_tag($tag, $style_attr, $replace);
	}
	
	//inserts any selected custom styling options into the feed's powered by attribute	
	//$content = '<a href="https://illuminatikarate.com/ik-facebook-plugin/" target="_blank" id="ikfb_powered_by">Powered By WP Social Plugin</a>';	
	function ikfb_powered_by_styling($content = ""){		
		$css = $this->root->build_typography_css('ik_fb_powered_by_');
		$style_attr = sprintf(' style="%s"', $css);		
		$tag = 'id="ikfb_powered_by"';
		return $this->replace_ikfb_merge_tag($tag, $style_attr, $content);
	}
	
	/* End Styling */
	
	/* Scheduling */
	//activate the cron job
	function cron_activate(){
		wp_schedule_event( time(), 'hourly', 'scheduled_feed_update');
	}
	
	//deactivate the cron job when the plugin is deactivated
	function cron_deactivate(){
		wp_clear_scheduled_hook('scheduled_feed_update');
	}
	
	//setup scheduling
	function setup_schedule(){	
		//scheduling
		add_action('scheduled_feed_update', array($this, 'run_scheduled_feed_update'));

		//deactivate schedule when plugin is deactivated
		register_deactivation_hook( __FILE__, array($this, 'cron_deactivate' ));
	}
	
	//uses the globally set feed options
	function run_scheduled_feed_update(){			
		//load profile id(s)
		$profile_ids = get_option('ik_fb_page_id');
		
		//if not currently an array, make it one
		//should only happen the first time they use this screen
		if(!is_array($profile_ids)){
			$profile_ids = array(
				$profile_ids
			);							
		}
		
		//determine number of posts to load
		$limit = get_option('ik_fb_feed_limit', 25);
				
		//option might be set to an empty string
		if(strlen($limit)<1){
			$limit = 25;
		}
		
		//we are in a scheduled event so doing cron should be true
		$this->doing_cron = true;		
		
		foreach($profile_ids as $profile_id){
			//profile id, limit, is status test, is forced
			$this->loadFacebookFeed($profile_id, $limit, false, $this->force_reload);
		}
		
		//store last time this update ran
		update_option("ik_fb_last_scheduled_update", time());
		
		//all done, reset force flag if needed
		$this->force_reload = false;
	}
	/* End Scheduling */
}