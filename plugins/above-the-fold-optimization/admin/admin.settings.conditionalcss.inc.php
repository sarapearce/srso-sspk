<?php

// enabled
$conditionalcss_enabled = (isset($options['conditionalcss_enabled']) && intval($options['conditionalcss_enabled']) === 1) ? true : false;

/**
 * Conditions
 */
$coptions = array();

if ($conditionalcss_enabled) {

	$coptions[] = '<optgroup label="'.__('Page Types').'" data-data=\'{"class":"optgroup-pagetype"}\'>';

	$coptions[] = '<option value="frontpage" data-data=\'{"title": "Front Page","class":"pagetype"}\'>Front Page</option>';
	$coptions[] = '<option value="categories" data-data=\'{"title": "Categories","class":"pagetype"}\'>Categories</option>';

	$post_types = get_post_types();
	foreach ($post_types as $pt) {
		if (in_array($pt,array('revision','nav_menu_item'))) {
			continue 1;
		}
		switch($pt) {
			case "post":
			case "page":
			case "attachment":
				$coptions[] = '<option value="pt_'.$pt.'" data-data=\'{"title": "'.ucfirst($pt).'s","class":"pagetype"}\'>All '.$pt.'s</option>';
			break;
			default:
				$coptions[] = '<option value="pt_'.$pt.'" data-data=\'{"title": "'.ucfirst($pt).'","class":"pagetype"}\'>All '.$pt.'</option>';
			break;
		}
		
	}

	/**
	 * Templates
	 */
	$templates = get_page_templates();
	foreach ($templates as $tplname => $file) {
		$coptions[] = '<option value="pt_tpl_'.htmlentities($file,ENT_COMPAT,'utf-8').'" data-data=\'{"title": "Tpl: '.ucfirst(htmlentities($tplname,ENT_COMPAT,'utf-8')).'","class":"pagetype"}\'>Template: '.htmlentities($tplname,ENT_COMPAT,'utf-8').'</option>';
	}

	/**
	 * WooCommerce
	 *
	 * @link https://docs.woocommerce.com/document/conditional-tags/
	 */
	if ( class_exists( 'WooCommerce' ) ) {

		$coptions[] = '<option value="wc_shop" data-data=\'{"title": "is_shop()","class":"pagetype"}\'>WooCommerce: is_shop()</option>';
		$coptions[] = '<option value="wc_product_category" data-data=\'{"title": "is_product_category()","class":"pagetype"}\'>WooCommerce: is_product_category()</option>';
		$coptions[] = '<option value="wc_product_tag" data-data=\'{"title": "is_product_tag()","class":"pagetype"}\'>WooCommerce: is_product_tag()</option>';
		$coptions[] = '<option value="wc_product" data-data=\'{"title": "is_product()","class":"pagetype"}\'>WooCommerce: is_product()</option>';
		$coptions[] = '<option value="wc_cart" data-data=\'{"title": "is_cart()","class":"pagetype"}\'>WooCommerce: is_cart()</option>';
		$coptions[] = '<option value="wc_checkout" data-data=\'{"title": "is_checkout()","class":"pagetype"}\'>WooCommerce: is_checkout()</option>';
		$coptions[] = '<option value="wc_account_page" data-data=\'{"title": "is_account_page()","class":"pagetype"}\'>WooCommerce: is_account_page()</option>';
	}

	$coptions[] = '</optgroup>';


	// blog categories
	/*$taxonomy = 'category';
	$terms = get_terms($taxonomy);
	if (!empty($terms)) {
		$coptions[] = '<optgroup label="'.__('Posts with categories').'" data-data=\'{"class":"optgroup-cat"}\'>';
		foreach($terms as $term) {
			$coptions[] = '<option value="cat'.$term->term_id.'" data-data=\'{"title" : "'.$term->term_id.'","class":"cat"}\'>' . $term->term_id . ': '.$term->slug.'</option>';
		}
		$coptions[] = '</optgroup>';
	}*/


	// categories
	$taxonomy = 'category';
	$terms = get_terms($taxonomy);
	if (!empty($terms)) {
		$coptions[] = '<optgroup label="'.__('Categories').'" data-data=\'{"class":"optgroup-cat"}\'>';
		foreach($terms as $term) {
			$coptions[] = '<option value="cat'.$term->term_id.'" data-data=\'{"title" : "'.$term->term_id.'","class":"cat"}\'>' . $term->term_id . ': '.$term->slug.'</option>';
		}
		$coptions[] = '</optgroup>';
	}

	// Taxomies
	$taxs = get_taxonomies();
	if (!empty($taxs)) {
		$coptions[] = '<optgroup label="'.__('Taxonomy').'" data-data=\'{"class":"optgroup-post"}\'>';
		foreach($taxs as $tax) {
			$coptions[] = '<option value="tax'.$tax.'" data-data=\'{"title" : "'.$tax.'","class":"post"}\'>' . $tax . '</option>';
		}
		$coptions[] = '</optgroup>';
	}

	// Posts
	 /*
	$args = array( 'post_type' => 'post', 'posts_per_page' => -1 );
	query_posts($args);
	if (have_posts()) {
		$coptions[] = '<optgroup label="'.__('Posts').'" data-data=\'{"class":"optgroup-post"}\'>';
		while (have_posts()) {
			the_post();
			$coptions[] = '<option value="post'.$wp_query->post->ID.'" data-data=\'{"title" : "'.$wp_query->post->ID.'","class":"post"}\'>' . $wp_query->post->ID . ': '.$wp_query->post->post_title.'</option>';
		}
		$coptions[] = '</optgroup>';
	}
	*/

	// Pages
	$args = array( 'post_type' => 'page', 'posts_per_page' => -1 );
	query_posts($args);
	if (have_posts()) {
		$coptions[] = '<optgroup label="'.__('Pages').'" data-data=\'{"class":"optgroup-page"}\'>';
		while (have_posts()) {
			the_post();
			$coptions[] = '<option value="page'.$wp_query->post->ID.'" data-data=\'{"title" : "'.$wp_query->post->ID.'","class":"page"}\'>' . $wp_query->post->ID . ': '.$wp_query->post->post_title.'</option>';
		}
		$coptions[] = '</optgroup>';
	}

	$pagetypes = array(
		'post',
		'page',
		'category',
		'tag',
		'search'
	);

}

