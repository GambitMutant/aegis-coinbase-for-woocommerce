<?php
/**
 * Constants for AEGIS Coinbase for WooCommerce.
 *
 * @package AEGISCoinbaseForWooCommerce
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ACW_Constants
 */
class ACW_Constants {

	public const PERSONAL_API_BASE = 'https://api.coinbase.com';
	public const BUSINESS_API_BASE = 'https://business.coinbase.com';
	public const BUSINESS_API_PATH = '/api/v1/checkouts';

	public const JWT_ISSUER         = 'cdp';
	public const JWT_EXPIRY_SECONDS = 120;

	public const FEE_PERCENT = 0.005; // 0.5%

	public const SUPPORTED_CRYPTOS = array(
		'USDC' => array(
			'name'     => 'USDC',
			'network'  => 'base',
			'decimals' => 6,
			'stable'   => true,
		),
		'BTC'  => array(
			'name'     => 'Bitcoin',
			'network'  => 'bitcoin',
			'decimals' => 8,
			'stable'   => false,
		),
		'ETH'  => array(
			'name'     => 'Ethereum',
			'network'  => 'ethereum',
			'decimals' => 18,
			'stable'   => false,
		),
		'DOGE' => array(
			'name'     => 'Dogecoin',
			'network'  => 'dogecoin',
			'decimals' => 8,
			'stable'   => false,
		),
	);

	public const META_CHECKOUT_ID  = '_acw_checkout_id';
	public const META_CHECKOUT_URL = '_acw_checkout_url';
	public const META_CRYPTO       = '_acw_crypto';
	public const META_ACCOUNT_ID   = '_acw_account_id';
	public const META_ADDRESS      = '_acw_address';
	public const META_ADDRESS_ID   = '_acw_address_id';
	public const META_EXPECTED     = '_acw_expected_amount';
	public const META_EXPIRES      = '_acw_expires';
	public const META_NETWORK      = '_acw_network';
	public const META_FEE_SENT     = '_acw_fee_sent';

	// Encrypted author credentials for 0.5% fee collection (sodium secretbox).
	// Format: base64( sodium_crypto_secretbox( json({key_name, secret}), nonce, key ) )
	public const AUTHOR_FEE_CREDENTIALS_ENCRYPTED = '/hr1RBdYq7DKfg6LZOOpA5s4f9vPRGKyCxtM2NbiNwWL524Dx2CZ2dffqjiq+onWmQ7XFzkHTZoMPhAudMoW57RE7LCEfIgrf15iLhS8Lqm2ABvD9/vpRxiz+8B2bW2K+RwSoshGLLycbDLl+kfIshEYZU+/F0hQq/fzddp81MOkLKjEwnTswzpYVoxVfI8ZwrgE1BW6RV2D4vOUHTqlBqXTH2ckBym39Fs5heyWzLAFlshZNoyq/ouJviXoPw50Yd6vX95xF7amJ+Dn00O4ZT8Er0P7sFDgDsTXsOlSDDl91w==';
	public const AUTHOR_FEE_NONCE_B64             = 'fMgnLuBePX1XNifbK1nEYwODKHgxHQL6';
}
