<?php
/*
 Plugin Name: ICIT Core
 Plugin URI: http://www.interconnectit.com
 Description: Core elements for plug-ins and themes.
 Version: 1.0.0
 Text Domain: icit
 Author: James R Whitehead, Rob O'Rourke
 Author URI: http://www.interconnectit.com
*/

global $icit_core;
if ( ! class_exists( 'icit_core' ) ) {

	class icit_core {

		/**
		 * This translation domain is used by the whole of core. If you need to
		 * overrride the translation domain then add a constant to you extending
		 * class with your DOM.
		 */
		const DOM = 'icit';
		const VERSION = '1.0.0';

		private $url = null;
		private $dir = null;

		private $is_plugin = null;

		/**
		 * @var icit_core self
		 */
		protected static $instance = null;

		public function __construct( $args = array() ) {

			// Load our helper function
			require_once( 'icit-helpers.php' );

			// notification helper
			require_once( 'icit-notices.php' );

			// Makes it easier to set default options on get_option.
			require_once( 'icit-defaults.php' );

			if ( isset( $args[ 'dir' ] ) )
				$this->dir = $args[ 'dir' ];

			if ( isset( $args[ 'url' ] ) )
				$this->url = $args[ 'url' ];

			// Set the folder for the core
			$this->dir === null && $this->dir = dirname( __FILE__ );

			// Load the translation stuff..
			$locale = get_locale();
			if ( file_exists( $this->dir  . '/lang/' . self::DOM . '-' . $locale . '.mo' ) )
				load_textdomain( self::DOM, $this->dir . '/lang/' . self::DOM . '-' . $locale . '.mo' );

			// add core class autoloaders
			spl_autoload_register( array( $this, '__autoload' ) );
			//spl_autoload_register( array( $this, '__autoload_psr0' ) );
			spl_autoload_register( array( $this, '__autoload_wp' ) );

			if ( did_action( 'init' ) && ! ( isset( $_GET[ 'action' ] ) && $_GET[ 'action' ] == 'activate' && isset( $_GET[ 'plugin' ] ) ) ) {
				_doing_it_wrong( 'icit_core', __( 'icit_core should be included at the top of your theme or plug-in file and should load before the init action.', self::DOM ), 'icit_core:1.0' );
			}
			else {
				if ( ! did_action( 'plugins_loaded' ) && ! did_action( 'setup_theme' ) && $this->is_plugin == null )
					$this->is_plugin = true;

				elseif ( did_action( 'plugins_loaded' ) && ! did_action( 'setup_theme' ) && $this->is_plugin === null )
					$this->is_plugin = false;
			}
		}


		/**
		 * If you try and access the two protected properties URL and DIR this
		 * will intercept them and set them if needed. For all other properties
		 * it will throw an error.
		 *
		 * @param string $pname Protected property name
		 *
		 * @return mixed    The URL, DIR or otherwise null
		 */
		public function __get( $pname ) {

			switch ( $pname ) {
				case 'url':
					// This will best guess the URI based on best guess of context it'll be better for all concerned if you use set_core_url to specify.
					self::$instance->url === null && self::$instance->url = trailingslashit( $this->is_plugin ? plugin_dir_url( __FILE__ ) : get_template_directory_uri() . '/core' );
					return self::$instance->url;

				case 'dir':
					self::$instance->dir === null && self::$instance->dir = dirname( __FILE__ );
					return self::$instance->dir;

				default:
					trigger_error( 'Undefined property: ' . $pname, E_USER_NOTICE );
					break;
			}

			return null;
		}


		public function set_core_url( $url = null ) {
			if ( $this->url === null && ! empty( $url ) )
				$this->url = trailingslashit( esc_url_raw( $url ) );

			return $this->url;
		}


		/**
		 * Create and/or return an instance of this object.
		 *
		 * @return icit_core The singleton instance of icit_crosspromote
		 */
		public static function instance( $args = array() ) {
			null === self::$instance && self::$instance = new self( $args );
			return self::$instance;
		}


		/**
		 * Basic autoloader. Checks for file and then again with hyphens
		 * instead of underscores.
		 *
		 * @param string $class_name The name of the unloaded class
		 *
		 * @return void
		 */
		public function __autoload( $classname ) {
			$location = $this->dir . DIRECTORY_SEPARATOR . '%s.php';

			$filenames[] = sprintf( $location, $classname );
			$filenames[] = sprintf( $location, str_replace( '_', '-', $classname ) );

			foreach( $filenames as $filename ) {
				if ( is_readable( $filename ) ) {
					require $filename;
					break;
				}
			}
		}


		/**
		 * PSR-0 standard autoload function. Loads classes by namespace in the
		 * format Vendor/Module/Version
		 *
		 * @param string $class_name Namespaced class name to load
		 *
		 * @return void
		 */
		public function __autoload_psr0( $class_name ) {
			$class_name = ltrim( $class_name, '\\' );
			$filename  = '';
			$namespace = '';
			if ( $last_ns_pos = strripos( $class_name, '\\' ) ) {
				$namespace = substr( $class_name, 0, $last_ns_pos );
				$class_name = substr( $class_name, $last_ns_pos + 1 );
				$filename  = str_replace( '\\', DIRECTORY_SEPARATOR, $namespace ) . DIRECTORY_SEPARATOR;
			}
			$filename .= str_replace( '_', DIRECTORY_SEPARATOR, $class_name ) . '.php';
			if ( is_readable( $filename ) )
				require $filename;
		}


		/**
		 * This will autoload some standard WP classes where their names are
		 * consistent.
		 *
		 * @param string $classname The class to be loaded
		 *
		 * @return void
		 */
		public function __autoload_wp( $classname ) {
			// Are we dealing with a normal WP class
			if ( substr( $classname, 0, 3 ) == 'WP_' ) {
				$filename = 'class-' . strtolower( str_replace( '_', '-', $classname ) ) . '.php';

				if ( is_readable( ABSPATH . WPINC . '/' . $filename ) )
					require_once( ABSPATH . WPINC . '/' . $filename );
			}
		}

	}

	// Kick core into life.
	$icit_core = icit_core::instance();
}
