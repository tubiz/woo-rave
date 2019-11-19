<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Tbz_WC_Rave_Gateway
 */
class Tbz_WC_Rave_Gateway extends WC_Payment_Gateway {

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
	public $testmode;

	/**
	 * Text displayed as the title of the payment modal
	 *
	 * @var string
	 */
	public $custom_title;

	/**
	 * Text displayed as a short modal description
	 *
	 * @var string
	 */
	public $custom_desc;

	/**
	 * Image to be displayed on the payment popup
	 *
	 * @var string
	 */
	public $custom_logo;

	/**
	 * Rave test public key.
	 *
	 * @var string
	 */
	public $test_public_key;

	/**
	 * Rave test secret key.
	 *
	 * @var string
	 */
	public $test_secret_key;

	/**
	 * Rave live public key.
	 *
	 * @var string
	 */
	public $live_public_key;

	/**
	 * Rave live secret key.
	 *
	 * @var string
	 */
	public $live_secret_key;

	/**
	 * API public key.
	 *
	 * @var string
	 */
	public $public_key;

	/**
	 * API secret key.
	 *
	 * @var string
	 */
	public $secret_key;

	/**
	 * Should we save customer cards?
	 *
	 * @var bool
	 */
	public $saved_cards;

	/**
	 * Rave API query URL
	 *
	 * @var string
	 */
	public $query_url;

	/**
	 * Rave API tokenized URL
	 *
	 * @var string
	 */
	public $tokenized_url;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id                 = 'tbz_rave';
		$this->method_title       = 'Rave by Flutterwave';
		$this->method_description = sprintf( 'Rave by Flutterwave is the easiest way to collect payments from customers anywhere in the world. <a href="%1$s" target="_blank">Sign up</a> for a Rave account, and <a href="%2$s" target="_blank">get your API keys</a>.', 'https://rave.flutterwave.com', 'https://rave.flutterwave.com/dashboard/settings/apis' );

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
		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->enabled     = $this->get_option( 'enabled' );
		$this->testmode    = $this->get_option( 'testmode' ) === 'yes' ? true : false;

		$this->custom_title = $this->get_option( 'custom_title' );
		$this->custom_desc  = $this->get_option( 'custom_desc' );
		$this->custom_logo  = $this->get_option( 'custom_logo' );

		$this->test_public_key = $this->get_option( 'test_public_key' );
		$this->test_secret_key = $this->get_option( 'test_secret_key' );

		$this->live_public_key = $this->get_option( 'live_public_key' );
		$this->live_secret_key = $this->get_option( 'live_secret_key' );

		$this->public_key = $this->testmode ? $this->test_public_key : $this->live_public_key;
		$this->secret_key = $this->testmode ? $this->test_secret_key : $this->live_secret_key;

		$this->saved_cards = $this->get_option( 'saved_cards' ) === 'yes' ? true : false;

		$this->query_url     = 'https://api.ravepay.co/flwv3-pug/getpaidx/api/v2/verify';
		$this->tokenized_url = 'https://api.ravepay.co/flwv3-pug/getpaidx/api/tokenized/charge';

