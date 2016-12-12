<?php

/**
 * Abovethefold optimization core class.
 *
 * This class provides the functionality for admin dashboard and WordPress hooks.
 *
 * @since      1.0
 * @package    abovethefold
 * @subpackage abovethefold/includes
 * @author     PageSpeed.pro <info@pagespeed.pro>
 */

class Abovethefold {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin
	 */
	public $loader;

	/**
	 * The unique identifier of this plugin
	 */
	public $plugin_name;

	/**
	 * The current version of the plugin
	 */
	protected $version;

	/**
	 * Disable abovethefold optimization
	 */
	public $disabled = false;

	/**
	 * Special view (e.g. full css export and critical css quality tester)
	 */
	public $view = false;

	/**
	 * Options
	 */

	public $options;

	/**
	 * Sub-controllers
	 */
	public $admin;
	public $optimization;
	public $plugins;
	public $gwfo;
	public $proxy;
	public $lazy;

	/**
	 * cURL controller
	 */
	public $curl;

	/**
	 * Template redirect hook called
	 */
	public $template_redirect_called = false;

	/**
	 * Default permissions
	 */
	public $CHMOD_DIR;
	public $CHMOD_FILE;

	/**
	 * Construct and initiated Abovethefold class
	 */
	public function __construct() {
		global $show_admin_bar;

		// set plugin meta
		$this->plugin_name = 'abovethefold';
		$this->version = WPABTF_VERSION;

		/**
		 * Disable plugin in admin or for testing
		 */
		if (!$this->is_enabled()) {
			$this->disabled = true;
		}

		// set permissions
		$this->CHMOD_DIR = ( ! defined('FS_CHMOD_DIR') ) ? intval( substr(sprintf('%o', fileperms( ABSPATH )),-4), 8 ) : FS_CHMOD_DIR;
		$this->CHMOD_FILE = ( ! defined('FS_CHMOD_FILE') ) ? intval( substr(sprintf('%o', fileperms( ABSPATH . 'index.php' )),-4), 8 ) : FS_CHMOD_FILE;

		/**
		 * Register Activate / Deactivate hooks.
		 */
		register_activation_hook( WPABTF_SELF, array( $this, 'activate' ) );
        register_deactivation_hook( WPABTF_SELF, array( $this, 'deactivate' ) );

		/**
		 * Special Views
		 */
		
		// a hash is used to prevent random traffic or abuse
		$view_hash = md5(SECURE_AUTH_KEY . AUTH_KEY);

		// availabble views
		$views = array(

			// extract full CSS view
			'extract-css' => array( 'admin_bar' => false ), 

			// compare critical CSS with full CSS
			'compare-abtf' => array( 'admin_bar' => false ), 

			// view website with just the critical CSS
			'abtf-critical-only' => array( 'admin_bar' => false ),

			// view website regularly, but without the admin toolbar for comparison view
			'abtf-critical-verify' => array( 'admin_bar' => false ),

			// build tool HTML export for Gulp.js critical task
			'abtf-buildtool-html' => array( 'admin_bar' => false ),

			// build tool full css export for Gulp.js critical task
			'abtf-buildtool-css' => array( 'admin_bar' => false ),

			// external resource proxy
			'abtf-proxy' => array( )
		);
		foreach ($views as $viewKey => $viewSettings) {

			// check if view is active
			if (isset($_REQUEST[$viewKey]) && $_REQUEST[$viewKey] === $view_hash) {

				// set view
				$this->view = $viewKey;

				// hide admin bar
				if (isset($viewSettings['admin_bar']) && $viewSettings['admin_bar'] === false) {
					$show_admin_bar = false;
				}
			}
		}


		// load dependencies
		$this->load_dependencies();

		// Load WordPress hook/filter loader
		$this->loader = new Abovethefold_Loader();

		// set language
		$this->set_locale();

		/**
		 * Load options
		 */
		$this->options = get_option( 'abovethefold' );

		// load webfont optimization controller
		$this->gwfo = new Abovethefold_WebFonts($this);

		/**
		 * External resource proxy
		 */
		$this->proxy = new Abovethefold_Proxy($this);

		// do not load rest of plugin for proxy
		if ($this->view === 'abtf-proxy') {
			return;
		}

		/**
		 * Load admin controller
		 */
		$this->admin = new Abovethefold_Admin( $this );

		// plugin module controller
		$this->plugins = new Abovethefold_Plugins( $this );
		$this->plugins->load_modules();

		// load optimization controller
		$this->optimization = new Abovethefold_Optimization( $this );

		// load lazy script loading module
		$this->lazy = new Abovethefold_LazyScripts($this);

		/**
		 * Use Above The Fold Optimization standard output buffer
		 */
		$this->loader->add_action('template_redirect', $this, 'template_redirect',-10);

		// add noindex meta
		if (isset($_GET['noabtf'])) {

			// wordpress header
			$this->loader->add_action('wp_head', $this, 'header', 1);
		}
	}

