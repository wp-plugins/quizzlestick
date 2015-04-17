<?php

/**
 * Filters and Actions
 */

if ( ! function_exists( 'icit_style_loader_tag' ) ) {

	// style loader - better conditional support
	add_filter( 'style_loader_tag', 'icit_style_loader_tag', 10, 2 );

	/**
	 * Add support for non IE conditional comments
	 *
	 * @param string $tag    The <link> tag to the CSS file
	 * @param string $handle The handle the style was registerd with
	 *
	 * @return string    Returns the link tag for output
	 */
	function icit_style_loader_tag( $tag, $handle ) {
		global $wp_styles;
		$obj = $wp_styles->registered[ $handle ];
		if ( isset( $obj->extra[ 'adv_conditional' ] ) && $obj->extra[ 'adv_conditional' ] ) {
			$cc = "<!--[if {$obj->extra['adv_conditional']}]>";
			$end_cc = '';
			if ( strstr( $obj->extra['adv_conditional'], '!IE' ) ) {
				$cc .= '<!-->';
				$end_cc = '<!--';
			}
			$end_cc .= "<![endif]-->\n";

			$tag = $cc . "\n" . $tag . $end_cc;
		}
		return $tag;
	}

}


if ( ! function_exists( 'icit_enable_shortcode_in_widget' ) ) {
	/**
	 * Simply add the do shortcode filter to the text-widget text
	 *
	 * @return null
	 */
	function icit_enable_shortcode_in_widget() {
		// Apply do_shortcode() to widgets so that shortcodes will be executed in widgets
		add_filter( 'widget_text', 'do_shortcode' );
	}
}


if ( ! function_exists( 'icit_disable_previousday' ) ) {
	/**
	 * Fix the date display when showing more than one post from the same day.
	 *
	 * @return null
	 */
	function icit_disable_previousday() {
		add_action( 'the_post', function( $post ) {
			global $previousday;
			$previousday = 0;
		} );
	}

}


/**
 * Helper functions
 */
if ( ! function_exists( 'icit_add_class_attrib' ) ) {
	/**
	 *
	 * Inject a class name into the first tag in an HTML string passed to it.
	 *
	 * @param string $tag The HTML that is to be amended
	 * @param string $class The new class name
	 * @param boolean $dom_save Use Save XML rather than regEx. Only if complete tags.
	 *
	 * @return string the resultant output.
	 *
	*/
	function icit_add_class_attrib( $tag, $class, $dom_save = false ) {

		if ( ! class_exists( 'DOMDocument' ) )
			return false;

		$first_node = '';
		$attributes = array();

		$dom = new DOMDocument();
		$dom->preserveWhiteSpace = true;
		@$dom->loadHTML( $tag );

		$x = new DOMXPath( $dom );

		// Find all nodes, step over them until we get to the first real one.
		foreach( $x->query( '//*' ) as $node ) {
			if ( strtolower( $node->nodeName ) == 'html' || strtolower( $node->nodeName ) == 'body' || $node->nodeName == '' )
				continue;

			// If we already have the class there's not much point going on.
			if ( $node->hasAttribute( 'class' ) && stristr( $node->getAttribute( 'class' ), $class ) !== false )
				return $tag;

			// Remember the first node
			$first_node = $node->nodeName;

			// Get all the attributes for the first tag
			foreach( $node->attributes as $attr_name => $attr_node ) {
				$attributes[ $attr_name ] = $attr_node->nodeValue;
			}

			// Add the class to the node.
			if ( !empty( $attributes[ 'class' ] ) )
				$attributes[ 'class' ] = trim( $attributes[ 'class' ] ) . ' ' . $class;
			else
				$attributes[ 'class' ] = $class;

			// We've done one so lets jump ship.
			break;
		}

		if ( empty( $first_node ) )
			return $tag;

		if ( $dom_save )
			// DomDoc Save. Will result in clean html but will close open tags
			$tag = $dom->saveXML( $dom->getElementsByTagName( $first_node )->item( 0 ) );

		else {
			// Build the replacement node manually.
			$new_tag = '<' . $first_node;
			foreach( $attributes as $attr => $value ) {
				$new_tag .= ' ' . $attr . '="' . $value . '"';
			}
			$new_tag .= '>';

			$tag = preg_replace( '/<' . $first_node. '[^>]*>/', '\1' . $new_tag . '\2', $tag, 1 );
		}

		return $tag;
	}
}