		// Hooks.
		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );

		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		add_action( 'woocommerce_admin_order_totals_after_total', array( $this, 'display_rave_fee' ) );
		add_action( 'woocommerce_admin_order_totals_after_total', array( $this, 'display_order_payout' ), 20 );

		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );

		// Payment listener/API hook.
		add_action( 'woocommerce_api_tbz_wc_rave_gateway', array( $this, 'verify_rave_transaction' ) );

		// Webhook listener/API hook.
		add_action( 'woocommerce_api_tbz_wc_rave_webhook', array( $this, 'process_webhooks' ) );
	}

	/**
	 * Display the payment icon on the checkout page
	 */
	public function get_icon() {

		$icon = '<img src="' . WC_HTTPS::force_https_url( plugins_url( 'assets/images/powered-by-rave.png', TBZ_WC_RAVE_MAIN_FILE ) ) . '" alt="cards" />';

		return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );

	}

	/**
	 * Check if Rave merchant details is filled
	 */
	public function admin_notices() {

		if ( 'no' === $this->enabled ) {
			return;
		}

		// Check required fields.
		if ( ! ( $this->public_key && $this->secret_key ) ) {
			echo '<div class="error"><p>' . sprintf( 'Please enter your rave merchant details <a href="%s">here</a> to be able to use the Rave WooCommerce plugin.', admin_url( 'admin.php?page=wc-settings&tab=checkout&section=tbz_rave' ) ) . '</p></div>';
			return;
		}

	}

	/**
	 * Check if Rave gateway is enabled.
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

		<h3>Rave</h3>

		<h4>Optional: To avoid situations where bad network makes it impossible to verify transactions, set your webhook URL <a href="https://rave.flutterwave.com/dashboard/settings/webhooks" target="_blank" rel="noopener noreferrer">here</a> to the URL below<strong style="color: red"><pre><code><?php echo WC()->api_request_url( 'Tbz_WC_Rave_Webhook' ); ?></code></pre></strong></h4>

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
			'enabled'         => array(
				'title'       => __( 'Enable/Disable', 'woo-rave' ),
				'label'       => __( 'Enable Rave', 'woo-rave' ),
				'type'        => 'checkbox',
				'description' => __( 'Enable Rave as a payment option on the checkout page.', 'woo-rave' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			'title'           => array(
				'title'       => __( 'Title', 'woo-rave' ),
				'type'        => 'text',
				'description' => __( 'This controls the payment method title which the user sees during checkout.', 'woo-rave' ),
				'desc_tip'    => true,
				'default'     => __( 'Rave', 'woo-rave' ),
			),
			'description'     => array(
				'title'       => __( 'Description', 'woo-rave' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the payment method description which the user sees during checkout.', 'woo-rave' ),
				'desc_tip'    => true,
				'default'     => __( 'Make payment using your debit, credit card & bank account', 'woo-rave' ),
			),
			'testmode'        => array(
				'title'       => __( 'Test mode', 'woo-rave' ),
				'label'       => __( 'Enable Test Mode', 'woo-rave' ),
				'type'        => 'checkbox',
				'description' => __( 'Test mode enables you to test payments before going live. <br />Once you are live uncheck this.', 'woo-rave' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'test_public_key' => array(
				'title'       => __( 'Test Public Key', 'woo-rave' ),
				'type'        => 'text',
				'description' => __( 'Required: Enter your Test Public Key here.', 'woo-rave' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_secret_key' => array(
				'title'       => __( 'Test Secret Key', 'woo-rave' ),
				'type'        => 'text',
				'description' => __( 'Required: Enter your Test Secret Key here', 'woo-rave' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'live_public_key' => array(
				'title'       => __( 'Live Public Key', 'woo-rave' ),
				'type'        => 'text',
				'description' => __( 'Required: Enter your Live Public Key here.', 'woo-rave' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'live_secret_key' => array(
				'title'       => __( 'Live Secret Key', 'woo-rave' ),
				'type'        => 'text',
				'description' => __( 'Required: Enter your Live Secret Key here.', 'woo-rave' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'custom_title'    => array(
				'title'       => __( 'Custom Title', 'woo-rave' ),
				'type'        => 'text',
				'description' => __( 'Optional: Text to be displayed as the title of the payment modal.', 'woo-rave' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'custom_desc'     => array(
				'title'       => __( 'Custom Description', 'woo-rave' ),
				'type'        => 'text',
				'description' => __( 'Optional: Text to be displayed as a short modal description.', 'woo-rave' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'custom_logo'     => array(
				'title'       => __( 'Custom Logo', 'woo-rave' ),
				'type'        => 'text',
				'description' => __( 'Optional: Enter the link to a image to be displayed on the payment popup. Preferably a square image.', 'woo-rave' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'saved_cards'     => array(
				'title'       => __( 'Saved Cards', 'woo-rave' ),
				'label'       => __( 'Enable Payment via Saved Cards', 'woo-rave' ),
				'type'        => 'checkbox',
				'description' => __( 'If enabled, users will be able to pay with a saved card during checkout. Card details are saved on Rave servers, not on your store.<br>Note that you need to have a valid SSL certificate installed.', 'woo-rave' ),
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

		if ( ! is_ssl() ){
			return;
		}

		if ( $this->supports( 'tokenization' ) && is_checkout() && $this->saved_cards && is_user_logged_in() ) {
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

		$order_key = urldecode( sanitize_text_field( $_GET['key'] ) );
		$order_id  = absint( get_query_var( 'order-pay' ) );

		$order = wc_get_order( $order_id );

		$payment_method = method_exists( $order, 'get_payment_method' ) ? $order->get_payment_method() : $order->payment_method;

		if ( $this->id !== $payment_method ) {
			return;
		}

		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'tbz_rave', 'https://api.ravepay.co/flwv3-pug/getpaidx/api/flwpbf-inline.js', array( 'jquery' ), TBZ_WC_RAVE_VERSION, false );
		wp_enqueue_script( 'tbz_wc_rave', plugins_url( 'assets/js/rave' . $suffix . '.js', TBZ_WC_RAVE_MAIN_FILE ), array( 'jquery', 'tbz_rave' ), TBZ_WC_RAVE_VERSION, false );

		$rave_params = array(
			'public_key' => $this->public_key,
		);

		if ( is_checkout_pay_page() && get_query_var( 'order-pay' ) ) {

			$email         = method_exists( $order, 'get_billing_email' ) ? $order->get_billing_email() : $order->billing_email;
			$billing_phone = method_exists( $order, 'get_billing_phone' ) ? $order->get_billing_phone() : $order->billing_phone;
			$first_name    = method_exists( $order, 'get_billing_first_name' ) ? $order->get_billing_first_name() : $order->billing_first_name;
			$last_name     = method_exists( $order, 'get_billing_last_name' ) ? $order->get_billing_last_name() : $order->billing_last_name;

			$amount = $order->get_total();

			$txnref = 'WC|' . $order_id . '|' . time();

			$the_order_id  = method_exists( $order, 'get_id' ) ? $order->get_id() : $order->id;
			$the_order_key = method_exists( $order, 'get_order_key' ) ? $order->get_order_key() : $order->order_key;

			$base_location = wc_get_base_location();
			$country       = $base_location['country'];
			$currency      = get_woocommerce_currency();

			$meta = array();

			if ( $the_order_id == $order_id && $the_order_key == $order_key ) {

				$meta[] = array(
					'metaname'  => 'Order ID',
					'metavalue' => $order_id,
				);

				$rave_params['txref']               = $txnref;
				$rave_params['amount']              = $amount;
				$rave_params['currency']            = get_woocommerce_currency();
				$rave_params['customer_email']      = $email;
				$rave_params['customer_phone']      = $billing_phone;
				$rave_params['customer_first_name'] = $first_name;
				$rave_params['customer_last_name']  = $last_name;
				$rave_params['custom_title']        = $this->custom_title;
				$rave_params['custom_desc']         = $this->custom_desc;
				$rave_params['custom_logo']         = $this->custom_logo;
				$rave_params['country']             = $this->get_route_country( $currency, $country );
				$rave_params['meta']                = $meta;
				$rave_params['hash']                = $this->generate_hash( $rave_params );

				update_post_meta( $order_id, '_rave_txn_ref', $txnref );

			}
		}

		wp_localize_script( 'tbz_wc_rave', 'tbz_wc_rave_params', $rave_params );

	}

	/**
	 * Generate integrity hash
	 */
	public function generate_hash( $params ) {

		$hashed_payload = $params['public_key'];

		unset( $params['public_key'] );
		unset( $params['meta'] );

		ksort( $params );

		foreach ( $params as $key => $value ) {
			$hashed_payload .= $value;
		}

		$hashed_payload .= $this->secret_key;

		$hashed_payload = html_entity_decode( $hashed_payload );

		$hash = hash( 'sha256', $hashed_payload );

		return $hash;
	}

	/**
	 * Get route country.
	 *
	 * @param string $currency     WooCommerce Store Currency Code
	 * @param string $country_code WooCommerce Store Country Code
	 *
	 * @return string Country code.
	 */
	public function get_route_country( $currency, $country_code ) {

		switch ( $currency ) {

			case 'NGN':
				$route_country = 'NG';
				break;

			case 'GHS':
				$route_country = 'GH';
				break;

			case 'KES':
				$route_country = 'KE';
				break;

			case 'RWF':
				$route_country = 'RW';
				break;

			case 'TZS':
				$route_country = 'TZ';
				break;

			case 'UGX':
				$route_country = 'UG';
				break;

			case 'ZAR':
				$route_country = 'ZA';
				break;

			case 'ZMW':
				$route_country = 'ZM';
				break;

			default:
				$route_country = $country_code;
				break;
		}

		return $route_country;
	}

	/**
	 * Load admin scripts
	 */
	public function admin_scripts() {

		if ( 'woocommerce_page_wc-settings' !== get_current_screen()->id ) {
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_script( 'tbz_wc_rave_admin', plugins_url( 'assets/js/rave-admin' . $suffix . '.js', TBZ_WC_RAVE_MAIN_FILE ), array(), TBZ_WC_RAVE_VERSION, true );

	}

	/**
	 * Displays the Rave fee
	 *
	 * @since 2.1.0
	 *
	 * @param int $order_id WC Order ID.
	 */
	public function display_rave_fee( $order_id ) {

		$order = wc_get_order( $order_id );

		if ( $this->is_wc_lt( '3.0' ) ) {
			$fee      = get_post_meta( $order_id, '_rave_fee', true );
			$currency = get_post_meta( $order_id, '_rave_currency', true );
		} else {
			$fee      = $order->get_meta( '_rave_fee', true );
			$currency = $order->get_meta( '_rave_currency', true );
		}

		if ( ! $fee || ! $currency ) {
			return;
		}

		?>

		<tr>
			<td class="label rave-fee">
				<?php echo wc_help_tip( __( 'This represents the fee Rave collects for the transaction.', 'woo-rave' ) ); ?>
				<?php esc_html_e( __( 'Rave Fee:', 'woo-rave' ) ); ?>
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
	 * @since 2.1.0
	 *
	 * @param int $order_id WC Order ID.
	 */
	public function display_order_payout( $order_id ) {

		$order = wc_get_order( $order_id );

		if ( $this->is_wc_lt( '3.0' ) ) {
			$net      = get_post_meta( $order_id, '_rave_net', true );
			$currency = get_post_meta( $order_id, '_rave_currency', true );
		} else {
			$net      = $order->get_meta( '_rave_net', true );
			$currency = $order->get_meta( '_rave_currency', true );
		}

		if ( ! $net || ! $currency ) {
			return;
		}

		?>

		<tr>
			<td class="label rave-payout">
				<?php $message = __( 'This represents the net total that will be credited to your bank account for this order.', 'woo-rave' ); ?>
				<?php if ( $net >= $order->get_total() ) : ?>
					<?php $message .= __( ' Rave transaction fees was passed to the customer.', 'woo-rave' ); ?>
				<?php endif; ?>
				<?php echo wc_help_tip( $message ); ?>
				<?php esc_html_e( __( 'Rave Payout:', 'woo-rave' ) ); ?>
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
			$token    = WC_Payment_Tokens::get( $token_id );

			if ( $token->get_user_id() !== get_current_user_id() ) {

				wc_add_notice( __( 'Invalid token ID', 'woo-rave' ), 'error' );

				return;

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

			if ( is_user_logged_in() && isset( $_POST['wc-tbz_rave-new-payment-method'] ) && true === (bool) $_POST['wc-tbz_rave-new-payment-method'] && $this->saved_cards ) {

				update_post_meta( $order_id, '_wc_rave_save_card', true );

			}

			$order = wc_get_order( $order_id );

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

			$order_amount = method_exists( $order, 'get_total' ) ? $order->get_total() : $order->order_total;

			$order_currency = method_exists( $order, 'get_currency' ) ? $order->get_currency() : $order->get_order_currency();

			$first_name = method_exists( $order, 'get_billing_first_name' ) ? $order->get_billing_first_name() : $order->billing_first_name;
			$last_name  = method_exists( $order, 'get_billing_last_name' ) ? $order->get_billing_last_name() : $order->billing_last_name;
			$email      = method_exists( $order, 'get_billing_email' ) ? $order->get_billing_email() : $order->billing_email;

			$ip_address = $order->get_customer_ip_address();

			$headers = array(
				'Content-Type' => 'application/json',
			);

			if ( strpos( $token, '##' ) !== false ) {
				$payment_token = explode( '##', $token );
				$token_code    = $payment_token[0];
			} else {
				$token_code = $token;
			}

			$body = array(
				'SECKEY'    => $this->secret_key,
				'token'     => $token_code,
				'currency'  => $order_currency,
				'amount'    => $order_amount,
				'email'     => $email,
				'firstname' => $first_name,
				'lastname'  => $last_name,
				'IP'        => $ip_address,
				'txRef'     => $txnref,
				'meta'      => array(
					array(
						'metaname'  => 'Order ID',
						'metavalue' => $order_id,
					),
				),
			);

			$args = array(
				'headers' => $headers,
				'body'    => json_encode( $body ),
				'timeout' => 60,
			);

			$request = wp_remote_post( $this->tokenized_url, $args );

			if ( ! is_wp_error( $request ) && 200 == wp_remote_retrieve_response_code( $request ) ) {

				$response = json_decode( wp_remote_retrieve_body( $request ) );

				$status = $response->status;

				$response_code = $response->data->chargeResponseCode;

				$payment_currency = $response->data->currency;

				$gateway_symbol = get_woocommerce_currency_symbol( $payment_currency );

				$valid_response_code = array( '0', '00' );

				if ( 'success' === $status && in_array( $response_code, $valid_response_code ) ) {

					if ( in_array( $order->get_status(), array( 'processing', 'completed', 'on-hold' ) ) ) {

						wp_redirect( $this->get_return_url( $order ) );

						exit;

					}

					$order_currency = $order->get_currency();

					$currency_symbol = get_woocommerce_currency_symbol( $order_currency );

					$order_total = $order->get_total();

					$amount_paid = $response->data->amount;

					$txn_ref     = $response->data->txRef;
					$payment_ref = $response->data->flwRef;

					$amount_charged = $response->data->charged_amount;

					$rave_fee = $response->data->appfee;
					$rave_net = $amount_charged - $rave_fee;

					if ( $this->is_wc_lt( '3.0' ) ) {
						update_post_meta( $order_id, '_rave_fee', $rave_fee );
						update_post_meta( $order_id, '_rave_net', $rave_net );
						update_post_meta( $order_id, '_rave_currency', $payment_currency );
					} else {
						$order->update_meta_data( '_rave_fee', $rave_fee );
						$order->update_meta_data( '_rave_net', $rave_net );
						$order->update_meta_data( '_rave_currency', $payment_currency );
					}

					// check if the amount paid is equal to the order amount.
					if ( $amount_paid < $order_total ) {

						$order->update_status( 'on-hold', '' );

						update_post_meta( $order_id, '_transaction_id', $txn_ref );

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

							$order->update_status( 'on-hold', '' );

							update_post_meta( $order_id, '_transaction_id', $txn_ref );

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

							$order->add_order_note( sprintf( 'Payment via Rave successful (<strong>Transaction ID:</strong> %s | <strong>Payment Reference:</strong> %s)', $txn_ref, $payment_ref ) );

						}
					}

					$this->save_subscription_payment_token( $order_id, $token_code );

					wc_empty_cart();

					return true;

				} else {

					$order = wc_get_order( $order_id );

					$order->update_status( 'failed', 'Payment was declined by Rave.' );

					wc_add_notice( 'Payment Failed. Try again.', 'error' );

					return false;

				}
			} else {

				wc_add_notice( 'Payment failed using the saved card. Kindly use another payment method.', 'error' );

				return false;

			}
		} else {

			wc_add_notice( 'Payment Failed.', 'error' );

			return false;

		}

	}

	/**
	 * Show new card can only be added when placing an order notice
	 */
	public function add_payment_method() {

		wc_add_notice( __( 'You can only add a new card when placing an order.', 'woo-rave' ), 'error' );

		return;

	}

	/**
	 * Displays the payment page
	 */
	public function receipt_page( $order_id ) {

		$order = wc_get_order( $order_id );

		echo '<p>Thank you for your order, please click the button below to pay with Rave.</p>';

		echo '<div id="tbz_wc_rave_form"><form id="order_review" method="post" action="' . WC()->api_request_url( 'Tbz_WC_Rave_Gateway' ) . '"></form><button class="button alt" id="tbz-rave-wc-payment-button">Pay Now</button> <a class="button cancel" href="' . esc_url( $order->get_cancel_order_url() ) . '">Cancel order &amp; restore cart</a></div>
			';

	}

	/**
	 * Verify Rave payment
	 */
	public function verify_rave_transaction() {

		@ob_clean();

		if ( isset( $_REQUEST['tbz_wc_rave_txnref'] ) ) {

			$rave_verify_url = $this->query_url;

			$headers = array(
				'Content-Type' => 'application/json',
			);

			$body = array(
				'flwref'    => $_REQUEST['tbz_wc_rave_txnref'],
				'SECKEY'    => $this->secret_key,
				'normalize' => '1',
			);

			$args = array(
				'headers' => $headers,
				'body'    => json_encode( $body ),
				'timeout' => 60,
			);

			$request = wp_remote_post( $rave_verify_url, $args );

			if ( ! is_wp_error( $request ) && 200 == wp_remote_retrieve_response_code( $request ) ) {

				$response = json_decode( wp_remote_retrieve_body( $request ) );

				$status              = $response->status;
				$response_code       = $response->data->chargecode;
				$payment_currency    = $response->data->currency;
				$gateway_symbol      = get_woocommerce_currency_symbol( $payment_currency );
				$valid_response_code = array( '0', '00' );
				$order_details       = explode( '|', $response->data->txref );

				if ( 'success' === $status && in_array( $response_code, $valid_response_code ) ) {

					$order_id = (int) $order_details[1];

					$order = wc_get_order( $order_id );

					if ( in_array( $order->get_status(), array( 'processing', 'completed', 'on-hold' ) ) ) {

						wp_redirect( $this->get_return_url( $order ) );

						exit;

					}

					$order_currency = $order->get_currency();

					$currency_symbol = get_woocommerce_currency_symbol( $order_currency );

					$order_total = $order->get_total();

					$amount_paid    = $response->data->amount;
					$txn_ref        = $response->data->txref;
					$payment_ref    = $response->data->flwref;
					$amount_charged = $response->data->chargedamount;
					$rave_fee       = $response->data->appfee;

					$rave_net = $amount_charged - $rave_fee;

					if ( $this->is_wc_lt( '3.0' ) ) {
						update_post_meta( $order_id, '_rave_fee', $rave_fee );
						update_post_meta( $order_id, '_rave_net', $rave_net );
						update_post_meta( $order_id, '_rave_currency', $payment_currency );
					} else {
						$order->update_meta_data( '_rave_fee', $rave_fee );
						$order->update_meta_data( '_rave_net', $rave_net );
						$order->update_meta_data( '_rave_currency', $payment_currency );
					}

					// check if the amount paid is equal to the order amount.
					if ( $amount_paid < $order_total ) {

						$order->update_status( 'on-hold', '' );

						update_post_meta( $order_id, '_transaction_id', $txn_ref );

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

							$order->update_status( 'on-hold', '' );

							update_post_meta( $order_id, '_transaction_id', $txn_ref );

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

							$order->add_order_note( sprintf( 'Payment via Rave successful (<strong>Transaction ID:</strong> %s | <strong>Payment Reference:</strong> %s)', $txn_ref, $payment_ref ) );

						}
					}

					$this->save_card_details( $response, $order->get_user_id(), $order_id );

					wc_empty_cart();

				} else {

					$order_id = (int) $order_details[1];

					$order = wc_get_order( $order_id );

					$order->update_status( 'failed', 'Payment was declined by Rave.' );

				}

				wp_redirect( $this->get_return_url( $order ) );

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

		if ( ( strtoupper( $_SERVER['REQUEST_METHOD'] ) != 'POST' ) ) {
			exit;
		}

		sleep( 10 );

		$body = @file_get_contents( 'php://input' );

		if ( $this->isJSON( $body ) ) {
			$_POST = (array) json_decode( $body );
		}

		if ( ! isset( $_POST['flwRef'] ) ) {
			exit;
		}

		$headers = array(
			'Content-Type' => 'application/json',
		);

		$body = array(
			'flwref'    => $_POST['flwRef'],
			'SECKEY'    => $this->secret_key,
			'normalize' => '1',
		);

		$args = array(
			'headers' => $headers,
			'body'    => json_encode( $body ),
			'timeout' => 60,
		);

		$request = wp_remote_post( $this->query_url, $args );

		if ( ! is_wp_error( $request ) && 200 == wp_remote_retrieve_response_code( $request ) ) {

			$response = json_decode( wp_remote_retrieve_body( $request ) );

			$status              = $response->status;
			$response_code       = $response->data->chargecode;
			$payment_currency    = $response->data->currency;
			$gateway_symbol      = get_woocommerce_currency_symbol( $payment_currency );
			$valid_response_code = array( '0', '00' );

			if ( 'success' === $status && in_array( $response_code, $valid_response_code ) ) {

				$order_details = explode( '|', $response->data->txref );

				$order_id = (int) $order_details[1];

				$order = wc_get_order( $order_id );

				$rave_txn_ref = get_post_meta( $order_id, '_rave_txn_ref', true );

				if ( $response->data->txref != $rave_txn_ref ) {
					exit;
				}

				http_response_code( 200 );

				if ( in_array( $order->get_status(), array( 'processing', 'completed', 'on-hold' ) ) ) {
					exit;
				}

				$order_currency = $order->get_currency();

				$currency_symbol = get_woocommerce_currency_symbol( $order_currency );

				$order_total = $order->get_total();

				$amount_paid    = $response->data->amount;
				$txn_ref        = $response->data->txref;
				$payment_ref    = $response->data->flwref;
				$amount_charged = $response->data->chargedamount;
				$rave_fee       = $response->data->appfee;

				$rave_net = $amount_charged - $rave_fee;

				if ( $this->is_wc_lt( '3.0' ) ) {
					update_post_meta( $order_id, '_rave_fee', $rave_fee );
					update_post_meta( $order_id, '_rave_net', $rave_net );
					update_post_meta( $order_id, '_rave_currency', $payment_currency );
				} else {
					$order->update_meta_data( '_rave_fee', $rave_fee );
					$order->update_meta_data( '_rave_net', $rave_net );
					$order->update_meta_data( '_rave_currency', $payment_currency );
				}

				// check if the amount paid is equal to the order amount.
				if ( $amount_paid < $order_total ) {

					$order->update_status( 'on-hold', '' );

					update_post_meta( $order_id, '_transaction_id', $txn_ref );

					$notice = 'Thank you for shopping with us.<br />Your payment was successful, but the amount paid is not the same as the total order amount.<br />Your order is currently on-hold.<br />Kindly contact us for more information regarding your order and payment status.';

					// Add Customer Order Note
					$order->add_order_note( $notice, 1 );

					// Add Admin Order Note.
					$order->add_order_note( '<strong>Look into this order</strong><br />This order is currently on hold.<br />Reason: Amount paid is less than the total order amount.<br />Amount Paid was <strong>' . $currency_symbol . $amount_paid . '</strong> while the total order amount is <strong>' . $currency_symbol . $order_total . '</strong><br /><strong>Transaction ID:</strong> ' . $txn_ref . ' | <strong>Payment Reference:</strong> ' . $payment_ref );

					wc_reduce_stock_levels( $order_id );

				} else {

					if ( $payment_currency !== $order_currency ) {

						$order->update_status( 'on-hold', '' );

						update_post_meta( $order_id, '_transaction_id', $txn_ref );

						$notice = 'Thank you for shopping with us.<br />Your payment was successful, but the payment currency is different from the order currency.<br />Your order is currently on-hold.<br />Kindly contact us for more information regarding your order and payment status.';

						// Add Customer Order Note.
						$order->add_order_note( $notice, 1 );

						// Add Admin Order Note.
						$order->add_order_note( '<strong>Look into this order</strong><br />This order is currently on hold.<br />Reason: Order currency is different from the payment currency.<br /> Order Currency is <strong>' . $order_currency . ' (' . $currency_symbol . ')</strong> while the payment currency is <strong>' . $payment_currency . ' (' . $gateway_symbol . ')</strong><br /><strong>Transaction ID:</strong> ' . $txn_ref . ' | <strong>Payment Reference:</strong> ' . $payment_ref );

						wc_reduce_stock_levels( $order_id );

					} else {

						$order->payment_complete( $txn_ref );

						$order->add_order_note( sprintf( 'Payment via Rave successful (<strong>Transaction ID:</strong> %s | <strong>Payment Reference:</strong> %s)', $txn_ref, $payment_ref ) );

					}
				}

				$this->save_card_details( $response, $order->get_user_id(), $order_id );

				wc_empty_cart();

			} else {

				$order_details = explode( '|', $response->data->tx_ref );

				$order_id = (int) $order_details[1];

				$order = wc_get_order( $order_id );

				$order->update_status( 'failed', 'Payment was declined by Rave.' );

			}
		}

		exit;
	}

	/**
	 * Save Customer Card Details.
	 */
	public function save_card_details( $rave_response, $user_id, $order_id ) {

		if ( isset( $rave_response->data->card->card_tokens[0]->embedtoken ) ) {
			$token_code = $rave_response->data->card->card_tokens[0]->embedtoken;
		} else {
			$token_code = '';
		}

		$this->save_subscription_payment_token( $order_id, $token_code );

		$save_card = get_post_meta( $order_id, '_wc_rave_save_card', true );

		if ( isset( $rave_response->data->card ) && $user_id && $this->saved_cards && $save_card && ! empty( $token_code ) ) {

			$last4 = $rave_response->data->card->last4digits;

			if ( 4 !== strlen( $rave_response->data->card->expiryyear ) ) {
				$exp_year = substr( date( 'Y' ), 0, 2 ) . $rave_response->data->card->expiryyear;
			} else {
				$exp_year = $rave_response->data->card->expiryyear;
			}

			$brand     = $rave_response->data->card->brand;
			$exp_month = $rave_response->data->card->expirymonth;

			$token = new WC_Payment_Token_CC();
			$token->set_token( $token_code );
			$token->set_gateway_id( 'tbz_rave' );
			$token->set_card_type( $brand );
			$token->set_last4( $last4 );
			$token->set_expiry_month( $exp_month );
			$token->set_expiry_year( $exp_year );
			$token->set_user_id( $user_id );
			$token->save();

		}

		delete_post_meta( $order_id, '_wc_rave_save_card' );

	}

	/**
	 * Save payment token to the order for automatic renewal for further subscription payment
	 */
	public function save_subscription_payment_token( $order_id, $payment_token ) {

		if ( ! function_exists( 'wcs_order_contains_subscription' ) ) {

			return;

		}

		if ( $this->order_contains_subscription( $order_id ) && ! empty( $payment_token ) ) {

			// Also store it on the subscriptions being purchased or paid for in the order.
			if ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order_id ) ) {

				$subscriptions = wcs_get_subscriptions_for_order( $order_id );

			} elseif ( function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( $order_id ) ) {

				$subscriptions = wcs_get_subscriptions_for_renewal_order( $order_id );

			} else {

				$subscriptions = array();

			}

			foreach ( $subscriptions as $subscription ) {

				$subscription_id = $subscription->get_id();

				update_post_meta( $subscription_id, '_tbz_rave_wc_token', $payment_token );

			}
		}

	}

	/**
	 * @param $string
	 *
	 * @return bool
	 */
	public function isJSON( $string ) {
		return is_string( $string ) && is_array( json_decode( $string, true ) ) ? true : false;
	}

	/**
	 * Checks if WC version is less than passed in version.
	 *
	 * @since 2.1.0
	 * @param string $version Version to check against.
	 * @return bool
	 */
	public function is_wc_lt( $version ) {
		return version_compare( WC_VERSION, $version, '<' );
	}

}