?>

<li>

	<h3 style="padding:0px;margin:0px;margin-top:1em;margin-bottom:10px;">Conditional Critical CSS</h3>

	<p class="description" style="margin-bottom:0px;"><?php _e('Configure tailored critical path CSS for individual posts, post types, categories, pages or page types based on conditions.', 'abovethefold'); ?></p>

	<p style="margin-top:1em;">
		<button type="button" id="addcriticalcss" class="button" style="<?php if (!$conditionalcss_enabled) { print 'display:none;'; } ?>margin-right:0.5em;">Add Conditional Critical CSS</button>
		
		<label><input type="checkbox" name="abovethefold[conditionalcss_enabled]" value="1"<?php if ($conditionalcss_enabled) { print ' checked="checked"'; } ?> <?php if (!$conditionalcss_enabled) { ?> onchange="if (jQuery(this).is(':checked')) { jQuery('.conditionalcssopts').fadeIn( { duration: 100 } ); } else { jQuery('.conditionalcssopts').hide(); }"<?php } ?>> Enable Conditional Critical CSS.</label>
	</p>

	<div style="clear:both;margin-top:10px;padding:5px;color:#000;float:left;display:none;background-color:#efefef;margin-bottom:10px;" class="conditionalcssopts">
		New options will become visible after saving.
	</div>

	<?php if ($conditionalcss_enabled) { ?>
		<div id="addcriticalcss-form" class="edit-conditional-critical-css" style="background:#f1f1f1;border:solid 1px #e5e5e5;margin-bottom:1em;margin-top:1em;display:none;">

			<h3 class="hndle" style="border-bottom:solid 1px #e5e5e5;"><span>Add Conditional Critical CSS</span></h3>

			<div class="inside" style="padding-bottom:0px;">

				<textarea id="addcc_condition_options" style="display:none;"><?php print json_encode($coptions,true); ?></textarea>
				<table class="form-table add-form">
					<tr valign="top">
						<td>
							<input type="text" name="" id="addcc_name" value="" placeholder="Name (admin reference)" style="width:100%;" />
						</td>
					</tr>
					<tr valign="top">
						<td>
							<select id="addcc_conditions" rel="selectize" multiple="multiple"></select>
						</td>
					</tr>
				</table>
				<button type="button" class="button button-yellow button-small" id="addcc_save"><?php _e('Save'); ?></button>

				<div style="height:10px;clear:both;overflow:hidden;font-size:1px;">&nbsp;</div>
			</div>

		</div>
	<?php } ?>
