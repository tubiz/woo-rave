<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tbz_WC_Rave_Subscription extends Tbz_WC_Rave_Gateway {

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
	 * @param WC_Order $renewal_order The order created for the customer to resubscribe to the old expired/cancelled subscription
	 *
	 * @return WC_Order Renewal order
	 */
	public function delete_renewal_meta( $renewal_order ) {

		if ( $this->is_wc_lt( '3.0' ) ) {
			$order_id = $renewal_order->id;
		} else {
			$order_id = $renewal_order->get_id();
		}

		delete_post_meta( $order_id, '_rave_fee' );
		delete_post_meta( $order_id, '_rave_net' );
		delete_post_meta( $order_id, '_rave_currency' );

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

		} else {

			return parent::process_payment( $order_id );

		}

	}

	/**
	 * Process a subscription renewal
	 */
	public function scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {

		$response = $this->process_subscription_payment( $renewal_order, $amount_to_charge );

		if ( is_wp_error( $response ) ) {

			$renewal_order->update_status( 'failed', sprintf( 'Rave Transaction Failed (%s)', $response->get_error_message() ) );

		}

	}

	/**
	 * Process a subscription renewal payment
	 */
	public function process_subscription_payment( $order = '', $amount = 0 ) {

		$order_id = method_exists( $order, 'get_id' ) ? $order->get_id() : $order->id;

		$auth_code = get_post_meta( $order_id, '_tbz_rave_wc_token', true );

		if ( $auth_code ) {

			$headers = array(
				'Content-Type' => 'application/json',
			);

			$txnref = 'WC|' . $order_id . '|' . uniqid();

			$order_currency = method_exists( $order, 'get_currency' ) ? $order->get_currency() : $order->get_order_currency();

			$first_name = method_exists( $order, 'get_billing_first_name' ) ? $order->get_billing_first_name() : $order->billing_first_name;
			$last_name  = method_exists( $order, 'get_billing_last_name' ) ? $order->get_billing_last_name() : $order->billing_last_name;
			$email      = method_exists( $order, 'get_billing_email' ) ? $order->get_billing_email() : $order->billing_email;

			$ip_address = $order->get_customer_ip_address();

			if ( strpos( $auth_code, '##' ) !== false ) {
				$payment_token = explode( '##', $auth_code );
				$token_code    = $payment_token[0];
			} else {
				$token_code = $auth_code;
			}

			$body = array(
				'SECKEY'    => $this->secret_key,
				'token'     => $token_code,
				'currency'  => $order_currency,
				'amount'    => $amount,
				'email'     => $email,
				'firstname' => $first_name,
				'lastname'  => $last_name,
				'IP'        => $ip_address,
				'txRef'     => $txnref,
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

				$valid_response_code = array( '0', '00' );

				if ( 'success' === $status && in_array( $response_code, $valid_response_code ) ) {

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

					$order->payment_complete( $txn_ref );

					$message = ( sprintf( 'Payment via Rave successful (<strong>Transaction Reference:</strong> %s | <strong>Payment Reference:</strong> %s)', $txn_ref, $payment_ref ) );

					$order->add_order_note( $message );

					return true;

				} else {

					return new WP_Error( 'rave_error', 'Rave payment failed. ' . $response->message );

				}
			}
		}

		return new WP_Error( 'rave_error', 'This subscription can\'t be renewed automatically. The customer will have to login to his account to renew his subscription' );

	}

}
