<?php
/**
 * Plugin Name: AEGIS Coinbase for WooCommerce
 * Plugin URI:  https://github.com/GambitMutant/aegis-coinbase-for-woocommerce
 * Description: Accept cryptocurrency payments via personal Coinbase account (unique addresses + QR) or Coinbase Business Checkouts. Mode selector (only one active at a time). Collects a disclosed 0.5% fee. Donations support The AEGIS Alliance project funding (secondary: business operations). Crypto accepted at Coinbase: USDC 0x39C7e115C4184f2E646D44E3681A87a22a93341B, BTC 3PCJwFfVytUCW2mfgUgKbjWmqsYvTzgCH1, ETH 0xcD8B620962e9ff4a098c4134f1C82fDf96236F87, DOGE DAo22guf8pmNcF1V7FvuqrXTgy2khuPysB, USDT 0x4aF2eE05A313c8a8dd53EdA87DDA69F3E375c3B5. HPOS, Checkout Blocks, and WP Bakery compatible.
 * Version:     1.0.4
 * Author:      Gambit Mutant
 * Author URI:  https://github.com/GambitMutant
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Tested up to: 6.9
 * WC requires at least: 8.0
 * WC tested up to: 12.1
 * License:     AEGIS Source-Available License
 * License URI: https://github.com/GambitMutant/aegis-coinbase-for-woocommerce/blob/main/LICENSE
 * Text Domain: aegis-coinbase
 *
 * @package AEGISCoinbaseForWooCommerce
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ACW_VERSION', '1.0.4' );
define( 'ACW_PLUGIN_FILE', __FILE__ );
define( 'ACW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Warn when this site's PHP is missing the October 21, 2026 runtime fixes.
 *
 * CVE-2026-109618 and CVE-2026-109619 are in PHP itself. This plugin cannot patch them.
 */
add_action( 'admin_notices', 'acw_php_runtime_security_notice' );

/**
 * Admin notice for an unpatched PHP runtime.
 */
function acw_php_runtime_security_notice(): void {
	if ( ! current_user_can( 'update_core' ) && ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}

	$message = acw_php_runtime_security_message();
	if ( null === $message ) {
		return;
	}

	echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
}

/**
 * Message when PHP is older than the patched release for its branch, or has no packaged fix.
 */
function acw_php_runtime_security_message(): ?string {
	$version = PHP_VERSION;
	$plugin  = 'AEGIS Coinbase for WooCommerce';
	$floors  = array(
		array( '8.2.0', '8.3.0', '8.2.35' ),
		array( '8.3.0', '8.4.0', '8.3.36' ),
		array( '8.4.0', '8.5.0', '8.4.27' ),
		array( '8.5.0', '8.6.0', '8.5.12' ),
	);

	foreach ( $floors as $floor ) {
		if ( version_compare( $version, $floor[0], '>=' ) && version_compare( $version, $floor[1], '<' ) && version_compare( $version, $floor[2], '<' ) ) {
			return sprintf(
				/* translators: 1: plugin name, 2: running PHP version, 3: patched PHP version */
				__( '%1$s is compatible with PHP 8.0 and later, but this site is running PHP %2$s. That release is missing the October 21, 2026 fixes for CVE-2026-109618 and CVE-2026-109619. Update PHP to %3$s or newer. Updating this plugin does not patch PHP.', 'aegis-coinbase' ),
				$plugin,
				$version,
				$floor[2]
			);
		}
	}

	if ( version_compare( $version, '8.2.0', '<' ) ) {
		return sprintf(
			/* translators: 1: plugin name, 2: running PHP version */
			__( '%1$s runs on PHP %2$s, but PHP 8.1 and older have no packaged fix for CVE-2026-109618 and CVE-2026-109619. Move this site to PHP 8.3.36, 8.4.27, or 8.5.12 or newer. Updating this plugin does not patch PHP.', 'aegis-coinbase' ),
			$plugin,
			$version
		);
	}

	return null;
}

/**
 * Declare HPOS and Blocks compatibility.
 */
add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

/**
 * Load plugin after WooCommerce.
 */
add_action(
	'plugins_loaded',
	static function (): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function (): void {
					echo '<div class="error"><p>' . esc_html__( 'AEGIS Coinbase for WooCommerce requires WooCommerce to be active.', 'aegis-coinbase' ) . '</p></div>';
				}
			);
			return;
		}

		require_once ACW_PLUGIN_DIR . 'includes/class-acw-constants.php';
		require_once ACW_PLUGIN_DIR . 'includes/class-acw-jwt-personal.php';
		require_once ACW_PLUGIN_DIR . 'includes/class-acw-jwt-business.php';
		require_once ACW_PLUGIN_DIR . 'includes/class-acw-api-personal.php';
		require_once ACW_PLUGIN_DIR . 'includes/class-acw-api-business.php';
		require_once ACW_PLUGIN_DIR . 'includes/class-acw-fee-collector.php';
		require_once ACW_PLUGIN_DIR . 'includes/class-wc-gateway-aegis-coinbase.php';
		require_once ACW_PLUGIN_DIR . 'includes/class-acw-webhook-handler.php';
		require_once ACW_PLUGIN_DIR . 'includes/class-acw-cron.php';

		add_filter(
			'woocommerce_payment_gateways',
			static function ( array $gateways ): array {
				$gateways[] = 'WC_Gateway_AEGIS_Coinbase';
				return $gateways;
			}
		);

		new ACW_Webhook_Handler();
		new ACW_Cron();

		add_action( 'wp_enqueue_scripts', static function (): void {
			$load = is_checkout() || is_order_received_page() || is_wc_endpoint_url( 'order-pay' );

			// Also load when WP Bakery is rendering a page that contains checkout shortcodes
			// or when the frontend editor is active.
			if ( ! $load && ( defined( 'WPB_VC_VERSION' ) || function_exists( 'vc_is_frontend_editor' ) ) ) {
				global $post;
				if ( $post && ( has_shortcode( $post->post_content, 'woocommerce_checkout' ) || has_shortcode( $post->post_content, 'woocommerce_cart' ) ) ) {
					$load = true;
				}
			}

			if ( $load ) {
				wp_enqueue_style( 'acw-style', ACW_PLUGIN_URL . 'assets/css/style.css', array(), ACW_VERSION );
				wp_enqueue_script( 'acw-payment', ACW_PLUGIN_URL . 'assets/js/payment.js', array( 'jquery' ), ACW_VERSION, true );
			}
		}, 20 );
	}
);

/**
 * Blocks support.
 */
add_action(
	'woocommerce_blocks_loaded',
	static function (): void {
		if ( class_exists( \Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType::class ) ) {
			require_once ACW_PLUGIN_DIR . 'includes/class-acw-blocks-support.php';
			add_action(
				'woocommerce_blocks_payment_method_type_registration',
				static function ( \Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $registry ): void {
					$registry->register( new ACW_Blocks_Support() );
				}
			);
		}
	}
);
