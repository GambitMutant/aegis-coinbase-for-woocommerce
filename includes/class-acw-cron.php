<?php
/**
 * Background polling via WP-Cron (Personal mode).
 *
 * @package AEGISCoinbaseForWooCommerce
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ACW_Cron
 */
class ACW_Cron {

	public function __construct() {
		add_action( 'acw_check_pending_payments', array( $this, 'check_pending' ) );
		add_action( 'init', array( $this, 'schedule' ) );
	}

	public function schedule(): void {
		if ( ! wp_next_scheduled( 'acw_check_pending_payments' ) ) {
			wp_schedule_event( time(), 'acw_every_minute', 'acw_check_pending_payments' );
		}
	}

	public function check_pending(): void {
		$orders = wc_get_orders( array(
			'status'         => array( 'on-hold', 'pending' ),
			'payment_method' => 'aegis_coinbase',
			'limit'          => 50,
			'return'         => 'objects',
		) );

		if ( empty( $orders ) ) {
			return;
		}

		$gateway = new WC_Gateway_AEGIS_Coinbase();

		foreach ( $orders as $order ) {
			if ( 'aegis_coinbase' !== $order->get_payment_method() ) {
				continue;
			}
			$gateway->check_and_update_order( $order );
		}
	}
}

add_filter( 'cron_schedules', static function ( array $schedules ): array {
	$schedules['acw_every_minute'] = array(
		'interval' => 60,
		'display'  => __( 'Every Minute (AEGIS Coinbase)', 'aegis-coinbase' ),
	);
	return $schedules;
} );
