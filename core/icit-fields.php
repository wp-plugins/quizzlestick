<?php
/*
 Plugin Name: ICIT Fields
 Plugin URI: http://www.interconnectit.com
 Description: Class containing several standard form fields which can be either call directly or the class can be extended.
 Version: 1.0
 Author: James R Whitehead
 Author URI: http://www.interconnectit.com
*/

if ( class_exists( 'icit_core' ) && ! class_exists( 'icit_fields' ) ) {

	class icit_fields {

		const DOM = icit_core::DOM;
		const VERSION = '1.0.0';

		public static $type = 'option';
		public static $object_id = -1;

		/**
		 * @var array Storage place for each ID attr set.
		 */
		public static $ids = array();

		/**
		 * Simple boolean field
		 * The value is either 0 for unchecked or 1 for checked.
		 *
		 * @param array $args An array of parameters used within this method.
		 * 			field:: The name and id of the option
		 * 			description:: Message to show next to the field.
		 *
		 * @return null
		 */
		public static function field_boolean( $args = array( ) ) {
			$defaults = array(
							  'name' => 'regular_text',
							  'description' => '',
							  'default' => false
							);

			$r = wp_parse_args( $args, $defaults );
			extract( $r, EXTR_SKIP );

			// Get the value from the db
			$option_args[ 'default' ] = ! empty( $default ) ? $default : null;
			$value = isset( $value ) ? $value : self::get_current_value( $name, $option_args );

			printf( '<label for="%1$s"><input type="checkbox" name="%1$s" id="%4$s" value="1" %3$s /> %2$s</label>',
				   esc_attr( $name ),
				   $description,
				   checked( $value == 1, true, false ),
				   self::the_id( $name )
				);
		}


		/**
		 * Regular text field.
		 *
		 * @param array $args An array of parameters used within this method.
		 * 			field:: The name and id of the option
		 * 			description:: Message to show next to the field.
		 *
		 * @return null
		 */
		public static function field_text( $args = array( ) ) {
			$defaults = array(
							  'name' => 'regular_text',
							  'description' => '',
							  'default' => '',
							  'type' => 'text',
							  'class' => 'regular-text',
							  'size' => 32,
							  'attr' => false
							);

			$r = wp_parse_args( $args, $defaults );
			extract( $r, EXTR_SKIP );

			// Get the value from the db
			$option_args[ 'default' ] = ! empty( $default ) ? $default : null;
			$value = isset( $value ) ? $value : self::get_current_value( $name, $option_args ) ;

			$types = apply_filters( 'field_text_types', array( 'text', 'color', 'date', 'datetime', 'datetime-local', 'email', 'month', 'number', 'range', 'search', 'tel', 'time', 'url', 'week', 'password' ) );
			printf( '<input type="%3$s" name="%1$s" id="%6$s" value="%2$s" class="%4$s" size="%5$s"%6$s />',
				   esc_attr( $name ),
				   esc_attr( $value ),
				   in_array( $type, $types ) ? strtolower( $type ) : 'text',
				   esc_attr( $class ),
				   absint( $size ),
				   self::the_id( $name ),
				   self::attribute_string( $attr )
				);

			if ( ! empty( $description ) )
				echo ' <span class="description">' . $description . '</span>';
		}


		/**
		 * Numeric up/down field that'll default back to text type for browsers
		 * without the numeric field type.
		 *
		 * @param array $args An array of parameters used within this method.
		 * 			field:: The name and id of the option
		 * 			description:: Message to show next to the field.
		 *
		 * @return null
		 */
		public static function field_numeric( $args = array( ) ) {
			$defaults = array(
							  'name' => 'regular_text',
							  'description' => '',
							  'min' => false,
							  'max' => false,
							  'attr' => false
							);

			$r = wp_parse_args( $args, $defaults );
			extract( $r, EXTR_SKIP );

			if ( $min !== false )
				$min = sprintf( ' min="%d"', $min );

			if ( $max !== false )
				$max = sprintf( ' max="%d"', $max );

			// Get the value from the db
			$option_args[ 'default' ] = ! empty( $default ) ? $default : null;
			$value = isset( $value ) ? $value : self::get_current_value( $name, $option_args );
			if ( empty( $value ) )
				$value = 0;

			printf( '<input type="number" name="%1$s" id="%3$s" value="%2$s" class="small-text"%4$s%5$s%6$s/>',
				   esc_attr( $name ),
				   esc_attr( $value ),
				   self::the_id( $name ), $min, $max, self::attribute_string( $attr ) );
			if ( ! empty( $description ) )
				echo ' <span class="description">' . $description . '</span>';
		}


		/**
		 * Large textarea.
		 *
		 * @param array $args An array of parameters used within this method.
		 * 			field:: (string) The name and id of the option
		 * 			description:: (string) Message to show next to the field.
		 * 			tiny_mce:: (bool) Tiny mce enhanced or plain html textarea.
		 * 			edit_args:: (array) TinyMCE arguments
		 *
		 * @return null
		 */
		public static function field_textarea( $args = array( ) ) {
			$defaults = array(
								'name' => 'regular_text',
								'description' => '',
								'tiny_mce' => true,
								'edit_args' => array(
									'media_buttons' => true,
									'teeny' => true,
									'textarea_rows' => 10,
									'wpautop' => true
								)
							);

			$r = wp_parse_args( $args, $defaults );
			extract( $r, EXTR_SKIP );

			// Get the value from the db
			$option_args[ 'default' ] = ! empty( $default ) ? $default : null;
			$value = isset( $value ) ? $value : self::get_current_value( $name, $option_args );

			if ( $tiny_mce ) {
				$edit_args[ 'textarea_name' ] = $name;
				wp_editor( $value, $name, $edit_args );
			}
			else
				printf( '<textarea name="%1$s" rows="10" cols="50" id="%3$s" class="large-text code">%2$s</textarea>',
					   esc_attr( $name ),
					   $value,
					   self::the_id( $name )
					);

			if ( ! empty( $description ) )
				echo ' <span class="description">' . $description . '</span>';
		}



		/**
		 * The meta fields as displayed by the settings api
		 *
		 * @param array $args An array of options
		 * 	field: The name of the option field
		 * 	error: Message to show when it all goes wrong
		 * 	description: Helpful text to be shown near the field
		 *
		 * @return null
		 */
		public static function field_select( $args = array( ) ) {
			$defaults = array(
							  'name' => 'period',
							  'description' => '',
							  'options' => array( 'day' => __( 'Day', self::DOM ), 'week' => __( 'Week', self::DOM ), 'month' => __( 'Month', self::DOM ) ),
							  'error' => __( 'Nothing to select', self::DOM ),
							  'multiple' => false,
							  'show_null' => false,
							  'null_title' => '&hellip;'
							);

			$r = wp_parse_args( $args, $defaults );
			extract( $r, EXTR_SKIP );

			// Get the value from the db
			$option_args[ 'default' ] = ! empty( $default ) ? $default : null;
			$value = isset( $value ) ? $value : self::get_current_value( $name, $option_args );

			if ( empty( $options ) || ( ! empty( $options ) && is_wp_error( $options ) ) ) { ?>
				<span class="description"><?php esc_html_e( $error ); ?></span><?php
			}
			else {
				if ( $multiple ) {
					$i = 1;
					echo '<div style="max-height:200px; border:solid 1px #dfdfdf;padding:5px;max-width:300px;overflow-y:auto">';
					foreach( $options as $option => $title ) {
						printf( '<div><label for="%1$s"><input type="checkbox" name="%2$s[]" id="%1$s"%4$s value="%5$s"/> %3$s</label></div>',
								esc_attr( $name ) . '_' . $i++,
								esc_attr( $name ),
								$title,
								in_array( $option, $value ) ? ' checked="checked"' : '',
								esc_attr( $option )
							);
					}
					echo '</div>';
				}
				else { ?>
					<select id="<?php esc_attr_e( self::the_id( $name ) ); ?>" name="<?php esc_attr_e( $name ); ?>"> <?php
						if ( $show_null )
							echo '<option value="">' . esc_html( $null_title ) . '</option>';

						foreach( $options as $option => $title ) {
							printf( '<option value="%s"%s>%s</option>', $option, selected( $value, $option, false ), $title );
						}
						?>
					</select><?php
				}

				if ( ! empty( $description ) )
					echo '<span class="description"> ' . $description . '</span>';

			}
		}


		/**
		 * The meta fields as displayed by the settings api
		 *
		 * @param array $args An array of options
		 * 	field: The name of the option field
		 * 	error: Message to show when it all goes wrong
		 * 	description: Helpful text to be shown near the field
		 *
		 * @return null
		 */
		public static function field_radio( $args = array( ) ) {
			$defaults = array(
							  'name' => 'period',
							  'description' => '',
							  'options' => array( 'day' => __( 'Day', self::DOM ), 'week' => __( 'Week', self::DOM ), 'month' => __( 'Month', self::DOM ) ),
							  'error' => __( 'Nothing to select', self::DOM )
							);

			$r = wp_parse_args( $args, $defaults );
			extract( $r, EXTR_SKIP );

			// Get the value from the db
			$option_args[ 'default' ] = ! empty( $default ) ? $default : null;
			$value = isset( $value ) ? $value : self::get_current_value( $name, $option_args );

			if ( empty( $options ) || ( ! empty( $options ) && is_wp_error( $options ) ) ) { ?>
				<span class="description"><?php echo esc_html( $error ); ?></span><?php
			}
			else {
				foreach( $options as $option => $title )
					printf( '<label for="%1$s-%2$s"><input id="%1$s-%2$s" name="%1$s" type="radio" value="%2$s"%3$s /> %4$s</label> ', esc_attr( $name ), $option, checked( $value, $option, false ), $title );

				if ( ! empty( $description ) )
					echo '<span class="description"> ' . $description . '</span>';

			}
		}


		/**
		 * Generic page selector for use with the settings api calls
		 *
		 * @param array $args An array of parameters used within this method.
		 * 			search_args:: An array passed to the get_pages function
		 * 			field:: The name and id of the option
		 * 			error:: Message to show when no pages are in the select.
		 * 			description:: Message shown next to the field
		 *
		 * @return null
		 */
		public static function field_page_select( $args = array() ) {
			$defaults = array(
							  'search_args' => array( ),
							  'name' => 'user_profile_page',
							  'error' => __( 'Could not find any pages.', self::DOM ),
							  'description' => ''
							);

			$r = wp_parse_args( $args, $defaults );
			extract( $r, EXTR_SKIP );

			// Get the value from the db
			$option_args[ 'default' ] = ! empty( $default ) ? $default : null;
			$value = isset( $value ) ? $value : self::get_current_value( $name, $option_args );

			$pages = get_pages( $search_args );

			if ( ! $pages ) { ?>
				<span class="description"><?php esc_html_e( $error ); ?></span><?php
			}

			else { ?>
				<select id="<?php esc_attr_e( self::the_id( $name ) ); ?>" name="<?php esc_attr_e( $name ); ?>">
					<option <?php selected( $value, 0 ); ?>value="0"><?php _e( 'None', self::DOM )?></option> <?php
					foreach( $pages as $page ) { ?>
						<option <?php selected( $value, $page->ID ); ?>value="<?php echo esc_attr( $page->ID ); ?>"><?php echo $page->post_title; ?></option><?php
					} ?>
				</select> <?php

				if ( ! empty( $description ) )
					echo '<span class="description"> ' . $description . '</span>';

			}
		}


		/**
		 * Term select box
		 *
		 * @param array $args Description
		 *
		 * @return null
		 */
		public static function field_term_select( $args = array( ) ) {
			$defaults = array(
							  'name' => 'category_select',
							  'error' => __( 'No terms found.', self::DOM ),
							  'description' => '',
							  'taxonomy' => 'category',
							  'get_terms_args' => array( 'hide_empty' => false )
							);

			$r = wp_parse_args( $args, $defaults );
			extract( $r, EXTR_SKIP );

			// Get the value from the db
			$option_args[ 'default' ] = ! empty( $default ) ? $default : null;
			$value = isset( $value ) ? $value : self::get_current_value( $name, $option_args );

			//self::$field_taxonomy = $taxonomy;
			$terms = get_terms( $taxonomy, $get_terms_args );

			if ( empty( $terms ) || ( ! empty( $terms ) && is_wp_error( $terms ) ) ) { ?>
				<span class="description"><?php esc_html_e( $error ); ?></span><?php
			}

			else { ?>
				<select id="<?php esc_attr_e( self::the_id( $name ) ); ?>" name="<?php esc_attr_e( $name ); ?>">
					<option <?php selected( $value, 0 ); ?>value="0"><?php _e( 'None', self::DOM )?></option> <?php
					foreach( $terms as $term ) { ?>
						<option <?php selected( $value, $term->term_id ); ?>value="<?php esc_attr_e( $term->term_id ); ?>"><?php esc_html_e( $term->name ); ?> (<?php esc_html_e( $term->count ) ?>)</option><?php
					} ?>
				</select> <?php

				if ( ! empty( $description ) )
					echo '<span class="description"> ' . $description . '</span>';
			}
		}


		/**
		 * Create a dialog for editing date & time similar to the WordPress one
		 * used on post edit pages. Most of the strings here use the default wp
		 * translation domain as they're the same as the WP date strings and
		 * should be dealt with by WP's own translation.
		 *
		 * @param arrat $args Array of parameters.
		 * 		default:: MySQL formatted date
		 * 		field::	Name of the option
		 *
		 * @return null
		 */
		public static function field_date_time( $args = array( ) ) {
			global $wp_locale;

			$defaults = array(
							  'name' => 'datetime',
							  'description' => '',
							  'label' => '',
							  'default' => get_gmt_from_date( '1970-01-01 00:00:00' ) // Epoch relative to timezone.
							);

			$r = wp_parse_args( $args, $defaults );
			extract( $r, EXTR_SKIP );

			// Get the value from the db
			$option_args[ 'default' ] = ! empty( $default ) ? $default : null;
			$value = isset( $value ) ? $value : self::get_current_value( $name, $option_args );
			$value = get_date_from_gmt( $value );

			// Convert it to a time stamp.
			$timestamp = strtotime( $value );

			// Has a date time been set?
			$set = ! empty( $timestamp );

			// Display format.
			$datef = _x( 'M j, Y @ G:i', 'Date time format', self::DOM );

			// Extract out the date parts
			$jj = gmdate( 'd', $set ? $timestamp : 0 );
			$mm = gmdate( 'm', $set ? $timestamp : 0 );
			$aa = gmdate( 'Y', $set ? $timestamp : 0 );
			$hh = gmdate( 'H', $set ? $timestamp : 0 );
			$mn = gmdate( 'i', $set ? $timestamp : 0 );
			$ss = gmdate( 's', $set ? $timestamp : 0 );

			// Build the default form fields.
			$month = '<select name="' . esc_attr( $name ) . '[mm]" class="date-mm" id="' . self::the_id( $name ) . '[mm]">' . "\n";
			for ( $i = 1; $i < 13; $i = $i +1 ) {
				$monthnum = zeroise( $i, 2 );
				$month .= "\t\t\t" . '<option value="' . $monthnum . '"';
				if ( $i == $mm )
					$month .= ' selected="selected"';
				/* translators: 1: month number (01, 02, etc.), 2: month abbreviation */
				$month .= '>' . sprintf( _x( '%1$s-%2$s', '1: month number (01, 02, etc.), 2: month abbreviation', self::DOM ), $monthnum, $wp_locale->get_month_abbrev( $wp_locale->get_month( $i ) ) ) . "</option>\n";
			}
			$month .= '</select>';
			$day = 		'<input type="number" name="' . esc_attr( $name ) . '[jj]" class="date-jj small-text" value="' . $jj . '" size="2" maxlength="2" autocomplete="off" min="1" max="31"/>';
			$year = 	'<input type="number" name="' . esc_attr( $name ) . '[aa]" class="date-aa small-text" value="' . $aa . '" size="4" maxlength="4" autocomplete="off" min="1"/>';
			$hour = 	'<input type="number" name="' . esc_attr( $name ) . '[hh]" class="date-hh small-text" value="' . $hh . '" size="2" maxlength="2" autocomplete="off" min="0" max="23"/>';
			$minute = 	'<input type="number" name="' . esc_attr( $name ) . '[mn]" class="date-mn small-text" value="' . $mn . '" size="2" maxlength="2" autocomplete="off" min="0" max="59"/>';

			$label_html = '';
			if ( ! empty( $label ) )
				$label_html = '<span class="label">' . esc_html( $label ) . '</span> '; ?>

			<div class="curtime">
				<img src="<?php echo admin_url( 'images/date-button.gif ' ); ?>" alt="<?php __( 'Date time', self::DOM ); ?>" style="vertical-align:text-top;" />
				<span class="timestamp"><?php echo $label_html ?><b><?php echo $set ? date_i18n( $datef, $timestamp ) : __( 'Not currently set.', self::DOM ); ?></b></span>

				<a href="#edit_timestamp" class="edit-timestamp hide-if-no-js" tabindex='4'><?php _e( 'Edit' ) ?></a>

				<div class="timestampdiv hide-if-js">
					<div class="timestamp-wrap"><?php printf( _x( '%1$s%2$s, %3$s @ %4$s : %5$s', '1:Month 2:Day, 3:Year, 4:Hour, 5:Minute', self::DOM ), $month, $day, $year, $hour, $minute ); ?></div> <?php

					foreach ( array( 'mm', 'jj', 'aa', 'hh', 'mn', 'ss' ) as $timeunit ) { ?>
						<input type="hidden" class="hidden-<?php echo $timeunit; ?>" value="<?php esc_attr_e( $$timeunit )?>" />
						<?php
					} ?>

					<p>
					<a href="#edit_timestamp" class="save-timestamp hide-if-no-js button"><?php _e( 'OK', self::DOM ); ?></a>
					<a href="#edit_timestamp" class="cancel-timestamp hide-if-no-js"><?php _e( 'Cancel', self::DOM ); ?></a>
					</p>
				</div>
			</div> <?php
			if ( ! empty( $description ) )
				echo '<span class="description"> ' . $description . '</span>';

			// The date pick JS to the footer
			wp_enqueue_script( 'icitdatepick', icit_core::instance()->url . '/js/date-time.js', array( 'jquery' ), 1, true );
		}


		/**
		 * Generic image picker for use with the settings api calls
		 *
		 * @param array $args An array of parameters used within this method.
		 * 			field:: The name and id of the option
		 * 			description:: Message shown next to the field
		 * 			size:: The image size to show on the options page
		 *
		 * @return null
		 */
		public static function field_image( $args = array() ) {
			global $wp_version;

			$defaults = array(
							  'name' => 'image',
							  'description' => '',
							  'size' => 'medium',
							  'post_parent' => 0
							);

			$r = wp_parse_args( $args, $defaults );
			extract( $r, EXTR_SKIP );

			// Get the value from the db
			$option_args[ 'default' ] = ! empty( $default ) ? $default : null;
			$value = isset( $value ) ? $value : self::get_current_value( $name, $option_args );

			if ( version_compare( $wp_version, '3.5', '<' ) ) {
				// You're out of luck.... ?>
				<input type="number" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" min="0" />
				<span class="description"><?php _e( 'This image select field is only supported on WordPress 3.5 and above but you can enter an attachment ID in the field provided.', self::DOM ); ?></span> <?php
			}

			else {
				// added image-field interface ?>
				<div class="field-image hide-if-no-js" data-size="<?php echo esc_attr( $size ); ?>"> <?php

				if ( $value ) { ?>
					<a class="choose-image" title="<?php _e( 'Change the image', self::DOM ); ?>" href="#">
					<?php echo wp_get_attachment_image( $value, $size, false ); ?>
					<span class="button"><?php _e( 'Change the image', self::DOM ); ?></span></a>
					<a class="deletion" href="#remove-image"><?php _e( 'Remove image', self::DOM ); ?></a>
					<input class="image-id" type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" /><?php
				}
				else { ?>
					<a class="choose-image" title="<?php _e( 'Choose an image', self::DOM ); ?>" href="#"><span class="button"><?php _e( 'Choose an image', self::DOM ); ?></span></a>
					<input class="image-id" type="hidden" name="<?php echo esc_attr( $name ); ?>" value="" /><?php
				} ?>
				</div>
				<div class="hide-if-js">
					<span class="description"><?php _e( 'JavaScript is required to select an image.', self::DOM ) ?></span>
				</div>
				<style>
					a.choose-image { text-decoration: none!important; }
					a.choose-image img { height: auto; max-width:100%; display:block; margin-bottom:10px; }
					a.deletion { margin-left: 10px; color: rgb(238,0,0); }
				</style>
				<?php

				// Add all the scripts and styles needed. The JS will head out in the footer not sure what'll happend to the style. :D
				wp_enqueue_media( );
				wp_enqueue_script( 'icitimageselect', icit_core::instance()->url . '/js/image.js', array( 'jquery' ), 1, true );
				wp_localize_script( 'icitimageselect', 'icitimageselectl10n', array(
																					'wpnonce'	=> esc_js( wp_create_nonce( 'get_attachment_image' ) ),
																					'choose'	=> esc_js( __( 'Choose an image', self::DOM ) ),
																					'select'	=> esc_js( __( 'Select', self::DOM ) ),
																					'change'	=> esc_js( __( 'Change the image', self::DOM ) ),
																					'remove'	=> esc_js( __( 'Remove the image', self::DOM ) ),
																					'error'		=> esc_js( __( 'Something has gone wrong.', self::DOM ) ),
																					'postid'	=> absint( $post_parent )
																				) );
			}

			if ( ! empty( $description ) )
				echo ' <span class="description">' . $description . '</span>';

		}


		/**
		 * Generic upload picker for use with the settings api calls
		 *
		 * @param array $args An array of parameters used within this method.
		 * 			field:: The name and id of the option
		 * 			description:: Message shown next to the field
		 *          type:: The file type
		 *          size:: Image size if type is image
		 *
		 * @return null
		 */
		public static function field_upload( $args = array() ) {
			global $wp_version;

			$defaults = array(
				'name' => 'media',
				'description' => '',
				'type' => 'any',
				'size' => 'medium',
				'post_parent' => 0
			);

			$r = wp_parse_args( $args, $defaults );
			extract( $r, EXTR_SKIP );

			// Get the value from the db
			$option_args[ 'default' ] = ! empty( $default ) ? $default : null;
			$value = isset( $value ) ? $value : self::get_current_value( $name, $option_args );

			if ( version_compare( $wp_version, '3.5', '<' ) ) {
				// You're out of luck.... ?>
				<input type="number" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" min="0" />
				<span class="description"><?php _e( 'This media select field is only supported on WordPress 3.5 and above but you can enter an attachment ID in the field provided.', self::DOM ); ?></span> <?php
			}

			else {
				// added image-field interface ?>
				<div class="field-media hide-if-no-js" data-size="<?php echo esc_attr( $size ); ?>"> <?php

					if ( $value ) { ?>
						<a class="choose-media" title="<?php _e( 'Change the file', self::DOM ); ?>" href="#">
							<span class="media-name">
								<?php echo wp_get_attachment_image( $value, $size, true ); ?>
								<?php echo basename( get_attached_file( $value ) ); ?>
							</span>
							<span class="button"><?php _e( 'Change the file', self::DOM ); ?></span></a>
						<a class="deletion" href="#remove-image"><?php _e( 'Remove file', self::DOM ); ?></a>
						<input class="media-id" type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" /><?php
					}
					else { ?>
						<a class="choose-media" title="<?php _e( 'Choose a file', self::DOM ); ?>" href="#"><span class="button"><?php _e( 'Choose a file', self::DOM ); ?></span></a>
						<input class="media-id" type="hidden" name="<?php echo esc_attr( $name ); ?>" value="" /><?php
					} ?>
				</div>
				<div class="hide-if-js">
					<span class="description"><?php _e( 'JavaScript is required to select a file.', self::DOM ) ?></span>
				</div>
				<style>
					a.choose-media { text-decoration: none!important; }
					a.choose-media .media-name img { max-width:100%; display:inline-block; }
					a.choose-media .media-name { display: block; margin-bottom: 10px; }
					a.deletion { margin-left: 10px; color: rgb(238,0,0); }
				</style>
				<?php

				// Add all the scripts and styles needed. The JS will head out in the footer not sure what'll happend to the style. :D
				wp_enqueue_media( );
				wp_enqueue_script( 'icitmediaselect', icit_core::instance()->url . '/js/media.js', array( 'jquery' ), 1, true );
				wp_localize_script( 'icitmediaselect', 'icitmediaselectl10n', array(
					'wpnonce'	=> esc_js( wp_create_nonce( 'get_attachment_image' ) ),
					'choose'	=> esc_js( __( 'Choose a file', self::DOM ) ),
					'select'	=> esc_js( __( 'Select', self::DOM ) ),
					'change'	=> esc_js( __( 'Change the file', self::DOM ) ),
					'remove'	=> esc_js( __( 'Remove the file', self::DOM ) ),
					'error'		=> esc_js( __( 'Something has gone wrong.', self::DOM ) ),
					'postid'	=> absint( $post_parent ),
					'type' 		=> esc_js( $type )
				) );
			}

			if ( ! empty( $description ) )
				echo ' <span class="description">' . $description . '</span>';

		}


		/**
		 * Generic colour picker for use with the settings api calls
		 *
		 * @param array $args An array of parameters used within this method.
		 * 			field:: The name and id of the option
		 * 			description:: Message shown next to the field
		 * 			default:: Default colour hex value
		 * 			palettes:: Whether to show common palettes on the wp3.5 picker
		 * 			hide:: Whether to show the colour picker by default or wait for a click
		 *
		 * @return null
		 */
		public static function field_colour( $args = array() ) {
			global $wp_version;

			$defaults = array(
							  'name' => 'colour',
							  'description' => '',
							  'palettes' => true,
							  'hide' => true,
							  'default' => '#ffffff'
							);

			$r = wp_parse_args( $args, $defaults );
			extract( $r, EXTR_SKIP );

			// Get the value from the db
			$option_args[ 'default' ] = ! empty( $default ) ? $default : null;
			$value = isset( $value ) ? $value : self::get_current_value( $name, $option_args );

			$data = '';
			$data .= ' data-default="' . ( $default ) . '"';
			$data .= ' data-palettes="' . ( $palettes ? 1 : 0 ) . '"';
			$data .= ' data-hide="' . ( $hide ? 1 : 0 ) . '"';

			?>
			<input class="color-picker" name="<?php echo esc_attr( $name ); ?>" id="<?php esc_attr_e( self::the_id( $name ) ); ?>" type="text" value="<?php echo esc_attr( $value ); ?>" <?php echo $data; ?> /><?php

			if ( ! empty( $description ) )
				echo ' <span class="description">' . $description . '</span>';


			if ( version_compare( $wp_version, '3.5', '<' ) ) {
				// Antiquated colour picker
				wp_enqueue_script( 'icitcolourselect', icit_core::instance()->url . '/js/colour.old.js', array( 'jquery', 'farbtastic' ), 1, true );
				wp_enqueue_style( 'farbtastic' );
			}
			else {
				wp_enqueue_script( 'icitcolourselect', icit_core::instance()->url . '/js/colour.js', array( 'jquery', 'wp-color-picker' ), 1, true );
				wp_enqueue_style( 'wp-color-picker' );
			}
		}


		public static function validate_boolean( $value = '' ) {
			return ! empty( $value ) && $value == 1 ? 1 : 0;
		}


		public static function validate_text( $value = '' ) {
			return ! empty( $value ) ? sanitize_text_field( $value ) : false;
		}


		public static function validate_email( $value = '' ) {
			return ! empty( $value ) && is_email( $value ) ? sanitize_text_field( $value ) : false;
		}

		public static function validate_url( $value = '' ) {
			return ! empty( $value ) && esc_url_raw( $value ) ? esc_url_raw( $value ) : false;
		}

		public static function validate_numeric( $value = 0 ) {
			return ! empty( $value ) && absint( $value ) > 0 ? absint( $value ) : 0;
		}


		public static function validate_textarea( $value = '' ) {
			return ! empty( $value ) ? html_entity_decode( stripcslashes( $value ) ) : false;
		}


		public static function validate_select( $value = '' ) {
			return ! empty( $value ) ? $value : null;
		}


		public static function validate_date_time( $value = '' ) {
			$timestamp = 0;

			if ( is_array( $value ) ) {
				$timestamp = mktime (
					( ! empty( $value[ 'hh' ] ) ? $value[ 'hh' ] : 0 ),
					( ! empty( $value[ 'mn' ] ) ? $value[ 'mn' ] : 0 ),
					( ! empty( $value[ 'ss' ] ) ? $value[ 'ss' ] : 0 ),
					( ! empty( $value[ 'mm' ] ) ? $value[ 'mm' ] : 01 ),
					( ! empty( $value[ 'jj' ] ) ? $value[ 'jj' ] : 01 ),
					( ! empty( $value[ 'aa' ] ) ? $value[ 'aa' ] : 1970 )
				);
			}
			// Get a timestamp from the string
			elseif ( is_string( $value ) ) {
				$timestamp = strtotime( get_date_from_gmt( $value ) );
			}

			// Convert the time stamp to an mysql date format
			$date = date( 'Y-m-d H:i:s', $timestamp );

			// Turn the date to a UTC time.
			$date = get_gmt_from_date( $date );

			return $date;
		}


		public static function validate_page_select( $value = '' ) {
			global $wpdb;

			$query = "SELECT ID FROM $wpdb->posts WHERE post_type = 'page' AND post_status = 'publish' ORDER BY ID;";
			$page_ids = $wpdb->get_col( $query );

			return ! is_wp_error( $page_ids ) && ! empty( $page_ids ) && ! empty( $value ) && in_array( absint( $value ), $page_ids ) ? absint( $value ) : 0;
		}


		public static function validate_image( $value = '' ) {
			return wp_attachment_is_image( $value ) ? $value : 0;
		}


		public static function validate_colour( $value = '' ) {
			if ( ! function_exists( 'sanitize_hex_color' ) )
				require_once( ABSPATH . WPINC . '/class-wp-customize-manager.php' );

			return ! empty( $value ) ? sanitize_hex_color( $value ) : false;
		}


		public static function get_attachment_image( $id = 0, $size = 'medium' ) {

			// handle ajax usage
			if ( isset( $_POST[ 'action' ] ) && $_POST[ 'action' ] == 'get_attachment_image' && isset( $_POST[ 'attachment_id' ] ) ) {
				check_ajax_referer( 'get_attachment_image', '_wpnonce' );

				$id = intval( $_POST[ 'attachment_id' ] );

				if ( isset( $_POST[ 'size' ] ) )
					$size = $_POST[ 'size' ];

				// if we're storing the new image let's make it so they don't have to save explicitly
				if ( $id && isset( $_POST[ 'option' ] ) && ! empty( $_POST[ 'option' ] ) )
					do_action( 'maybe_save_attachment_image', $_POST[ 'option' ], $id );
			}

			// output image at requested size
			if ( $id )
				echo wp_get_attachment_image( $id, $size, isset( $_POST[ 'type' ] ) );

			// so we can use the function for both ajax and standard calls
			if ( isset( $_POST[ 'action' ] ) && $_POST[ 'action' ] == 'get_attachment_image' )
				die;
		}


		private static function get_current_value( $name = '', $args = array() ) {
			global $wpdb;

			$defaults = array(
							  'default' => null,
							  'type' => self::$type,
							  'object_id' => self::$object_id
							);

			$r = wp_parse_args( $args, $defaults );
			extract( $r, EXTR_SKIP );

			if ( empty( $default ) )
				$default = ! empty( self::$$name ) ? self::$$name : null;

			switch ( $type ) {
				case 'option':
					$value = get_option( $name, $default );
					break;

				case 'site_option':
					$value = get_site_option( $name, $default );
					break;

				case 'theme_mod':
					$value = get_theme_mod( $name, $default );
					break;

				default:
					// If it's not one of the above let us guess that it's meta.
					if ( _get_meta_table( $type ) && $object_id > 0 ) {
						$value = get_metadata( $type, $object_id, $name, true );
						if ( ! metadata_exists( $type, $object_id, $name ) && $value === '' && ! empty( $default ) ) {
							$value = $default;
						}
					}

					break;
			}

			return isset( $value ) ? $value : $default;
		}


		private static function the_id( $name = '' ) {
			$i = 0;
			$id = $id_base = preg_replace( '/[^A-Za-z0-9_-]/s', '', $name );

			while ( in_array( $id, self::$ids ) ) {
				$id = $id_base . '_' . ++$i;
			}

			self::$ids[] = $id;

			return apply_filters( 'icit_fields_the_id', esc_attr( $id ) );
		}


		private static function attribute_string( $attr = '' ) {
			if ( $attr && is_array( $attr ) ) {
				$att_string = '';
				foreach( $attr as $name => $value )
					$att_string .= " {$name}=\"{$value}\"";
				return $att_string;
			}
			return false;
		}


		public function setup_fields( ) {
			if ( is_callable( array( __CLASS__, '__construct' ) ) )
				call_user_func( array( __CLASS__, '__construct' ) );
		}


		public function __construct( ) {
			// Make sure core is the correct version for this module.
			if ( version_compare( icit_core::VERSION, self::VERSION, '<' ) ) {
				error_log( sprintf( 'Wrong ICIT Core version. Class "%3$s" expecting core version version %2$s but got version %1$s in file %4$s', icit_core::VERSION, self::VERSION, __CLASS__, __FILE__ ), E_USER_WARNING );
				return false;
			}

			// Add the ajax image field stuff.
			if ( !has_action( 'wp_ajax_get_attachment_image', array( __CLASS__, 'get_attachment_image' ) ) )
				add_action( 'wp_ajax_get_attachment_image', array( __CLASS__, 'get_attachment_image' ) );
		}
	}
}
