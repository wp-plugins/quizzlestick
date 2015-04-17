<?php

/**
 * Theme base class
 *
 * @package icit-core
 */

if ( ! class_exists( 'icit_theme' ) ) {

	abstract class icit_theme {

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
		 * @var object The WordPress theme object.
		 */
		public $_theme = null;

		/**
		 * @var Bool Do we create the default page or not.
		 */
		public $create_options_page = true;

		/**
		 * @var array Setting for the default theme page
		 */
		public $option_page_settings = array(
								'parent' => 'themes.php',
								'title' => 'Theme Options',
								'menu_title' => 'Theme Options',
								'permissions' => 'manage_options',
								'option_group' => 'theme-options',
								'screen_icon' => 'themes',
								'default_content' => null,
								'default_content_side' => null,
								'show_theme_info' => true
							);

		/**
		 * @var array Store for each page in the theme, most likely only one
		 * page in the theme but we need to allow for more.
		 */
		private $option_pages = array();

		/**
		 * Thumbnail X and Y dims in px.
		 */
		public $thumbnail_x = 120;
		public $thumbnail_y = 120;
		public $thumbnail_crop = true;
		public $body_ua_class = false;

		/**
		 * @var bool Did the __construct run or not?
		 */
		private $did_setup = false;

		public static $url;
		public static $dir;
		public $content_width = 900;

		/**
		 * Sets up the theme actions and data
		 *
		 * @param array $settings The plugin configuration
		 *
		 * @return void
		 */
		public function __construct( $settings = array() ) {
			global $content_width, $wp_version;

			// The constructor has been triggered...
			$this->did_setup = true;

			// Check we're happening at the right time.
			if ( did_action( 'init' ) )
				error_log( __( 'icit_theme should extend your theme class and parent::__construct should be activated before the init action.', self::DOM ), E_USER_WARNING );

			// Make sure core is the correct version for this module.
			if ( version_compare( icit_core::VERSION, $this::VERSION, '<' ) ) {
				error_log( sprintf( __( 'Wrong ICIT Core version. Class "%3$s" expecting core version version %2$s but got version %1$s in file %4$s', self::DOM ), icit_core::VERSION, self::VERSION, __CLASS__, __FILE__ ), E_USER_WARNING );
				return false;
			}

			// Die if we're running with old stuff.
			if ( ! is_admin() && ! version_compare( $wp_version, $this::WP_VER, 'ge' ) || ! version_compare( phpversion(), $this::PHP_VER, 'ge' ) )
				wp_die( sprintf( 'You need %s version of WordPress and %s version of PHP to use this theme.', $this::WP_VER, $this::PHP_VER ) );

			// Localisation $this:DOM is the theme, self::DOM is core
			if ( ! defined( 'ICIT_THEME_DOM' ) )
				define( 'ICIT_THEME_DOM', $this::DOM );

			// Grab the domain files for the theme, core should have been delt with already
			$locale = get_locale();
			if ( file_exists( get_template_directory() . '/lang/' . $this::DOM . '-' . $locale . '.mo' ) )
				load_textdomain( $this::DOM, get_template_directory() . '/lang/' . $this::DOM . '-' . $locale . '.mo' );

			// Merge the settings passed in with the defaults
			$this->option_page_settings = wp_parse_args( $settings, $this->option_page_settings );

			// Add the settings page if we've not got one and it's not been forbidden
			if ( $this->create_options_page && empty( $this->options_pages ) )
				$this->add_page( $this->option_page_settings );

			// Threaded comments script (required)
			add_action( 'wp_enqueue_scripts', array( $this, 'comment_reply_script' ) );

			// Theme support for feed links (required)
			add_theme_support( 'automatic-feed-links' );

			// Define content width (required)
			if ( ! isset( $content_width ) )
				$content_width = $this->content_width;

			// Add post thumbnails
			add_theme_support( 'post-thumbnails' );
			set_post_thumbnail_size( $this->thumbnail_x, $this->thumbnail_y, $this->thumbnail_crop );

			// General init methods for overriding
			add_action( 'init', array( $this, 'init' ), 9 );
			add_action( 'after_setup_theme', array( $this, 'setup' ), 2 );
			add_action( 'admin_init', array( $this, 'admin_init' ) );
			add_action( 'widgets_init', array( $this, 'widgets_init' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
			add_action( 'after_switch_theme', array( $this, 'on_activate' ) );
			add_action( 'switch_theme', array( $this, 'on_deactivate' ) ); // calls self::deactivation
			add_action( 'wp', array( $this, 'wp' ) );

			if ( ! is_admin() ) {
				add_action( 'wp_head', array( $this, 'wp_head' ), 10 );
				add_action( 'wp_head', array( $this, 'autoload_ie_css' ), 11 );


				// Some basic theme modification
				if ( get_option( 'icit_excerpt_length', false ) )
					add_filter( 'excerpt_length', array( $this, 'filter_excerpt_length' ), 1 );

				if ( get_option( 'icit_excerpt_more_link', false ) )
					add_filter( 'excerpt_more', array( $this, 'filter_excerpt_more_link' ), 1 );

				//add_filter( 'language_attributes', array( $this, 'filter_no_script_class' ) );
				add_action( 'wp_enqueue_scripts', array( $this, 'filter_no_script_script' ), 1 );
				add_filter( 'the_content', array( $this, 'filter_extend_content' ), 11 );
				add_filter( 'the_excerpt', array( $this, 'filter_extend_excerpt' ), 11 );
				add_filter( 'post_class',  array( $this, 'filter_has_thumbnail_class' ) );
				if ( $this->body_ua_class )
					add_filter( 'body_class',  array( $this, 'filter_add_ua_body_class' ) );

				// Enables $in_same_cat for any taxonomy
				add_filter( 'get_previous_post_join', array( $this, 'filter_taxonomical_prev_next' ), 10, 3 );
				add_filter( 'get_previous_post_where', array( $this, 'filter_taxonomical_prev_next' ), 10, 3 );
				add_filter( 'get_next_post_join', array( $this, 'filter_taxonomical_prev_next' ), 10, 3 );
				add_filter( 'get_next_post_where', array( $this, 'filter_taxonomical_prev_next' ), 10, 3 );
			}

			add_action( 'admin_init', array( $this, 'default_setting_fields' ) );
		}


		/**
		 * Customise the excerpt options
		 *
		 * @return null
		 */
		public function default_setting_fields( ) {
			add_settings_section( 'icit_extra_excerpt', __( 'Excerpt', self::DOM ), '__return_false', 'reading' );
			register_setting( 'reading', 'icit_excerpt_length', array( 'icit_fields', 'validate_numeric' ) );
			register_setting( 'reading', 'icit_excerpt_more_link', array( 'icit_fields', 'validate_text' ) );

			add_settings_field( 'icit_excerpt_more_link', __( 'Read more link', self::DOM ), array( 'icit_fields', 'field_text' ), 'reading', 'icit_extra_excerpt', array( 'name' => 'icit_excerpt_more_link', 'description' => __( 'Shown after the excerpt as a link to the full content. default is blank.', self::DOM ) ) );
			add_settings_field( 'icit_excerpt_length', __( 'Excerpt length', self::DOM ), array( 'icit_fields', 'field_numeric' ), 'reading', 'icit_extra_excerpt', array( 'name' => 'icit_excerpt_length', 'description' => __( 'Words, default is 55 minimum is 3.', self::DOM ), 'default' => 55 ) );
		}


		/**
		 * Add IE6-8 CSS files if they exists. Also respects child theme wishes.
		 *
		 * @return null
		 */
		public function autoload_ie_css() {
			for ( $i = 6; $i <= 8; $i++ ) {
				$file = false;
				if ( file_exists( get_template_directory() . '/css/ie' . $i . '.css' ) ) {
					$time = filectime( get_template_directory() . '/css/ie' . $i . '.css' );
					$file = get_template_directory_uri() . '/css/ie' . $i . '.css?ver=' . $time;
				}

				if ( is_child_theme() && file_exists( get_stylesheet_directory() . '/css/ie' . $i . '.css' ) ) {
					$time = filectime( get_stylesheet_directory() . '/css/ie' . $i . '.css' );
					$file = get_stylesheet_directory_uri() . '/css/ie' . $i . '.css?ver=' . $time;
				}

				if ( $file !== false ) {
					echo "\n<!--[if IE $i]>\n";
					echo '<link rel="stylesheet" id="IE-' . $i . '-style" href="' . $file . '" type="text/css" media="all" />';
					echo "\n<![endif]-->";
				}
			}
		}

		/**
		 * Empty place holder functions for easy action hooks in parent class
		 */
		public function setup() {
			// empty placeholder
		}

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


		public function wp_head() {
			// empty placeholder
		}

		/**
		 * Child class should replace this
		 */
		public function register_settings() {
			// This function should be overridden in the child class
			if ( function_exists( 'add_settings_field' ) ) {
				add_settings_field( 'warning', __( 'Instructions', self::DOM ), array( $this, '_note' ), $this->option_page_settings[ 'option_group' ], 'default' );
			}
		}


		public function filter_taxonomical_prev_next( $sql, $in_same_cat, $excluded_categories ) {
			global $post;
			if ( $post->post_type != 'post' && $in_same_cat ) {
				$taxonomies = get_object_taxonomies( $post->post_type );
				$cat_array = wp_get_object_terms($post->ID, $taxonomies, array('fields' => 'ids'));
				if ( count( $cat_array ) )
					$sql = str_replace( "tt.term_id IN ()", "tt.term_id IN (". implode( ",", $cat_array ) . ") ", $sql );
				else
					$sql = str_replace( "AND tt.term_id IN ()", "", $sql );
				$sql = str_replace( "tt.taxonomy = 'category'", "tt.taxonomy IN ('" . implode( "','", $taxonomies ) . "') ", $sql );
			}
			return $sql;
		}


		/**
		 * Add browser detection class to the body tag. Best not to rely on this as
		 * it can be broken by caching and UA monging
		 *
		 * @param array $class Current body class as passed to us by the wp action
		 *
		 * @return array    Now with added UA class element
		 */
		public function filter_add_ua_body_class( $class = array( ) ) {
			$useragent = getenv( 'HTTP_USER_AGENT' );
			$handhelds = array(
							   'iPhone',
							   'iPod',
							   'incognito',
							   'webmate',
							   'Android',
							   'dream',
							   'CUPCAKE',
							   'blackberry9500',
							   'blackberry9530',
							   'blackberry9520',
							   'blackberry9550',
							   'blackberry 9800',
							   'webOS',
							   's8000',
							   'bada',
							   'Googlebot-Mobile'
							);

			if ( preg_match( '!gecko/\d+!i', $useragent ) )
				$class[ ] = 'gecko';
			elseif ( preg_match( '!(applewebkit|konqueror)/[\d\.]+!i', $useragent ) )
				$class[ ] = 'webkit';
			elseif ( preg_match( '!msie\s+(\d+\.\d+)!i', $useragent, $match ) ) {
				$class[ ] = 'ie';
				$version = floatval( $match[ 1 ] );

				/* Add an identifier for IE versions. */
				if ( $version >= 10 )					array_push( $class, 'ie10' );
				if ( $version >= 9 &&	$version < 10 )	array_push( $class, 'ie9' );
				if ( $version >= 8 &&	$version < 9 )	array_push( $class, 'ie8' );
				if ( $version >= 7 &&	$version < 8 )	array_push( $class, 'ie7' );
				if ( $version >= 6 &&	$version < 7 )	array_push( $class, 'ie6' );
				if ( $version >= 5.5 &&	$version < 6 )	array_push( $class, 'ie55' );
				if ( $version >= 5 &&	$version < 5.5 ) array_push( $class, 'ie5' );
				if ( $version < 5 ) 					array_push( $class, 'ieold' );
			}

			$preg = implode( '|', $handhelds );
			if ( preg_match( '/(' . $preg . ')/i', $useragent ) ) {
				$class[ ] = 'handheld';
			}

			return $class;
		}


		/**
		 * Gives you an optional filter at the head and tail of the_content
		 *
		 * @param string $content The content
		 *
		 * @return string    the_content with header and footer filters added.
		 */
		public function filter_extend_content( $content ) {
			$head = '';
			$foot = '';
			$head = apply_filters( 'content_head', $head, $content );
			$foot = apply_filters( 'content_foot', $foot, $content );
			return $head . trim( $content ) . $foot;
		}


		/**
		 * Add has-thumbnail class to the post_classes when it has a post_thumb.
		 *
		 * @param array $classes current classes assigned to the post
		 *
		 * @return array    Same array with our class added if needed.
		 */
		public function filter_has_thumbnail_class( $classes ) {
			global $post;
			if ( function_exists( 'has_post_thumbnail' ) && has_post_thumbnail( $post->ID ) )
				$classes[] = 'has-thumbnail';
			return $classes;
		}


		/**
		 * Gives you an optional filter at the head and tail of the_excerpt
		 *
		 * @param string $content The excerpt
		 *
		 * @return string    the_excerpt with header and footer filters added.
		 */
		public function filter_extend_excerpt( $excerpt ) {
			$head = '';
			$foot = '';
			$head = apply_filters( 'excerpt_head', $head, $excerpt );
			$foot = apply_filters( 'excerpt_foot', $foot, $excerpt );
			return $head . trim( $excerpt ) . $foot;
		}


		/**
		 * If the excerpt length option is set then add the filter that will be
		 * added to set the length.
		 *
		 * @param int $length How many words should the excerpt contain.
		 *
		 * @return int    Number of words
		 */
		public function filter_excerpt_length( $length = 55 ) {
			$new_length = intval( get_option( 'icit_excerpt_length', 55 ) );
			return $new_length > 2 ? $new_length : $length;
		}


		/**
		 * If the excerpt more link option is set then we'll replace the normal
		 * hellip with a link to the associated post.
		 *
		 * @param string $helips Text to replace the excerpt tail with
		 *
		 * @return string    Linked version on the excerpt tail.
		 */
		public function filter_excerpt_more_link( $helips = '' ) {
			$text = get_option( 'icit_excerpt_more_link', __( 'Read more', self::DOM ) );
			$text = str_replace( ' ', '&nbsp;', $text );
			return '&#8230; <a class="' . apply_filters( 'icit_excerpt_more_link_class', 'excerpt-more' ) . '" href="' . apply_filters( 'icit_excerpt_more_link', get_permalink( ) ) . '" >' . esc_html( $text ) . '</a>' ;
		}


		/**
		 * Add the no-js class to the html tag
		 *
		 * @param string $lang_attribs html tag attributes
		 *
		 * @return string
		 */
		public function filter_no_script_class( $lang_attribs = '' ) {
			if ( stristr( $lang_attribs, 'class=' ) === false )
				$lang_attribs .= ' class="no-js"';

			return $lang_attribs;
		}


		/**
		 * Add js as high in the head as we can to change the no-js tag to has
		 * js.
		 *
		 * @return null
		 */
		public function filter_no_script_script() {
			// Add js to change the no-js class attached to the html tag to js which lets you use hide-if-no-js type classes. ?>
<script type="text/javascript">(function(){var h=document.getElementsByTagName('html')[0],c=h.className;c=c.replace(/no-js/,'js');h.className=c;})();</script>
<?php
		}


		/**
		 * Queues up the comment reply script on singular pages
		 *
		 * @return void
		 */
		public function comment_reply_script() {
			if ( is_singular() ) wp_enqueue_script( 'comment-reply' );
		}


		/**
		 * This should also never be seen as it's being called from a function
		 * that should be overridden by a child class.
		 *
		 * @return null
		 */
		public function _note() {
			printf( __( 'You need to have a %s method when extending this class (%s) or add $create_options_page = false; to your class.', self::DOM ), 'register_settings', __CLASS__ );
		}


		public function add_page( $args = array( ) ) {
			if ( ! $this->did_setup )
				self::__construct( );

			$defaults = $this->option_page_settings;
			$defaults[ 'register_settings' ] = array( $this, 'register_settings' );
			$defaults[ 'use_customiser' ] = true;

			$r = wp_parse_args( $args, $defaults );
			$r[ 'option_group' ] = $option_group = sanitize_title( $r[ 'option_group' ] );

			// Set branded box
			if ( ! isset( $r[ 'default_content_side' ] ) )
				$r[ 'default_content_side' ] = $this->get_theme_meta_box();

			if ( ! isset( $this->options_pages[ $option_group ] ) )
				$this->options_pages[ $option_group ] = new icit_options( $r );

			// Return the created object.
			return $this->options_pages[ $option_group ];
		}


		public function get_page( $option_group = 'theme-options' ) {
			$option_group = sanitize_title( $option_group );

			if ( isset( $this->options_pages[ $option_group ] ) )
				return $this->options_pages[ $option_group ];
			else
				return false;
		}


		/**
		 * Outputs a branded theme meta box on an options page
		 *
		 * @return string 	HTML metabox output
		 */
		public function get_theme_meta_box() {
			global $icit_core;
			$icit_core = icit_core::instance();

			// This is quite heavy so we want it in a plce where it only runs once and only when needed.
			if ( ! isset( $this->_theme ) )
				$this->_theme = wp_get_theme();

			$theme_data = '
				<div class="postbox icit-branding">
					<div class="icit-logo">interconnect<span>/</span><strong>it</strong></div>
					<div class="icit-info">
						<h3>' . $this->_theme->get( 'Name' ) . '</h3>
						<div class="icit-version">v<strong>' . $this->_theme->get( 'Version' ) . '</strong></div>
						<p class="icit-description">' . $this->_theme->get( 'Description' ) . '</p>
						<div class="icit-url"><a href="' . $this->_theme->get( 'ThemeURI' ) . '">' . __( 'Visit theme home page', self::DOM ) . '</a></div>
						<div class="icit-credit">by <a href="' . $this->_theme->get( 'AuthorURI' ) . '">' . $this->_theme->get( 'Author' ) . '</a></div>';

			if ( is_super_admin( ) ) {
				$theme_data .= '
					<div class="core-location">v' . $icit_core::VERSION . ':<a href="' . esc_attr( $icit_core->url ) . '">' . __( 'Core files', self::DOM ) . '</a></div>';
			}

			$theme_data .= '
					</div>
				</div>';

			return $theme_data;
		}

	}
}
