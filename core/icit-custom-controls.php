<?php


if ( ! class_exists( 'icit_customiser_textarea_control' ) && class_exists( 'WP_Customize_Control' ) ) {

	class icit_customiser_textarea_control extends WP_Customize_Control {

		public $type = 'textarea';
		public $tiny_mce = true;
		public $media_buttons = true;
		public $teeny = true;
		public $textarea_rows = 5;

		public function __construct( $manager, $id, $args = array() ) {
			if ( isset( $args[ 'tiny_mce' ] ) ) $this->tiny_mce = $args[ 'tiny_mce' ];
			if ( isset( $args[ 'media_buttons' ] ) ) $this->tiny_mce = $args[ 'media_buttons' ];
			if ( isset( $args[ 'teeny' ] ) ) $this->tiny_mce = $args[ 'teeny' ];
			parent::__construct( $manager, $id, $args );
		}

		public function render_content() {
			echo '<label><span class="customize-control-title">' . esc_html( $this->label ) . '</span></label>';
			ob_start();
			icit_fields::field_textarea( array(
				'name' => (string) $this->setting->id,
				'description' => '',
				'default' => $this->setting->default,
				'tiny_mce' => false, // $this->tiny_mce; Until I can figure out how to make tinyMCE work on the cusomizer this is being forced to off.
				'edit_args' => array(
					'media_buttons' => false,
					'teeny' => true,
					'textarea_rows' => $this->textarea_rows
			 	)
			) );
			$textarea = ob_get_clean();
			echo str_replace( '<textarea', '<textarea ' . $this->get_link(), $textarea );
		}

		public function enqueue( ) {
			//wp_enqueue_script( 'editor' );
		}
	}

}

if ( ! class_exists( 'icit_customiser_date_time_control' ) && class_exists( 'WP_Customize_Control' ) ) {

	class icit_customiser_date_time_control extends WP_Customize_Control {

		public $type = 'date-time';

		public function __construct( $manager, $id, $args = array() ) {
			// custom init
			parent::__construct( $manager, $id, $args );
		}

		public function render_content() {
			echo '<label><span class="customize-control-title">' . esc_html( $this->label ) . '</span></label>';
			ob_start();
			icit_fields::field_date_time( array(
				'name' => $this->setting->id,
				'description' => '',
				'default' => $this->setting->default
			) );
			$date_field = ob_get_clean();
			echo str_replace( '<input', '<input ' . $this->get_link(), $date_field );
		}

	}

}

if ( ! class_exists( 'icit_customiser_term_select_control' ) && class_exists( 'WP_Customize_Control' ) ) {

	class icit_customiser_term_select_control extends WP_Customize_Control {

		public $type = 'term-select';
		public $taxonomy = 'category';

		public function __construct( $manager, $id, $args = array() ) {
			if ( isset( $args[ 'taxonomy' ] ) && in_array( $args[ 'taxonomy' ], get_taxonomies() ) )
				$this->taxonomy = $args[ 'taxonomy' ];
			parent::__construct( $manager, $id, $args );
		}

		public function render_content() {
			$dropdown = wp_dropdown_categories(
				array(
					'name'              => $this->setting->id,
					'echo'              => 0,
					'show_option_none'  => __( '&mdash; Select &mdash;' ),
					'option_none_value' => '0',
					'selected'          => $this->value(),
					'taxonomy' 			=> $this->taxonomy
				)
			);

			$dropdown = str_replace( '<select', '<select ' . $this->get_link(), $dropdown );

			printf(
				'<label class="customize-control-select"><span class="customize-control-title">%s</span> %s</label>',
				$this->label,
				$dropdown
			);

		}

	}

}


if ( ! class_exists( 'ICIT_Customize_Image_Control_AttID' ) && class_exists( 'WP_Customize_Image_Control' ) ) {
	class ICIT_Customize_Image_Control_AttID extends WP_Customize_Image_Control {

		public $context = 'custom_image';

		public function __construct( $manager, $id, $args ) {
			$this->get_url = array( $this, 'get_img_url' );
			if ( ! empty( $id ) )
				$this->context = $id;

			parent::__construct( $manager, $id, $args );
		}

		// As our default save deals with attachment ids not urls we needs this.
		public function get_img_url( $attachment_id = 0 ) {
			if ( is_numeric( $attachment_id ) && wp_attachment_is_image( $attachment_id ) )
				list( $image, $x, $y ) = wp_get_attachment_image_src( $attachment_id );

			return ! empty( $image ) ? $image : $attachment_id;
		}


		public static function attachment_guid_to_id( $value ) {
			global $wpdb;
			if ( ! is_numeric( $value ) ) {
				$attachment_id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type = 'attachment' AND guid = %s ORDER BY post_date DESC LIMIT 1;", $value ) );
				if ( ! is_wp_error( $attachment_id ) && wp_attachment_is_image( $attachment_id ) )
					$value = $attachment_id;
			}

			return $value;
		}

		public function tab_uploaded() {
			$backgrounds = get_posts( array(
				'post_type'  => 'attachment',
				'meta_key'   => '_wp_attachment_context',
				'meta_value' => $this->context,
				'orderby'    => 'none',
				'nopaging'   => true,
			) );

			?><div class="uploaded-target"></div><?php

			if ( empty( $backgrounds ) )
				return;

			foreach ( (array) $backgrounds as $background )
				$this->print_tab_image( esc_url_raw( $background->guid ) );
		}

	}
}