if ( ! function_exists( 'add_resized_attachment' ) ) {

	/**
	 * Add a new image size to the an already existing attachment. Handy for one
	 * off spaces where you don't want to have an image size created for every
	 * attachment uploaded. Once created the image will be accessable via the
	 * normal wp route with your size name.
	 *
	 * @param integer $id          ID of the attachment we want to mod
	 * @param integer $crop_width  The width of the new image in px
	 * @param integer $crop_height Height of the new image in px
	 * @param boolean $crop        Are we going to crop this image
	 * @param string $size_name   Name of the image size
	 *
	 * @return Mixed wp_error or string, wp_error on fail otherwise the url to the new image.
	 */
	function add_resized_attachment( $id, $crop_width, $crop_height, $crop = false, $size_name = 'special-size' ) {

		if ( ! wp_attachment_is_image( $id ) )
			return new WP_Error( 'attachment_invalid', sprintf( __( 'The passed ID (%d) is not that of an attachment or an image.', icit_core::DOM ), $id));


		// If it's been created already lets not do anything.
		$image_auto = image_get_intermediate_size( $id, $size_name );
		if ( $crop === true && (int) $image_auto[ 'width' ] == $crop_width && (int) $image_auto[ 'height' ] == $crop_height && $image_auto[ 'url' ] )
			return $image_auto[ 'url' ];

		elseif ( $crop === false && ( $crop_width == $image_auto[ 'width' ] || $crop_height == $image_auto[ 'height' ] ) && $image_auto[ 'url' ] )
			return $image_auto[ 'url' ];

		// Make sure we've got the right tools to hand.
		if ( ! function_exists( 'wp_crop_image' ) && file_exists( ABSPATH . '/wp-admin/includes/image.php' ) )
			require( ABSPATH . '/wp-admin/includes/image.php' );


		//	Collect and check the attachments meta data.
		$file = get_attached_file( $id );
		$attachment_meta = wp_get_attachment_metadata( $id, true );


		// Can't do much if there is no file listed on the attachment.
		if ( $file == '' || ! preg_match( '/(png|jpg|gif|jpeg)$/i', $file ) )
			return new WP_Error( 'attachment_invalid', __( 'The attachment has not got a file path attached.', icit_core::DOM ) );

		// Collect Width and Height
		$width = $attachment_meta[ 'width' ];
		$height = $attachment_meta[ 'height' ];
		if ( $width == '' || $height == '' )
			list( $width, $height ) = getimagesize( $file );

		// The image is broken, probabaly a bmp
		if ( (int) $width == 0 || (int) $height == 0 )
			return new WP_Error( 'attachment_invalid', __( 'The attachment is reporting a width or height of zero.', icit_core::DOM ) );


		// The image is already the right size @todo Add this the intermediate image size array
		if ( $width == $crop_width && $height == $crop_height ) {
			list ( $image, $width, $hight ) = wp_get_attachment_image_src( $id, 'fullsize' );
			return $image;
		}


		// If the Crop Width == 0 then I'll assume that dimension is to remain unchanged
		if ( $crop_width == 0 )
			$crop_width = $width;


		// Height is 0 so don't change from source only width is to change.
		if ( $crop_height == 0 )
			$crop_height = $height;


		// Init the cropped image var
		$cropped_image = false;

		// Image isn't to be cropped or scaled up just a new size created if needed
		if ( $crop === false ) {
			list( $new_width, $new_height ) = wp_constrain_dimensions( $width, $height, $crop_width, $crop_height );

			//	Find a unique filename in the destination directory.
			$new_name = preg_replace( '/^([^\.]*)\.(.*)$/', "$1-{$new_width}x{$new_height}.$2", basename($file));
			$file_name = dirname( $file ). '/'. wp_unique_filename( dirname( $file ), $new_name );

			$cropped_image = wp_crop_image( $file, 0, 0, $width, $height, $new_width, $new_height, false, $file_name );
		}

		// Scale the image
		else {
			//	Find a unique filename in the destination directory.
			$new_name = preg_replace( '/^([^\.]*)\.(.*)$/', "$1-{$crop_width}x{$crop_height}.$2", basename($file));
			$file_name = dirname( $file ). '/'. wp_unique_filename( dirname( $file ), $new_name );

			if ( $width < $crop_width && $height < $crop_height && $crop === true ) {
				//Scale up
				if ( ( $crop_width / $width ) * $height >= $crop_height ) {
					$new_height = ceil( $crop_height / ( $crop_width / $width ) );
					$offset = ceil( ( $height - $new_height ) / 2 );
					$cropped_image = wp_crop_image( $file, 0, $offset, $width, $new_height, $crop_width, $crop_height, false, $file_name );
				}

				else {
					$new_width = ceil( $crop_width / ( $crop_height / $height ) );
					$offset = ceil( ( $width - $new_width ) / 2 );
					$cropped_image = wp_crop_image( $file, $offset, 0, $new_width, $height, $crop_width, $crop_height, false, $file_name );
				}
			}

			elseif ( ( $width > $crop_width || $height > $crop_height) && $crop === true ) {
				//Scale Down
				if ( $height / ( $width / $crop_width ) < $crop_height ) {
					$new_width = ceil( $height * ( $crop_width / $crop_height ) );
					$offset = ceil( ( $width - $new_width ) / 2 );
					$cropped_image = wp_crop_image( $file, $offset, 0, $new_width, $height, $crop_width, $crop_height, false, $file_name );
				}

				else {
					$new_height = ceil( $width * ( $crop_height / $crop_width ) );
					$offset = ceil( ( $height - $new_height ) / 2 );
					$cropped_image = wp_crop_image( $file, 0, $offset, $width, $new_height, $crop_width, $crop_height, false, $file_name );
				}
			}
		}

		// Something went wrong up there
		if ( is_wp_error( $cropped_image ) )
			return $cropped_image;

		// Something didn't got quite as wrong but still not good
		if ( $cropped_image === false || ! file_exists( $cropped_image ) )
			return new WP_Error( 'attachment_invalid', __( 'Couldn\'t create the new resized image.', icit_core::DOM ) );

		// Grab the file size of our new image
		$new_sizes = getimagesize( $cropped_image );

		// Add the new image size to the originals meta.
		$attachment_meta[ 'sizes' ][ $size_name ] = array( 'file' => basename( $cropped_image ), 'width' => absint( $new_sizes[ 0 ] ), 'height' => absint( $new_sizes[ 1 ] ) );
		wp_update_attachment_metadata( $id, $attachment_meta );

		// Lets send the url to the new image as the return.
		return wp_get_attachment_image_src( $id, $size_name );
	}

}