</li>
<?php if ($conditionalcss_enabled) { 

	if (!empty($options['conditional_css'])) {

		
		foreach ($options['conditional_css'] as $condition_hash => $cCSS) {

			/**
			 * Read global critical CSS
			 */
			$inlinecss = '';
			$cssfile = $this->CTRL->cache_path() . 'criticalcss_'.$condition_hash.'.css';
			if (file_exists($cssfile)) {
				$inlinecss = file_get_contents($cssfile);
			}
?>
	<li class="menu-item menu-item-depth-0 menu-item-page pending" style="display: list-item; position: relative; top: 0px;">
		<div class="menu-item-bar criticalcss-edit-header" rel="<?php print htmlentities($condition_hash,ENT_COMPAT,'utf-8'); ?>">
			<div class="menu-item-handle" style="width:auto!important;cursor: pointer;">
				<span class="item-title">
					<span class="menu-item-title"><?php print htmlentities($cCSS['name'],ENT_COMPAT,'utf-8'); ?></span> 
					<span class="is-submenu" ><?php if (trim($inlinecss) !== '') { print '<span>'.size_format(strlen($inlinecss),2).'</span>'; } else { print '<span style="color:#f1b70a;">empty</span>';} ?> <span style="float:right;">Weight: <?php if (isset($cCSS['weight'])) { print $cCSS['weight']; } else { print '1'; } ?></span></span>
					<span class="is-submenu loading-editor" style="display:none;">
						<span style="color:#ea4335;">Loading editor...</span>
					</span>
				</span>
				<span class="item-controls">
					<a class="item-delete button button-small button-del" title="Delete conditional Critical CSS" href="javascript:void(0);" data-confirm="<?php echo htmlentities(__('Are you sure you want to delete this conditional Critical CSS?', 'abovethefold'),ENT_COMPAT,'utf-8'); ?>">&#x2717;</a>
					<a class="item-edit" href="javascript:void(0);">^</a>
				</span>
			</div>
		</div>

		<div id="ccss_editor_<?php print htmlentities($condition_hash,ENT_COMPAT,'utf-8'); ?>" class="ccss_editor" style="display:none;">
			<textarea class="abtfcss" name="abovethefold[conditional_css][<?php print htmlentities($condition_hash,ENT_COMPAT,'utf-8'); ?>][css]"><?php echo htmlentities($inlinecss,ENT_COMPAT,'utf-8'); ?></textarea>
			<div class="conditions edit-conditional-critical-css">
				<select name="abovethefold[conditional_css][<?php print htmlentities($condition_hash,ENT_COMPAT,'utf-8'); ?>][conditions][]" multiple="multiple" data-conditions="<?php print htmlentities(json_encode($cCSS['conditions'],true),ENT_COMPAT,'utf-8'); ?>">
<?php

	/**
	 * Print default conditions
	 */
	foreach ($cCSS['conditions'] as $condition) {
		print '<option value="'.$condition.'" selected>'.$condition.'</option>';
	}
?>
</select>
			<div>Weight: <input type="number" size="3" style="width:50px;" name="abovethefold[conditional_css][<?php print htmlentities($condition_hash,ENT_COMPAT,'utf-8'); ?>][weight]" value="<?php print (isset($cCSS['weight']) ? intval($cCSS['weight']) : '1'); ?>" placeholder="..." /> (higher weight is selected over lower weight conditions)</div>

			</div>
			<div style="height:10px;clear:both;overflow:hidden;font-size:1px;">&nbsp;</div>
		</div>
		<ul class="menu-item-transport"></ul>
	</li>
<?php
		}
?>
	<li>
		<br />
		<?php
			submit_button( __( 'Save' ), 'primary large', 'is_submit', false );
		?>
	</li>
<?php

	}
 } 