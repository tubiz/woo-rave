<?php

namespace Tubiz\Flutterwave_Woocommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Gateway_Flutterwave_Subscription extends WC_Gateway_Flutterwave {

	/**
	 * Constructor
	 */
	public function __construct() {

		parent::__construct();

		if ( class_exists( 'WC_Subscriptions_Order' ) ) {

			add_action( 'wcs_renewal_order_created', array( $this, 'delete_renewal_meta' ), 10 );
			add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'scheduled_subscription_payment' ), 10, 2 );

		}
	}

	/**
	 * Don't transfer Rave fee/ID meta to renewal orders.
	 *
	 * @param \WC_Order $renewal_order The order created for the customer to resubscribe to the old expired/cancelled subscription
	 *
	 * @return \WC_Order Renewal order
	 */
	public function delete_renewal_meta( $renewal_order ) {

		$renewal_order->delete_meta_data( '_rave_fee' );
		$renewal_order->delete_meta_data( '_rave_net' );
		$renewal_order->delete_meta_data( '_rave_currency' );

		return $renewal_order;
	}

	/**
	 * Check if an order contains a subscription
	 */
	public function order_contains_subscription( $order_id ) {

		return function_exists( 'wcs_order_contains_subscription' ) && ( wcs_order_contains_subscription( $order_id ) || wcs_order_contains_renewal( $order_id ) );

	}

	/**
	 * Process a trial subscription order with 0 total
	 */
	public function process_payment( $order_id ) {

		$order = wc_get_order( $order_id );

		// Check for trial subscription order with 0 total
		if ( $this->order_contains_subscription( $order ) && $order->get_total() == 0 ) {

			$order->payment_complete();

			$order->add_order_note( 'This subscription has a free trial, reason for the 0 amount' );

			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			);

		}

		return parent::process_payment( $order_id );
	}

	/**
	 * Process a subscription renewal
	 */
	public function scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {

		$response = $this->process_subscription_payment( $renewal_order, $amount_to_charge );

		if ( is_wp_error( $response ) ) {

			$renewal_order->update_status( 'failed', sprintf( 'Flutterwave Transaction Failed (%s)', $response->get_error_message() ) );

		}

	}

	/**
	 * Process a subscription renewal payment
	 */
	public function process_subscription_payment( $order = '', $amount = 0 ) {

		$order_id = $order->get_id();

		$auth_code = $order->get_meta( '_tbz_rave_wc_token' );

		if ( $auth_code ) {

			$headers = array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $this->secret_key,
			);

			$txnref = 'WC|' . $order_id . '|' . uniqid();

			$order_currency = $order->get_currency();
			$first_name     = $order->get_billing_first_name();
			$last_name      = $order->get_billing_last_name();
			$email          = $order->get_billing_email();

			$ip_address = $order->get_customer_ip_address();

			if ( strpos( $auth_code, '###' ) !== false ) {
				$payment_token = explode( '###', $auth_code );
				$token_code    = $payment_token[0];
				$email         = $payment_token[1];
			} else {
				$token_code = $auth_code;
			}

			$body = array(
				'token'     => $token_code,
				'email'     => $email,
				'currency'  => $order_currency,
				'amount'    => $amount,
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
				'body'    => json_encode( $body ),
				'timeout' => 60,
			);

			$tokenized_url = 'https://api.flutterwave.com/v3/tokenized-charges';

			$request = wp_remote_post( $tokenized_url, $args );

			if ( ! is_wp_error( $request ) && 200 == wp_remote_retrieve_response_code( $request ) ) {

				$response = json_decode( wp_remote_retrieve_body( $request ) );

				$status           = $response->data->status;
				$payment_currency = $response->data->currency;

				if ( 'successful' === $status ) {

					$txn_ref     = $response->data->tx_ref;
					$payment_ref = $response->data->flw_ref;

					$amount_charged = $response->data->charged_amount;

					$rave_fee = $response->data->app_fee;
					$rave_net = $amount_charged - $rave_fee;

					$order->update_meta_data( '_rave_fee', $rave_fee );
					$order->update_meta_data( '_rave_net', $rave_net );
					$order->update_meta_data( '_rave_currency', $payment_currency );

					$order->payment_complete( $txn_ref );

					$message = ( sprintf( 'Payment via Flutterwave successful (<strong>Transaction Reference:</strong> %s | <strong>Payment Reference:</strong> %s)', $txn_ref, $payment_ref ) );

					$order->add_order_note( $message );

					if ( $this->autocomplete_order ) {
						$order->update_status( 'completed' );
					}

					$order->save();

					return true;
				}

				return new \WP_Error( 'flutterwave_error', 'Payment via Flutterwave failed. ' . $response->message );
			}
		}

		return new \WP_Error( 'flutterwave_error', 'This subscription can\'t be renewed automatically. The customer will have to login to their account to renew their subscription' );
	}
}
