(function($){

	$( document ).ready( function() {

		$( 'input.color-picker' ).each( function( i ) {
			var input = $( this ),
				default_colour = input.data( 'default' ) || '#ffffff',
				hide = input.data( 'hide' ) || false,
				palettes = input.data( 'palettes' ) || true;

			input.wpColorPicker({
				defaultColor: default_colour,
				hide: hide,
				palettes: palettes,
				change: function(event, ui) {
					input.val( input.wpColorPicker( "color" ) );
				},
				clear: function() {
					input.val( default_colour ).wpColorPicker(  );
				}
			});

		} )

	} );

})(jQuery);
