<?php

namespace Tubiz\Flutterwave_Woocommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Gateway_Flutterwave
 */
class WC_Gateway_Flutterwave extends \WC_Payment_Gateway {

	/**
	 * Checkout page title
	 *
	 * @var string
	 */
	public $title;

	/**
	 * Checkout page description
	 *
	 * @var string
	 */
	public $description;

	/**
	 * Is gateway enabled?
	 *
	 * @var bool
	 */
	public $enabled;

	/**
	 * Is test mode active?
	 *
	 * @var bool
	 */
	public bool $testmode;

	/**
	 * Should orders be marked as complete after payment?
	 *
	 * @var bool
	 */
	public bool $autocomplete_order;

	/**
	 * Text displayed as the title of the payment modal
	 *
	 * @var string
	 */
	public string $custom_title;

	/**
	 * Text displayed as a short modal description
	 *
	 * @var string
	 */
	public string $custom_desc;

	/**
	 * Image to be displayed on the payment popup
	 *
	 * @var string
	 */
	public string $custom_logo;

	/**
	 * Flutterwave test public key.
	 *
	 * @var string
	 */
	public string $test_public_key;

	/**
	 * Flutterwave test secret key.
	 *
	 * @var string
	 */
	public string $test_secret_key;

	/**
	 * Flutterwave live public key.
	 *
	 * @var string
	 */
	public string $live_public_key;

	/**
	 * Flutterwave live secret key.
	 *
	 * @var string
	 */
	public string $live_secret_key;

	/**
	 * API public key.
	 *
	 * @var string
	 */
	public string $public_key;

	/**
	 * API secret key.
	 *
	 * @var string
	 */
	public string $secret_key;

	/**
	 * Should we save customer cards?
	 *
	 * @var bool
	 */
	public bool $saved_cards;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id                 = 'tbz_rave';
		$this->method_title       = 'Flutterwave';
		$this->method_description = sprintf( 'Flutterwave is the easiest way to collect payments from customers anywhere in the world. <a href="%1$s" target="_blank">Sign up</a> for a Flutterwave account, and <a href="%2$s" target="_blank">get your API keys</a>.', 'https://dashboard.flutterwave.com', 'https://dashboard.flutterwave.com/dashboard/settings/apis' );

		$this->has_fields = true;

		$this->supports = array(
			'products',
			'tokenization',
			'subscriptions',
			'multiple_subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			'subscription_payment_method_change',
			'subscription_payment_method_change_customer',
		);

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Get setting values.
		$this->title              = $this->get_option( 'title' );
		$this->description        = $this->get_option( 'description' );
		$this->enabled            = $this->get_option( 'enabled' );
		$this->testmode           = $this->get_option( 'testmode' ) === 'yes';
		$this->autocomplete_order = $this->get_option( 'autocomplete_order' ) === 'yes';

		$this->custom_title = $this->get_option( 'custom_title' );
		$this->custom_desc  = $this->get_option( 'custom_desc' );
		$this->custom_logo  = $this->get_option( 'custom_logo' );

		$this->test_public_key = $this->get_option( 'test_public_key' );
		$this->test_secret_key = $this->get_option( 'test_secret_key' );

		$this->live_public_key = $this->get_option( 'live_public_key' );
		$this->live_secret_key = $this->get_option( 'live_secret_key' );

		$this->public_key = $this->testmode ? $this->test_public_key : $this->live_public_key;
		$this->secret_key = $this->testmode ? $this->test_secret_key : $this->live_secret_key;

		$this->saved_cards = $this->get_option( 'saved_cards' ) === 'yes';

