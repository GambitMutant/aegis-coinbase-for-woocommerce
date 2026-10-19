<?php
/**
 * Uninstall cleanup.
 *
 * @package AEGISCoinbaseForWooCommerce
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Optionally clean options. Leave order meta intact for historical orders.
delete_option( 'woocommerce_aegis_coinbase_settings' );

wp_clear_scheduled_hook( 'acw_check_pending_payments' );