if ( ! function_exists( 'get_irregular_post_thumb' ) ) {
	/**
	 * Generate or retrieve the an image of a specific size from the thumb_id.
	 *
	 * @param array/obj/int $term Description
	 * @param integer $x    Width of image
	 * @param integer $y    Height of image
	 * @param boolean $crop Cropped or not
	 * @param string $name Unique name for this image size
	 * @param array $attr Array of attributes
	 * @param integer $post_id Optional ID for the post we're to get the thumb for.
	 *
	 * @return string    The html generated from request.
	 */
	function get_irregular_post_thumb( $x = 100, $y = 100, $crop = true, $name = 'irregular-post-thumb', $attr = '', $post_id = null ) {
		if ( ! has_post_thumbnail( $post_id ) )
			return false;

		$thumb_id = get_post_thumbnail_id( $post_id );

		if ( ! empty( $thumb_id ) ) {
			// Check for and create our image size as needed
			$thumb_meta = wp_get_attachment_metadata( $thumb_id );
			if ( ! isset( $thumb_meta[ 'sizes' ][ $name ] ) && function_exists( 'add_resized_attachment' ) )
				add_resized_attachment( $thumb_id, $x, $y, $crop, $name );
		}

		// The the thumbnail
		return get_the_post_thumbnail( $post_id, $name, $attr );
	}
}


if ( ! function_exists( 'get_irregular_term_thumb' ) ) {
	/**
	 * Generate or retrieve the an image of a specific size from the thumb_id.
	 *
	 * @param array/obj/int $term Description
	 * @param integer $x    Width of image
	 * @param integer $y    Height of image
	 * @param boolean $crop Cropped or not
	 * @param string $name Unique name for this image size
	 * @param array $attr Array of attributes
	 *
	 * @return string    The html generated from request.
	 */
	function get_irregular_term_thumb( $term, $x = 100, $y = 100, $crop = true, $name = 'irregular-post-thumb', $attr = '' ) {
		if ( ! is_object( $term ) || ! isset( $term->term_id ) || ! function_exists( 'has_term_thumbnail' ) )
			return false;

		$term_id = $term->term_id;

		if ( ! has_term_thumbnail( $term_id ) )
			return false;

		$thumb_id = get_term_thumbnail_id( $term_id );
		if ( ! empty( $thumb_id ) ) {
			// Check for and create our image size as needed
			$thumb_meta = wp_get_attachment_metadata( $thumb_id );
			if ( ! isset( $thumb_meta[ 'sizes' ][ $name ] ) && function_exists( 'add_resized_attachment' ) )
				add_resized_attachment( $thumb_id, $x, $y, $crop, $name );
		}

		// The the thumbnail
		return get_the_term_thumbnail( $term_id, $name, $attr );
	}
}



if ( ! function_exists( 'icit_register_category_sidebars' ) ) {
	/**
	 * Adds a sidebar for every root category found
	 *
	 * @param array $args Same params as register_sidebar
	 *
	 * @return null
	 */
	function icit_register_category_sidebars( $args = array() ) {

		$name = ! empty( $args[ 'name' ] ) ? $args[ 'name' ] : 'Sidebar';
		$id = 'category' . sanitize_title( $name ) . '-%d';
		$description = __( 'This will override the default %s for archive listings and singular posts in the %s category.', icit_core::DOM );

		// Add a sidebar for every root level category
		$archives = get_categories( array( 'hierarchical' => false, 'parent' => 0, 'hide_empty' => false ) );
		if ( ! empty( $archives ) ) {
			foreach( $archives as $index => $category ) {
				$args[ 'name' ] = $name . ' (' . $category->name . ')';
				$args[ 'id' ] = sprintf( $id, $category->term_id );
				$args[ 'description' ] = sprintf( $description, $name, $category->name );

				register_sidebar( $args );
			}
		}
	}
}


if ( ! function_exists( 'icit_dynamic_category_sidebar' ) ) {

	/**
	 * Retrieve a sidebar associated with a root category level category. If
	 * this is called from an archive page then it will look for the category
	 * that is the parent of the current. If called from a post it will find the
	 * first category the post is in.
	 *
	 * @param string $name Name of the sidebar to be output
	 *
	 * @return bool    True if we found one otherwise false.
	 */
	function icit_dynamic_category_sidebar( $name = 'sidebar' ) {

		if ( ! is_string( $name ) )
			return false;

		$name = ! empty( $name ) ? $name : 'Sidebar';
		$id = 'category' . sanitize_title( $name ) . '-%d';

		$found_one = false;
		if ( is_single() || is_category() ) {

			// Step over each category looking for sidebar to use
			$archives = get_categories( array( 'hierarchical' => false, 'parent' => 0, 'hide_empty' => false ) );

			foreach( $archives as $i => $category ) {
				if ( $found_one )
					break;

				$sidebar_id = sprintf( $id, $category->term_id );

				if ( is_active_sidebar( $sidebar_id ) ) {

					// Is the category one of the root cats or is the cat a child of the root cat.
					if ( is_category( $category->term_id ) || ( is_category() && term_is_ancestor_of( $category->term_id, get_queried_object_id(), 'category' ) ) ) {
						$found_one = dynamic_sidebar( $sidebar_id );
					}

					// Are we in a single post and that post in a root cat
					elseif( is_single() && in_category( $category->term_id ) ) {
						$found_one = dynamic_sidebar( $sidebar_id );
					}

					// Search through all post cats to see if any are children or the current.
					elseif( is_single() ) {
						$post_cats = wp_list_pluck( (array) get_the_terms( get_the_ID(), 'category' ), 'term_id' );
						foreach( $post_cats as $p_cat ) {
							if ( $found_one )
								continue;

							if ( term_is_ancestor_of( $category->term_id, $p_cat, 'category' ) ) {
								$found_one = dynamic_sidebar( $sidebar_id );
							}
						}
					}
				}
			}
		}

		return $found_one;
	}
}


