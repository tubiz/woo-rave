jQuery( function( $ ) {

    var rave_submit = false;

    jQuery( '#tbz-rave-wc-payment-button' ).click( function() {
        return tbzWCRavePaymentHandler();
    });

    function tbzWCRavePaymentHandler() {

        if ( rave_submit ) {
            rave_submit = false;
            return true;
        }

        var $form            = $( 'form#payment-form, form#order_review' ),
            rave_txnref      = $form.find( 'input.tbz_wc_rave_txnref' );

        rave_txnref.val( '' );

        var rave_callback = function( response ) {

            $form.append( '<input type="hidden" class="tbz_wc_rave_txnref" name="tbz_wc_rave_txnref" value="' + response.tx.flwRef + '"/>' );

            rave_submit = true;

            $form.submit();

            $( 'body' ).block({
                message: null,
                overlayCSS: {
                    background: '#fff',
                    opacity: 0.6
                },
                css: {
                    cursor: "wait"
                }
            });
        };

        getpaidSetup( {
            PBFPubKey: tbz_wc_rave_params.public_key,
            customer_email: tbz_wc_rave_params.customer_email,
            customer_firstname: tbz_wc_rave_params.customer_first_name,
            customer_lastname: tbz_wc_rave_params.customer_last_name,
            custom_description: tbz_wc_rave_params.custom_desc,
            custom_logo: tbz_wc_rave_params.custom_logo,
            custom_title: tbz_wc_rave_params.custom_title,
            amount: tbz_wc_rave_params.amount,
            customer_phone: tbz_wc_rave_params.customer_phone,
            country: tbz_wc_rave_params.country,
            currency: tbz_wc_rave_params.currency,
	        txref: tbz_wc_rave_params.txref,
	        meta: tbz_wc_rave_params.meta,
            integrity_hash: tbz_wc_rave_params.hash,
            onClose: function() {
                $( this.el ).unblock();
            },
            callback: rave_callback
        } );

        return false;
    }

} );