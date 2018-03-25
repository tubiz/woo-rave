jQuery( function( $ ) {
	'use strict';

	/**
	 * Object to handle rave admin functions.
	 */
	var wc_tbz_rave_admin = {
		/**
		 * Initialize.
		 */
		init: function() {

			// Toggle api key settings.
			$( document.body ).on( 'change', '#woocommerce_tbz_rave_testmode', function() {
				var test_public_key = $( '#woocommerce_tbz_rave_test_public_key' ).parents( 'tr' ).eq( 0 ),test_secret_key = $( '#woocommerce_tbz_rave_test_secret_key' ).parents( 'tr' ).eq( 0 ),
					live_public_key = $( '#woocommerce_tbz_rave_live_public_key' ).parents( 'tr' ).eq( 0 ),
					live_secret_key = $( '#woocommerce_tbz_rave_live_secret_key' ).parents( 'tr' ).eq( 0 );

				if ( $( this ).is( ':checked' ) ) {
					test_public_key.show();
					test_secret_key.show();
					live_public_key.hide();
					live_secret_key.hide();
				} else {
					test_public_key.hide();
					test_secret_key.hide();
					live_public_key.show();
					live_secret_key.show();
				}
			} );

			$( '#woocommerce_tbz_rave_testmode' ).change();
		}
	};

	wc_tbz_rave_admin.init();

});