if ( ! function_exists( 'paginate_archive_links' ) ) {
	/**
	 * Generates page links for category, tags, search results and most other
	 * lists created by wordpress. Provides easier navigation that with the
	 * default next prev links WP uses.
	 *
	 * @param array $args 	echo: boolean: Return the results or send to OB,
	 * 						before: string: What to show before the pages,
	 * 						after: string: What to show after the pages,
	 * 						prev_text: string: Text used in the prev button,
	 * 						next_text: string: Text used in the next button
	 *
	 * @return null/string    If echo is false then the generated html will be returned
	 */
	function paginate_archive_links( $args = array() ) {
		global $wp_query, $wp_rewrite;

		$defaults = array('echo' => true,
						  'before' => '<div class="pagination-links">',
						  'after' => '</div>',
						  'prev_text' => '&laquo;',
						  'next_text' => '&raquo;',
						  'end_point' => 'page',
						  'type' => 'plain'
						);
		$args = apply_filters( 'paginate_archive_links_args', $args );
		$r = wp_parse_args( $args, $defaults );
		extract( $r, EXTR_SKIP );

		$max_page = $wp_query->max_num_pages;
		if ( is_singular() || $max_page == 1 )
			return;

		$page = get_query_var( 'paged' );
		if ( ! $page )
			$page = 1;

		$url = parse_url( get_option( 'home' ) );
		if ( isset( $url['path' ] ) ) {
			$root = $url['path' ];
		} else{
			$root = '';
		}

		$root = preg_quote( trailingslashit( $root ), '/' );
		$request = preg_replace( "/^$root/", '', remove_query_arg( 'paged' ) );
		$request = preg_replace( '/^\/+/', '', $request );

		if ( ! $wp_rewrite->using_permalinks() ) {
			$base = add_query_arg( 'paged', '%#%', trailingslashit( home_url() ) . $request );
		}

		else {
			//Permalinks are on.
			$qs_regex = '|\?.*?$|';
			preg_match( $qs_regex, $request, $qs_match );

			if ( ! empty( $qs_match[0 ] ) ) {
				$query_string = $qs_match[0 ];
				$request = preg_replace( $qs_regex, '', $request );
			} else {
				$query_string = '';
			}

			$request = preg_replace( "|$end_point/\d+/?$|", '', $request );
			$request = preg_replace( '|^index\.php|', '', $request );
			$request = ltrim( $request, '/' );
			$base = trailingslashit( get_bloginfo( 'url' ) );

			$ep = ! empty( $end_point ) ? $end_point . '/' : '';
			$request = ( ( ! empty( $request ) ) ? trailingslashit( $request ) : $request ) . user_trailingslashit( $ep . '%#%', 'paged' );

			$base = $base . $request . $query_string;
		}

		$paginate_links_args = apply_filters( 'paginate_links_args', array(
									 'base' => $base ,
									 'format' => '',
									 'total' => $max_page,
									 'current' => $page,
									 'type' => $type,
									 'prev_text' => esc_html( $prev_text ),
									 'next_text' => esc_html( $next_text ),
									 'end_size' => 1,
									 'mid_size' => 4,
									 'show_all' => false
									), $args );

		$page_links = paginate_links( $paginate_links_args );

		if ( $echo )
			echo $before . $page_links . $after;
		else
			return $before . $page_links . $after;
	}
}


if ( ! function_exists ( 'icit_simple_function_cache' ) ) {
	/**
	 * ICIT Simple function cache
	 *
	 * Caches the output from a function in a wp transient. The transient will
	 * last a maximum of 24hrs each and each item within the cache can be set to
	 * its own expiry within that. If the args passed are changed from one run
	 * to the next the cache will be recreated for the item. Setting the last
	 * param to true will force the cache to be recreated no matter what the
	 * expiry is set to.
	 *
	 * If you need to disable it you can define ICIT_CACHE_MAX as false.
	 *
	 * @param string $function_name The name name of the function that will be called.
	 * @param array $args An array of the arguments to be passed to your function.
	 * @param int $expire_in How long to keep the cache around for.
	 * @param bool $force Force a recaching of this item.
	 *
	 * @return Will return whatever the passed in function returned.
	*/
	function icit_simple_function_cache( $function_name = '', $args = '', $expire_in = 600, $force = false ) {
		if( !is_callable( $function_name ) )
			return false;

		// If set_transient isn't here then we're in an old WP and need to go away.
		if( ! function_exists( 'set_transient' ) || ( defined( 'ICIT_CACHE_MAX' ) && ICIT_CACHE_MAX === false ) )
			return call_user_func_array( $function_name, ( array )$args );

		if ( ! defined( 'ICIT_CACHE_MAX' ) )
			define( 'ICIT_CACHE_MAX', 86400 );

		$hash = hash( 'md5', maybe_serialize( $args ) );

		if ( is_callable( $function_name ) && is_array( $function_name ) && count( $function_name ) == 2 ) {
			$cache_name = $function_name[ 1 ];
			$hash .= hash( 'md5', maybe_serialize( $function_name[ 0 ] ) );
		} else
			$cache_name = $function_name;

		$cache = get_transient( "{$cache_name}_cache" );

		if( isset( $cache[ $hash ][ 'timeout' ] ) && $cache[ $hash ][ 'timeout' ] >= time() && ! $force ) {
			// Cache is good so use it.
			$screen_output = $cache[ $hash ][ 'output' ];
			$return = $cache[ $hash ][ 'return' ];
		} else {
			// Collect screen output and the return value.
			ob_start();
				$return = call_user_func_array( $function_name, ( array )$args );
				$screen_output = ob_get_contents();
			ob_end_clean();

			// Add our stuff to the cache
			$cache[ $hash ][ 'output' ] = $screen_output;
			$cache[ $hash ][ 'return' ] = $return;
			$cache[ $hash ][ 'timeout' ] = time() + $expire_in;

			// Clear out some old expired items.
			foreach( $cache as $key => $value ) {
				if( time() >= $value[ 'timeout' ] ) {
					unset( $cache[ $key ] );
				}
			}

			//Set the transient with a 24hr expiry.
			set_transient( "{$cache_name}_cache", $cache, ICIT_CACHE_MAX );
		}

		// Give the user what they want..
		echo $screen_output;
		return $return;
	}
}


