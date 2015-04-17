(function($){

	$( document ).ready( function() {

		$( 'input.color-picker' ).each( function( i ) {
			var input = $( this ),
				default_colour = input.data( 'default' ) || '#ffffff';

			input.after( '<div class="color-picker-box color-picker-box-' + i + '"></div>' );
			$( '.color-picker-box-' + i ).farbtastic( input );

		} );

	} );

})(jQuery);
