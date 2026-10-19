<?php
/**
 * Checkout Blocks support.
 *
 * @package AEGISCoinbaseForWooCommerce
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Class ACW_Blocks_Support
 */
final class ACW_Blocks_Support extends AbstractPaymentMethodType {

	protected $name = 'aegis_coinbase';

	public function initialize(): void {
		$this->settings = get_option( 'woocommerce_aegis_coinbase_settings', array() );
	}

	public function is_active(): bool {
		return ! empty( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'];
	}

	public function get_payment_method_script_handles(): array {
		wp_register_script(
			'acw-blocks',
			ACW_PLUGIN_URL . 'assets/js/blocks.js',
			array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities', 'wp-i18n' ),
			ACW_VERSION,
			true
		);
		return array( 'acw-blocks' );
	}

	public function get_payment_method_data(): array {
		$mode    = $this->settings['mode'] ?? 'personal';
		$cryptos = (array) ( $this->settings['enabled_cryptos'] ?? array( 'USDC' ) );

		return array(
			'title'       => $this->settings['title'] ?? __( 'Cryptocurrency (AEGIS Coinbase)', 'aegis-coinbase' ),
			'description' => $this->settings['description'] ?? '',
			'supports'    => array( 'products' ),
			'mode'        => $mode,
			'cryptos'     => $cryptos,
		);
	}
}