if ( ! function_exists( 'icit_clear_simple_function_cache' ) ) {
	/**
	 * ICIT Clear simple function cache
	 *
	 * Basically this will, as the name should suggest, clear an specified
	 * cache. It will clear either all caches for the function if just the
	 * function name is passed or if you pass the args in to it will clear just
	 * that part of the cache.
	 *
	 * @param string $function_name The name name of the function that was cached.
	 * @param array $args An array of the arguments that was passed to your function.
	 *
	 * @return bool.
	*/
	function icit_clear_simple_function_cache( $function_name = '', $args = '' ) {

		if ( empty( $function_name ) )
			return false;

		$hash = hash( 'md5', maybe_serialize( $args ) );

		if ( is_callable( $function_name ) && is_array( $function_name ) && count( $function_name ) == 2 ) {
			$cache_name = $function_name[ 1 ];
			$hash .= hash( 'md5', maybe_serialize( $function_name[ 0 ] ) );
		} else
			$cache_name = $function_name;

		if ( ! empty( $args ) ) {
			$cache = get_transient( "{$cache_name}_cache" );
			unset( $cache[ $hash ] );
			set_transient( "{$cache_name}_cache", $cache, ICIT_CACHE_MAX );
		} else {
			delete_transient( "{$cache_name}_cache" );
		}

		return true;
	}
}


if ( ! function_exists( 'icit_all_taxonomies' ) ) {

	/**
	 * Merge all taxonomies into a list so that they can be output at the foot
	 * of posts.
	 *
	 * @param array $args Array of arguments:
	 * 		sep: string: Separator,
	 * 		title: string: Text to use as the title for the output,
	 * 		echo: boolean: Output or return,
	 * 		before: string: What to show before the output,
	 * 		after: string: What to show after the output,
	 * 		tax_exclude: array: Taxonomies not to be included,
	 * 		term_exclude: Array: terms to be excluded from the output,
	 * 		sort: boolean: Sort by name or not?
	 *
	 * @return string/null    If echo is false then we'll return the html to you
	 */
	function icit_all_taxonomies( $args = '' ) {
		global $post;

		$defaults = array('sep' => ', ',
						  'title' => 'Tags: ',
						  'echo' => true,
						  'before' => '<span class="all-taxonomies">',
						  'after' => '</span>',
						  'tax_exclude' => array(),
						  'term_exclude' => array(),
						  'sort' => true
						);

		$r = wp_parse_args( apply_filters( 'icit_all_taxonomies_args', $args ), $defaults );
		extract( $r, EXTR_SKIP );

		$links = array();
		$all_terms = array();

		if ( ! is_array( $tax_exclude ) )
			$tax_exclude = (array) $tax_exclude;

		foreach ( get_object_taxonomies( $post->post_type ) as $taxonomy ) {
			$tax = get_taxonomy( $taxonomy );
			$terms = array();
			if ( $tax->public && ! in_array( $tax->name, $tax_exclude ) ) {
				$terms = get_object_term_cache( $post->ID, $taxonomy );
				if ( empty( $terms ) )
					$terms = wp_get_object_terms( $post->ID, $taxonomy, array() );
			}

			if ( ! empty( $terms ) )
				$all_terms = array_merge( $terms, $all_terms );
		}

		if ( empty( $all_terms ) )
			return false;

		if ( $sort )
			usort( $all_terms, create_function( '$a, $b', 'return strcmp( strtolower( $a->name ), strtolower( $b->name ) );' ) );

		foreach ( $all_terms as $term ) {
			if ( in_array( $term->slug, ( array ) $term_exclude ) )
				continue;
			$links[] = '<a href="' . esc_attr( get_term_link( $term, $term->taxonomy ) ) . '">' . $term->name . '</a>';
		}

		$output  = ! empty( $title ) && count( $links ) ? '<span class="tag-title">' . $title . '</span>' : '';
		$output .= implode( $sep, ( array ) $links );

		if( $echo && $output ) {
			echo $before . $output . $after;
		} else {
			return $before . $output . $after;
		}
	}
}


