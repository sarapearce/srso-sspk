<?php

/**
 * Critical CSS admin controller
 *
 * @since      2.5.4
 * @package    abovethefold
 * @subpackage abovethefold/admin
 * @author     PageSpeed.pro <info@pagespeed.pro>
 */

class Abovethefold_Admin_CriticalCSS {

	/**
	 * Above the fold controller
	 */
	public $CTRL;

	/**
	 * Options
	 */
	public $options;

	/**
	 * Initialize the class and set its properties.
	 */
	public function __construct( &$CTRL ) {

		$this->CTRL =& $CTRL;
		$this->options =& $CTRL->options;

		/**
		 * Admin panel specific
		 */
		if (is_admin()) {

			// Hook in the admin styles and scripts
			$this->CTRL->loader->add_action('admin_enqueue_scripts', $this, 'enqueue_scripts',30);

			/**
			 * Handle form submissions
			 */
			$this->CTRL->loader->add_action('admin_post_abtf_criticalcss_update', $this,  'update_settings');
			$this->CTRL->loader->add_action('admin_post_abtf_add_ccss', $this,  'add_conditional_criticalcss');
			$this->CTRL->loader->add_action('admin_post_abtf_delete_ccss', $this,  'delete_conditional_criticalcss');

		}

	}

	/**
	 * Enqueue scripts and styles
	 */
	public function enqueue_scripts($hook) {

		if (!isset($_REQUEST['page']) || $_REQUEST['page'] !== 'abovethefold') {
			return;
		}

		// get active tab
		$tab = $this->CTRL->admin->active_tab();

		switch($tab) {
			case "criticalcss":

				$options = get_option('abovethefold');

				if (!isset($options['csseditor']) || intval($options['csseditor']) === 1) {

					/**
					 * Codemirror CSS highlighting
					 */
					wp_enqueue_style( 'abtf_codemirror', plugin_dir_url( __FILE__ ) . 'css/codemirror.min.css' );
					wp_enqueue_script( 'abtf_codemirror', plugin_dir_url( __FILE__ ) . 'js/codemirror.min.js', array( 'jquery','jquery-ui-resizable','abtf_admincp' ) );

				}

			break;
		}
		
	}

    /**
	 * Update settings
	 */
	public function update_settings() {

		check_admin_referer('abovethefold');

		// stripslashes should always be called
		// @link https://codex.wordpress.org/Function_Reference/stripslashes_deep
		$_POST = array_map( 'stripslashes_deep', $_POST );

		$options = get_option('abovethefold');
		if (!is_array($options)) { $options = array(); }

		// input
		$input = (isset($_POST['abovethefold']) && is_array($_POST['abovethefold'])) ? $_POST['abovethefold'] : array();

		/**
		 * Critical CSS settings
		 */
		$options['csseditor'] = (isset($input['csseditor']) && intval($input['csseditor']) === 1) ? true : false;
		$options['conditionalcss_enabled'] = (isset($input['conditionalcss_enabled']) && intval($input['conditionalcss_enabled']) === 1) ? true : false;
		
		/**
		 * Save Critical CSS
		 */
		if (!is_writable($this->CTRL->cache_path())) {
			$this->CTRL->admin->set_notice('<p style="font-size:18px;">Critical CSS storage directory is not writable. Please check the write permissions for the following directory:</p><p style="font-size:22px;color:red;"><strong>'.str_replace(trailingslashit(ABSPATH),'/',$this->CTRL->cache_path()).'</strong></p>', 'ERROR');
		} else {

			/**
			 * Store global critical CSS
			 */
			$css = trim($input['css']);
			$cssfile = $this->CTRL->cache_path() . 'criticalcss_global.css';
			if (file_exists($cssfile) && !is_writable($cssfile)) {
				$this->CTRL->admin->set_notice('<p style="font-size:18px;">Failed to write to Critical CSS storage file. Please check the write permissions for the following file:</p><p style="font-size:22px;color:red;"><strong>'.str_replace(trailingslashit(ABSPATH),'/',$cssfile).'</strong></p>', 'ERROR');
			} else {

				// write Critical CSS
				@file_put_contents( $cssfile, $css );
				chmod($cssfile, $this->CTRL->CHMOD_FILE);

				// failed to store Critical CSS
				if (!is_writable($cssfile)) {
					$this->CTRL->admin->set_notice('<p style="font-size:18px;">Failed to write to Critical CSS storage file. Please check the write permissions for the following file:</p><p style="font-size:22px;color:red;"><strong>'.str_replace(trailingslashit(ABSPATH),'/',$cssfile).'</strong></p>', 'ERROR');
				}

			}
			
			/**
			 * Store conditional critical CSS
			 */
			if (!empty($input['conditional_css'])) {
				foreach ($input['conditional_css'] as $hashkey => $data) {
					if (!isset($options['conditional_css'][$hashkey])) {
						$error = true;
						$this->CTRL->admin->set_notice('Conditional Critical CSS not configured.', 'ERROR');
					} else if (empty($data['conditions'])) {
						$error = true;
						$this->CTRL->admin->set_notice('You did not select conditions for <strong>'.htmlentities($options['conditional_css'][$hashkey]['name'],ENT_COMPAT,'utf-8').'</strong>.', 'ERROR');
					} else {
						$options['conditional_css'][$hashkey]['conditions'] = $data['conditions'];
						$options['conditional_css'][$hashkey]['weight'] = (isset($data['weight']) && intval($data['weight']) > 0) ? intval($data['weight']) : 1;

						$css = trim($data['css']);
						$cssfile = $this->CTRL->cache_path() . 'criticalcss_'.$hashkey.'.css';

						if (file_exists($cssfile) && !is_writable($cssfile)) {
							$this->CTRL->admin->set_notice('<p style="font-size:18px;">Failed to write to Conditional Critical CSS storage file. Please check the write permissions for the following file:</p><p style="font-size:22px;color:red;"><strong>'.str_replace(trailingslashit(ABSPATH),'/',$cssfile).'</strong></p>', 'ERROR');
						} else {

							// write Critical CSS
							@file_put_contents( $cssfile, $css );
							chmod($cssfile, $this->CTRL->CHMOD_FILE);

							// failed to store Critical CSS
							if (!is_writable($cssfile)) {
								$this->CTRL->admin->set_notice('<p style="font-size:18px;">Failed to write to Conditional Critical CSS storage file. Please check the write permissions for the following file:</p><p style="font-size:22px;color:red;"><strong>'.str_replace(trailingslashit(ABSPATH),'/',$cssfile).'</strong></p>', 'ERROR');
							}

						}
						
					}
				}

				
				/**
				 * Sort conditions based on weight
				 */
				uasort($options['conditional_css'], array($this,'weight_sort'));
			}
		}

		// update settings
		$this->CTRL->admin->save_settings($options, 'Critical CSS saved.');

		wp_redirect( add_query_arg( array( 'page' => 'abovethefold', 'tab' => 'criticalcss' ), admin_url( 'admin.php' ) ) );
		exit;
    }


