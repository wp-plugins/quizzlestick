<?php

/**
 * Plugin base class
 *
 * @package icit-core
 */

if ( ! class_exists( 'icit_plugin' ) ) {

	abstract class icit_plugin {

		/**
		 * Class text domain
		 */
		const DOM = icit_core::DOM;

		/**
		 * The version of core this file belongs to.
		 */
		const VERSION = '1.0.0';

		/**
		 * The minimum WP version needed
		 */
		const WP_VER = 3.4;

		/**
		 * The Minimum PHP version needed
		 */
		const PHP_VER = 5.2;

		/**
		 * Constant that should point at the file that contains the WordPress
		 * plug-in header and should be set on the extending class.
		 */
		const FILE = __FILE__;

		/**
		 * @var object The WordPress plug-in object.
		 */
		public $_plugin = null;

		/**
		 * @var Bool Do we create the default page or not.
		 */
		public $create_options_page = true;

		/**
		 * @var array Setting for the default plug-in page
		 */
		public $option_page_settings = array(
								'parent' => 'options-general.php',
								'title' => 'Plugin Settings',
								'menu_title' => 'Plugin Settings',
								'permissions' => 'manage_options',
								'option_group' => 'plugin_settings',
								'screen_icon' => 'options-general',
								'default_content' => null,
								'default_content_side' => null
							);

		/**
		 * @var array Store for each page in the plug-in, most likely only one
		 * page in the plug-in but we need to allow for more.
		 */
		private $option_pages = array();

		/**
		 * @var bool Did the __construct run or not?
		 */
		private $did_setup = false;

		/**
		 * Sets up the plugin actions and data
		 *
		 * @param array $settings The plugin configuration
		 *
		 * @return void
		 */
		public function __construct( $settings = array() ) {
			global $wp_version;

			// The constructor has been triggered...
			$this->did_setup = true;

			// Die if we're running with old stuff.
			if ( ! is_admin() && ! version_compare( $wp_version, $this::WP_VER, 'ge' ) || ! version_compare( phpversion(), $this::PHP_VER, 'ge' ) )
				wp_die( sprintf( 'You need %s version of WordPress and %s version of PHP to use this plugin.', $this::WP_VER, $this::PHP_VER ) );

			// Merge the settings passed in with the defaults
			$this->option_page_settings = wp_parse_args( $settings, $this->option_page_settings );

			// Check we're happening at the right time.
			if ( did_action( 'init' ) )
				error_log( __( 'icit_plugin should extend your plug-in class and parent::__construct should be activated before the init action.', self::DOM ), E_USER_WARNING );

			// Make sure core is the correct version for this module.
			if ( version_compare( icit_core::VERSION, self::VERSION, '<' ) ) {
				error_log( sprintf( __( 'Wrong ICIT Core version. Class "%3$s" expecting core version version %2$s but got version %1$s in file %4$s', self::DOM ), icit_core::VERSION, self::VERSION, __CLASS__, __FILE__ ), E_USER_WARNING );
				return false;
			}

			// Check that the class has a file const set and that it doesn't point at this file.
			if ( $this::FILE === __FILE__ ) {
				error_log( __( 'You need to add "const FILE = __FILE__;" to any class extending ' . __CLASS__ . ' or replace __FILE__ with the file path to main plug-in file."', self::DOM ), E_USER_WARNING );
				return false;
			}

			// Grab the domain files for the theme, core should have been delt with already
			$locale = get_locale();
			if ( file_exists( plugins_url( '', $this::FILE ) . '/lang/' . $this::DOM . '-' . $locale . '.mo' ) )
				load_textdomain( $this::DOM, plugins_url( '', $this::FILE ) . '/lang/' . $this::DOM . '-' . $locale . '.mo' );

			// Create the default page
			if ( is_admin( ) && $this->create_options_page ) {
				$this->add_page( $this->option_page_settings );
			}
			elseif ( is_admin() && !has_action( 'wp_ajax_get_attachment_image', array( 'icit_fields', 'get_attachment_image' ) ) ) {
				add_action( 'wp_ajax_get_attachment_image', array( 'icit_fields', 'get_attachment_image' ) );
			}

			// General init methods for overriding
			add_action( 'init', array( $this, 'init' ) );
			add_action( 'admin_init', array( $this, 'admin_init' ) );
			add_action( 'widgets_init', array( $this, 'widgets_init' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
			add_action( 'wp', array( $this, 'wp' ) );

			// activation/deactivation
			register_activation_hook( $this::FILE, array( $this, 'on_activate' ) );
			register_deactivation_hook( $this::FILE, array( $this, 'on_deactivate' ) );
		}


		/**
		 * Empty place holder functions for easy action hooks in parent class
		 */
		public function init() {
			// empty placeholder
		}

		public function admin_init() {
			// empty placeholder
		}

		public function widgets_init() {
			// empty placeholder
		}

		public function enqueue_scripts() {
			// empty placeholder
		}

		public function admin_enqueue_scripts( $hook = '' ) {
			// empty placeholder
		}

		public function on_activate() {
			// empty placeholder
		}

		public function on_deactivate() {
			// empty placeholder
		}

		public function wp() {
			// empty placeholder
		}


		/**
		 * Child class should replace this
		 */
		public function register_settings() {
			add_settings_field( 'warning', __( 'Instructions', self::DOM ), array( $this, '_note' ), sanitize_title( $this->option_page_settings[ 'option_group' ] ), 'default' );
		}


		/**
		 * This should also never be seen as it's being called from a function
		 * that should be overridden by a child class.
		 *
		 * @return null
		 */
		public function _note( ) {
			printf( __( 'You need to have a %s method when extending this class (%s) or add $create_options_page = false; to your class.', self::DOM ), 'register_settings', __CLASS__ );
		}


		public function add_page( $args = array( ) ) {
			if ( ! $this->did_setup )
				self::__construct( );

			$defaults = $this->option_page_settings;
			$defaults[ 'register_settings' ] = array( $this, 'register_settings' );

			$r = wp_parse_args( $args, $defaults );
			$r[ 'option_group' ] = $option_group = sanitize_title( $r[ 'option_group' ] );

			// Set up the branded box
			if ( ! isset( $r[ 'default_content_side' ] ) )
				$r[ 'default_content_side' ] = $this->get_plugin_meta_box();

			if ( ! isset( $this->options_pages[ $option_group ] ) )
				$this->options_pages[ $option_group ] = new icit_options( $r );

			// Return the created object.
			return $this->options_pages[ $option_group ];
		}


		/**
		 * Get the options page
		 *
		 * @param string $option_group Group name
		 *
		 * @return icit_options    The options page object
		 */
		public function get_page( $option_group = '' ) {
			$option_group = ! empty( $option_group ) ?
				sanitize_title( $option_group ) :
				$this->option_page_settings[ 'option_group' ];

			if ( isset( $this->options_pages[ $option_group ] ) )
				return $this->options_pages[ $option_group ];
			else
				return false;
		}

		public function get_plugin_filename() {
			return $this::FILE;
		}


		/**
		 * Outputs a branded theme meta box on an options page
		 *
		 * @return string 	HTML metabox output
		 */
		public function get_plugin_meta_box() {
			// This is quite heavy so we want it in a plce where it only runs once and only when needed.
			if ( ! isset( $this->_plugin ) ) {
				if ( ! function_exists( 'get_plugin_data' ) )
					require_once( ABSPATH . 'wp-admin/includes/plugin.php' );

				$this->_plugin = get_plugin_data( $this->get_plugin_filename() );
			}

			return '
				<div class="postbox icit-branding">
					<div class="icit-logo">interconnect<span>/</span><strong>it</strong></div>
					<div class="icit-info">
						<h3>' . $this->_plugin[ 'Name' ] . '</h3>
						<div class="icit-version">v<strong>' . $this->_plugin[ 'Version' ] . '</strong></div>
						<p class="icit-description">' . $this->_plugin[ 'Description' ] . '</p>
						<div class="icit-url"><a href="' . $this->_plugin[ 'PluginURI' ] . '">' . __( 'Visit plugin home page', self::DOM ) . '</a></div>
						<div class="icit-credit">by <a href="' . $this->_plugin[ 'AuthorURI' ] . '">' . $this->_plugin[ 'Author' ] . '</a></div>
					</div>
				</div>';
		}

	}
}
