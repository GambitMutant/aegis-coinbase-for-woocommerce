<?php
/**
 * Coinbase App API handler (personal account).
 *
 * @package AEGISCoinbaseForWooCommerce
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ACW_API_Personal
 */
class ACW_API_Personal {

	private ACW_JWT_Personal $jwt;

	public function __construct( string $key_name, string $secret_b64 ) {
		$this->jwt = new ACW_JWT_Personal( $key_name, $secret_b64 );
	}

	/**
	 * @return array|WP_Error
	 */
	public function request( string $method, string $path, ?array $body = null ) {
		$token = $this->jwt->generate( $method, $path );
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$args = array(
			'method'  => strtoupper( $method ),
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
			),
			'timeout' => 30,
		);

		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( ACW_Constants::PERSONAL_API_BASE . $path, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			$msg = $data['errors'][0]['message'] ?? ( $data['error'] ?? 'Coinbase API error ' . $code );
			return new WP_Error( 'acw_api_error', $msg, array( 'status' => $code, 'body' => $data ) );
		}

		return $data;
	}

	/**
	 * @return array|WP_Error
	 */
	public function list_accounts() {
		$accounts = array();
		$path     = '/v2/accounts?limit=300';

		do {
			$result = $this->request( 'GET', $path );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			if ( ! empty( $result['data'] ) ) {
				$accounts = array_merge( $accounts, $result['data'] );
			}
			$path = $result['pagination']['next_uri'] ?? null;
			if ( $path && 0 === strpos( $path, 'http' ) ) {
				$path = wp_parse_url( $path, PHP_URL_PATH ) . ( wp_parse_url( $path, PHP_URL_QUERY ) ? '?' . wp_parse_url( $path, PHP_URL_QUERY ) : '' );
			}
		} while ( $path );

		return $accounts;
	}

	/**
	 * @return string|WP_Error
	 */
	public function find_account_id( string $currency ) {
		$accounts = $this->list_accounts();
		if ( is_wp_error( $accounts ) ) {
			return $accounts;
		}

		foreach ( $accounts as $account ) {
			if ( isset( $account['currency']['code'] ) && strtoupper( $account['currency']['code'] ) === strtoupper( $currency ) && ( $account['primary'] ?? false ) ) {
				return (string) $account['id'];
			}
		}

		foreach ( $accounts as $account ) {
			if ( isset( $account['currency']['code'] ) && strtoupper( $account['currency']['code'] ) === strtoupper( $currency ) ) {
				return (string) $account['id'];
			}
		}

		return new WP_Error( 'acw_no_account', 'No Coinbase account found for ' . $currency . '.' );
	}

	/**
	 * @return array|WP_Error
	 */
	public function create_address( string $account_id, string $name, string $network ) {
		$path = '/v2/accounts/' . rawurlencode( $account_id ) . '/addresses';
		return $this->request( 'POST', $path, array(
			'name'    => $name,
			'network' => $network,
		) );
	}

	/**
	 * @return float|WP_Error
	 */
	public function get_spot_price( string $pair ) {
		$path   = '/v2/prices/' . rawurlencode( $pair ) . '/spot';
		$result = $this->request( 'GET', $path );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$amount = $result['data']['amount'] ?? null;
		if ( null === $amount ) {
			return new WP_Error( 'acw_price', 'Could not fetch spot price for ' . $pair );
		}
		return (float) $amount;
	}

	/**
	 * @return array|WP_Error
	 */
	public function list_address_transactions( string $account_id, string $address_id ) {
		$path   = '/v2/accounts/' . rawurlencode( $account_id ) . '/addresses/' . rawurlencode( $address_id ) . '/transactions?limit=25';
		$result = $this->request( 'GET', $path );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $result['data'] ?? array();
	}

	/**
	 * Send crypto from an account to an address.
	 *
	 * @return array|WP_Error
	 */
	public function send( string $account_id, string $to_address, string $amount, string $currency, string $description = '' ) {
		$path = '/v2/accounts/' . rawurlencode( $account_id ) . '/transactions';
		$body = array(
			'type'        => 'send',
			'to'          => $to_address,
			'amount'      => $amount,
			'currency'    => $currency,
			'description' => $description,
		);
		return $this->request( 'POST', $path, $body );
	}
}
