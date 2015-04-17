<?php

/**
 * Admin notification class
 *
 * @package icit-core
 */

if ( ! class_exists( 'icit_notices' ) ) {
	add_action( 'admin_init', array( 'icit_notices', 'setup' ) );

	class icit_notices {

		/**
		 * Class text domain
		 */
		const DOM = icit_core::DOM;
		const VERSION = '1.0.0'; // The version of core this file belongs to.

		/**
		 * Add action hooks
		 *
		 * @return void
		 */
		public static function setup() {

			// flash notifications and naggers
			add_action( 'admin_notices', array( 'icit_notices', 'display_notices' ) );

			// ajax handler for removing notices via dismiss link
			add_action( 'wp_ajax_remove_notice', array( 'icit_notices', 'remove' ) );

			// script
			add_action( 'admin_print_footer_scripts', array( 'icit_notices', 'notices_js' ) );

		}


		/**
		 * Returns all notices from the DB
		 *
		 * @return array    Array of serialised notices
		 */
		public static function get_notices() {
			global $wpdb;
			return $wpdb->get_col( "SELECT option_value FROM {$wpdb->options} WHERE option_name LIKE '_notice_%'" );
		}


		public static function get_screen_notice( $notice, $current_user ) {
			$notice = maybe_unserialize( $notice );

			// check notice is for current user
			if ( $notice[ 'user' ] && intval( $notice[ 'user' ] ) !== $current_user->ID )
				return false;

			// Super users are weird, they're admins with no roles.
			$roles = $current_user->roles;
			if ( empty( $roles ) && is_super_admin() )
				$roles[] = 'administrator';

			// check user role for multiuser notices
			if ( ! $notice[ 'user' ] && is_string( $notice[ 'role' ] ) && ! in_array( $notice[ 'role' ], $roles ) )
				return false;

			// check screen for screen-specific notices
			if ( isset( $notice[ 'screen' ] ) && $notice[ 'screen' ] && $notice[ 'screen' ] !== get_current_screen()->id )
				return false;

			// remove & skip dismissed
			if ( isset( $_GET[ 'remove_notice_id' ] ) && $_GET[ 'remove_notice_id' ] == $notice[ 'id' ] ) {
				self::remove( $notice[ 'id' ], isset( $_GET[ 'notice_user' ] ) ? $_GET[ 'notice_user' ] : false );
				return false;
			}

			// remove flash notices
			if ( ! $notice[ 'persist' ] )
				self::remove( $notice[ 'id' ], $notice[ 'user' ] );

			return $notice;
		}


		/**
		 * Gets the notices for the current screen
		 *
		 * @return array 	array of notices
		 */
		public static function get_screen_notices() {
			// retrieve notices
			$opts = self::get_notices();

			// notice collection
			$notices = array();

			// who is viewing
			$current_user = get_user_by( 'id', get_current_user_id() );

			foreach ( $opts as $notice ) {
				$processed_notice = self::get_screen_notice( $notice, $current_user );
				if ( $processed_notice != false ) {
					$notices[] = $processed_notice;
				}
			}

			return $notices;
		}


		/**
		 * Displays the notices
		 *
		 * @uses self::get_notices
		 *
		 * @return void
		 */
		public static function display_notices() {

			$notices = self::get_screen_notices();

			foreach ( $notices as $notice ) {
				// dismissable
				$dismiss_args = array( 'remove_notice_id' => $notice[ 'id' ] );
				if ( $notice[ 'user' ] )
					$dismiss_args[ 'notice_user' ] = $notice[ 'user' ];

				$dismiss_link = '<p class="dismiss-link"><a data-id="' .
					implode( '" data-user="', $dismiss_args ) . '" href="' .
					add_query_arg( $dismiss_args ) . '">' . __( 'Dismiss', self::DOM ) .
					'</a></p>';
				// output notices
				echo '
					<div class="' . ( $notice[ 'error' ] ? 'error' : 'updated' ) . '">' .
						wpautop( $notice[ 'notice' ], false ) .
						( $notice[ 'dismissable' ] && $notice[ 'persist' ] ? $dismiss_link : '' ) . '
					</div>';
			}

		}


		/**
		 * Add a notice to display in the wordpress admin. Can be tailored to
		 * appear once, to be permanent until removed using self::remove.
		 * Can also be targeted to specific users.
		 *
		 * @param string $id          A unique identifier to refer to the notice
		 * @param string $notice     The notice content
		 * @param array $args 		Settings to modify the way the notice behaves and displays
		 * 			bool 'error'       	If true the notice is displayed in a red box as an error
		 * 			bool|int 'user'    	False means notice is for everyone, true means for current user
		 * 			bool|string 'role' 	False means for all user roles, otherwise notice is shown to specified role
		 * 			bool 'persist'     	Whether the notice should stay for more than one page load or until dismissed
		 * 			bool 'dismissable' 	Whether the notice can be removed by clicking a link
		 *
		 * @return void
		 */
		public static function add( $id, $notice, $args = array() ) {
			global $wp_roles;

			$args = wp_parse_args( $args, array(
				'error' => false,
				'user' => false,
				'role' => 'administrator',
				'persist' => true,
				'dismissable' => true,
				'screen' => false
			) );

			// check role
			if ( is_string( $args[ 'role' ] ) && ! array_key_exists( $args[ 'role' ], $wp_roles->roles ) ) {
				$args[ 'role' ] = 'administrator';
			}

			// get specific screen if true
			if ( $args[ 'screen' ] === true )
				$args[ 'screen' ] = get_current_screen()->id;

			// option_name col is max 64 chars
			$args[ 'user' ] = is_int( $args[ 'user' ] ) && $args[ 'user' ] ? $args[ 'user' ] : ( $args[ 'user' ] === true ? get_current_user_id() : false );
			$key = self::get_notice_key( $id, $args[ 'user' ] );

			$msg = array_merge( array(
				'id' => $id,
				'key' => $key,
				'notice' => $notice
			), $args );

			// no autoloading needed, collected in a single query so use update
			update_option( $key, $msg );
		}

		public static function remove( $id = '', $user = false ) {
			// handle ajax
			if ( isset( $_POST[ '_wpnonce' ] ) )
				check_ajax_referer( 'remove_notice' );
			if ( isset( $_POST[ 'id' ] ) )
				$id = sanitize_text_field( $_POST[ 'id' ] );
			if ( isset( $_POST[ 'user' ] ) )
				$user = $_POST[ 'user' ];

			// handle get param
			if ( is_string( $user ) && $user == 'true' )
				$user = true;

			$key = self::get_notice_key( $id, $user );

			// remove notice
			delete_option( $key );
		}

		public static function add_flash( $id, $notice, $error = false, $user = true, $role = false ) {
			self::add( $id, $notice, array(
				'error' => $error,
				'user' => $user,
				'persist' => false,
				'role' => $role
			) );
		}

		public static function add_nagger( $id, $notice, $error = false, $user = false, $role = 'administrator' ) {
			self::add( $id, $notice, array(
				'error' => $error,
				'user' => $user, 'persist' => true,
				'dismissable' => false, 'role' => $role
			) );
		}

		public static function remove_nagger( $id, $user = false ) {
			self::remove( $id, $user );
		}

		public static function notice_exists( $id, $user = false ) {
			$key = self::get_notice_key( $id, $user );
			return get_option( $key, false );
		}


		/**
		 * Generates an option key to identify the database option
		 *
		 * @param string $id   An identifier for the notice for later reference
		 * @param bool|int $user Target notices to a user by passing in a User ID.
		 * 			False if notice is for everyone, true if notice is for
		 * 			current user
		 *
		 * @return string    The unique identifier for the notice
		 */
		public static function get_notice_key( $id, $user ) {
			$user = is_int( $user ) && $user ? $user : ( $user ? get_current_user_id() : '' );
			$key = substr( "_notice_{$user}_{$id}", 0, 64 );
			return $key;
		}


		/**
		 * Notices handling js
		 *
		 * @return void
		 */
		public static function notices_js() {
			?>
			<script>
				// handle ajax notice dismissals
				;(function($){
					$('body').on('click','.dismiss-link a',function(e){
						e.preventDefault();
						var $a = $(this),
							id = $a.data('id'),
							user = $a.data('user');
						$(this).parent().parent().slideUp(400,function(){
							$(this).remove();
							$.post(ajaxurl,{
								action: 'remove_notice',
								id: id,
								user: user,
								_wpnonce: '<?php echo wp_create_nonce( 'remove_notice' ); ?>'
							});
						});
					});
				})(jQuery);
			</script>
			<?php
		}

	}
}