		// Hooks.
		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );

		add_action( 'admin_notices', array( $this, 'admin_notices' ) );

		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			array(
				$this,
				'process_admin_options',
			)
		);

		add_action( 'woocommerce_admin_order_totals_after_total', array( $this, 'display_flutterwave_fee' ) );
		add_action( 'woocommerce_admin_order_totals_after_total', array( $this, 'display_order_payout' ), 20 );

		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );

		// Payment listener/API hook.
		add_action( 'woocommerce_api_tbz_wc_rave_gateway', array( $this, 'verify_flutterwave_transaction' ) );

		// Webhook listener/API hook.
		add_action( 'woocommerce_api_tbz_wc_rave_webhook', array( $this, 'process_webhooks' ) );
	}

	/**
	 * Display the payment icon on the checkout page
	 */
	public function get_icon() {

		$icon = '<img src="' . \WC_HTTPS::force_https_url( plugins_url( 'assets/images/powered-by-rave.png', TBZ_WC_FLUTTERWAVE_MAIN_FILE ) ) . '" alt="cards" />';

		return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );

	}

	/**
	 * Check if Flutterwave merchant details is filled
	 */
	public function admin_notices() {

		if ( 'no' === $this->enabled ) {
			return;
		}

		// Check required fields.
		if ( ! ( $this->public_key && $this->secret_key ) ) {
			echo '<div class="error"><p>' . sprintf( 'Please enter your Flutterwave merchant details <a href="%s">here</a> to be able to use the Flutterwave WooCommerce plugin.', admin_url( 'admin.php?page=wc-settings&tab=checkout&section=tbz_rave' ) ) . '</p></div>';
		}
	}

	/**
	 * Check if Flutterwave gateway is enabled.
	 */
	public function is_available() {

		if ( 'yes' === $this->enabled ) {

			if ( ! ( $this->public_key && $this->secret_key ) ) {

				return false;

			}

			return true;

		}

		return false;
	}

	/**
	 * Admin Panel Options
	 */
	public function admin_options() {

		?>

        <h3>Flutterwave</h3>

        <h4>Optional: To avoid situations where bad network makes it impossible to verify transactions, set your webhook
            URL <a href="https://app.flutterwave.com/dashboard/settings/webhooks/" target="_blank"
                   rel="noopener noreferrer">here</a> to the URL below<strong style="color: red">
                <pre><code><?php echo WC()->api_request_url( 'Tbz_WC_Rave_Webhook' ); ?></code></pre>
            </strong></h4>

		<?php

		echo '<table class="form-table">';
		$this->generate_settings_html();
		echo '</table>';
	}

	/**
	 * Initialise Gateway Settings Form Fields
	 */
	public function init_form_fields() {

		$this->form_fields = array(
			'enabled'            => array(
				'title'       => __( 'Enable/Disable', 'woo-rave' ),
				'label'       => __( 'Enable Flutterwave', 'woo-rave' ),
				'type'        => 'checkbox',
				'description' => __( 'Enable Flutterwave as a payment option on the checkout page.', 'woo-rave' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			'title'              => array(
				'title'       => __( 'Title', 'woo-rave' ),
				'type'        => 'text',
				'description' => __( 'This controls the payment method title which the user sees during checkout.', 'woo-rave' ),
				'desc_tip'    => true,
				'default'     => __( 'Flutterwave', 'woo-rave' ),
			),
			'description'        => array(
				'title'       => __( 'Description', 'woo-rave' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the payment method description which the user sees during checkout.', 'woo-rave' ),
				'desc_tip'    => true,
				'default'     => __( 'Make payment using your debit, credit card & bank account', 'woo-rave' ),
			),
			'testmode'           => array(
				'title'       => __( 'Test mode', 'woo-rave' ),
				'label'       => __( 'Enable Test Mode', 'woo-rave' ),
				'type'        => 'checkbox',
				'description' => __( 'Test mode enables you to test payments before going live. <br />Once you are live uncheck this.', 'woo-rave' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'test_public_key'    => array(
				'title'       => __( 'Test Public Key', 'woo-rave' ),
				'type'        => 'text',
				'description' => __( 'Required: Enter your Test Public Key here.', 'woo-rave' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_secret_key'    => array(
				'title'       => __( 'Test Secret Key', 'woo-rave' ),
				'type'        => 'password',
				'description' => __( 'Required: Enter your Test Secret Key here', 'woo-rave' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'live_public_key'    => array(
				'title'       => __( 'Live Public Key', 'woo-rave' ),
				'type'        => 'text',
				'description' => __( 'Required: Enter your Live Public Key here.', 'woo-rave' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'live_secret_key'    => array(
				'title'       => __( 'Live Secret Key', 'woo-rave' ),
				'type'        => 'password',
				'description' => __( 'Required: Enter your Live Secret Key here.', 'woo-rave' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'custom_title'       => array(
				'title'       => __( 'Custom Title', 'woo-rave' ),
				'type'        => 'text',
				'description' => __( 'Optional: Text to be displayed as the title of the payment modal.', 'woo-rave' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'custom_desc'        => array(
				'title'       => __( 'Custom Description', 'woo-rave' ),
				'type'        => 'text',
				'description' => __( 'Optional: Text to be displayed as a short modal description.', 'woo-rave' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'custom_logo'        => array(
				'title'       => __( 'Custom Logo', 'woo-rave' ),
				'type'        => 'text',
				'description' => __( 'Optional: Enter the link to a image to be displayed on the payment popup. Preferably a square image.', 'woo-rave' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'saved_cards'        => array(
				'title'       => __( 'Saved Cards', 'woo-rave' ),
				'label'       => __( 'Enable Payment via Saved Cards', 'woo-rave' ),
				'type'        => 'checkbox',
				'description' => __( 'If enabled, users will be able to pay with a saved card during checkout. Card details are saved on Flutterwave servers, not on your store.<br>Note that you need to have a valid SSL certificate installed.', 'woo-rave' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			'autocomplete_order' => array(
				'title'       => __( 'Autocomplete Order After Payment', 'woo-rave' ),
				'label'       => __( 'Autocomplete Order', 'woo-rave' ),
				'type'        => 'checkbox',
				'description' => __( 'If enabled, the order will be marked as complete after successful payment', 'woo-rave' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
		);

	}

	/**
	 * Payment form on checkout page
	 */
	public function payment_fields() {

		if ( $this->description ) {
			echo wpautop( wptexturize( $this->description ) );
		}

		if ( ! is_ssl() ) {
			return;
		}

		if ( $this->saved_cards && $this->supports( 'tokenization' ) && is_checkout() && is_user_logged_in() ) {
			$this->tokenization_script();
			$this->saved_payment_methods();
			$this->save_payment_method_checkbox();
		}
	}

	/**
	 * Outputs scripts used by Rave.
	 */
	public function payment_scripts() {

		if ( ! is_checkout_pay_page() ) {
			return;
		}

		if ( 'no' === $this->enabled ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order_key = urldecode( sanitize_text_field( $_GET['key'] ) );
		$order_id  = absint( get_query_var( 'order-pay' ) );

		$order = wc_get_order( $order_id );

		$payment_method = method_exists( $order, 'get_payment_method' ) ? $order->get_payment_method() : $order->payment_method;

		if ( $this->id !== $payment_method ) {
			return;
		}

		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'tbz_flutterwave', 'https://checkout.flutterwave.com/v3.js', array( 'jquery' ), TBZ_WC_FLUTTERWAVE_VERSION, false );
		wp_enqueue_script(
			'tbz_wc_flutterwave',
			plugins_url( 'assets/js/flutterwave' . $suffix . '.js', TBZ_WC_FLUTTERWAVE_MAIN_FILE ),
			array(
				'jquery',
				'tbz_flutterwave',
			),
			TBZ_WC_FLUTTERWAVE_VERSION,
			false
		);

		$flutterwave_params = array(
			'public_key' => $this->public_key,
		);

		if ( is_checkout_pay_page() && get_query_var( 'order-pay' ) ) {

			$email         = $order->get_billing_email();
			$billing_phone = $order->get_billing_phone();
			$first_name    = $order->get_billing_first_name();
			$last_name     = $order->get_billing_last_name();

			$amount = $order->get_total();

			$txnref = 'WC|' . $order_id . '|' . time();

			$the_order_id  = $order->get_id();
			$the_order_key = $order->get_order_key();

			$currency = $order->get_currency();

			$meta = array();

			if ( $the_order_id == $order_id && $the_order_key == $order_key ) {

				$meta['Order ID'] = $order_id;

				$customer_name = trim( "$first_name $last_name" );

				$location = wc_get_base_location();

				$flutterwave_params['txref']          = $txnref;
				$flutterwave_params['amount']         = $amount;
				$flutterwave_params['currency']       = $currency;
				$flutterwave_params['country']        = $location['country'];
				$flutterwave_params['customer_email'] = $email;
				$flutterwave_params['customer_phone'] = $billing_phone;
				$flutterwave_params['customer_name']  = $customer_name;
				$flutterwave_params['custom_title']   = $this->custom_title;
				$flutterwave_params['custom_desc']    = $this->custom_desc;
				$flutterwave_params['custom_logo']    = $this->custom_logo;
				$flutterwave_params['meta']           = $meta;

				$order->update_meta_data( '_rave_txn_ref', $txnref );
				$order->save();
			}
		}

		wp_localize_script( 'tbz_wc_flutterwave', 'tbz_wc_flutterwave_params', $flutterwave_params );
	}

	/**
	 * Load admin scripts
	 */
	public function admin_scripts() {

		if ( 'woocommerce_page_wc-settings' !== get_current_screen()->id ) {
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_script( 'tbz_wc_flutterwave_admin', plugins_url( 'assets/js/flutterwave-admin' . $suffix . '.js', TBZ_WC_FLUTTERWAVE_MAIN_FILE ), array(), TBZ_WC_FLUTTERWAVE_VERSION, true );

	}

	/**
	 * Displays the Rave fee
	 *
	 * @param int $order_id WC Order ID.
	 *
	 * @since 2.1.0
	 *
	 */
	public function display_flutterwave_fee( $order_id ) {

		$order = wc_get_order( $order_id );

		$fee      = $order->get_meta( '_rave_fee', true );
		$currency = $order->get_meta( '_rave_currency', true );

		if ( ! $fee || ! $currency ) {
			return;
		}

		?>

        <tr>
            <td class="label rave-fee">
				<?php echo wc_help_tip( __( 'This represents the fee Flutterwave collects for the transaction.', 'woo-rave' ) ); ?>
				<?php esc_html_e( __( 'Flutterwave Fee:', 'woo-rave' ) ); ?>
            </td>
            <td width="1%"></td>
            <td class="total">
                -&nbsp;<?php echo wc_price( $fee, array( 'currency' => $currency ) ); ?>
            </td>
        </tr>

		<?php
	}

	/**
	 * Displays the net total of the transaction without the charges of Rave.
	 *
	 * @param int $order_id WC Order ID.
	 *
	 * @since 2.1.0
	 *
	 */
	public function display_order_payout( $order_id ) {

		$order = wc_get_order( $order_id );

		$net      = $order->get_meta( '_rave_net', true );
		$currency = $order->get_meta( '_rave_currency', true );

		if ( ! $net || ! $currency ) {
			return;
		}

		?>

        <tr>
            <td class="label rave-payout">
				<?php $message = __( 'This represents the net total that will be credited to your bank account for this order.', 'woo-rave' ); ?>
				<?php if ( $net >= $order->get_total() ) : ?>
					<?php $message .= __( ' Flutterwave transaction fees was passed to the customer.', 'woo-rave' ); ?>
				<?php endif; ?>
				<?php echo wc_help_tip( $message ); ?>
				<?php esc_html_e( __( 'Flutterwave Payout:', 'woo-rave' ) ); ?>
            </td>
            <td width="1%"></td>
            <td class="total">
				<?php echo wc_price( $net, array( 'currency' => $currency ) ); ?>
            </td>
        </tr>

		<?php
	}

	/**
	 * Process payment
	 *
	 * @param int $order_id WC Order ID.
	 *
	 * @return array|void
	 */
	public function process_payment( $order_id ) {

		if ( isset( $_POST['wc-tbz_rave-payment-token'] ) && 'new' !== $_POST['wc-tbz_rave-payment-token'] ) {

			$token_id = wc_clean( $_POST['wc-tbz_rave-payment-token'] );
			$token    = \WC_Payment_Tokens::get( $token_id );

			if ( $token->get_user_id() !== get_current_user_id() ) {

				wc_add_notice( __( 'Invalid token ID', 'woo-rave' ), 'error' );

			} else {

				$status = $this->process_token_payment( $token->get_token(), $order_id );

				if ( $status ) {

					$order = wc_get_order( $order_id );

					return array(
						'result'   => 'success',
						'redirect' => $this->get_return_url( $order ),
					);

				}
			}
		} else {

			$order = wc_get_order( $order_id );

			if ( is_user_logged_in() && isset( $_POST['wc-tbz_rave-new-payment-method'] ) && true === (bool) $_POST['wc-tbz_rave-new-payment-method'] && $this->saved_cards ) {

				$order->update_meta_data( '_wc_rave_save_card', true );
				$order->save();

			}
			return array(
				'result'   => 'success',
				'redirect' => $order->get_checkout_payment_url( true ),
			);

		}

	}

	/**
	 * Process a token payment
	 */
	public function process_token_payment( $token, $order_id ) {

		if ( $token && $order_id ) {

			$order = wc_get_order( $order_id );

			$txnref = 'WC|' . $order_id . '|' . uniqid();

			$order_amount   = $order->get_total();
			$order_currency = $order->get_currency();
			$first_name     = $order->get_billing_first_name();
			$last_name      = $order->get_billing_last_name();
			$email          = $order->get_billing_email();

			$ip_address = $order->get_customer_ip_address();

			$headers = array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $this->secret_key,
			);

			if ( strpos( $token, '##' ) !== false ) {
				$payment_token = explode( '##', $token );
				$token_code    = $payment_token[0];
			} else {
				$token_code = $token;
			}

			$body = array(
				'token'     => $token_code,
				'email'     => $email,
				'currency'  => $order_currency,
				'amount'    => $order_amount,
				'tx_ref'    => $txnref,
				'firstname' => $first_name,
				'lastname'  => $last_name,
				'ip'        => $ip_address,
				'meta'      => array(
					array(
						'metaname'  => 'Order ID',
						'metavalue' => $order_id,
					),
				),
			);

			$args = array(
				'headers' => $headers,
				'body'    => wp_json_encode( $body ),
				'timeout' => 60,
			);

			$tokenized_url = 'https://api.flutterwave.com/v3/tokenized-charges';

			$request = wp_remote_post( $tokenized_url, $args );

			if ( ! is_wp_error( $request ) && 200 === wp_remote_retrieve_response_code( $request ) ) {

				$response = json_decode( wp_remote_retrieve_body( $request ) );

				$status           = $response->data->status;
				$payment_currency = $response->data->currency;
				$gateway_symbol   = get_woocommerce_currency_symbol( $payment_currency );

				if ( 'successful' === $status ) {

					if ( in_array( $order->get_status(), array( 'processing', 'completed', 'on-hold' ), true ) ) {

                        // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
						wp_redirect( $this->get_return_url( $order ) );

						exit;
					}

					$order_currency = $order->get_currency();

					$currency_symbol = get_woocommerce_currency_symbol( $order_currency );

					$order_total = $order->get_total();

					$amount_paid = $response->data->amount;

					$txn_ref     = $response->data->tx_ref;
					$payment_ref = $response->data->flw_ref;

					$amount_charged = $response->data->charged_amount;

					$flutterwave_fee = $response->data->app_fee;
					$flutterwave_net = $amount_charged - $flutterwave_fee;

					$order->update_meta_data( '_rave_fee', $flutterwave_fee );
					$order->update_meta_data( '_rave_net', $flutterwave_net );
					$order->update_meta_data( '_rave_currency', $payment_currency );

					// check if the amount paid is equal to the order amount.
					if ( $amount_paid < $order_total ) {

						$order->update_status( 'on-hold', '' );

						$order->set_transaction_id( $txn_ref );

						$notice      = 'Thank you for shopping with us.<br />Your payment was successful, but the amount paid is not the same as the total order amount.<br />Your order is currently on-hold.<br />Kindly contact us for more information regarding your order and payment status.';
						$notice_type = 'notice';

						// Add Customer Order Note.
						$order->add_order_note( $notice, 1 );

						// Add Admin Order Note.
						$order->add_order_note( '<strong>Look into this order</strong><br />This order is currently on hold.<br />Reason: Amount paid is less than the total order amount.<br />Amount Paid was <strong>' . $currency_symbol . $amount_paid . '</strong> while the total order amount is <strong>' . $currency_symbol . $order_total . '</strong><br /><strong>Transaction ID:</strong> ' . $txn_ref . ' | <strong>Payment Reference:</strong> ' . $payment_ref );

						wc_reduce_stock_levels( $order_id );

						wc_add_notice( $notice, $notice_type );

					} else {

						if ( $payment_currency !== $order_currency ) {

							$order->update_status( 'on-hold' );

							$order->set_transaction_id( $txn_ref );

							$notice      = 'Thank you for shopping with us.<br />Your payment was successful, but the payment currency is different from the order currency.<br />Your order is currently on-hold.<br />Kindly contact us for more information regarding your order and payment status.';
							$notice_type = 'notice';

							// Add Customer Order Note.
							$order->add_order_note( $notice, 1 );

							// Add Admin Order Note.
							$order->add_order_note( '<strong>Look into this order</strong><br />This order is currently on hold.<br />Reason: Order currency is different from the payment currency.<br /> Order Currency is <strong>' . $order_currency . ' (' . $currency_symbol . ')</strong> while the payment currency is <strong>' . $payment_currency . ' (' . $gateway_symbol . ')</strong><br /><strong>Transaction ID:</strong> ' . $txn_ref . ' | <strong>Payment Reference:</strong> ' . $payment_ref );

							wc_reduce_stock_levels( $order_id );

							wc_add_notice( $notice, $notice_type );

						} else {

							$order->payment_complete( $txn_ref );

							$order->add_order_note( sprintf( 'Payment via Flutterwave successful (<strong>Transaction ID:</strong> %s | <strong>Payment Reference:</strong> %s)', $txn_ref, $payment_ref ) );

							if ( $this->autocomplete_order ) {
								$order->update_status( 'completed' );
							}
						}
					}

					$order->save();

					$this->save_subscription_payment_token( $order_id, $token );

					wc_empty_cart();

					return true;

				}

				$order = wc_get_order( $order_id );

				$order->update_status( 'failed', 'Payment was declined by Flutterwave.' );

				wc_add_notice( 'Payment Failed. Try again.', 'error' );

				return false;

			}

			wc_add_notice( 'Payment failed using the saved card. Kindly use another payment method.', 'error' );

			return false;
		}

		wc_add_notice( 'Payment Failed.', 'error' );

		return false;
	}

	/**
	 * Show new card can only be added when placing an order notice
	 */
	public function add_payment_method() {
		wc_add_notice( __( 'You can only add a new card when placing an order.', 'woo-rave' ), 'error' );
	}

	/**
	 * Displays the payment page
	 */
	public function receipt_page( $order_id ) {

		$order = wc_get_order( $order_id );

		echo '<div id="tbz_wc_flutterwave_form">';

		echo '<p>Thank you for your order, please click the button below to pay via Flutterwave.</p>';

		echo '<form id="order_review" method="post" action="' . WC()->api_request_url( 'Tbz_WC_Rave_Gateway' ) . '"></form><button class="button alt" id="tbz-rave-wc-payment-button">Pay Now</button> <a class="button cancel" href="' . esc_url( $order->get_cancel_order_url() ) . '">Cancel order &amp; restore cart</a>';

		echo '</div>';
	}

	/**
	 * Verify Rave payment
	 */
	public function verify_flutterwave_transaction() {

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		@ob_clean();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_REQUEST['tbz_wc_flutterwave_txnref'] ) ) {

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$txn_id = sanitize_text_field( $_REQUEST['tbz_wc_flutterwave_txnref'] );

			$flutterwave_txn = $this->verify_transaction( $txn_id );

			$status = $flutterwave_txn->data->status ?? 'failed';

			if ( 'successful' === $status ) {

				$payment_currency = $flutterwave_txn->data->currency;
				$gateway_symbol   = get_woocommerce_currency_symbol( $payment_currency );
				$order_details    = explode( '|', $flutterwave_txn->data->tx_ref );

				$order_id = (int) $order_details[1];

				$order = wc_get_order( $order_id );

				if ( in_array( $order->get_status(), array( 'processing', 'completed', 'on-hold' ), true ) ) {

					wp_redirect( $this->get_return_url( $order ) );

					exit;
				}

				$order_currency = $order->get_currency();

				$currency_symbol = get_woocommerce_currency_symbol( $order_currency );

				$order_total = $order->get_total();

				$amount_paid     = $flutterwave_txn->data->amount;
				$txn_ref         = $flutterwave_txn->data->tx_ref;
				$payment_ref     = $flutterwave_txn->data->flw_ref;
				$amount_charged  = $flutterwave_txn->data->charged_amount;
				$flutterwave_fee = $flutterwave_txn->data->app_fee;

				$flutterwave_net = $amount_charged - $flutterwave_fee;

				$order->update_meta_data( '_rave_fee', $flutterwave_fee );
				$order->update_meta_data( '_rave_net', $flutterwave_net );
				$order->update_meta_data( '_rave_currency', $payment_currency );

				// check if the amount paid is equal to the order amount.
				if ( $amount_paid < $order_total ) {

					$order->update_status( 'on-hold' );

					$order->set_transaction_id( $txn_ref );

					$notice      = 'Thank you for shopping with us.<br />Your payment was successful, but the amount paid is not the same as the total order amount.<br />Your order is currently on-hold.<br />Kindly contact us for more information regarding your order and payment status.';
					$notice_type = 'notice';

					// Add Customer Order Note
					$order->add_order_note( $notice, 1 );

					// Add Admin Order Note
					$order->add_order_note( '<strong>Look into this order</strong><br />This order is currently on hold.<br />Reason: Amount paid is less than the total order amount.<br />Amount Paid was <strong>' . $currency_symbol . $amount_paid . '</strong> while the total order amount is <strong>' . $currency_symbol . $order_total . '</strong><br /><strong>Transaction ID:</strong> ' . $txn_ref . ' | <strong>Payment Reference:</strong> ' . $payment_ref );

					wc_reduce_stock_levels( $order_id );

					wc_add_notice( $notice, $notice_type );

				} else {

					if ( $payment_currency !== $order_currency ) {

						$order->update_status( 'on-hold' );

						$order->set_transaction_id( $txn_ref );

						$notice      = 'Thank you for shopping with us.<br />Your payment was successful, but the payment currency is different from the order currency.<br />Your order is currently on-hold.<br />Kindly contact us for more information regarding your order and payment status.';
						$notice_type = 'notice';

						// Add Customer Order Note
						$order->add_order_note( $notice, 1 );

						// Add Admin Order Note
						$order->add_order_note( '<strong>Look into this order</strong><br />This order is currently on hold.<br />Reason: Order currency is different from the payment currency.<br /> Order Currency is <strong>' . $order_currency . ' (' . $currency_symbol . ')</strong> while the payment currency is <strong>' . $payment_currency . ' (' . $gateway_symbol . ')</strong><br /><strong>Transaction ID:</strong> ' . $txn_ref . ' | <strong>Payment Reference:</strong> ' . $payment_ref );

						wc_reduce_stock_levels( $order_id );

						wc_add_notice( $notice, $notice_type );

					} else {

						$order->payment_complete( $txn_ref );

						$order->add_order_note( sprintf( 'Payment via Flutterwave successful (<strong>Transaction ID:</strong> %s | <strong>Payment Reference:</strong> %s)', $txn_ref, $payment_ref ) );

						if ( $this->autocomplete_order ) {
							$order->update_status( 'completed' );
						}
					}
				}

				$order->save();

				$this->save_card_details( $flutterwave_txn, $order->get_user_id(), $order_id );

				wc_empty_cart();

				wp_redirect( $this->get_return_url( $order ) );

				exit;

			} else {

				// phpcs:ignore  WordPress.Security.NonceVerification.Recommended
				$order_txn_ref = sanitize_text_field( $_REQUEST['tbz_wc_flutterwave_order_txnref'] );
				$order_details = explode( '|', $order_txn_ref );
				$order_id      = (int) $order_details[1];
				$order         = wc_get_order( $order_id );

				if ( $order ) {
					$order->add_order_note( sprintf( 'Unable to retrieve transaction details from Flutterwave. This could be due to invalid Flutterwave API keys on the settings page. <strong>Transaction ID:</strong> %s', $order_txn_ref ) );
				}

				wc_add_notice( 'Unable to retrieve transaction details from Flutterwave. If debited contact website owner.', 'error' );

				wp_redirect( wc_get_page_permalink( 'checkout' ) );

				exit;
			}
		}

		wc_add_notice( 'Payment failed. Try again.', 'error' );

		wp_redirect( wc_get_page_permalink( 'checkout' ) );

		exit;
	}

	/**
	 * Process Webhook
	 */
	public function process_webhooks() {

		if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) ) {
			exit;
		}

		sleep( 10 );

		$body = file_get_contents( 'php://input' );

		if ( $this->is_json( $body ) ) {
			$webhook_body = (array) json_decode( $body, true );
		} else {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$webhook_body = $_POST;
		}

		if ( isset( $webhook_body['data']['id'] ) ) {
			$transaction_id = $webhook_body['data']['id'];
		} elseif ( isset( $webhook_body['id'] ) ) {
			$transaction_id = $webhook_body['id'];
		} else {
			$transaction_id = '';
		}

		if ( empty( $transaction_id ) ) {
			exit;
		}

		$flutterwave_txn = $this->verify_transaction( $transaction_id );

		if ( ! isset( $flutterwave_txn->data->status ) ) {
			return;
		}

		// Payment failed.
		if ( 'successful' !== strtolower( $flutterwave_txn->data->status ) ) {
			$order_details = explode( '|', $flutterwave_txn->data->tx_ref );

			$order_id = (int) $order_details[1];

			$order = wc_get_order( $order_id );

			if ( $order ) {
				$order->update_status( 'failed', 'Payment was declined by Flutterwave.' );
			}

			exit;
		}

		$payment_currency = $flutterwave_txn->data->currency;
		$gateway_symbol   = get_woocommerce_currency_symbol( $payment_currency );

		$order_details = explode( '|', $flutterwave_txn->data->tx_ref );

		$order_id = (int) $order_details[1];

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$rave_txn_ref = $order->get_meta( '_rave_txn_ref' );

		if ( strtolower( $flutterwave_txn->data->tx_ref ) !== strtolower( $rave_txn_ref ) ) {
			exit;
		}

		http_response_code( 200 );

		if ( in_array( $order->get_status(), array( 'processing', 'completed', 'on-hold' ), true ) ) {
			exit;
		}

		$order_currency = $order->get_currency();

		$currency_symbol = get_woocommerce_currency_symbol( $order_currency );

		$order_total = $order->get_total();

		$amount_paid     = $flutterwave_txn->data->amount;
		$txn_ref         = $flutterwave_txn->data->tx_ref;
		$payment_ref     = $flutterwave_txn->data->flw_ref;
		$amount_charged  = $flutterwave_txn->data->charged_amount;
		$flutterwave_fee = $flutterwave_txn->data->app_fee;

		$flutterwave_net = $amount_charged - $flutterwave_fee;

		$order->update_meta_data( '_rave_fee', $flutterwave_fee );
		$order->update_meta_data( '_rave_net', $flutterwave_net );
		$order->update_meta_data( '_rave_currency', $payment_currency );

		// check if the amount paid is equal to the order amount.
		if ( $amount_paid < $order_total ) {

			$order->update_status( 'on-hold' );

			$order->set_transaction_id( $txn_ref );

			$notice = 'Thank you for shopping with us.<br />Your payment was successful, but the amount paid is not the same as the total order amount.<br />Your order is currently on-hold.<br />Kindly contact us for more information regarding your order and payment status.';

			// Add Customer Order Note
			$order->add_order_note( $notice, 1 );

			// Add Admin Order Note.
			$order->add_order_note( '<strong>Look into this order</strong><br />This order is currently on hold.<br />Reason: Amount paid is less than the total order amount.<br />Amount Paid was <strong>' . $currency_symbol . $amount_paid . '</strong> while the total order amount is <strong>' . $currency_symbol . $order_total . '</strong><br /><strong>Transaction ID:</strong> ' . $txn_ref . ' | <strong>Payment Reference:</strong> ' . $payment_ref );

			wc_reduce_stock_levels( $order_id );

		} elseif ( $payment_currency !== $order_currency ) {

			$order->update_status( 'on-hold', '' );

			$order->set_transaction_id( $txn_ref );

			$notice = 'Thank you for shopping with us.<br />Your payment was successful, but the payment currency is different from the order currency.<br />Your order is currently on-hold.<br />Kindly contact us for more information regarding your order and payment status.';

			// Add Customer Order Note.
			$order->add_order_note( $notice, 1 );

			// Add Admin Order Note.
			$order->add_order_note( '<strong>Look into this order</strong><br />This order is currently on hold.<br />Reason: Order currency is different from the payment currency.<br /> Order Currency is <strong>' . $order_currency . ' (' . $currency_symbol . ')</strong> while the payment currency is <strong>' . $payment_currency . ' (' . $gateway_symbol . ')</strong><br /><strong>Transaction ID:</strong> ' . $txn_ref . ' | <strong>Payment Reference:</strong> ' . $payment_ref );

			wc_reduce_stock_levels( $order_id );

		} else {

			$order->payment_complete( $txn_ref );

			$order->add_order_note( sprintf( 'Payment via Flutterwave successful (<strong>Transaction ID:</strong> %s | <strong>Payment Reference:</strong> %s)', $txn_ref, $payment_ref ) );

			if ( $this->autocomplete_order ) {
				$order->update_status( 'completed' );
			}
		}

		$order->save();

		$this->save_card_details( $flutterwave_txn, $order->get_user_id(), $order_id );

		wc_empty_cart();

		exit;
	}

	/**
	 * Save Customer Card Details.
	 */
	public function save_card_details( $flutterwave_txn, $user_id, $order_id ) {

		$token_code     = $flutterwave_txn->data->card->token ?? '';
		$customer_email = $flutterwave_txn->data->customer->email ?? '';

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		if ( empty( $token_code ) || empty( $customer_email ) ) {
			$order->delete_meta_data( '_wc_rave_save_card' );
			$order->save();
			return;
		}

		$card_token = "$token_code###$customer_email";

		$this->save_subscription_payment_token( $order_id, $card_token );

		$save_card = $order->get_meta( '_wc_rave_save_card' );

		if ( $user_id && $this->saved_cards && $save_card ) {

			$brand       = $flutterwave_txn->data->card->type;
			$last4       = $flutterwave_txn->data->card->last_4digits;
			$expiry_date = explode( '/', $flutterwave_txn->data->card->expiry );
			$exp_month   = $expiry_date[0];
			$exp_year    = $expiry_date[1];

			if ( 2 === strlen( $exp_year ) ) {
				$exp_year = date_create_from_format( 'y', $exp_year );
				$exp_year = $exp_year->format( 'Y' );
			}

			$token = new \WC_Payment_Token_CC();
			$token->set_token( $card_token );
			$token->set_gateway_id( 'tbz_rave' );
			$token->set_card_type( $brand );
			$token->set_last4( $last4 );
			$token->set_expiry_month( $exp_month );
			$token->set_expiry_year( $exp_year );
			$token->set_user_id( $user_id );
			$token->save();
		}

		$order->delete_meta_data( '_wc_rave_save_card' );
		$order->save();
	}

	/**
	 * Save payment token to the order for automatic renewal for further subscription payment
	 */
	public function save_subscription_payment_token( $order_id, $payment_token ) {

		if ( ! function_exists( 'wcs_order_contains_subscription' ) ) {
			return;
		}

		if ( empty( $payment_token ) ) {
			return;
		}

		if ( ! $this->order_contains_subscription( $order_id ) ) {
			return;
		}

		// Also store it on the subscriptions being purchased or paid for in the order.
		if ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order_id ) ) {

			$subscriptions = wcs_get_subscriptions_for_order( $order_id );

		} elseif ( function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( $order_id ) ) {

			$subscriptions = wcs_get_subscriptions_for_renewal_order( $order_id );

		} else {

			$subscriptions = array();

		}

		if ( empty( $subscriptions ) ) {
			return;
		}

		foreach ( $subscriptions as $subscription ) {
			$subscription->update_meta_data( '_tbz_rave_wc_token', $payment_token );
			$subscription->save();
		}
	}

	/**
	 * @param $string
	 *
	 * @return bool
	 */
	public function is_json( $string ) {
		return is_string( $string ) && is_array( json_decode( $string, true ) ) ? true : false;
	}

	private function verify_transaction( $txn_id ) {

		$api_url = "https://api.flutterwave.com/v3/transactions/$txn_id/verify";

		$headers = array(
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $this->secret_key,
		);

		$args = array(
			'headers' => $headers,
			'timeout' => 60,
		);

		$request = wp_remote_get( $api_url, $args );

		return json_decode( wp_remote_retrieve_body( $request ) );
	}
}

class_alias( WC_Gateway_Flutterwave::class, 'Tbz_WC_Rave_Webhook' );