	/**
	 * Check if optimization should be applied to output
	 */
	public function is_enabled() {

		/**
		 * Disable for Google AMP pages
		 */
		if (function_exists('is_amp_endpoint') && is_amp_endpoint()) {
			return false;
		}

		/**
		 * Disable above the fold optimization
		 */
		if (defined('DONOTABTF') && DONOTABTF) {
			return false;
		}

		/**
         * Skip if admin
         */
        if (defined('WP_ADMIN')) {
            return false;
        }

        /**
         * Skip if doing AJAX
         */
        if (defined('DOING_AJAX')) {
            return false;
        }

        /**
         * Skip if doing cron
         */
        if (defined('DOING_CRON')) {
            return false;
        }

        /**
         * Skip if APP request
         */
        if (defined('APP_REQUEST')) {
            return false;
        }

        /**
         * Skip if XMLRPC request
         */
        if (defined('XMLRPC_REQUEST')) {
            return false;
        }

        /**
         * Check for WPMU's and WP's 3.0 short init
         */
        if (defined('SHORTINIT') && SHORTINIT) {
            return false;
        }

        /**
         * Check if we're displaying feed
         */
        if ($this->template_redirect_called && is_feed()) {
            return false;
        }

        /**
         * Register or login page
         */
        if (in_array($GLOBALS['pagenow'], array('wp-login.php', 'wp-register.php'))) {
        	return false;
        }

        /**
         * Disable plugin with query string ?noabtf
         */
        if (isset($_GET['noabtf'])) {
        	return false;
        }

        return true;
	}

	/**
	 * Template redirect hook (required for is_feed() enabled check)
	 */
	public function template_redirect() {

		$this->template_redirect_called = true;

		/**
		 * Disable plugin
		 */
		if (!$this->is_enabled()) {
			$this->disabled = true;
		}
	}

	/**
	 * Load the required dependencies
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin
		 */
		require_once WPABTF_PATH . 'includes/loader.class.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin
		 */
		require_once WPABTF_PATH . 'includes/i18n.class.php';

		/**
		 * The class responsible for defining all actions related to Web Font optimization
		 */
		require_once WPABTF_PATH . 'includes/webfonts.class.php';

		/**
		 * External resource proxy
		 */
		require_once WPABTF_PATH . 'includes/proxy.class.php';

		// do not load the rest of the dependencies for proxy
		if ($this->view === 'abtf-proxy') {
			return;
		}

		/**
		 * The class responsible for defining all actions related to optimization.
		 */
		require_once WPABTF_PATH . 'includes/optimization.class.php';

		/**
		 * The class responsible for defining all actions related to lazy script loading.
		 */
		require_once WPABTF_PATH . 'includes/lazyscripts.class.php';

		/**
		 * Extract Full CSS view
		 */
		if (in_array($this->view,array('extract-css','abtf-buildtool-css'))) {

			/**
			 * The class responsible for defining all actions related to full css extraction
			 */
			require_once WPABTF_PATH . 'includes/extract-full-css.class.php';
		}

		/**
		 * Compare Critical CSS view
		 */
		if ($this->view === 'compare-abtf') {

			/**
			 * The class responsible for defining all actions related to compare critical CSS
			 */
			require_once WPABTF_PATH . 'includes/compare-abtf.class.php';
		}

		/**
		 * The class responsible plugin extension modules.
		 */
		require_once WPABTF_PATH . 'includes/plugins.class.php';
		require_once WPABTF_PATH . 'modules/plugins.class.php';

