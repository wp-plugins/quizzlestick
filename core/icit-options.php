<?php

/**
 * Options class that represents an options page
 *
 * @package icit-core
 */

if ( ! class_exists( 'icit_options' ) ) {

	class icit_options extends icit_fields {

		protected static $version = '1.0';

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
		 * @var array Setting for the default theme page
		 */
		public $option_page_settings = array(
								'parent' => 'options-general.php',
								'title' => 'icit Settings',
								'menu_title' => 'icit Settings',
								'permissions' => 'manage_options',
								'option_group' => 'icit-settings',
								'screen_icon' => 'themes',
								'icon_url' => '',
								'default_content' => null,
								'default_content_side' => null,
								'use_customiser' => false,
								'register_settings' => null,
								'hook' => false
							);

		private $priority = 0;

		/**
		 *  these shouldn't be changed once set use $this->get_options_post() or
		 *  $this->get_customiser()
		 */
		protected $_options_post;

		/**
		 * @var object The customiser object will be stored here.
		 */
		protected $_customiser = null;

		/**
		 * @var int As section are added we increment this
		 * 			across all options classes to avoid collisions
		 */
		protected static $_section_priorty = 1000;


		public function __construct( $settings = array() ) {
			parent::__construct();

			// Make sure core is the correct version for this module.
			if ( version_compare( icit_core::VERSION, self::VERSION, '<' ) ) {
				error_log( sprintf( __( 'Wrong ICIT Core version. Class "%3$s" expecting core version version %2$s but got version %1$s in file %4$s', self::DOM ), icit_core::VERSION, self::VERSION, __CLASS__, __FILE__ ), E_USER_WARNING );
				return false;
			}

			// Merge the settings passed in with the defaults
			$this->option_page_settings = wp_parse_args( $settings, $this->option_page_settings );

			// Options page
			add_action( 'admin_menu', array( $this, '_add_options_page' ) );

			// Set customiser
			if ( $this->option_page_settings[ 'use_customiser' ] )
				add_action( 'customize_register', array( $this, '_set_customiser' ), 1 );

			// Add settings fields
			$register_settings_cb = is_callable( $this->option_page_settings[ 'register_settings' ] ) ? $this->option_page_settings[ 'register_settings' ] : array( $this, 'register_settings' );

			add_action( 'admin_init', $register_settings_cb );
			if ( $this->option_page_settings[ 'use_customiser' ] )
				add_action( 'customize_register', $register_settings_cb );

			// Setup / get options page hidden post
			add_action( 'admin_init', array( $this, '_set_options_post' ) );
			add_action( 'customize_register', array( $this, '_set_options_post' ) );

			// Admin page scripts
			add_action( 'admin_enqueue_scripts', array( $this, '_option_page_scripts' ) );

			// Option page posts, well and truly hidden away. Used to attach
			// images uploaded to option page for easy later reference (<=3.4.x)
			if ( ! get_post_type_object( 'icit_option_page' ) ) {
				register_post_type( 'icit_option_page', array(
					'public' => false,
					'exclude_from_search' => true,
					'publicly_queryable' => false,
					'show_ui' => false,
					'show_in_menu' => false,
					'has_archive' => false,
					'can_export' => false, // revisit this
					'rewrite' => false,
					'query_var' => false,
					'capability_type' => 'page'
				) );
			}

			// Handle settings errors and notices
			add_action( 'admin_notices', array( $this, '_admin_notices' ) );

		}


		/**
		 * Map admin notices for this options page only to the placeholder
		 *
		 * @return void
		 */
		public function _admin_notices() {
			if ( get_current_screen()->id !== $this->option_page_settings[ 'hook' ] )
				return;

			$this->admin_notices();
		}

		public function admin_notices() {
			// place holder for parent class
		}


		/**
		 * This shouldn't be seen and should be overridden by child classes
		 *
		 * @return null
		 */
		public function register_settings( ) {
			// This function should be overridden in the child class
			if ( empty( $this->option_page_settings[ 'customiser' ] ) )
				add_settings_field( 'warning', __( 'Instructions', self::DOM ), array( $this, '_note' ), $this->option_page_settings[ 'option_group' ], 'default' );
		}


		/**
		 * Get the post ID for this options page
		 *
		 * @return int|null    Post ID or null
		 */
		public function get_options_post() {
			return $this->_options_post;
		}


		/**
		 * Return theme customiser object if set
		 *
		 * @return WP_Customize_Manager|null
		 */
		public function get_customiser() {
			return $this->_customiser;
		}


		/**
		 * Register settings wrappers to easily extend theme customiser
		 */

		/**
		 * Add a section to the theme options page and theme customiser
		 *
		 * @param string $id                      A unique ID for the section
		 * @param string $title                   A translatable title for the section
		 * @param callback $callback 			A callback function that output HTML for the admin page
		 *
		 * @return void
		 */
		public function add_section( $id, $title, $callback = '__return_false' ) {

			if ( $this->_customiser )
				$this->_customiser->add_section( "{$this->option_page_settings['option_group']}_{$id}", array(
					'title'    => $title,
					'priority' => $this->priority++,
					'description' => '',
					'theme_supports' => ''
				) );
			elseif ( function_exists( 'add_settings_section' ) )
				add_settings_section( $id, $title, $callback, $this->option_page_settings[ 'option_group' ] );

		}

		public function add_field( $type, $name, $label, $args, $customiser = null ) {

		}


		/**
		 * Checkbox
		 *
		 * @param string $name  Option name
		 * @param string $label Human readable description
		 * @param array $args  Settings for this field
		 * 		callback 		callback function
				extra_args		array
				section 		string
				description 	string
				default 		bool
		 *
		 * @return null
		 */
		public function add_checkbox_field( $name, $label, $args = array() ) {

			extract( wp_parse_args( $args, array(
				'callback' 		=> array( $this, 'validate_boolean' ),
				'extra_args'	=> array(),
				'section' 		=> 'default',
				'description' 	=> '',
				'default' 		=> false
			) ) );

			if ( $this->_customiser ) {

				$this->_customiser->add_setting( $name, array(
					'default' 		=> $default,
					'capability' 	=> 'edit_theme_options',
					'type' 			=> 'option',
				) );

				$this->_customiser->add_control( "{$this->option_page_settings[ 'option_group' ]}_{$name}", array(
					'label' 	=> $label,
					'section' 	=> "{$this->option_page_settings[ 'option_group' ]}_{$section}",
					'settings' 	=> $name,
					'type' 		=> 'checkbox'
				) );

			} else {

				$extra_args = array_merge( array(
								'name' => $name,
								'description' => $description,
								'default' => $default
							), $extra_args );

				register_setting( $this->option_page_settings[ 'option_group' ], $name, $callback );
				add_settings_field( $name, $label, array( $this, 'field_boolean' ), $this->option_page_settings[ 'option_group' ], $section, $extra_args );

			}

		}

		// text
		public function add_text_field( $name, $label, $args = array() ) {
			extract( wp_parse_args( $args, array(
				'callback' 		=> array( $this, 'validate_text' ),
				'extra_args'	=> array(),
				'section' 		=> 'default',
				'description' 	=> '',
				'default' 		=> '',
				'type' 			=> 'text'
			) ) );

			if ( $this->_customiser ) {

				$this->_customiser->add_setting( $name, array(
					'default' 		=> $default,
					'capability' 	=> 'edit_theme_options',
					'type' 			=> 'option',
				) );

				$this->_customiser->add_control( "{$this->option_page_settings[ 'option_group' ]}_{$name}", array(
					'label'      => $label,
					'section'    => "{$this->option_page_settings[ 'option_group' ]}_{$section}",
					'settings'   => $name,
				) );

			} else {

				$extra_args = array_merge( array(
								'name' => $name,
								'description' => $description,
								'default' => $default,
								'type' => $type
							), $extra_args );

				register_setting( $this->option_page_settings[ 'option_group' ], $name, $callback );
				add_settings_field( $name, $label, array( $this, 'field_text' ), $this->option_page_settings[ 'option_group' ], $section, $extra_args );

			}

		}

		// numeric
		public function add_number_field( $name, $label, $args = array() ) {

			extract( wp_parse_args( $args, array(
				'callback' => array( $this, 'validate_numeric' ),
				'extra_args' => array(),
				'section' => 'default',
				'description' => '',
				'default' => '',
				'min' => false,
				'max' => false
			) ) );

			if ( $this->_customiser ) {

				$this->_customiser->add_setting( $name, array(
					'default' 		=> $default,
					'capability' 	=> 'edit_theme_options',
					'type' 			=> 'option',
				) );

				$this->_customiser->add_control( "{$this->option_page_settings[ 'option_group' ]}_{$name}", array(
					'label'     => $label,
					'section'   => "{$this->option_page_settings[ 'option_group' ]}_{$section}",
					'settings'  => $name,
					'type' 		=> 'number'
				) );

			} else {

				$extra_args = array_merge( array(
								'name' => $name,
								'description' => $description,
								'default' => $default,
								'min' => $min,
								'max' => $max
							), $extra_args );

				register_setting( $this->option_page_settings[ 'option_group' ], $name, $callback );
				add_settings_field( $name, $label, array( $this, 'field_numeric' ), $this->option_page_settings[ 'option_group' ], $section, $extra_args );

			}

		}


		// textarea
		public function add_textarea( $name, $label, $args = array() ) {
			require_once( 'icit-custom-controls.php' );

			extract( wp_parse_args( $args, array(
				'callback' 		=> array( $this, 'validate_textarea' ),
				'extra_args'	=> array(),
				'section' 		=> 'default',
				'description' 	=> '',
				'default' 		=> '',
				'tiny_mce' 		=> true,
				'edit_args' 	=> array(
					'media_buttons' => true,
					'teeny' => true
				)
			) ) );

			if ( $this->_customiser ) {

				$this->_customiser->add_setting( $name, array(
					'default' 		=> $default,
					'capability' 	=> 'edit_theme_options',
					'type' 			=> 'option',
				) );

				$this->_customiser->add_control( new icit_customiser_textarea_control( $this->_customiser, "{$this->option_page_settings[ 'option_group' ]}_{$name}", array(
					'label' 		=> $label,
					'section' 		=> "{$this->option_page_settings[ 'option_group' ]}_{$section}",
					'settings' 		=> $name,
					'tiny_mce' 		=> $tiny_mce,
					'media_buttons' => $edit_args[ 'media_buttons' ],
					'teeny' 		=> $edit_args[ 'teeny' ]
				) ) );

			} else {

				$extra_args = array_merge( array(
								'name' => $name,
								'description' => $description,
								'default' => $default,
								'tiny_mce' => $tiny_mce,
								'edit_args' => $edit_args
							), $extra_args );

				register_setting( $this->option_page_settings[ 'option_group' ], $name, $callback );
				add_settings_field( $name, $label, array( $this, 'field_textarea' ), $this->option_page_settings[ 'option_group' ], $section, $extra_args );

			}

		}


		// select
		public function add_select_field( $name, $label, $options, $args = array() ) {

			extract( wp_parse_args( $args, array(
				'callback' 		=> array( $this, 'validate_select' ),
				'extra_args'	=> array(),
				'section' 		=> 'default',
				'description' 	=> '',
				'default' 		=> ''
			) ) );

			if ( $this->_customiser ) {

				$this->_customiser->add_setting( $name, array(
					'default' 		=> $default,
					'capability' 	=> 'edit_theme_options',
					'type' 			=> 'option',
				) );

				$this->_customiser->add_control( "{$this->option_page_settings[ 'option_group' ]}_{$name}", array(
					'label' 	=> $label,
					'section' 	=> "{$this->option_page_settings[ 'option_group' ]}_{$section}",
					'settings' 	=> $name,
					'type' 		=> 'select',
					'choices' 	=> $options
				) );

			} else {

				$extra_args = array_merge( array(
								'name' => $name,
								'description' => $description,
								'default' => $default,
								'options' => $options
							), $extra_args );

				register_setting( $this->option_page_settings[ 'option_group' ], $name, $callback );
				add_settings_field( $name, $label, array( $this, 'field_select' ), $this->option_page_settings[ 'option_group' ], $section, $extra_args );

			}

		}

		// page select
		public function add_page_select_field( $name, $label, $args = array() ) {

			extract( wp_parse_args( $args, array(
				'callback' 		=> array( $this, 'validate_page_select' ),
				'extra_args'	=> array(),
				'section' 		=> 'default',
				'description' 	=> '',
				'default' 		=> ''
			) ) );

			if ( $this->_customiser ) {

				$this->_customiser->add_setting( $name, array(
					'default' 		=> $default,
					'capability' 	=> 'edit_theme_options',
					'type' 			=> 'option',
				) );

				$this->_customiser->add_control( "{$this->option_page_settings[ 'option_group' ]}_{$name}", array(
					'label' 	=> $label,
					'section' 	=> "{$this->option_page_settings[ 'option_group' ]}_{$section}",
					'settings' 	=> $name,
					'type' 		=> 'dropdown-pages'
				) );

			} else {

				$extra_args = array_merge( array(
								'name' => $name,
								'description' => $description,
								'default' => $default
							), $extra_args );

				register_setting( $this->option_page_settings[ 'option_group' ], $name, $callback );
				add_settings_field( $name, $label, array( $this, 'field_page_select' ), $this->option_page_settings[ 'option_group' ], $section, $extra_args );

			}

		}

		// radio
		public function add_radio_field( $name, $label, $options, $args = array() ) {

			extract( wp_parse_args( $args, array(
				'callback' 		=> 'sanitize_text_field',
				'extra_args'	=> array(),
				'section' 		=> 'default',
				'description' 	=> '',
				'default' 		=> ''
			) ) );

			if ( $this->_customiser ) {

				$this->_customiser->add_setting( $name, array(
					'default' 		=> $default,
					'capability' 	=> 'edit_theme_options',
					'type' 			=> 'option',
				) );

				$this->_customiser->add_control( "{$this->option_page_settings[ 'option_group' ]}_{$name}", array(
					'label' 	=> $label,
					'section' 	=> "{$this->option_page_settings[ 'option_group' ]}_{$section}",
					'settings' 	=> $name,
					'type' 		=> 'radio',
					'choices' 	=> $options
				) );

			} else {
				$extra_args = array_merge( array(
								'name' => $name,
								'description' => $description,
								'default' => $default,
								'options' => $options
							), $extra_args );

				register_setting( $this->option_page_settings[ 'option_group' ], $name, $callback );
				add_settings_field( $name, $label, array( $this, 'field_radio' ), $this->option_page_settings[ 'option_group' ], $section, $extra_args );

			}

		}

		// term select
		public function add_term_select_field( $name, $label, $taxonomy = 'category', $args = array() ) {
			require_once( 'icit-custom-controls.php' );

			extract( wp_parse_args( $args, array(
				'callback' 		=> array( $this, 'validate_numeric' ),
				'extra_args'	=> array(),
				'section' 		=> 'default',
				'description' 	=> '',
				'default' 		=> ''
			) ) );

			if ( $this->_customiser ) {

				$this->_customiser->add_setting( $name, array(
					'default' 		=> $default,
					'capability' 	=> 'edit_theme_options',
					'type' 			=> 'option',
				) );

				$this->_customiser->add_control( new icit_customiser_term_select_field( $this->_customiser, "{$this->option_page_settings[ 'option_group' ]}_{$name}", array(
					'label' 	=> $label,
					'section' 	=> "{$this->option_page_settings[ 'option_group' ]}_{$section}",
					'settings' 	=> $name,
					'taxonomy' 	=> $taxonomy
				) ) );

			} else {

				$extra_args = array_merge( array(
								'name' => $name,
								'description' => $description,
								'default' => $default,
								'taxonomy' => $taxonomy
							), $extra_args );

				register_setting( $this->option_page_settings[ 'option_group' ], $name, $callback );
				add_settings_field( $name, $label, array( $this, 'field_term_select' ), $this->option_page_settings[ 'option_group' ], $section, $extra_args );

			}

		}

		// date/time
		public function add_date_time_field( $name, $label, $args = array() ) {
			require_once( 'icit-custom-controls.php' );

			extract( wp_parse_args( $args, array(
				'callback' 		=> array( $this, 'validate_image' ),
				'extra_args'	=> array(),
				'section' 		=> 'default',
				'description' 	=> '',
				'default' 		=> '',
			) ) );

			if ( $this->_customiser ) {

				$this->_customiser->add_setting( $name, array(
					'default' 		=> $default,
					'capability' 	=> 'edit_theme_options',
					'type' 			=> 'option',
				) );

				$this->_customiser->add_control( new icit_customiser_date_time_field( $this->_customiser, "{$this->option_page_settings[ 'option_group' ]}_{$name}", array(
					'label'      => $label,
					'section'    => "{$this->option_page_settings[ 'option_group' ]}_{$section}",
					'settings'   => $name
				) ) );

			} else {

				$extra_args = array_merge( array(
								'name' => $name,
								'description' => $description,
								'default' => $default
							), $extra_args );

				register_setting( $this->option_page_settings[ 'option_group' ], $name, $callback );
				add_settings_field( $name, $label, array( $this, 'field_date_time' ), $this->option_page_settings[ 'option_group' ], $section, $extra_args );

			}

		}

		// image
		public function add_image_field( $name, $label, $args = array() ) {
			require_once( 'icit-custom-controls.php' );

			extract( wp_parse_args( $args, array(
				'callback' 		=> array( $this, 'validate_image' ),
				'extra_args'	=> array(),
				'section' 		=> 'default',
				'description' 	=> '',
				'default' 		=> '',
				'size' 			=> 'medium'
			) ) );

			if ( $this->_customiser ) {

				$this->_customiser->add_setting( $name, array(
					'default' 		=> $default,
					'capability' 	=> 'edit_theme_options',
					'type' 			=> 'option',
					'sanitize_callback' => array( 'ICIT_Customize_Image_Control_AttID', 'attachment_guid_to_id' ),
					'sanitize_js_callback' => array( 'ICIT_Customize_Image_Control_AttID', 'attachment_guid_to_id' ),
				) );

				$this->_customiser->add_control( new ICIT_Customize_Image_Control_AttID( $this->_customiser, "{$this->option_page_settings[ 'option_group' ]}_{$name}", array(
					'label'      => $label,
					'section'    => "{$this->option_page_settings[ 'option_group' ]}_{$section}",
					'settings'   => $name
				) ) );

			} else {

				$extra_args = array_merge( array(
								'name' => $name,
								'description' => $description,
								'default' => $default
							), $extra_args );

				register_setting( $this->option_page_settings[ 'option_group' ], $name, $callback );
				add_settings_field( $name, $label, array( $this, 'field_image' ), $this->option_page_settings[ 'option_group' ], $section, $extra_args );

			}

		}

		// colour
		public function add_colour_field( $name, $label, $args = array() ) {

			extract( wp_parse_args( $args, array(
				'callback' 		=> array( $this, 'validate_colour' ),
				'extra_args'	=> array(),
				'section' 		=> 'default',
				'description' 	=> '',
				'default' 		=> ''
			) ) );

			if ( $this->_customiser ) {

				$this->_customiser->add_setting( $name, array(
					'default' 		=> $default,
					'sanitize_callback' => 'sanitize_hex_color',
					'capability' 	=> 'edit_theme_options',
					'type' 			=> 'option',
				) );

				$this->_customiser->add_control( new WP_Customize_Color_Control( $this->_customiser, "{$this->option_page_settings[ 'option_group' ]}_{$name}", array(
					'label'      => $label,
					'section'    => "{$this->option_page_settings[ 'option_group' ]}_{$section}",
					'settings'   => $name
				) ) );

			} else {

				$extra_args = array_merge( array(
								'name' => $name,
								'description' => $description,
								'default' => $default
							), $extra_args );

				register_setting( $this->option_page_settings[ 'option_group' ], $name, $callback );
				add_settings_field( $name, $label, array( $this, 'field_colour' ), $this->option_page_settings[ 'option_group' ], $section, $extra_args );

			}

		}


		/***********************************************************************
		 * Internal use only please don't overload or use outside of context.
		 **********************************************************************/


		/**
		 * Sets the options post and creates it if it doesn't exist yet.
		 * This is to group files uploaded to this options page
		 *
		 * @return void
		 */
		final public function _set_options_post() {
			// Placeholder post corresponding to options page for attaching images
			if ( $options_page_post = get_option( "{$this->option_page_settings[ 'option_group' ]}_post_id", false ) ) {

				$this->_options_post = $options_page_post;

			} else {

				$this->_options_post = wp_insert_post( array(
					'post_title' => $this->option_page_settings[ 'title' ],
					'post_type' => "icit_option_page"
				) );

				add_option( "{$this->option_page_settings[ 'option_group' ]}_post_id", $this->_options_post );
			}
		}


		/**
		 * Stores a reference to the theme customiser for use when adding theme options
		 *
		 * @param object $wp_customiser The theme customiser object
		 *
		 * @return void
		 */
		final public function _set_customiser( $wp_customiser ) {
			$this->_customiser = $wp_customiser;
		}


		final public function _option_page_scripts( $hook ) {
			global $wp_version;

			// options CSS. May be required by other stylesheets
			wp_register_style( 'icit-options', icit_core::instance()->url . 'css/options.css' );

			if ( $hook !== $this->option_page_settings[ 'hook' ] )
				return;

			// metabox handling
			wp_enqueue_script( 'post' );
			wp_enqueue_style( 'postbox' );

			// admin page CSS
			wp_enqueue_style( 'icit-options' );
		}


		/**
		 * Add the submenu page to hold our settings page
		 *
		 * @return null
		 */
		final public function _add_options_page() {
			$settings = $this->option_page_settings;

			if ( $settings[ 'parent' ] == 'themes.php' ) { // here for purposes of
				$this->option_page_settings[ 'hook' ] = add_theme_page( $settings[ 'title' ], $settings[ 'menu_title' ], $settings[ 'permissions' ], $settings[ 'option_group' ], array( $this, '_form' ) );
			}
			elseif ( $settings[ 'parent' ] != '' )
				$this->option_page_settings[ 'hook' ] = add_submenu_page( $settings[ 'parent' ], $settings[ 'title' ], $settings[ 'menu_title' ], $settings[ 'permissions' ], $settings[ 'option_group' ], array( $this, '_form' ) );

			else
				$this->option_page_settings[ 'hook' ] = add_utility_page( $settings[ 'title' ], $settings[ 'menu_title' ], $settings[ 'permissions' ], $settings[ 'option_group' ], array( $this, '_form' ), $settings[ 'icon_url' ] );
		}


		/**
		 * This should also never be seen as it's being called from a function
		 * that should be overridden by a child class.
		 *
		 * @return null
		 */
		final public function _note( ) {
			printf( __( 'You need to have a %s method when extending this class (%s) or add $create_options_page = false; to your class.', self::DOM ), 'register_settings', __CLASS__ );
		}


		/**
		 * The settings page form.
		 *
		 * @return null
		 */
		final public function _form( ) {
			global $parent_file;

			$settings = $this->option_page_settings;

			if ( ! current_user_can( $settings[ 'permissions' ] ) )
				wp_die( __( 'You do not have permission to use this page.', self::DOM ) );

			// options header
			if ( $parent_file !== 'options-general.php' )
				require( ABSPATH . 'wp-admin/options-head.php' );

			echo '
			<div class="wrap icit-options-page">';

			// allow plugins toextend page before anything else happens
			do_action( 'options_page_before', $settings );
			do_action( "options_page_before_{$settings['option_group']}", $settings );

			// title
			screen_icon( $settings[ 'screen_icon' ] );
			echo '
				<h2>' . esc_html( $settings[ 'title' ] ) . '</h2>';

			// wrap form around everything
			echo '
				<form action="' . admin_url( 'options.php' ) . '" method="post" enctype="multipart/form-data">';

			wp_nonce_field( $settings[ 'option_group' ] );
			/* Used to save closed meta boxes and their order */
			wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false );
			wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );

			settings_fields( $settings[ 'option_group' ] );

			// error/update messages
			settings_errors( $settings[ 'option_group' ] );

			// version & info metabox
			echo '
					<div id="poststuff">
						<div id="post-body" class="metabox-holder columns-' . ( method_exists( get_current_screen(), 'get_columns' ) && 1 == get_current_screen()->get_columns() ? '1' : '2' ) . '">
							<div class="right-column postbox-container" id="postbox-container-1">
								<div class="column-inner">';

			// custom callback content sidebar (branding etc...)
			if ( is_callable( $settings[ 'default_content_side' ] ) )
				call_user_func_array( $settings[ 'default_content_side' ], array( 'options_page' => $settings[ 'option_group' ] ) );
			elseif( is_string( $settings[ 'default_content_side' ] ) )
				echo $settings[ 'default_content_side' ];

			// add a Save Changes box
			add_meta_box( 'save-changes-side', __( 'Save Changes', self::DOM ), array( $this, '_submit_metabox' ), $settings[ 'option_group' ], 'side', 'high' );

			// process sidebar metaboxes
			do_meta_boxes( $settings[ 'option_group' ], 'side', $settings[ 'hook' ] );

			do_action( 'options_page_right', $settings );
			do_action( "options_page_right_{$settings['option_group']}", $settings );

			echo '
								</div>
							</div>
							<div class="left-column postbox-container" id="postbox-container-2">
								<div class="column-inner">';

			// custom callback content
			if ( is_callable( $settings[ 'default_content' ] ) )
				call_user_func_array( $settings[ 'default_content' ], array( 'options_page' => $settings[ 'option_group' ] ) );

			elseif( is_string( $settings[ 'default_content' ] ) )
				echo $settings[ 'default_content' ];

			// settings API hooks
			ob_start();
			do_settings_fields( $settings[ 'option_group' ], 'default' );
			$settings_fields = trim( ob_get_clean() );

			ob_start();
			do_settings_sections( $settings[ 'option_group' ] );
			$settings_sections = trim( ob_get_clean() );

			if ( ! empty( $settings_fields ) || ! empty( $settings_sections ) ) {

				ob_start();
				if ( ! empty( $settings_fields ) ) {
					echo '<table class="form-table">' . $settings_fields . '</table>';
				}
				if ( ! empty( $settings_sections ) ) {
					echo $settings_sections;
				}

				echo ob_get_clean();

			}

			ob_start();
			// normal context metaboxes
			do_meta_boxes( $settings[ 'option_group' ], 'normal', $settings[ 'hook' ] );

			// advanced context metaboxes
			do_meta_boxes( $settings[ 'option_group' ], 'advanced', $settings[ 'hook' ] );

			$meta_boxes = ob_get_clean();

			echo $meta_boxes;

			do_action( 'options_page_left', $settings );
			do_action( "options_page_left_{$settings['option_group']}", $settings );

			echo '
								</div>
							</div>
						</div>';

			do_action( 'options_page_after', $settings );
			do_action( "options_page_after_{$settings['option_group']}", $settings );

			echo '
					</div>
				</form>
			</div>';

		}

		/**
		 * Generic submit button meta box
		 *
		 * @return void
		 */
		public function _submit_metabox() {
			submit_button( null, 'primary', 'submit', false );
		}

	}

}
