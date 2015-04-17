<?php

if ( ! class_exists( 'icit_defaults' ) ) {

	class icit_defaults {

		/**
		 * @var array Used to force default option for the named options to the
		 * matching property on this object
		 */
		private $defaults = array( );

		private $site = false;

		/**
		 * Construtor
		 *
		 * @param bool $site Will this be a site option or a normal get_option?
		 *
		 * @return null
		 */
		public function __construct( $site = false ) {
			if ( is_bool( $site ) && is_multisite( ) )
				$this->site = $site;
		}


		public function __get( $option ) {
			$option = esc_attr( $option );
			if ( ! empty( $this->defaults[ $option ] ) ) {
				return $this->defaults[ $option ];
			}

			return null;
		}


		public function __set( $option, $value ) {
			$option = esc_attr( $option );
			if ( empty( $this->defaults[ $option ] ) ) {
				$this->defaults[ $option ] = $value;

				if ( $this->site ) {
					add_filter( 'default_site_option_' . $option, array( $this, 'filter_default_options' ), 11, 1 );
					add_filter( 'pre_update_site_option_' . $option, array( $this, 'pre_update_option' ), 11, 2 );
				}
				else {
					add_filter( 'default_option_' . $option, array( $this, 'filter_default_options' ), 11, 1 );
					add_filter( 'pre_update_option_' . $option, array( $this, 'pre_update_option' ), 11, 2 );
				}
			}
		}


		/**
		 * Make sure options get added to the db.
		 *
		 * @param string $newvalue The value the option is to be set to.
		 * @param string $oldvalue The value is used to be.
		 *
		 * @return string    The $newvalue unchanged.
		 */
		public function pre_update_option( $newvalue = '', $oldvalue = ''  ) {

			$filter = current_filter();

			// Find the option name
			preg_match( '/^pre_update_(?:site_)?option_(.*)/is', $filter, $matches );
			if ( ! empty( $matches[ 1 ] ) )
				$option = $matches[ 1 ];

			// Determine the need to add the option?
			if ( $newvalue != $oldvalue && $oldvalue == $this->defaults[ $option ] ) {

				// We'll need to run add_option to make sure this hits the db.
				if ( ( ! $this->site && add_option( $option, $newvalue ) ) ||
						( $this->site && add_site_option( $option, $newvalue ) ) ) {

					// Set these the same so the next step taken by update option is return.
					$newvalue = $oldvalue;
				}

			}

			return $newvalue;
		}


		public function filter_default_options( $default = '' ) {
			// Something has been passed to the get_option let's honour that.
			if ( ! empty( $default ) )
				return $default;

			$filter = current_filter();
			preg_match( '/^default_(?:site_)?option_(.*)/is', $filter, $matches );
			if ( ! empty( $matches[ 1 ] ) )
				$option = $matches[ 1 ];

			if ( isset( $this->defaults[ $option ] ) )
				return $this->defaults[ $option ];

			return $default;
		}
	}
}
