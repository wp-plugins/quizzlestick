( function( $ ){
    $( document ).ready( function ( ) {

		function check_date( aa, mm, jj, hh, mn ) {
			var  new_date = new Date( aa, mm - 1, jj, hh, mn );
			if ( new_date.getFullYear() != aa || ( 1 + new_date.getMonth( ) ) != mm || new_date.getDate( ) != jj || new_date.getMinutes() != mn )
				return false;

			return true;
		}

		function zero_pad( num, length ) {
			var _tmp = '', i;

			if ( num.toString( ).length < length ) {
				for ( i=0; i < length - num.toString( ).length; i++ ) {
					_tmp += '0';
				}

				num = _tmp + num.toString( );
			}

			return num.toString();
		}

		$( '.timestampdiv' ).each( function( ) {
			var _div = $( this ),
				date_mm = _div.find( '.date-mm' ), // Month
				date_jj = _div.find( '.date-jj' ), // Day
				date_aa = _div.find( '.date-aa' ), // Year
				date_hh = _div.find( '.date-hh' ), // Hour
				date_mn = _div.find( '.date-mn' ), // Min
				hidden_mm = _div.find( '.hidden-mm' ), // Fallback
				hidden_jj = _div.find( '.hidden-jj' ),
				hidden_aa = _div.find( '.hidden-aa' ),
				hidden_hh = _div.find( '.hidden-hh' ),
				hidden_mn = _div.find( '.hidden-mn' ),
				label = _div.parent( '.curtime' ).children( '.timestamp' ).children( 'span.label' ).html();

			// Make sure the label is wrapped or blanked.
			label = ( label !== undefined && label !== '' ) ? '<span class="label">' + label + ' </span>' : '';

			// Show the fields when you click edit.
			_div.siblings( 'a.edit-timestamp' ).click( function() {
				var new_date = new Date( date_aa.val(), ( date_mm.val() - 1 ), date_jj.val(), date_hh.val(), date_mn.val() ),
					now = new Date();

				if ( _div.is( ':hidden' ) ) {
					_div.slideDown( 'fast' );
					$( this ).hide( );

					if ( new_date.getTime() === 0 ) {
						date_jj.val( now.getDate() );
						date_aa.val( now.getFullYear() );
						date_hh.val( now.getHours() );
						date_mn.val( now.getMinutes() );

						$( 'option:selected', date_mm ).removeAttr( 'selected' ).siblings( 'option' ).filter( '[value=' + zero_pad( now.getMonth() + 1, 2 ) + ']' ).attr( 'selected', 'selected' );
					}
				}
				return false;
			} );

			// Reset everything when you click cancel
			_div.find( '.cancel-timestamp' ).click( function( ) {
				_div.slideUp( 'fast' );

				// Return the fields to their original values
				date_mm.val( hidden_mm.val() );
				date_jj.val( hidden_jj.val() );
				date_aa.val( hidden_aa.val() );
				date_hh.val( hidden_hh.val() );
				date_mn.val( hidden_mn.val() );

				// Show the edit link again
				_div.siblings( 'a.edit-timestamp' ).show( );

				// Remove the error indication
				_div.find( '.timestamp-wrap' ).removeClass( 'form-invalid' );

				return false;
			} );


			// Update the when you hit save
			_div.find( '.save-timestamp' ).click( function() {
				var aa = date_aa.val(),
					mm = date_mm.val(),
					jj = date_jj.val(),
					hh = date_hh.val(),
					mn = date_mn.val();

				_div.find( '.timestamp-wrap' ).removeClass( 'form-invalid' );

				if ( check_date( aa, mm, jj, hh, mn ) ) {
					_div.slideUp( 'fast' );

					// Update the date display
					_div.parent( '.curtime' ).children( '.timestamp' ).html(
						label +
						'<b>' +
						_div.find( 'option[value="' + mm + '"]' ).text( ).match( /^\d+-(.*)$/ )[1] + ' ' +
						zero_pad( jj, 2 ) + ', ' +
						aa + ' @ ' +
						zero_pad( hh, 2 ) + ':' +
						zero_pad( mn, 2 ) + '</b> '
					);

					// Update the fallback date to the new one
					hidden_mm.val( mm );
					hidden_jj.val( jj );
					hidden_aa.val( aa );
					hidden_hh.val( hh );
					hidden_mn.val( mn );

					// Show the edit button again.
					_div.siblings( 'a.edit-timestamp' ).show( );
				}

				else {
					_div.find( '.timestamp-wrap' ).addClass( 'form-invalid' );
				}
				return false;
			} );
		} );
	} );
} ) ( jQuery );