if ( ! class_exists( 'icit_excerpt_length' ) && ! function_exists( 'set_excerpt_length' ) ) {

	/**
	 * Set the length of the next excerpt to a number of chars.
	 *
	 * @param interger $length Number of chars the excerpt should be.
	 * @param boolean $words If you'd rather set word count than char send true.
	 *
	 * @return object
	 */
	function set_excerpt_length( $length = 140, $words = false ) {
		return new icit_excerpt_length( $length, $words );
	}

	class icit_excerpt_length {
		public $length = 140;
		public $words = false;

		public function __construct( $length = 140, $words = false ) {
			$this->length = intval( $length );
			$this->words = (bool) $words;

			add_filter( 'excerpt_length', array( $this, 'force_excerpt_length' ), 1812 );
			if ( $this->words === false )
				add_filter( 'the_excerpt', array( $this, 'trim_excerpt' ), 1812 );
		}

		// Make sure the excerpt isn't already restricted
		public function force_excerpt_length( $length = 1000000 ) {
			return $this->words === false ? 1000000 : $this->length;
		}

		public function trim_excerpt( $excerpt = '' ) {
			$excerpt = html_entity_decode( strip_tags( $excerpt ), ENT_COMPAT, 'UTF-8' );
			$excerpt = substr( $excerpt, 0, intval( $this->length ) );
			$excerpt = htmlentities( $excerpt, ENT_COMPAT, 'UTF-8' );
			$excerpt = '<p>' . $excerpt . apply_filters( 'excerpt_more', '&hellip;' ) . '</p>';

			$this->__destruct();
			return $excerpt;
		}

		public function oops() {
			self::__destruct();
		}

		public function __destruct() {
			global $wp_filter;

			if ( isset( $wp_filter[ 'excerpt_length' ][ 1812 ] ) )
				remove_all_filters( 'excerpt_length', 1812 );

			if ( isset( $wp_filter[ 'the_excerpt' ][ 1812 ] ) )
				remove_all_filters( 'the_excerpt', 1812 );
		}
	}
}


if ( ! function_exists( 'icit_load' ) ) {

	/**
	 * Load any icit module that follows the module-name/module-name.php
	 * convention.
	 *
	 * @param string $module      The relative or absolute path to the module folder
	 * @param bool $instantiate Whether to check for an 'instance' method on the class and call it
	 *
	 * @return void
	 */
	function icit_load( $module, $instantiate = false ) {
		$class_name = str_replace( '-', '_', basename( $module ) );
		$module_file = DIRECTORY_SEPARATOR . basename( $module ) . '.php';

		if ( strpos( $module, DIRECTORY_SEPARATOR ) === 0 ) {
			$module_file = "{$module}{$module_file}";
		} else {
			$bt = debug_backtrace();
			$caller = array_shift( $bt );
			$module_file = dirname( $caller[ 'file' ] ) . DIRECTORY_SEPARATOR . $module . $module_file;
		}

		if ( is_readable( $module_file ) ) {
			require_once $module_file;
			if ( $instantiate && class_exists( $class_name ) && method_exists( $class_name, 'instance' ) )
				add_action( 'plugins_loaded', array( $class_name, 'instance' ), 100001 );
		}
	}

}


if ( ! function_exists( 'icit_load_theme_modules' ) ) {
	/**
	 * Load theme files from the modules folder
	 *
	 * @param unknown $modules Description
	 *
	 * @return null
	 */
	function icit_load_theme_modules( $parts = array(), $path = 'modules', $instantiate = false, $priority = 1000 ) {
		foreach( (array) $parts as $i => $part ) {
			if ( strtolower( $part ) == 'core' )
				continue;

			$path = ! empty( $path ) ? trailingslashit( $path ) : '';
			get_template_part( $path . $part . '/' . $part );

			if ( $instantiate === true ) {
				$class_name = str_replace( '-', '_', basename( $part ) );
				if ( class_exists( $class_name ) && is_callable( array( $class_name, 'instance' ) ) ) {
					add_action( 'init', array( $class_name, 'instance' ), $priority );
				}
			}
		}
	}
}


if ( ! function_exists( 'icit_comment_layout' ) ) {
	/**
	 * Point comment layout to our parts folder. Makes things a little easier.
	 *
	 * @param object	$comment The comment
	 * @param array		$args    The parameters for the comment
	 * @param interger	$depth   Depth of nesting
	 *
	 * @return null
	 */
	function icit_comment_layout( $comment = '', $args = array(), $depth = 0 ) {
		$GLOBALS[ 'comment' ] = $comment;
		$GLOBALS[ 'comment_args' ] = $args;

		get_template_part( 'parts/comments/layout' );
	}
}


if ( ! function_exists( 'icit_part_name' ) ) {
	/**
	 * Take the filename passed in and strip it down to something we can use in
	 * get_template_part
	 *
	 * @param string $filename __FILE__ from theme template file.
	 *
	 * @return string    Simplified name for use in get_template_part
	 */
	function icit_part_name( $filename = '' ) {
		global $template;

		if ( empty( $filename ) )
			$filename = $template;

		if ( file_exists( $filename ) ) {
			$path_info = pathinfo( $filename );
			$part_name = strtolower( $path_info[ 'filename' ] );
		}

		return ! empty( $part_name) ? $part_name : false;
	}
}


/**
 * Generally usefull stuff....
 */
if ( ! function_exists( 'array_filter_recursive_callback' ) ) {

	/**
	 * Will filter an array recursively with a callback, duh!
	 *
	 * @param array $input    Array to be processed
	 * @param callback $callback Function to use to process the array
	 *
	 * @return array    The processed array
	 */
	function array_filter_recursive_callback( $input, $callback = null ) {
		if ( is_array( $input ) && is_callable( $callback ) ) {
			foreach ( $input as $key => & $value ) {
				if ( is_array( $value ) )
					$input[ $key ] = array_filter_recursive_callback ( $value, $callback );
				else
					$input[ $key ] = call_user_func( $callback, $value );
			}
			return $input;
		}
	}

}


