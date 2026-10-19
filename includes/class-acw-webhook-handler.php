<?php
/**
 * Webhook handler for Coinbase Business mode.
 *
 * @package AEGISCoinbaseForWooCommerce
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ACW_Webhook_Handler
 */
class ACW_Webhook_Handler {

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			'aegis-coinbase/v1',
			'/webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function handle( WP_REST_Request $request ): WP_REST_Response {
		$raw_body  = $request->get_body();
		$signature = $request->get_header( 'x-hook0-signature' );

		$settings = get_option( 'woocommerce_aegis_coinbase_settings', array() );
		$secret   = $settings['webhook_secret'] ?? '';

		if ( empty( $secret ) || empty( $signature ) ) {
			return new WP_REST_Response( array( 'error' => 'Missing secret or signature' ), 400 );
		}

		if ( ! $this->verify_signature( $raw_body, $signature, $secret, $request->get_headers() ) ) {
			return new WP_REST_Response( array( 'error' => 'Invalid signature' ), 401 );
		}

		$event = json_decode( $raw_body, true );
		if ( ! is_array( $event ) || empty( $event['eventType'] ) ) {
			return new WP_REST_Response( array( 'error' => 'Invalid payload' ), 400 );
		}

		$this->process_event( $event['eventType'], $event['data'] ?? array() );

		return new WP_REST_Response( array( 'status' => 'ok' ), 200 );
	}

	private function verify_signature( string $payload, string $signature_header, string $secret, array $headers ): bool {
		$parts = array();
		foreach ( explode( ',', $signature_header ) as $part ) {
			$kv = explode( '=', trim( $part ), 2 );
			if ( count( $kv ) === 2 ) {
				$parts[ $kv[0] ] = $kv[1];
			}
		}

		$timestamp    = $parts['t'] ?? '';
		$header_names = $parts['h'] ?? '';
		$provided_sig = $parts['v1'] ?? ( $parts['v0'] ?? '' );

		if ( ! $timestamp || ! $provided_sig ) {
			return false;
		}

		$age = abs( time() - (int) $timestamp );
		if ( $age > 300 ) {
			return false;
		}

		$header_values = '';
		if ( $header_names ) {
			$names = explode( ' ', $header_names );
			$vals  = array();
			foreach ( $names as $name ) {
				$lower  = strtolower( $name );
				$vals[] = $headers[ $lower ][0] ?? ( $headers[ $name ][0] ?? '' );
			}
			$header_values = implode( '.', $vals );
		}

		$signed   = $timestamp . '.' . $header_names . '.' . $header_values . '.' . $payload;
		$expected = hash_hmac( 'sha256', $signed, $secret );

		return hash_equals( $expected, $provided_sig );
	}

	private function process_event( string $event_type, array $data ): void {
		$checkout_id = $data['id'] ?? ( $data['checkout']['id'] ?? '' );
		if ( ! $checkout_id ) {
			return;
		}

		$orders = wc_get_orders(
			array(
				'meta_key'   => ACW_Constants::META_CHECKOUT_ID,
				'meta_value' => $checkout_id,
				'limit'      => 1,
				'return'     => 'objects',
			)
		);

		if ( empty( $orders ) ) {
			return;
		}

		/** @var WC_Order $order */
		$order = $orders[0];

		$settings       = get_option( 'woocommerce_aegis_coinbase_settings', array() );
		$success_status = $settings['order_status'] ?? 'processing';

		switch ( $event_type ) {
			case 'checkout.payment.success':
				if ( ! $order->is_paid() ) {
					$order->payment_complete( $checkout_id );
					$order->update_status( $success_status, __( 'Coinbase Business payment confirmed via webhook.', 'aegis-coinbase' ) );
					$order->add_order_note( sprintf( __( 'Checkout %s completed.', 'aegis-coinbase' ), $checkout_id ) );
					ACW_Fee_Collector::collect( $order, 'business' );
				}
				break;

			case 'checkout.payment.failed':
				$order->update_status( 'failed', __( 'Coinbase Business payment failed.', 'aegis-coinbase' ) );
				break;

			case 'checkout.payment.expired':
				if ( $order->has_status( array( 'pending', 'on-hold' ) ) ) {
					$order->update_status( 'cancelled', __( 'Coinbase Business checkout expired.', 'aegis-coinbase' ) );
				}
				break;
		}

		$order->save();
	}
}
