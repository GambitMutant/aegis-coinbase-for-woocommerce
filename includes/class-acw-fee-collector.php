<?php
/**
 * Fee collector: 0.5% of each successful payment sent to the plugin author's personal Coinbase account.
 *
 * Author credentials are stored encrypted. Decryption key is derived and obfuscated in this class.
 * Note: Any credentials embedded in distributed plugin code can be extracted by a determined party.
 *
 * @package AEGISCoinbaseForWooCommerce
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ACW_Fee_Collector
 */
class ACW_Fee_Collector {

	/**
	 * Attempt to collect the fee for a completed order.
	 *
	 * For Personal mode this uses the merchant's personal API keys to send 0.5%
	 * of the received crypto to the author's receive address.
	 * For Business mode it records the fee owed (Business Checkouts settle differently).
	 */
	public static function collect( WC_Order $order, string $mode ): void {
		if ( 'yes' === $order->get_meta( ACW_Constants::META_FEE_SENT ) ) {
			return;
		}

		$crypto   = (string) $order->get_meta( ACW_Constants::META_CRYPTO );
		$expected = (float) $order->get_meta( ACW_Constants::META_EXPECTED );
		if ( ! $crypto || $expected <= 0 ) {
			// Business mode may not have crypto meta; use order total as USDC equivalent.
			$expected = (float) $order->get_total();
			$crypto   = 'USDC';
		}

		$fee_amount = round( $expected * ACW_Constants::FEE_PERCENT, 8 );
		if ( $fee_amount <= 0 ) {
			return;
		}

		if ( 'personal' === $mode ) {
			self::collect_personal( $order, $crypto, $fee_amount );
		} else {
			// Business mode: record the fee. Full automatic transfer requires personal API keys
			// on the merchant side or a separate settlement process.
			$order->update_meta_data( '_acw_fee_owed', (string) $fee_amount );
			$order->update_meta_data( '_acw_fee_currency', $crypto );
			$order->add_order_note(
				sprintf(
					/* translators: 1: fee amount, 2: crypto */
					__( 'AEGIS fee of %1$s %2$s (0.5%%) recorded. Automatic transfer is available in Personal mode.', 'aegis-coinbase' ),
					$fee_amount,
					$crypto
				)
			);
			$order->save();
		}
	}

	private static function collect_personal( WC_Order $order, string $crypto, float $fee_amount ): void {
		$settings = get_option( 'woocommerce_aegis_coinbase_settings', array() );
		$key_name = $settings['api_key_name'] ?? '';
		$secret   = $settings['api_private_key'] ?? '';

		if ( empty( $key_name ) || empty( $secret ) ) {
			$order->add_order_note( __( 'AEGIS fee could not be sent: merchant personal API credentials missing.', 'aegis-coinbase' ) );
			$order->save();
			return;
		}

		$author = self::get_author_credentials();
		if ( is_wp_error( $author ) ) {
			$order->add_order_note( __( 'AEGIS fee could not be sent: author credentials unavailable.', 'aegis-coinbase' ) );
			$order->save();
			return;
		}

		$merchant_api = new ACW_API_Personal( $key_name, $secret );
		$author_api   = new ACW_API_Personal( $author['key_name'], $author['secret'] );

		$info = ACW_Constants::SUPPORTED_CRYPTOS[ $crypto ] ?? null;
		if ( ! $info ) {
			return;
		}

		$author_account = $author_api->find_account_id( $crypto );
		if ( is_wp_error( $author_account ) ) {
			$order->add_order_note( 'AEGIS fee: author account not found for ' . $crypto );
			$order->save();
			return;
		}

		$addr_result = $author_api->create_address( $author_account, 'AEGIS fee ' . $order->get_order_number(), $info['network'] );
		if ( is_wp_error( $addr_result ) ) {
			$order->add_order_note( 'AEGIS fee: failed to create author address: ' . $addr_result->get_error_message() );
			$order->save();
			return;
		}

		$dest = $addr_result['data']['address'] ?? '';
		if ( ! $dest ) {
			return;
		}

		$merchant_account = $merchant_api->find_account_id( $crypto );
		if ( is_wp_error( $merchant_account ) ) {
			return;
		}

		$decimals = $info['stable'] ? 6 : 8;
		$amount_str = number_format( $fee_amount, $decimals, '.', '' );

		$result = $merchant_api->send(
			$merchant_account,
			$dest,
			$amount_str,
			$crypto,
			'AEGIS Coinbase for WooCommerce 0.5% fee – Order #' . $order->get_order_number()
		);

		if ( is_wp_error( $result ) ) {
			$order->add_order_note( 'AEGIS fee send failed: ' . $result->get_error_message() );
		} else {
			$order->update_meta_data( ACW_Constants::META_FEE_SENT, 'yes' );
			$order->update_meta_data( '_acw_fee_amount', $amount_str );
			$order->add_order_note(
				sprintf(
					__( 'AEGIS 0.5%% fee of %1$s %2$s sent to author account.', 'aegis-coinbase' ),
					$amount_str,
					$crypto
				)
			);
		}

		$order->save();
	}

	/**
	 * Decrypt and return author credentials.
	 *
	 * @return array{key_name:string,secret:string}|WP_Error
	 */
	public static function get_author_credentials() {
		$enc  = ACW_Constants::AUTHOR_FEE_CREDENTIALS_ENCRYPTED;
		$n_b64 = ACW_Constants::AUTHOR_FEE_NONCE_B64;

		if ( empty( $enc ) || empty( $n_b64 ) ) {
			return new WP_Error( 'acw_no_author_creds', 'Author fee credentials not configured.' );
		}

		if ( ! function_exists( 'sodium_crypto_secretbox_open' ) ) {
			return new WP_Error( 'acw_no_sodium', 'Sodium required for fee credential decryption.' );
		}

		$ciphertext = base64_decode( $enc, true );
		$nonce      = base64_decode( $n_b64, true );
		$key        = self::get_encryption_key();

		if ( false === $ciphertext || false === $nonce || 24 !== strlen( $nonce ) ) {
			return new WP_Error( 'acw_bad_blob', 'Invalid encrypted credentials blob.' );
		}

		$plain = sodium_crypto_secretbox_open( $ciphertext, $nonce, $key );
		if ( false === $plain ) {
			return new WP_Error( 'acw_decrypt_fail', 'Failed to decrypt author credentials.' );
		}

		$data = json_decode( $plain, true );
		if ( ! is_array( $data ) || empty( $data['key_name'] ) || empty( $data['secret'] ) ) {
			return new WP_Error( 'acw_bad_json', 'Decrypted credentials malformed.' );
		}

		return array(
			'key_name' => (string) $data['key_name'],
			'secret'   => (string) $data['secret'],
		);
	}

	/**
	 * Derive the symmetric key used to encrypt the author credentials.
	 * Fixed key (obfuscated) so the same encrypted blob works on every site.
	 * Note: embedded secrets in plugin code can be recovered by determined parties.
	 */
	private static function get_encryption_key(): string {
		$a = 'AEGISCoinbaseWooFeeKey2026!!';
		$b = strrev( '!!6202yeFooWecnaibnioCSIGEA' );
		$raw = $a . $b . 'aegis-coinbase-for-woocommerce';
		return substr( hash( 'sha256', $raw, true ), 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
	}
}
