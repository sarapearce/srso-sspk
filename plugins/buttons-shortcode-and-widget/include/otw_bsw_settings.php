<?php
$message = '';
$massages = array();
$messages[1] = __( 'Settings saved', 'otw_bsw' );

if( isset( $_GET['message'] ) && isset( $messages[ $_GET['message'] ] ) ){
	$message .= $messages[ $_GET['message'] ];
}
?>
<?php if ( $message ) : ?>
<div id="message" class="updated"><p><?php echo $message; ?></p></div>
<?php endif; ?>
<div class="wrap">
	<div id="icon-edit" class="icon32"><br/></div>
	<h2>
		<?php _e('Plugin Settings', 'otw_bsw') ?>
	</h2>
	<div class="form-wrap otw_bsw_options" id="poststuff">
		<form method="post" action="" class="validate">
			<input type="hidden" name="otw_bsw_action" value="otw_bsw_settings_action" />
			<?php wp_original_referer_field(true, 'previous'); wp_nonce_field('otw-bsw-settings'); ?>
			<div id="post-body">
				<div id="post-body-content">
					<?php include_once( 'otw_bsw_help.php' );?>
				</div>
			</div>
		</form>
	</div>
</div>

