// Props to Mike Jolley for an excellent writeup. I love your face Mike.
// http://mikejolley.com/2012/12/using-the-new-wordpress-3-5-media-uploader-in-plugins/
;(function($){
	// Uploading files
	var file_frame, current_field, img,
		l10n = typeof( icitmediaselectl10n ) !== 'undefined' && icitmediaselectl10n != '' ? icitmediaselectl10n : {},
		imtext = {
			choose: l10n.choose || 'Choose a file',
			select: l10n.select || 'Select',
			change: l10n.change || 'Change the file',
			remove: l10n.remove || 'Remove the file',
			error : l10n.error  || 'Something has gone wrong.',
			att_id: l10n.att_id || -1,
			postid: l10n.postid || 0,
			type  : l10n.type   || 'any'
		},
		wpnonce = l10n.wpnonce || 'nononce';

	$( document ).on( 'click.choosemedia', '.field-media .choose-media', function( event ) {
		current_field = $( this ).parents( '.field-media' );

		event.preventDefault();

		// If the media frame already exists, reopen it.
		if ( file_frame ) {
			if ( imtext.postid !== 0 ) {
				file_frame.uploader.uploader.param( 'post_id', imtext.postid );
			}
			file_frame.open();
			return;
		}
		else {
			if ( imtext.postid !== 0 ) {
				wp.media.model.settings.post.id = imtext.postid;
			}
		}

		// Create the media frame.
		file_frame = wp.media.frames.file_frame = wp.media({
			title: imtext.choose,
			button: {
				text: imtext.select
			},
			multiple: false  // Set to true to allow multiple files to be selected
		});

		// Preselect the current item.
		file_frame.on( 'open', function( ){
			var selection = file_frame.state().get( 'selection' ),
				attachment = wp.media.attachment( $( '.media-id', current_field ).val( ) );

			attachment.fetch();
			selection.add( [ attachment ] );
		} );

		// When an image is selected, run a callback.
		file_frame.on( 'select', function() {
			// We set multiple to false so only get one image from the uploader
			attachment = file_frame.state().get( 'selection' ).first().toJSON();

			// indicate we're doing something
			$( 'img', current_field ).fadeTo( 500, .5 );

			// display image
			$.post( ajaxurl, {
				action: 'get_attachment_image',
				attachment_id: attachment.id,
				size: current_field.data( 'size' ),
				option: $( '.media-id', current_field ).attr( 'name' ),
				type: imtext.type,
				_wpnonce: wpnonce
			}, function( data ) {
				if ( data && data != -1 && data != 0 ) {

					// update UI
					$( '.media-id', current_field ).val( attachment.id );
					$( 'img, .deletion', current_field ).remove();
					$( '.choose-media', current_field ).attr( 'title', imtext.change ).find( '.button' ).html( imtext.change );

					$( current_field )
						.find('.choose-media').prepend( data ).end()
						.append('<a class="deletion" href="#remove-media">' + imtext.remove + '</a>');

				}
				else {
					// Zero if nothing was selected so we don't error on that.
					if ( data != 0 )
						alert( imtext.error );	// error
					$( 'img', current_field ).fadeTo( 500, 1 );
				}
			} );

		});

		// Finally, open the modal
		file_frame.open();
	});

	$( document ).on( 'click.removemedia', '.field-media .deletion', function(){
		$(this).parents('.field-media').find('.choose-media .button').html( imtext.choose );
		$(this).parents('.field-media').find('img').remove();
		$(this).parents('.field-media').find('.media-id').val('');
		$(this).remove();
		current_field = false;
		return false;
	});
})(jQuery);
