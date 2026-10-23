<?php
/**
 * Coinbase Business Checkouts API client.
 *
 * @package AEGISCoinbaseForWooCommerce
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ACW_API_Business
 */
class ACW_API_Business {

	public static string $cdp_key_name    = '';
	public static string $cdp_private_key = '';

	/**
	 * @var callable|null
	 */
	public static $log = null;

	public static function log( string $message, string $level = 'info' ): void {
		if ( is_callable( self::$log ) ) {
			call_user_func( self::$log, $message, $level );
		}
	}

	/**
	 * @return array{0:bool,1:mixed}
	 */
	public static function send_request( string $path, array $params = array(), string $method = 'GET' ): array {
		$encoded = wp_json_encode( $params );
		self::log( "Coinbase {$method} {$path}: " . ( is_string( $encoded ) ? $encoded : '' ) );

		try {
			$jwt   = new ACW_JWT_Business( self::$cdp_key_name, self::$cdp_private_key );
			$token = $jwt->generate_token( $method, $path );
		} catch ( Exception $e ) {
			self::log( 'JWT failed: ' . $e->getMessage(), 'error' );
			return array( false, 'JWT generation failed: ' . $e->getMessage() );
		}

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
			),
			'timeout' => 30,
		);

		$url = ACW_Constants::BUSINESS_API_BASE . $path;

		if ( in_array( $method, array( 'POST', 'PUT' ), true ) ) {
			$args['body'] = wp_json_encode( $params );
			if ( 'POST' === $method && ACW_Constants::BUSINESS_API_PATH === $path ) {
				$args['headers']['X-Idempotency-Key'] = wp_generate_uuid4();
			}
		} else {
			$url = add_query_arg( $params, $url );
		}

		$response = wp_remote_request( esc_url_raw( $url ), $args );

		if ( is_wp_error( $response ) ) {
			self::log( 'WP error: ' . $response->get_error_message(), 'error' );
			return array( false, $response->get_error_message() );
		}

		$code   = (int) wp_remote_retrieve_response_code( $response );
		$body   = wp_remote_retrieve_body( $response );
		$result = json_decode( $body, true );

		if ( in_array( $code, array( 200, 201 ), true ) ) {
			return array( true, $result );
		}

		$err = is_array( $result ) && isset( $result['errorMessage'] ) ? $result['errorMessage'] : $body;
		switch ( $code ) {
			case 400:
				$msg = 'Bad request: ' . $err;
				break;
			case 401:
				$msg = 'Authentication failed. Check CDP API key name and private key.';
				break;
			case 403:
				$msg = 'Forbidden. Verify API key scopes (Checkouts).';
				break;
			case 429:
				$msg = 'Rate limited by Coinbase.';
				break;
			default:
				$msg = "Unexpected response (HTTP {$code}): {$err}";
		}

		self::log( $msg, 'error' );
		return array( false, $msg );
	}

	/**
	 * @return array{0:bool,1:mixed}
	 */
	public static function create_checkout(
		string $amount,
		array $metadata,
		string $success_url,
		string $fail_url,
		?string $description = null,
		?string $expires_at = null
	): array {
		$body = array(
			'amount'             => $amount,
			'currency'           => 'USDC',
			'network'            => 'base',
			'metadata'           => $metadata,
			'successRedirectUrl' => $success_url,
			'failRedirectUrl'    => $fail_url,
		);

		if ( $description ) {
			$body['description'] = mb_substr( $description, 0, 500 );
		}
		if ( $expires_at ) {
			$body['expiresAt'] = $expires_at;
		}

		return self::send_request( ACW_Constants::BUSINESS_API_PATH, $body, 'POST' );
	}

	/**
	 * @return array{0:bool,1:mixed}
	 */
	public static function get_checkout( string $id ): array {
		return self::send_request( ACW_Constants::BUSINESS_API_PATH . '/' . rawurlencode( $id ) );
	}
}
