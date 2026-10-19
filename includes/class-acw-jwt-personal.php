<?php
/**
 * Ed25519 JWT authentication for Coinbase App API (personal).
 *
 * @package AEGISCoinbaseForWooCommerce
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ACW_JWT_Personal
 */
class ACW_JWT_Personal {

	private string $key_name;
	private string $secret_b64;

	public function __construct( string $key_name, string $secret_b64 ) {
		$this->key_name   = trim( $key_name );
		$this->secret_b64 = trim( $secret_b64 );
	}

	/**
	 * Generate a short-lived EdDSA JWT.
	 *
	 * @return string|WP_Error
	 */
	public function generate( string $method, string $path ) {
		if ( empty( $this->key_name ) || empty( $this->secret_b64 ) ) {
			return new WP_Error( 'acw_missing_credentials', 'API key name or secret is missing.' );
		}

		if ( ! function_exists( 'sodium_crypto_sign_detached' ) ) {
			return new WP_Error( 'acw_no_sodium', 'PHP sodium extension is required for Ed25519 signing.' );
		}

		$secret = base64_decode( $this->secret_b64, true );
		if ( false === $secret || 64 !== strlen( $secret ) ) {
			return new WP_Error( 'acw_invalid_secret', 'API secret must be a base64 string that decodes to exactly 64 bytes (Ed25519 seed + public key).' );
		}

		$now       = time();
		$path_only = strtok( $path, '?' ) ?: $path;
		$uri       = strtoupper( $method ) . ' api.coinbase.com' . $path_only;

		$header = array(
			'alg'   => 'EdDSA',
			'typ'   => 'JWT',
			'kid'   => $this->key_name,
			'nonce' => bin2hex( random_bytes( 16 ) ),
		);

		$payload = array(
			'sub' => $this->key_name,
			'iss' => 'cdp',
			'nbf' => $now,
			'exp' => $now + 120,
			'uri' => $uri,
		);

		$header_b64    = $this->base64url_encode( wp_json_encode( $header ) );
		$payload_b64   = $this->base64url_encode( wp_json_encode( $payload ) );
		$signing_input = $header_b64 . '.' . $payload_b64;

		$signature = sodium_crypto_sign_detached( $signing_input, $secret );
		if ( false === $signature || 64 !== strlen( $signature ) ) {
			return new WP_Error( 'acw_sign_failed', 'Failed to create Ed25519 signature.' );
		}

		return $signing_input . '.' . $this->base64url_encode( $signature );
	}

	private function base64url_encode( string $data ): string {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}
}
