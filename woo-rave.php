<?php
/*
	Plugin Name:			WooCommerce Flutterwave Payment Gateway
	Plugin URI: 			https://flutterwave.com
	Description:            WooCommerce payment gateway for Flutterwave
	Version:                2.4.0
	Author: 				Tunbosun Ayinla
	Author URI: 			https://bosun.me
	License:        		GPL-2.0+
	License URI:    		http://www.gnu.org/licenses/gpl-2.0.txt
	WC requires at least:   8.0
	WC tested up to:        8.6
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TBZ_WC_FLUTTERWAVE_MAIN_FILE', __FILE__ );

define( 'TBZ_WC_FLUTTERWAVE_URL', untrailingslashit( plugins_url( '/', __FILE__ ) ) );

define( 'TBZ_WC_FLUTTERWAVE_VERSION', '2.4.0' );

/**
 * Initialize Flutterwave WooCommerce payment gateway.
 */
function tbz_wc_flutterwave_init() {

	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	require_once __DIR__ . '/includes/class-wc-gateway-flutterwave.php';

	if ( class_exists( 'WC_Subscriptions_Order' ) && class_exists( 'WC_Payment_Gateway_CC' ) ) {
		require_once __DIR__ . '/includes/class-wc-gateway-flutterwave-subscription.php';
	}

	add_filter( 'woocommerce_payment_gateways', 'tbz_wc_add_flutterwave_gateway' );
}
add_action( 'plugins_loaded', 'tbz_wc_flutterwave_init' );

/**
* Add Settings link to the plugin entry in the plugins menu
**/
function tbz_wc_flutterwave_plugin_action_links( $links ) {

	$settings_link = array(
		'settings' => '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=tbz_rave' ) . '" title="View Settings">Settings</a>',
	);

	return array_merge( $settings_link, $links );

}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'tbz_wc_flutterwave_plugin_action_links' );

/**
* Add Flutterwave Gateway to WC
**/
function tbz_wc_add_flutterwave_gateway( $methods ) {

	if ( class_exists( 'WC_Subscriptions_Order' ) && class_exists( 'WC_Payment_Gateway_CC' ) ) {
		$methods[] = Tubiz\Flutterwave_Woocommerce\WC_Gateway_Flutterwave_Subscription::class;
	} else {
		$methods[] = Tubiz\Flutterwave_Woocommerce\WC_Gateway_Flutterwave::class;
	}

	return $methods;

}

/**
* Display the test mode notice
**/
function tbz_wc_flutterwave_testmode_notice() {

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$rave_settings = get_option( 'woocommerce_tbz_rave_settings' );
	$test_mode     = $rave_settings['testmode'] ?? '';

	if ( 'yes' === $test_mode ) {

		$flutterwave_admin_url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=tbz_rave' );

		/* translators: 1. Flutterwave settings page URL. */
		echo '<div class="error"><p>' . sprintf( __( 'Flutterwave test mode is still enabled, Click <strong><a href="%s">here</a></strong> to disable it when you want to start accepting live payment on your site.', 'woo-rave' ), esc_url( $flutterwave_admin_url ) ) . '</p></div>';
	}
}
add_action( 'admin_notices', 'tbz_wc_flutterwave_testmode_notice' );

add_action(
	'before_woocommerce_init',
	function() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

/**
 * Registers WooCommerce Blocks integration.
 */
function tbz_wc_flutterwave_gateway_woocommerce_block_support() {
	if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
		require_once 'includes/blocks/class-wc-gateway-flutterwave-blocks-support.php';
		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			static function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
				$payment_method_registry->register( new \Tubiz\Flutterwave_Woocommerce\WC_Gateway_Flutterwave_Blocks_Support() );
			}
		);
	}
}
add_action( 'woocommerce_blocks_loaded', 'tbz_wc_flutterwave_gateway_woocommerce_block_support' );
