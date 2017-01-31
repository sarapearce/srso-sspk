=== WP Social ===
Contributors: richardgabriel, ghuger
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=V7HR8DP4EJSYN
Tags: facebook, Facebook Feed, facebook embed, Facebook Feed widgets, Facebook Feed embed, like button widget, facebook events
Requires at least: 3.0.1
Tested up to: 4.7
Stable tag: 3.0.3
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

WP Social is an easy-to-use plugin for adding a Custom Facebook Feeds of any Public Facebook Page to your site, with a shortcode or widget.

== Description ==

= IK Facebook is now WP Social! =

The WP Social Plugin is an **easy-to-use** plugin that allows users to add a **custom Facebook Feed widgets** to the sidebar, as a widget, or to embed the custom Facebook Feed widget into a Page or Post using the shortcode, of any Public Facebook Page.  The WP Social Plugin also allows you to insert a Facebook Like Button widget into the Page, Post, or theme.  The WP Social Plugin allows you to add Facebook Events, Photos, and Galleries to your website, too!


_"I searched forever for a simple plugin to display a public Facebook page pictures on my site. This plugin worked perfectly, and even better is the great support. Thanks so much Richard."_


_"Looks great, easy to use. Better than any of the other Facebook Feed plugins I've tried."_


= The WP Social Plugin is a great plugin for many uses, including: =

* Powering your blog with your Facebook Feed!
* Embed a Custom Facebook Feed widgets in your Sidebar or Footer
* Styling a Custom Facebook Feed, without the need for CSS!
* Custom HTML Options allow your Feeds to be displayed any way you like!
* Works with Facebook Groups and Facebook Pages!
* Adding a Facebook Like Button to your website!
* Showing Facebook Comments in your Feed!
* Adding a Facebook Photo Gallery!
* Show multiple different custom Facebook Feeds!
* Display Upcoming Facebook Events on your website!
* Use a Facebook Event instead of a Page - output a customized Feed from the Facebook Event's Wall!
* Custom Facebook Feed widgetss allows user to override Site Wide Options
* Ability to Pass Page ID Via Shortcode and Widget Allows Multiple Feeds on One Page!

= Upgrade To WP Social PRO For Advanced Features and Email Support =

