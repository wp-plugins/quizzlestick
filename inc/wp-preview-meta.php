<?php

if ( ! class_exists( 'wp_preview_meta' ) ) {

	// post meta for previews
	class wp_preview_meta {

		private $doing_preview = false;

		public function __construct() {
			add_filter( 'add_post_metadata', 	array( $this, 'add' 	), 10, 5 );
			add_filter( 'update_post_metadata', array( $this, 'update' 	), 10, 5 );
			add_filter( 'delete_post_metadata', array( $this, 'delete' 	), 10, 5 );
			add_filter( 'get_post_metadata', 	array( $this, 'get' 	), 10, 4 );
		}

		public function is_preview() {
			if( is_admin() )
				return ! $this->doing_preview && isset( $_POST[ 'wp-preview' ] ) && $_POST['wp-preview'] == 'dopreview';

			// And on the front end: (props @yrosen)
			return ! $this->doing_preview && isset( $_GET[ 'preview' ] ) && $_GET[ 'preview' ] == 'true';
		}

		private function mod_key( $key ) {
			if ( empty( $key ) )
				return $key;
			if ( strlen( $key ) > 50 )
				$key = md5( $key );
			return "_preview__{$key}";
		}

		public function __call( $method, $args ) {
			if ( ! $this->is_preview() || ! function_exists( "{$method}_metadata" ) )
				return $args[ 0 ];

			// check we're only changing the meta key for the current post
			if ( $args[ 1 ] !== get_the_ID() )
				return $args[ 0 ];

			// replace $check with $meta_type
			$args[ 0 ] = 'post';

			// modify key
			$args[ 2 ] = $this->mod_key( $args[ 2 ] );

			// call original function but make sure we don't get stuck in a loop
			$this->doing_preview = true;
			$result = call_user_func_array( "{$method}_metadata", $args );
			$this->doing_preview = false;

			return $result;
		}

	}

	$wp_preview_meta = new wp_preview_meta();

}