if ( ! function_exists( 'strip_accents' ) ) {

	/**
	 * Much as the name implies, this will convert accented chars into their
	 * none accented couterpart.
	 *
	 * @param string $string String with accesnts in it.
	 *
	 * @return string    String without accents.
	 */
	function strip_accents( $string ) {
		$chars = array( 'Š'=>'S', 'š'=>'s', 'Ð'=>'Dj', 'Ž'=>'Z', 'ž'=>'z',
					    'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A',
						'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E',
						'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I',
						'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O',
						'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U', 'Ú'=>'U',
						'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss',
						'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a',
						'å'=>'a', 'æ'=>'a', 'ç'=>'c', 'è'=>'e', 'é'=>'e',
						'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i',
						'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o',
						'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o', 'ù'=>'u',
						'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'ý'=>'y', 'þ'=>'b',
						'ÿ'=>'y', 'ƒ'=>'f'
		);

		return strtr( $string, $chars );
	}
}


if ( ! function_exists( 'roman_numerals' ) ) {

	/**
	 * @create a roman numeral from a number
	 * @param int $num
	 * @return string
	 */
	function roman_numerals( $num ) {
		$n = intval( $num );
		$res = '';

		/*** roman_numerals array  ***/
		$roman_numerals = array(
			'M'  => 1000,
			'CM' => 900,
			'D'  => 500,
			'CD' => 400,
			'C'  => 100,
			'XC' => 90,
			'L'  => 50,
			'XL' => 40,
			'X'  => 10,
			'IX' => 9,
			'V'  => 5,
			'IV' => 4,
			'I'  => 1);

		foreach ( $roman_numerals as $roman => $number ) {
			/*** divide to get matches ***/
			$matches = intval($n / $number);

			/*** assign the roman char * $matches ***/
			$res .= str_repeat($roman, $matches);

			/*** substract from the number ***/
			$n = $n % $number;
		}

		/*** return the res ***/
		return $res;
	}

}


if ( ! function_exists( 'eng_list' ) ) {

	/**
	 * Take an array and turn it into an English formatted list. Like so:
	 * array( 'a', 'b', 'c', 'd' ); = a, b, c, or d.
	 *
	 * @param array $input_arr The source array
	 *
	 * @return string    English formatted string
	 */
	function eng_list( $input_arr = array(), $sep = ', ', $before = '"', $after = '"', $word = 'and' ) {
		if ( ! is_array( $input_arr ) )
			return false;

		$_tmp = $input_arr;

		if ( count( $_tmp ) >= 2 ) {
			$end2 = array_pop( $_tmp );
			$end1 = array_pop( $_tmp );
			array_push( $_tmp, $end1 . $after . ' ' . $word . ' ' . $before . $end2 );
		}

		return $before . implode( $before . $sep . $after, $_tmp ) . $after;
	}
}


if ( ! function_exists( 'merge_two_dates' ) ) {

	/**
	 * Take two dates and merge them together for display. So 3rd Jan 2011 +
	 * 14th Jan 2011 would display as 3rd - 14th Jan 2011. And 27th Jan 2011 +
	 * 3rd Feb 2011 would show as 27th Jan - 3rd Feb 2011.
	 *
	 * @param string $start_date MySQL timestamp
	 * @param string $end_date   MySQL timestamp
	 *
	 * @return string    Merged date
	 */
	function merge_two_dates( $start_date, $end_date = '' ) {

		// Start with the default, overwrite this if there's something better.
		$display_date = mysql2date( 'j F Y', $start_date );

		if ( ! empty( $end_date ) ) {
			$to = explode( ' ', $end_date );
			$to = explode( '-', $to[ 0 ] );

			$from = explode( ' ', $start_date );
			$from = explode( '-', $from[ 0 ] );

			if ( $to[0] > $from[0] ) {
				$display_date = mysql2date( 'j F Y', $start_date ) . ' &mdash; ' . mysql2date( 'j F Y', $end_date );
			} elseif ( $to[1] > $from[1] ) {
				$display_date = mysql2date( 'j F', $start_date ) . ' &mdash; ' . mysql2date( 'j F Y', $end_date );
			} elseif ( $to[2] > $from[2] ) {
				$display_date = mysql2date( 'j', $start_date ) . ' &mdash; ' . mysql2date( 'j F Y', $end_date );
			}
		}

		return $display_date;
	}
}


if ( ! function_exists( 'current_url' ) ) {
	/**
	 * straight copy of self_link() but returned rather than echoed.
	 *
	 * @return string    The current url
	 */
	function current_url() {
		$host = @parse_url( home_url() );
		return esc_url_raw( apply_filters( 'self_link', set_url_scheme( 'http://' . $host[ 'host' ] . wp_unslash( $_SERVER[ 'REQUEST_URI' ] ) ) ) );
	}
}


if ( ! function_exists( 'truncate_string' ) ) {

	/**
	 * Truncate a string of words to a length you want.
	 *
	 * @param string $string Text to be worked on
	 * @param integer $length Length you want you string to be.
	 * @param string $suffix Something to attach to the end of the new string
	 *
	 * @return string    The completed string
	 */
	function truncate_string( $string, $length = 50, $suffix = '&hellip;') {
		$length = intval( $length );
		if ( ! $length )
			return $string;

		$decoded = html_entity_decode( $string, ENT_QUOTES, get_bloginfo( 'charset' ) );
		$encoded = strlen( $string ) != strlen( $decoded ) ? true : false;

		if ( strlen( $string ) > $length ) {
			$new_string = '';
			foreach( ( array ) explode( ' ', $decoded ) as $word ) {
				if ( strlen( $new_string . ' ' . $word ) > ( $length  - 1 ) )
					break;
				$new_string .= ' ' . $word;
			}
			// Check that we've not over shortened the string.
			if ( strlen( $new_string ) < ( $length * 0.75 ) )
				$new_string = substr( $decoded, 0, ( $length - 1 ) );

			$string = $encoded ? htmlentities( $new_string, ENT_QUOTES, get_bloginfo( 'charset' ) ) . $suffix : $new_string . $suffix;
		}

		return $string;
	}
}