The GoldPlugins team does not provide direct support for WP Social plugin on the WordPress.org forums. However, one-on-one email support is available to people who have upgraded to the premium edition, [WP Social Pro](http://goldplugins.com/our-plugins/wp-social-pro/?utm_source=wp&utm_campaign=desc_learn_more). In addition to outstanding support, WP Social Pro includes all kinds of new customization options, such as the ability to hide third party posts, use custom image sizes, and more. You should [upgrade today!](http://goldplugins.com/our-plugins/wp-social-pro/upgrade-to-wp-social-pro/?utm_source=wp&utm_campaign=desc_upgrade "Upgrade to WP Social Pro")

[Upgrade To WP Social Pro](http://goldplugins.com/our-plugins/wp-social-pro/upgrade-to-wp-social-pro/?utm_source=wp&utm_campaign=desc_upgrade2)

= User Friendly Features =
The WP Social Plugin includes options to set the Title of the custom Facebook Feed widgets, whether or not to show the Like Button above the custom Facebook Feed widgets, and whether or not to show the Profile Picture.  The WP Social Plugin supports both the Light and Dark color schemes for the Like Button widget and has multiple color schemes for the custom Facebook Feed widgets.  The WP Social Plugin allows you to pass the ID of the Facebook page via the shortcode - allowing you to display the feeds from multiple accounts on one page!  Many plugins require you to know CSS to style your custom Facebook Feed widgets - ours gives you full control over the output of your custom Facebook Feed, with Themes, Colorpickers, Options, and more!

= Professional Development =

The WP Social Plugin is a free version of [WP Social Pro](http://http://goldplugins.com/our-plugins/wp-social-pro/ "WP Social Pro") - WP Social Pro is a professionally developed WordPress plugin that integrates your Facebook Feeds into your WordPress website as custom widgets.  The WP Social Plugin receives regular updates.  With the WP Social Plugin, you can easily add **Search Engine Optimization friendly** content to your website -- the content exists on your site and is crawlable by search engines like Google!

= Powerful Customization =

The WP Social Plugin includes the option to set your own custom CSS for styling purposes or, if you prefer, the WP Social Plugin allows you to include a custom style sheet in your theme directory  -- either method is great for displaying a custom Facebook Feed widgets. The WP Social Plugin also allows the user to select from a few pre-made Feed Themes, to help generate their custom Facebook Feed widgets.  *Gone are the days of fighting with the Facebook Social Plugin!*

= More Than Just A Custom Facebook Feed - Events and Photo Galleries, too! =

The WP Social Plugin intends to support all types Facebook content - not just standard Feeds.  Currently, we have support for Facebook Events and Facebook Photo Galleries.  Show your Upcoming Facebook Events on your Website, and power your Photo Galleries with Facebook!

== Installation ==

This section describes how to install the plugin and get it working.

1. Upload the contents of `/ik-facebook/` to the `/wp-content/plugins/` directory
2. Activate the WP Social Plugin through the 'Plugins' menu in WordPress
3. [Click here](http://iksocialpro.com/installation-usage-instructions/configuration-options-and-instructions/ "Configuration Options and Instructions") for information on how to configure the plugin.

= How To Get an App ID and Secret Key From Facebook =

Watch this video to learn how to get an App ID and Secret Key for your Facebook Feed:
https://www.youtube.com/watch?v=JGY9mQkRxK0

You can also read our step-by-step tutorial on the process [here](http://goldplugins.com/documentation/wp-social-pro-documentation/how-to-get-an-app-id-and-secret-key-from-facebook/ "How To Get An App ID and Secret Key From Facebook")

Once you have your App ID and Secret Key, you will need to place them in the appropriate fields on the Settings page, for our plugin to work.

= Outputting the Facebook Event Feed =
* This is no different than outputting a normal Page Feed!  Just follow the instructions below and our plugin will detect what type of feed is being displayed.
* To Reverse the Order of Events, use the Reverse Event Order option on the Pro Event Options tab.  If you are using the Free version of the plugin, you'll need to [purchase WP Social Pro](http://goldplugins.com/our-plugins/wp-social-pro/ "Purchase WP Social Pro") first.

= Outputting the Facebook Feed =
* To output the custom Facebook Feed, place `[ik_fb_feed colorscheme="light" use_thumb="true" width="250" num_posts="5"]` in the body of a post, or use the Appearance section to add the The WP Social Plugin Widget to your Widgets area.  Valid choices for colorscheme are "light" and "dark"  If 'use_thumb' is set to true, the value of 'width' will be ignored.  If 'use_thumb' or 'width’ is not set, the values from the Options page will be used.  If id is not set, the shortcode will use the Page ID from your Settings page.  All of the options on the widget will use the defaults, drawn from the Settings page, if they aren't passed via the widget.
* You can also use the function `ik_fb_display_feed($colorscheme,$use_thumb,$width)` to display the custom Facebook Feed in your theme.

= Outputting the Facebook Like Button = 
* To output the Like Button, place `[ik_fb_like_button url="http://some_url" height="desired_iframe_height" colorscheme="light"]` in the body of a post.  Valid choices for colorscheme are "light" and "dark".
* You can also use the function `ik_fb_display_like_button($url_to_like,$height_of_iframe,$colorscheme)` to output a like button in your theme.

= Outputting a Facebook Photo Gallery = 
* To output a Photo Gallery, place `[ik_fb_gallery id="539627829386059" num_photos="25" size="130x73" title="Hello World!"]` in the body of a post.  If no size is passed, it will default to 320 x 180.  Size options are 2048x1152, 960x540, 720x405, 600x337, 480x270, 320x180, and 130x73.  If num_photos is not passed, the Gallery will default to the amount set on the Dashboard - if no amount is set there, it will display up to 25 photos.  The ID number is found by looking at the URL of the link to the Album on Facebook

== Frequently Asked Questions ==

= Help!  I need a Facebook App ID / Facebook Secret Key! =

OK!  We have a great page with some helpful information [here](goldplugins.com/documentation/wp-social-pro-documentation/how-to-get-an-app-id-and-secret-key-from-facebook/ "Configuration Options and Instructions").

Follow the information on that page to create a Simple Facebook App - you'll be guided along the way to get your App ID, Secret Key, and any other info you may need.

= Ack!  All I see is 'WP Social: Please check your settings.' - What do I do? =

It's all good!  This just means there is no feed data - this could be due to bad settings, including a bad Page ID, App ID, or Secret Key, or it could be due to some other error.  Be sure to check your Facebook Page's Privacy Settings, too!  Check the plugin instructions for help (or send us a message if you think it's an error.)

Some people have options enabled that hide any items from being shown, such as Show Only Events.  If you have no future events scheduled and Show Only Events is enabled, you will see no feed items and an error message on the Help & Status screen.

= So what's up with this 'Publicly Accessible Page' thing? =

OK, so here's the deal:

Your Facebook Feed needs to come from a Publicly Accessible Facebook page.  If your page is Private, if it’s a Personal Profile and not a Page, or if you have an Age Limit set that thus requires the user to login, the plugin won't be able to display the feed data (it will instead just display the page title, like button, and profile pic - even that can be dependent upon your settings.)

Here's how to test if your page is Public or Private:

Logout of Facebook and then try to visit the Facebook Page in question.  If Facebook wants you to login to be able to view the Feed, then this page is not Publicly Accessible.  You just need to update the Page's relevant settings so that it is.

= Yo!! My Photos aren't showing up in my Feed.  What gives?? =

Check it - this typically occurs due to either the Show Photos in my Feed option not being checked or due to the App ID and Secret Key being in development mode, or being rate limited.  If you suspect the App ID and Secret Key are the issue, go ahead and generate a new pair to use on this site.

= I've set an image width on the options page, but it isn't working! =

No worries!  Did you include any non-integer characters?  Be sure the width is just something like "250" (ignore the quotes) - you don't need to include "px".

= Instead of a Like Button, all I see is "Error"! =

That probably means the URL you've given the Like Button is invalid.  Sometimes this happens in the feed widget, if the URL isn't a valid Facebook Page.

= Other people's posts are showing up on my wall!  How do I stop it? =

If you are using the Free version of the plugin, you'll need to [purchase WP Social Pro](http://goldplugins.com/our-plugins/wp-social-pro/ "Purchase WP Social Pro") first.  Once installed, look for the option titled "Only Show Page Owner's Posts".  When that is checked, these posts will be hidden from view.

= Hey!  I need more control over the styling of my feed, but I don't know CSS! =

If you are using the Free version of the plugin, you'll need to [purchase WP Social Pro](http://goldplugins.com/our-plugins/wp-social-pro/ "Purchase WP Social Pro") first.  Once installed, look for the long list of options under the heading "Display Options".  You will be able to use these to control font size and color for all of the different text elements, feed width and height for the in page and sidebar versions each, and more being added all the time!

= I see this plugin uses caching. Do I need to do anything for this? =

Nope!  Thanks to the WordPress Transient API, all you have to do is sit back and relax and we'll do the rest!

= I think I broke my date formatting!  Help! =

It's OK!  Just place %B %d in the field, and you'll be back to default!

= I Want All Of My Posts Visible -- Not Contained In A Box! =

Try using the "No Style" theme -- this will output everything in a list.  You can also turn off the Like Button, Feed Title, and Profile Pic to have it look more like a list of posts.

= I Want Even More Themes To Choose From For My Feed =

The Pro version of WP Social has tons of themes!  [purchase WP Social Pro](http://goldplugins.com/our-plugins/wp-social-pro/ "Purchase WP Social Pro")

= I Want All To Show The Number of Likes In My Facebook Feed! =

The Pro version of WP Social has this functionality - [purchase WP Social Pro](http://goldplugins.com/our-plugins/wp-social-pro/ "Purchase WP Social Pro")

= I Want All To Show Avatars In My Facebook Feed! =

The Pro version of WP Social has this functionality - [purchase WP Social Pro](http://goldplugins.com/our-plugins/wp-social-pro/ "Purchase WP Social Pro")

= I Want All To Show Comments In My Facebook Feed! =

The Pro version of WP Social has this functionality - [purchase WP Social Pro](http://goldplugins.com/our-plugins/wp-social-pro/ "Purchase WP Social Pro")

= Urk!  How to find my Album's ID for outputting a photo gallery? =

OK, try this: looking at the following URL, you want to grab the number that appears directly after "set=a." and before the next period - 
facebook.com/media/set/?set=a.**539627829386059**.148135.322657451083099&type=3

In this case, the Facebook Album ID is '539627829386059'.

== Screenshots ==

1. These are the Facebook API Settings Options.
2. These are the Facebook Synced Feeds Options.
3. These are the Facebook API Status Options.
4. These are the Style Options Options.
5. These are the Feed Images settings Options.
6. These are the Feed Window Color and Dimension Options.
7. These are the Font Styling Options.
8. These are the Fields to Display Options.
9. These are the Advanced Display Options Options.
10. These are the Event Order Options.
11. These are the Event Date Format Options.
12. These are the Event Date Range Options.
13. These are the Event Image Size Options.
14. These are the Custom HTML Options.

== Changelog ==

= 3.0.3 =

* 3.0.3: Update CPT for compatibility with other Gold Plugins; compatible with WordPress 4.7

* [View Changelog](https://goldplugins.com/documentation/wp-social-pro-documentation/wp-social-pro-changelog/ "View Changelog")

== Upgrade Notice ==

= 3.0.3 =
* Compatibility updates.
