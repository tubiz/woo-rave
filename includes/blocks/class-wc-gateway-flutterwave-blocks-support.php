<?php

namespace Tubiz\Flutterwave_Woocommerce;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Flutterwave Blocks integration
 *
 * @since 2.4.0
 */
final class WC_Gateway_Flutterwave_Blocks_Support extends AbstractPaymentMethodType {

	/**
	 * The gateway instance.
	 *
	 * @var WC_Gateway_Flutterwave_Blocks_Support
	 */
	private $gateway;

	/**
	 * Payment method name/id/slug.
	 *
	 * @var string
	 */
	protected $name = 'tbz_rave';

	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_tbz_rave_settings', array() );
		$gateways       = \WC()->payment_gateways->payment_gateways();
		$this->gateway  = $gateways[ $this->name ];
	}

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active() {
		return $this->gateway->is_available();
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		$script_asset_path = plugins_url( '/assets/js/blocks/frontend/blocks.asset.php', TBZ_WC_FLUTTERWAVE_MAIN_FILE );
		$script_asset      = file_exists( $script_asset_path )
			? require $script_asset_path
			: array(
				'dependencies' => array(),
				'version'      => TBZ_WC_FLUTTERWAVE_VERSION,
			);

		$script_url = plugins_url( '/assets/js/blocks/frontend/blocks.js', TBZ_WC_FLUTTERWAVE_MAIN_FILE );

		wp_register_script(
			'wc-tbz-flutterwave-blocks',
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'wc-tbz-flutterwave-blocks', 'woo-rave' );
		}

		return array( 'wc-tbz-flutterwave-blocks' );
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		$payment_method_logo = \WC_HTTPS::force_https_url( plugins_url( '/assets/images/powered-by-rave.png', TBZ_WC_FLUTTERWAVE_MAIN_FILE ) );

		return array(
			'title'              => $this->get_setting( 'title' ),
			'description'        => $this->get_setting( 'description' ),
			'checkout_image_url' => $payment_method_logo,
			'supports'           => array_filter( $this->gateway->supports, array( $this->gateway, 'supports' ) ),
		);
	}
}