    /**
	 * Add conditional critical CSS
	 */
	public function add_conditional_criticalcss() {

		check_admin_referer('abovethefold');

		// @link https://codex.wordpress.org/Function_Reference/stripslashes_deep
		$_POST = array_map( 'stripslashes_deep', $_POST );

		$options = get_option('abovethefold');
		if (!is_array($options)) { $options = array(); }

		// prepare conditional css storage
		if (!isset($options['conditional_css'])) {
			$options['conditional_css'] = array();
		}

		// name (reference)
		$name = (isset($_POST['name'])) ? trim($_POST['name']) : '';
		if ($name === '') {
			$this->CTRL->admin->set_notice('You did not enter a name.', 'ERROR');
			wp_redirect( add_query_arg( array( 'page' => 'abovethefold', 'tab' => 'criticalcss' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		$id = md5($name);
		if (isset($options['conditional_css'][$id])) {
			$this->CTRL->admin->set_notice('A conditional critical CSS configuration with the name <strong>'.htmlentities($name,ENT_COMPAT,'utf-8').'</strong> already exists.', 'ERROR');
			wp_redirect( add_query_arg( array( 'page' => 'abovethefold', 'tab' => 'criticalcss' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		$_conditions = (isset($_POST['conditions']) && !empty($_POST['conditions'])) ? $_POST['conditions'] : array();

		$conditions = array();
		foreach ($_conditions as $condition) {
			if (trim($condition) === '') { continue 1; }
			$conditions[] = trim($condition);
		}
		if (empty($conditions)) {
			$this->CTRL->admin->set_notice('You did not select conditions.', 'ERROR');
			wp_redirect( add_query_arg( array( 'page' => 'abovethefold', 'tab' => 'criticalcss' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		$options['conditional_css'][$id] = array(
			'name' => $name,
			'conditions' => $conditions,
			'css' => '',
			'weight' => '1'
		);

		// update settings
		$this->CTRL->admin->save_settings($options, 'Conditional Critical CSS created.');

		wp_redirect( add_query_arg( array( 'page' => 'abovethefold', 'tab' => 'criticalcss' ), admin_url( 'admin.php' ) )  . '#conditional' );
		exit;
    }

    /**
	 * Delete conditional critical CSS
	 */
	public function delete_conditional_criticalcss() {

		check_admin_referer('abovethefold');

		// @link https://codex.wordpress.org/Function_Reference/stripslashes_deep
		$_POST = array_map( 'stripslashes_deep', $_POST );

		$options = get_option('abovethefold');
		if (!is_array($options)) { $options = array(); }

		// prepare conditional css storage
		if (!isset($options['conditional_css'])) {
			$options['conditional_css'] = array();
		}

		// conditional css id
		$id = (isset($_POST['id'])) ? trim($_POST['id']) : '';

		// verify hash
		if (!preg_match('|^[a-z0-9]{32}|Ui',$id)) {
			wp_die('Invalid conditional critical CSS ID.');
		}

		/**
		 * Delete critical CSS entry
		 */
		if (isset($options['conditional_css'][$id])) {
			unset($options['conditional_css'][$id]);
		}

		/**
		 * Delete critical CSS file
		 */
		$cssfile = $this->CTRL->cache_path() . 'criticalcss_'.$id.'.css';
		if (file_exists($cssfile)) {

			// empty it
			@file_put_contents( $cssfile, '' );
			
			// delete file
			@unlink( $cssfile );
		}

		// update settings
		$this->CTRL->admin->save_settings($options, 'Conditional Critical CSS deleted.');

		wp_redirect( add_query_arg( array( 'page' => 'abovethefold', 'tab' => 'criticalcss' ), admin_url( 'admin.php' ) ) . '#conditional' );
		exit;
    }

    /**
     * Weight sort
     */
    public function weight_sort($a, $b) {
		if (!isset($a['weight'])) { $a['weight'] = 1; }
		if (!isset($b['weight'])) { $b['weight'] = 1; }
		if ($a['weight'] == $b['weight']) {
			return 0;
		}
		return ($a['weight'] > $b['weight']) ? -1 : 1;
	}
}