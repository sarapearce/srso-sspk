var gold_plugins_init_selectable_code_boxes = function () {
	jQuery('.gp_code_to_copy').bind('click', function () {
		jQuery(this).select();
	});
};
	
jQuery(function () {
	gold_plugins_init_selectable_code_boxes();
}); 

//page IDs interface
jQuery(function () {
	//current feed IDs
	var feed_ids = jQuery("#ik_fb_page_id").val();

	//insert new fb id button
	var button_html = '<a href="" style="margin-top: 10px;" class="button" id="add_new_feed_id">Add New ID</a>';
	
	//new id input html
	var input_html = '<div class="ikfb_feed_id_wrap"><input type="text" value="" id="ik_fb_page_id[]" class="ik_fb_page_id" name="ik_fb_page_id[]"> <select id="ik_fb_page_id_types[]" name="ik_fb_page_id_types[]" class="ik_fb_page_id_type"><option value="default">News Feed</option><option value="events">Event Feed</option></select> <a href="" class="remove_this_id">X</a><br></div>';
	
	//insert our new button
	jQuery("#ikfb_multi_feed_ids .form-table .text_wrapper").append(button_html);
	
	//when clicked, insert a new text input
	jQuery("#add_new_feed_id").click(function() {
		jQuery(this).before(input_html);	
	
		//when remove is clicked, remove a text input
		jQuery(".remove_this_id").click(function() {
			jQuery(this).parent().remove();
			return false;
		});	
	
		jQuery('.ik_fb_page_id').change(function() {
			var new_val =  "ik_fb_page_id_types[" + jQuery(this).val() + "]";
			jQuery(this).parent().find('.ik_fb_page_id_type').attr("name", new_val).attr("id", new_val);
		});
		
		return false;
	});
	
	//when remove is clicked, remove a text input
	jQuery(".remove_this_id").click(function() {
		jQuery(this).parent().remove();
		return false;
	});		
	
	jQuery('.ik_fb_page_id').change(function() {
		var new_val =  "ik_fb_page_id_types[" + jQuery(this).val() + "]";
		jQuery(this).parent().find('.ik_fb_page_id_type').attr("name", new_val).attr("id", new_val);
	});
});

/* Feed Refresh */
jQuery(function () {	
	//when refresh is clicked, replace with a loading icon
	jQuery("#ik_fb_force_feed_reload").click(function() {
		var newHTML = '<img src="/wp-admin/images/loading.gif" alt="Loading..." />';
		jQuery(this).replaceWith(newHTML);
	});	
});

/* galahad */
var wps_replace_last_instance = function (srch, repl, str) {
	n = str.lastIndexOf(srch);
	if (n >= 0 && n + srch.length >= str.length) {
		str = str.substring(0, n) + repl;
	}
	return str;
}

var wps_submit_ajax_form = function (f) {
	var msg = jQuery('<p><span class="fa fa-refresh fa-spin"></span><em> One moment..</em></p>');	
	var f = jQuery(f).after(msg).detach();
	var enc = f.attr('enctype');
	var act = f.attr('action');
	var meth = f.attr('method');
	var submit_with_ajax = ( f.data('ajax-submit') == 1 );
	var ok_to_send_site_details = ( f.find('input[name="include_wp_info"]:checked').length > 0 );
	
	if ( !ok_to_send_site_details ) {
		f.find('.gp_galahad_site_details').remove();
	}
	
	var wrap = f.wrap('<form></form>').parent();
	wrap.attr('enctype', f.attr('enctype'));
	wrap.attr('action', f.attr('action'));
	wrap.attr('method', f.attr('method'));
	wrap.find('#submit').attr('id', '#notsubmit');

	if ( !submit_with_ajax ) {
		jQuery('body').append(wrap);
		setTimeout(function () {
			wrap.submit();
		}, 500);	
		return false;
	}
	
	data = wrap.serialize();
	
	$.ajax(act,
	{
		crossDomain: true,
		method: 'post',
		data: data,
		dataType: "json",
		success: function (ret) {
			var r = jQuery(ret)[0];
			msg.html('<p class="ajax_response_message">' + r.msg + '</p>');
		}
	});		
};

var wps_submit_ajax_contact_form = function (f) {
	$ = jQuery;
	
	// initialize the form
	var ajax_url = 'https://goldplugins.com/tickets/galahad/catch.php';
	//f.attr('action', ajax_url);
	
	// show 'one moment' emssage
	var msg = '<p><span class="fa fa-refresh fa-spin"></span><em> One moment..</em></p>';
	$('.gp_ajax_contact_form_message').html(msg);
	
	var f = jQuery(f).after(msg).detach();
	var enc = f.attr('enctype');
	var act = f.attr('action');
	var meth = f.attr('method');

	jQuery('body').append(f);	
	var wrap = f.wrap('<form></form>').parent();
	wrap.attr('enctype', f.attr('enctype'));
	wrap.attr('action', f.attr('action'));
	wrap.attr('method', f.attr('method'));	
	wrap.find('#submit').attr('id', '#notsubmit');

	setTimeout(function () {
		wrap.submit();
	}, 100);
	
	
	
	
	
	data = f.serialize();
	
	$.ajax(
		ajax_url,
		'post',
		data,
		function (ret) {
			alert(ret);
		}
	);
	return false; // prevent form from submitting normally
};

var wps_setup_contact_forms = function() {
	$ = jQuery;
	var forms = $('.gp_support_form_wrapper div[data-gp-ajax-contact-form="1"]');
	if (forms.length > 0) {
		forms.each(function () {
			var f = this;
			var btns = $(this).find('.button[type="submit"]').on('click', 
				function () {
					wps_submit_ajax_contact_form(f);
					return false;
				} 
			);
		});
	}
	jQuery('.gp_ajax_contact_form').on('submit', wps_submit_contact_form);
};

var wps_setup_ajax_forms = function() {
	$ = jQuery;
	var forms = $('div[data-gp-ajax-form="1"]');
	if (forms.length > 0) {
		forms.each(function () {
			var f = this;
			var btns = $(this).find('.button[type="submit"]').on('click', 
				function () {
					wps_submit_ajax_form(f);
					return false;
				} 
			);
		});
	}
};
jQuery(function () {
	wps_setup_ajax_forms();
	//wps_setup_contact_forms();
});