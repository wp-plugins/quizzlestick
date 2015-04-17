(function( $ ){

	$.fn.quizzlestick.defaults.api = {
		send: function( obj, config ) {

			// map action as we need that var for WP ajax
			obj.qsaction = obj.action;

			// only post polls or completions
			//if ( obj.qsaction !== 'complete' ) {
			//	return;
			//}

			// add nonce
			obj = $.extend( true, obj, {
				_qsnonce: quizzlestickwp.nonce,
				action: 'quizzlestick_api',
				state: config.state 			// force state to be sent just in case
			} );

			// post data back
			$.post( quizzlestickwp.ajaxurl, obj );

		}
	};

})( jQuery );
