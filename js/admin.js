(function($){

	$( document )
		// add question
		.on( 'click', 'input[name^="addquestion"]', function( e ) {
			e.preventDefault();
			e.stopPropagation();

			var $button = $( this ),
				current_id = $button.data( 'qid' ),
				new_id = current_id + 1;

			console.log( current_id, new_id );

			$( '<div class="postbox question-field">' + $( '#question-template' ).html().replace( /Question \d+/, 'Question ' + $( '.question-field' ).length ).replace( /__i__/g, new_id ).replace( /__j__/g, '0' ).replace( 'id="answer-template"', '' ) + '</div>' )
				.hide()
				.insertBefore( $( '#question-template' ) )
				.slideDown( 'fast' )
				.find( 'input[name^=deleteanswer]' ).remove().end()
				.find( 'textarea' ).wrap( '<div class="wp-editor-container"></div>' ).each( function( i ) {
					tinyMCEPreInit.mceInit[ this.id ] = $.extend( true, {}, tinyMCEPreInit.mceInit.excerpt );
					tinyMCEPreInit.mceInit[ this.id ].elements = this.id;
					try { tinymce.init( tinyMCEPreInit.mceInit[ this.id ] ); } catch(e){}
				} );

			// increment new question ID
			$button.add().data( 'qid', new_id ).attr( 'data-qid', new_id );

		} )
		// delete question
		.on( 'click', 'input[name^="deletequestion"]', function( e ) {
			e.preventDefault();
			e.stopPropagation();

			$( this ).parents( '.question-field' ).slideUp( 'fast', function() {
				$( this ).remove();
			} );

		} )
		// add answer
		.on( 'click', 'input[name^="addanswer"]', function( e ) {
			e.preventDefault();
			e.stopPropagation();

			var $button = $( this ),
				current_id = $button.data( 'aid' ),
				new_id = current_id + 1,
				qid = $button.data( 'qid' );

			$( '<div class="postbox answer-field">' + $( '#answer-template' ).html().replace( /__i__/g, qid ).replace( /__j__/g, new_id ) + '</div>' )
				.hide()
				.insertAfter( $( this ).parents( '.question-field' ).find( '.answer-field' ).eq( -1 ) )
				.slideDown( 'fast' );

			$button.data( 'aid', new_id ).attr( 'data-aid', new_id );

		} )
		// delete answer
		.on( 'click', 'input[name^="deleteanswer"]', function( e ) {
			e.preventDefault();
			e.stopPropagation();

			$( this ).parents( '.answer-field' ).slideUp( 'fast', function() {
				$( this ).remove();
			} );

		} )
		// add result
		.on( 'click', 'input[name^="addresult"]', function( e ) {
			e.preventDefault();
			e.stopPropagation();

			$( '<div class="postbox multiple-results result-field">' + $( '#result-template' ).html().replace( /__i__/g, $( '.result-field' ).length - 1 ) + '</div>' )
				.hide()
				.insertBefore( $( '#result-template' ) )
				.slideDown( 'fast' )
				.find( 'textarea' ).wrap( '<div class="wp-editor-container"></div>' ).each( function( i ) {
					tinyMCEPreInit.mceInit[ this.id ] = $.extend( true, {}, tinyMCEPreInit.mceInit.excerpt );
					tinyMCEPreInit.mceInit[ this.id ].elements = this.id;
					try { tinymce.init( tinyMCEPreInit.mceInit[ this.id ] ); } catch(e){}
				} );

			var points = 0;
			$( '.multiple-results .quiz-result-points' ).each( function( i ) {
				var $input = $( this ).find( 'input[id$="points"]' ),
					$cell = $input.parent(),
					$from = $cell.find( 'strong' ),
					from = parseInt( $from.text(), 10 ),
					to = parseInt( $input.val(), 10 );

				if ( i > 0 ) {
					$from.html( points + 1 );
					if ( ! to )
						$input.val( points + 1 );
				}

				points = to;
			} );

		} )
		// delete result
		.on( 'click', 'input[name^="deleteresult"]', function( e ) {
			e.preventDefault();
			e.stopPropagation();

			$( this ).parents( '.result-field' ).slideUp( 'fast', function() {
				$( this ).remove();

				var points = 0;
				$( '.multiple-results .quiz-result-points' ).each( function( i ) {
					var $input = $( this ).find( 'input[id$="points"]' ),
						$cell = $input.parent(),
						$from = $cell.find( 'strong' ),
						from = parseInt( $from.text(), 10 ),
						to = parseInt( $input.val(), 10 );

					if ( i > 0 ) {
						$from.html( points + 1 );
						if ( ! to )
							$input.val( points + 1 );
					}

					points = to;
				} );
			} );

		} );

})(jQuery)
