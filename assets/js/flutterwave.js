jQuery(
	function( $ ) {

		var flutterwave_submit = false;

		$( '#tbz_wc_flutterwave_form' ).hide();

		tbzWcFlutterwavePaymentHandler()

		jQuery( '#tbz-rave-wc-payment-button' ).click(
			function() {
				return tbzWcFlutterwavePaymentHandler();
			}
		);

		jQuery( '#tbz_wc_flutterwave_form form#order_review' ).submit( function() {
			return tbzWcFlutterwavePaymentHandler();
		} );

		function tbzWcFlutterwavePaymentHandler() {

			$( '#tbz_wc_flutterwave_form' ).hide();

			if ( flutterwave_submit ) {
				flutterwave_submit = false;
				return true;
			}

			let $form    = $( 'form#payment-form, form#order_review' ),
			 flutterwave_txnref = $form.find( 'input.tbz_wc_flutterwave_txnref' );

			flutterwave_txnref.val( '' );

			let flutterwave_callback = function( response ) {

				$form.append( '<input type="hidden" class="tbz_wc_flutterwave_txnref" name="tbz_wc_flutterwave_txnref" value="' + response.transaction_id + '"/>' );
				$form.append( '<input type="hidden" class="tbz_wc_flutterwave_order_txnref" name="tbz_wc_flutterwave_order_txnref" value="' + response.tx_ref + '"/>' );

				flutterwave_submit = true;

				$form.submit();

				$( 'body' ).block(
					{
						message: null,
						overlayCSS: {
							background: '#fff',
							opacity: 0.6
						},
						css: {
							cursor: "wait"
						}
					}
				);
			};

			FlutterwaveCheckout(
				{
					public_key: tbz_wc_flutterwave_params.public_key,
					tx_ref: tbz_wc_flutterwave_params.txref,
					amount: tbz_wc_flutterwave_params.amount,
					currency: tbz_wc_flutterwave_params.currency,
					country: tbz_wc_flutterwave_params.country,
					meta: tbz_wc_flutterwave_params.meta,
					customer: {
						email: tbz_wc_flutterwave_params.customer_email,
						name: tbz_wc_flutterwave_params.customer_name,
					},
					customizations: {
						title: tbz_wc_flutterwave_params.custom_title,
						description: tbz_wc_flutterwave_params.custom_desc,
						logo: tbz_wc_flutterwave_params.custom_logo,
					},
					callback: flutterwave_callback,
					onclose: function() {
						$( '#tbz_wc_flutterwave_form' ).show();
						$( this.el ).unblock();
					}
				}
			);

			return false;
		}

	}
);