if ( ! function_exists( 'get_svg' ) ) {

	/**
	 * Returns an svg file from within the context. Context defaults to the theme folder
	 *
	 * @param string $path The path to the svg file relative to $context with or without the .svg file type
	 * @param string $context The path to search $path relative to. Default to the theme directory
	 *
	 * @return string    The SVG file content
	 */
	function get_svg( $path, $context = '' ) {

		if ( empty( $context ) )
			$context = get_stylesheet_directory();

		$path = ltrim( $path, '/' );
		if ( ! strstr( $path, '.svg' ) )
			$path .= '.svg';

		$path = $context . '/' . $path;

		if ( file_exists( $path ) ) {
			ob_start();
			include( $path );
			return trim( ob_get_clean() );
		}

	}

}


if ( ! function_exists( 'is_ajax' ) ) {

	/**
	 * Simple function to use if calling front end templates via ajax eg. for inf scroll
	 * @return bool
	 */
	function is_ajax() {
		return isset( $_REQUEST[ 'ajax' ] );
	}

}


if ( ! function_exists( 'icit_get_post_format' ) ) {
	function icit_get_post_format( ) {
		if ( ! in_the_loop( ) )
			return '';

		$post_format = get_post_format( );
		if ( $post_format === false || is_wp_error( $post_format ) )
			$post_format = '';

		return $post_format;
	}
}

if ( ! function_exists( 'icit_part_type' ) ) {
	function icit_part_type() {
		$avail_types = array(
			'attachment' => 'is_attachment',
			'author' => 'is_author',
			'category' => 'is_category',
			'tag' => 'is_tag',
			'taxonomy' => 'is_tax',
			'date' => 'is_date',
			'home' => 'is_home',
			'page' => 'is_page',
			'search' => 'is_search',
			'single' => 'is_single',
			'archive' => 'is_archive',
			'404' => 'is_404'
		);

		foreach( $avail_types as $type => $callback ) {
			if ( call_user_func( $callback ) )
				return $type;
		}

		return false;
	}
}

if ( ! function_exists( 'single_archive_title' ) ) {
	function single_archive_title( $args = '' ) {
		global $wp_query;

		$defaults = array( 'echo' => true, 'show_type' => true, 'type_string' => '<p class="archive-title">%s</p>', 'title_string' => '<h1>%s</h1>' );
		$r = wp_parse_args( $args, $defaults );
		extract( $r, EXTR_SKIP );

		if ( is_date( ) ) {
			if ( is_year( ) ) {
				$type = __( 'Year', ICIT_THEME_DOM );
				$title = __( 'From: ', ICIT_THEME_DOM ) . get_the_time( 'Y' );
			}

			elseif ( is_month( ) ) {
				$type = __( 'Month', ICIT_THEME_DOM );
				$title =  __( 'From: ', ICIT_THEME_DOM ) . get_the_time( 'F, Y' );
			}

			elseif ( is_day( ) ) {
				$type = __( 'Day', ICIT_THEME_DOM );
				$title = __( 'From: ', ICIT_THEME_DOM ) . get_the_time( 'l, F jS, Y' );
			}
		}

		elseif ( is_author( )) {
			$type = __( 'Author', ICIT_THEME_DOM );
			$title = get_the_author_meta( 'display_name', ( int ) get_query_var( 'author' ) );
		}

		elseif ( is_search( ) ) {
			$type = __( 'Search', ICIT_THEME_DOM );
			$title = __( 'Search results for: ', ICIT_THEME_DOM ) . htmlentities( get_search_query( ), ENT_QUOTES, get_bloginfo( 'charset' ) );
		}

		elseif ( is_category( ) ) {
			$type = __( 'Category', ICIT_THEME_DOM );
			$title = single_cat_title( '', false );
		}

		elseif ( is_tag( ) ) {
			$type = __( 'Tag', ICIT_THEME_DOM );
			$title = single_term_title( '', false );
		}
		elseif ( is_tax( ) ) {
			$title = single_term_title( '', false );
			$taxonomy_o = get_queried_object( );
			if ( isset( $taxonomy_o->taxonomy ) ) {
				$tax = get_taxonomy( $taxonomy_o->taxonomy );
				$type = $tax->labels->name;
			}
		}
		else {
			return false;
		}

		if ( empty( $title ) )
			return false;

		$output = '';

		if ( $show_type && ! empty( $type ) && $type )
			$output .= sprintf( $type_string, $type );

		$output .= sprintf( $title_string, $title );

		if ( $echo ) {
			echo $output;
		}
		else {
			return $output;
		}
	}
}

if ( ! function_exists( 'unregister_taxonomy_for_object_type' ) ) {

	/**
	 * Remove an already registered taxonomy to an object type.
	 *
	 * @uses $wp_taxonomies Modifies taxonomy object
	 *
	 * @param string $taxonomy Name of taxonomy object
	 * @param array|string $object_type Name of the object type
	 * @return bool True if successful, false if not
	 */
	function unregister_taxonomy_for_object_type( $taxonomy, $object_type ) {
		global $wp_taxonomies;

		if ( ! isset( $wp_taxonomies[ $taxonomy ] ) )
			return false;

		foreach( (array)$object_type as $type ) {
			if ( ! get_post_type_object( $type ) )
				continue;

			foreach( (array)$wp_taxonomies[ $taxonomy ]->object_type as $i => $ob ) {
				if ( $type == $ob ) {
					unset( $wp_taxonomies[ $taxonomy ]->object_type[ $i ] );
					break;
				}
			}
		}

		return true;
	}

}