<?php
/**
 * ES256 JWT generator for Coinbase Business CDP API keys.
 *
 * @package AEGISCoinbaseForWooCommerce
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ACW_JWT_Business
 */
class ACW_JWT_Business {

	private string $key_name;
	private string $private_key;

	public function __construct( string $key_name, string $private_key_pem ) {
		$this->key_name    = $key_name;
		$this->private_key = str_replace( array( '\\n', "\r" ), array( "\n", '' ), $private_key_pem );
	}

	/**
	 * @throws Exception
	 */
	public function generate_token( string $method, string $path ): string {
		$private_key = openssl_pkey_get_private( $this->private_key );
		if ( ! $private_key ) {
			throw new Exception( 'Invalid CDP private key: ' . openssl_error_string() );
		}

		$now   = time();
		$nonce = bin2hex( random_bytes( 16 ) );
		$uri   = $method . ' business.coinbase.com' . $path;

		$header = array(
			'alg'   => 'ES256',
			'typ'   => 'JWT',
			'kid'   => $this->key_name,
			'nonce' => $nonce,
		);

		$payload = array(
			'sub' => $this->key_name,
			'iss' => ACW_Constants::JWT_ISSUER,
			'nbf' => $now,
			'exp' => $now + ACW_Constants::JWT_EXPIRY_SECONDS,
			'uri' => $uri,
		);

		$header_b64    = $this->base64url_encode( wp_json_encode( $header ) );
		$payload_b64   = $this->base64url_encode( wp_json_encode( $payload ) );
		$signing_input = $header_b64 . '.' . $payload_b64;

		$signature = '';
		$ok        = openssl_sign( $signing_input, $signature, $private_key, OPENSSL_ALGO_SHA256 );
		if ( ! $ok ) {
			throw new Exception( 'Failed to sign JWT: ' . openssl_error_string() );
		}

		$raw_sig = $this->der_to_raw( $signature );
		return $signing_input . '.' . $this->base64url_encode( $raw_sig );
	}

	private function base64url_encode( string $data ): string {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	/**
	 * @throws Exception
	 */
	private function der_to_raw( string $der ): string {
		$offset = 0;
		if ( ord( $der[ $offset++ ] ) !== 0x30 ) {
			throw new Exception( 'Invalid DER signature' );
		}
		$len = ord( $der[ $offset++ ] );
		if ( $len & 0x80 ) {
			$offset += $len & 0x7f;
		}

		if ( ord( $der[ $offset++ ] ) !== 0x02 ) {
			throw new Exception( 'Invalid DER signature (r)' );
		}
		$r_len = ord( $der[ $offset++ ] );
		$r     = substr( $der, $offset, $r_len );
		$offset += $r_len;

		if ( ord( $der[ $offset++ ] ) !== 0x02 ) {
			throw new Exception( 'Invalid DER signature (s)' );
		}
		$s_len = ord( $der[ $offset++ ] );
		$s     = substr( $der, $offset, $s_len );

		$r = ltrim( $r, "\x00" );
		$s = ltrim( $s, "\x00" );
		$r = str_pad( $r, 32, "\x00", STR_PAD_LEFT );
		$s = str_pad( $s, 32, "\x00", STR_PAD_LEFT );

		return $r . $s;
	}
}