		/**
		 * The class responsible for defining all actions that occur in the Dashboard.
		 */
		require_once WPABTF_PATH . 'admin/admin.class.php';

	}
	
	/**
	 * Return url with view query string
	 */
	public function view_url($view, $query = array(), $currenturl = false) {

		if (!$currenturl) {
			if (is_admin()
				|| ( defined( 'DOING_AJAX' ) && DOING_AJAX )
				|| in_array($GLOBALS['pagenow'], array('wp-login.php', 'wp-register.php'))
			) {
				$currenturl = home_url();
			} else {
				$currenturl = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
			}
		}

		/**
		 * Return url with view query string
		 */
		return preg_replace('|\#.*$|Ui','',$currenturl) . ((strpos($currenturl,'?') !== false) ? '&' : '?') . $view . '=' . md5(SECURE_AUTH_KEY . AUTH_KEY) . (!empty($query) ? '&' . http_build_query($query) : '');
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Abovethefold_i18n class in order to set the domain and to register the hook
	 * with WordPress
	 */
	private function set_locale() {

		$plugin_i18n = new Abovethefold_i18n();
		$plugin_i18n->set_domain( $this->get_plugin_name() );

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * WordPress header
	 */
	public function header() {
		print '<meta name="robots" content="noindex, nofollow" />';
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress
	 */
	public function run() {

		$this->loader->run();

		/**
		 * If proxy, start processing request
		 */
		if ($this->view === 'abtf-proxy') {
			$this->proxy->handle_request();
		}
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin
	 */
	public function get_version() {
		return $this->version;
	}

	/**
	 * Cache path
	 */
	public function cache_path() {

		$dir = wp_upload_dir();
		$path = trailingslashit($dir['basedir']) . 'abovethefold/';
		if (!is_dir($path)) {
			if (!@mkdir( $path, $this->CHMOD_DIR)) {
				wp_die('Failed to write to ' . $path);
			}
		}
		return apply_filters('abovethefold_cache_path', $path);
	}

	/**
	 * Cache URL
	 */
	public function cache_dir( $cdn = '' ) {
		$dir = wp_upload_dir();

		if ($cdn !== '') {
			$path = trailingslashit($cdn) . trailingslashit(str_replace(trailingslashit(ABSPATH),'',$dir['basedir'])) . 'abovethefold/';
		} else {
			$path = trailingslashit($dir['baseurl']) . 'abovethefold/';
		}
		return apply_filters('abtf_cache_dir', $path);
	}

	/**
	 * Remote get (previously cURL)
	 */
	public function remote_get($url, $args = array() ) {

		$args = array_merge(array(
			'timeout'     => 60,
			'redirection' => 5,
    		'sslverify'   => false,

			// Chrome Generic Win10
			// @link https://techblog.willshouse.com/2012/01/03/most-common-user-agents/
			'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/53.0.2785.116 Safari/537.36',
		),$args);

		$res = wp_remote_get($url, $args);
		if( is_array($res) ) {
			return trim($res['body']);
		}

		return false; // error

	}

	/**
	 * Fired during plugin activation.
	 */
	public function activate() {

		/**
		 * Set default options
		 */
		$default_options = array( );

		/**
		 * Critical CSS
		 */
		$default_options['csseditor'] = true;

		/**
		 * CSS Delivery Optimization
		 */
		$default_options['cssdelivery'] = false;
		$default_options['loadcss_enhanced'] = true;
		$default_options['cssdelivery_position'] = 'header';

		/**
		 * Javascript Delivery Optimization
		 */
		$default_options['jsdelivery'] = false;
		$default_options['jsdelivery_position'] = 'header';
		$default_options['jsdelivery_jquery'] = true;
		$default_options['jsdelivery_async_all'] = true;

		/**
		 * Web Font Optimization
		 */
		$default_options['gwfo'] = false;
		$default_options['gwfo_loadmethod'] = 'inline';
		$default_options['gwfo_loadposition'] = 'header';

		/**
		 * Other
		 */
		$default_options['debug'] = false;
		$default_options['adminbar'] = true;
		$default_options['clear_pagecache'] = false;

		// Store default options
		$options = get_option( 'abovethefold' );
		if ( empty( $options ) ) {
			update_option( "abovethefold", $default_options, true );
		}

	}

	/**
	 * Fired during plugin deactivation.
	 */
	public function deactivate() {

	}

}
