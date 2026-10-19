<?php
/**
 * Unified WooCommerce payment gateway for AEGIS Coinbase (Personal or Business mode).
 *
 * @package AEGISCoinbaseForWooCommerce
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Gateway_AEGIS_Coinbase
 */
class WC_Gateway_AEGIS_Coinbase extends WC_Payment_Gateway {

	public function __construct() {
		$this->id                 = 'aegis_coinbase';
		$this->icon               = ACW_PLUGIN_URL . 'assets/images/icon.png';
		$this->has_fields         = true;
		$this->method_title       = __( 'AEGIS Coinbase', 'aegis-coinbase' );
		$this->method_description = __( 'Accept cryptocurrency payments via personal Coinbase account or Coinbase Business Checkouts. Mode is selected in settings.', 'aegis-coinbase' );
		$this->supports           = array( 'products' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->enabled     = $this->get_option( 'enabled' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
		add_action( 'wp_ajax_acw_check_payment', array( $this, 'ajax_check_payment' ) );
		add_action( 'wp_ajax_nopriv_acw_check_payment', array( $this, 'ajax_check_payment' ) );
		add_action( 'wp_ajax_acw_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'admin_footer', array( $this, 'admin_test_script' ) );

		// Wire business API if in business mode.
		if ( 'business' === $this->get_mode() ) {
			ACW_API_Business::$cdp_key_name    = $this->get_option( 'cdp_key_name' );
			ACW_API_Business::$cdp_private_key = $this->get_option( 'cdp_private_key' );
			ACW_API_Business::$log             = array( $this, 'log' );
		}
	}

	public function get_mode(): string {
		$mode = $this->get_option( 'mode', 'personal' );
		return in_array( $mode, array( 'personal', 'business' ), true ) ? $mode : 'personal';
	}

	public function init_form_fields(): void {
		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Enable/Disable', 'aegis-coinbase' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable AEGIS Coinbase payments', 'aegis-coinbase' ),
				'default' => 'no',
			),
			'documentation' => array(
				'title'       => __( 'Documentation', 'aegis-coinbase' ),
				'type'        => 'title',
				'description' => '<a href="https://github.com/GambitMutant/aegis-coinbase-for-woocommerce" target="_blank" rel="noopener">View full instructions, setup guide, and donation info on GitHub</a>',
			),
			'mode' => array(
				'title'       => __( 'Account mode', 'aegis-coinbase' ),
				'type'        => 'select',
				'description' => __( 'Choose Personal (unique addresses + QR) or Business (Coinbase Business Checkouts). Only one mode is active at a time.', 'aegis-coinbase' ),
				'default'     => 'personal',
				'options'     => array(
					'personal' => __( 'Personal Coinbase account', 'aegis-coinbase' ),
					'business' => __( 'Coinbase Business Checkouts', 'aegis-coinbase' ),
				),
			),
			'title' => array(
				'title'   => __( 'Title', 'aegis-coinbase' ),
				'type'    => 'text',
				'default' => __( 'Cryptocurrency (AEGIS Coinbase)', 'aegis-coinbase' ),
			),
			'description' => array(
				'title'   => __( 'Description', 'aegis-coinbase' ),
				'type'    => 'textarea',
				'default' => __( 'Pay with cryptocurrency via Coinbase. A small 0.5% fee supports continued development.', 'aegis-coinbase' ),
			),
			'api_key_name' => array(
				'title'       => __( 'Personal: API Key Name', 'aegis-coinbase' ),
				'type'        => 'text',
				'description' => __( 'Full CDP key name (organizations/.../apiKeys/...). Used in Personal mode.', 'aegis-coinbase' ),
				'default'     => '',
			),
			'api_private_key' => array(
				'title'       => __( 'Personal: API Secret (Ed25519 base64)', 'aegis-coinbase' ),
				'type'        => 'textarea',
				'description' => __( 'Base64 Ed25519 secret (~88 chars, decodes to 64 bytes). Not a PEM file.', 'aegis-coinbase' ),
				'default'     => '',
				'css'         => 'min-height: 80px; font-family: monospace;',
			),
			'enabled_cryptos' => array(
				'title'       => __( 'Personal: Enabled cryptocurrencies', 'aegis-coinbase' ),
				'type'        => 'multiselect',
				'options'     => array(
					'USDC' => 'USDC (Base)',
					'USDT' => 'USDT (Ethereum)',
					'BTC'  => 'Bitcoin',
					'ETH'  => 'Ethereum',
					'DOGE' => 'Dogecoin',
				),
				'default'     => array( 'USDC', 'USDT', 'BTC', 'ETH', 'DOGE' ),
				'description' => __( 'Which coins customers can choose (Personal mode).', 'aegis-coinbase' ),
			),
			'price_margin' => array(
				'title'             => __( 'Personal: Price margin %', 'aegis-coinbase' ),
				'type'              => 'number',
				'default'           => '1.5',
				'description'       => __( 'Extra % added to spot price for non-stablecoins.', 'aegis-coinbase' ),
				'custom_attributes' => array( 'step' => '0.1', 'min' => '0' ),
			),
			'quote_minutes' => array(
				'title'             => __( 'Personal: Quote validity (minutes)', 'aegis-coinbase' ),
				'type'              => 'number',
				'default'           => '15',
				'custom_attributes' => array( 'min' => '5', 'max' => '60' ),
			),
			'cdp_key_name' => array(
				'title'       => __( 'Business: CDP API Key Name', 'aegis-coinbase' ),
				'type'        => 'text',
				'description' => __( 'Full API key name from Coinbase Developer Platform.', 'aegis-coinbase' ),
				'default'     => '',
			),
			'cdp_private_key' => array(
				'title'       => __( 'Business: CDP Private Key (PEM)', 'aegis-coinbase' ),
				'type'        => 'textarea',
				'description' => __( 'EC private key in PEM format (-----BEGIN EC PRIVATE KEY-----).', 'aegis-coinbase' ),
				'default'     => '',
				'css'         => 'height: 180px; font-family: monospace;',
			),
			'webhook_secret' => array(
				'title'       => __( 'Business: Webhook Secret', 'aegis-coinbase' ),
				'type'        => 'password',
				'description' => __( 'Secret from Coinbase Business webhook subscription.', 'aegis-coinbase' ),
				'default'     => '',
			),
			'webhook_url' => array(
				'title'             => __( 'Business: Webhook URL', 'aegis-coinbase' ),
				'type'              => 'text',
				'description'       => __( 'Add this URL in Coinbase Business webhook settings.', 'aegis-coinbase' ),
				'default'           => rest_url( 'aegis-coinbase/v1/webhook' ),
				'custom_attributes' => array( 'readonly' => 'readonly' ),
			),
			'min_amount' => array(
				'title'             => __( 'Business: Minimum amount', 'aegis-coinbase' ),
				'type'              => 'number',
				'default'           => '0',
				'description'       => __( 'Minimum order total to show this method (0 = no minimum).', 'aegis-coinbase' ),
				'custom_attributes' => array( 'step' => '0.01', 'min' => '0' ),
			),
			'show_product_pricing' => array(
				'title'   => __( 'Business: Show USDC pricing', 'aegis-coinbase' ),
				'type'    => 'checkbox',
				'label'   => __( 'Display approximate USDC price on product pages.', 'aegis-coinbase' ),
				'default' => 'yes',
			),
			'usdc_rate' => array(
				'title'             => __( 'Business: USDC rate', 'aegis-coinbase' ),
				'type'              => 'number',
				'default'           => '1',
				'custom_attributes' => array( 'step' => '0.0001', 'min' => '0.0001' ),
			),
			'order_status' => array(
				'title'   => __( 'Order status after payment', 'aegis-coinbase' ),
				'type'    => 'select',
				'options' => array(
					'processing' => 'Processing',
					'completed'  => 'Completed',
					'on-hold'    => 'On hold',
				),
				'default' => 'processing',
			),
			'debug' => array(
				'title'   => __( 'Debug log', 'aegis-coinbase' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable logging (WooCommerce > Status > Logs).', 'aegis-coinbase' ),
				'default' => 'no',
			),
			'test_connection' => array(
				'title'       => __( 'Test connection', 'aegis-coinbase' ),
				'type'        => 'title',
				'description' => '<button type="button" class="button" id="acw-test-connection">' . esc_html__( 'Test API connection', 'aegis-coinbase' ) . '</button> <span id="acw-test-result"></span>',
			),
		);
	}

	public function payment_fields(): void {
		echo wpautop( wp_kses_post( $this->description ) );

		if ( 'personal' !== $this->get_mode() ) {
			return;
		}

		$enabled = (array) $this->get_option( 'enabled_cryptos', array( 'USDC' ) );
		if ( empty( $enabled ) ) {
			$enabled = array( 'USDC' );
		}

		$available = $this->get_available_cryptos( $enabled );

		if ( empty( $available ) ) {
			echo '<p>' . esc_html__( 'No cryptocurrency wallets are available right now. Please contact the store.', 'aegis-coinbase' ) . '</p>';
			return;
		}

		echo '<p class="form-row form-row-wide">';
		echo '<label>' . esc_html__( 'Pay with', 'aegis-coinbase' ) . '</label>';
		echo '<select name="acw_crypto" id="acw_crypto" style="width:100%;">';
		foreach ( $available as $code ) {
			$info = ACW_Constants::SUPPORTED_CRYPTOS[ $code ];
			$net  = ucfirst( $info['network'] );
			echo '<option value="' . esc_attr( $code ) . '">' . esc_html( $info['name'] . ' (' . $code . ') — ' . $net . ' network' ) . '</option>';
		}
		echo '</select></p>';
		echo '<p class="acw-network-hint" style="font-size:0.9em;color:#555;margin-top:0.5em;">';
		echo esc_html__( 'You will be shown the exact network to use on the next page.', 'aegis-coinbase' );
		echo '</p>';
	}

	private function get_available_cryptos( array $enabled ): array {
		$key_name = $this->get_option( 'api_key_name' );
		$secret   = $this->get_option( 'api_private_key' );
		if ( empty( $key_name ) || empty( $secret ) ) {
			return array();
		}

		$api      = new ACW_API_Personal( $key_name, $secret );
		$accounts = $api->list_accounts();
		if ( is_wp_error( $accounts ) ) {
			return array();
		}

		$found = array();
		foreach ( $accounts as $account ) {
			$code = strtoupper( (string) ( $account['currency']['code'] ?? '' ) );
			if ( $code && isset( ACW_Constants::SUPPORTED_CRYPTOS[ $code ] ) ) {
				$found[ $code ] = true;
			}
		}

		$available = array();
		foreach ( $enabled as $code ) {
			$code = strtoupper( (string) $code );
			if ( isset( $found[ $code ] ) ) {
				$available[] = $code;
			}
		}

		return $available;
	}

	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return array( 'result' => 'failure' );
		}

		if ( 'business' === $this->get_mode() ) {
			return $this->process_business_payment( $order );
		}

		return $this->process_personal_payment( $order );
	}

	private function process_personal_payment( WC_Order $order ): array {
		$crypto    = isset( $_POST['acw_crypto'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['acw_crypto'] ) ) ) : 'USDC';
		$enabled   = (array) $this->get_option( 'enabled_cryptos', array( 'USDC' ) );
		$available = $this->get_available_cryptos( $enabled );

		if ( ! in_array( $crypto, $available, true ) || ! isset( ACW_Constants::SUPPORTED_CRYPTOS[ $crypto ] ) ) {
			wc_add_notice( __( 'Selected cryptocurrency is not available.', 'aegis-coinbase' ), 'error' );
			return array( 'result' => 'failure' );
		}

		$key_name = $this->get_option( 'api_key_name' );
		$priv_key = $this->get_option( 'api_private_key' );
		$api      = new ACW_API_Personal( $key_name, $priv_key );

		$account_id = $api->find_account_id( $crypto );
		if ( is_wp_error( $account_id ) ) {
			wc_add_notice( $account_id->get_error_message(), 'error' );
			return array( 'result' => 'failure' );
		}

		$usd_total = (float) $order->get_total();
		$info      = ACW_Constants::SUPPORTED_CRYPTOS[ $crypto ];
		$margin    = (float) $this->get_option( 'price_margin', 1.5 ) / 100;

		if ( $info['stable'] ) {
			$crypto_amount = $usd_total;
		} else {
			$price = $api->get_spot_price( $crypto . '-USD' );
			if ( is_wp_error( $price ) || $price <= 0 ) {
				wc_add_notice( __( 'Could not fetch current price. Please try again.', 'aegis-coinbase' ), 'error' );
				return array( 'result' => 'failure' );
			}
			$crypto_amount = ( $usd_total / $price ) * ( 1 + $margin );
		}

		$decimals      = $info['stable'] ? 6 : 8;
		$crypto_amount = round( $crypto_amount, $decimals );

		$address_result = $api->create_address(
			$account_id,
			'WooCommerce Order #' . $order->get_order_number(),
			$info['network']
		);

		if ( is_wp_error( $address_result ) ) {
			wc_add_notice( $address_result->get_error_message(), 'error' );
			return array( 'result' => 'failure' );
		}

		$address_data = $address_result['data'] ?? array();
		$address      = $address_data['address'] ?? '';
		$address_id   = $address_data['id'] ?? '';

		if ( ! $address || ! $address_id ) {
			wc_add_notice( __( 'Failed to create receive address.', 'aegis-coinbase' ), 'error' );
			return array( 'result' => 'failure' );
		}

		$minutes = (int) $this->get_option( 'quote_minutes', 15 );
		$expires = time() + ( $minutes * 60 );

		$order->update_meta_data( ACW_Constants::META_CRYPTO, $crypto );
		$order->update_meta_data( ACW_Constants::META_ACCOUNT_ID, $account_id );
		$order->update_meta_data( ACW_Constants::META_ADDRESS, $address );
		$order->update_meta_data( ACW_Constants::META_ADDRESS_ID, $address_id );
		$order->update_meta_data( ACW_Constants::META_EXPECTED, (string) $crypto_amount );
		$order->update_meta_data( ACW_Constants::META_EXPIRES, $expires );
		$order->update_meta_data( ACW_Constants::META_NETWORK, $info['network'] );
		$order->save();

		$order->update_status( 'on-hold', __( 'Awaiting cryptocurrency payment.', 'aegis-coinbase' ) );

		return array(
			'result'   => 'success',
			'redirect' => $order->get_checkout_payment_url( true ),
		);
	}

	private function process_business_payment( WC_Order $order ): array {
		$min = (float) $this->get_option( 'min_amount', 0 );
		if ( $min > 0 && (float) $order->get_total() < $min ) {
			wc_add_notice(
				sprintf( __( 'AEGIS Coinbase requires a minimum order total of %s.', 'aegis-coinbase' ), wc_price( $min ) ),
				'error'
			);
			return array( 'result' => 'failure' );
		}

		$success_url = $this->get_return_url( $order );
		$fail_url    = wc_get_checkout_url();

		$metadata = array(
			'order_id'  => (string) $order->get_id(),
			'order_key' => $order->get_order_key(),
			'source'    => 'woocommerce',
		);

		$description = sprintf( __( 'WooCommerce Order #%s', 'aegis-coinbase' ), $order->get_order_number() );
		$expires     = gmdate( 'c', time() + DAY_IN_SECONDS );

		list( $ok, $result ) = ACW_API_Business::create_checkout(
			(string) $order->get_total(),
			$metadata,
			$success_url,
			$fail_url,
			$description,
			$expires
		);

		if ( ! $ok || empty( $result['url'] ) ) {
			$msg = is_string( $result ) ? $result : __( 'Failed to create Coinbase checkout.', 'aegis-coinbase' );
			wc_add_notice( $msg, 'error' );
			return array( 'result' => 'failure' );
		}

		$checkout_id  = $result['id'] ?? '';
		$checkout_url = $result['url'];

		$order->update_meta_data( ACW_Constants::META_CHECKOUT_ID, $checkout_id );
		$order->update_meta_data( ACW_Constants::META_CHECKOUT_URL, $checkout_url );
		$order->update_meta_data( ACW_Constants::META_CRYPTO, 'USDC' );
		$order->update_meta_data( ACW_Constants::META_EXPECTED, (string) $order->get_total() );
		$order->update_status( 'pending', __( 'Awaiting Coinbase Business payment.', 'aegis-coinbase' ) );
		$order->save();

		WC()->cart->empty_cart();

		return array(
			'result'   => 'success',
			'redirect' => $checkout_url,
		);
	}

	public function receipt_page( $order_id ): void {
		if ( 'personal' !== $this->get_mode() ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$crypto  = $order->get_meta( ACW_Constants::META_CRYPTO );
		$address = $order->get_meta( ACW_Constants::META_ADDRESS );
		$amount  = $order->get_meta( ACW_Constants::META_EXPECTED );
		$expires = (int) $order->get_meta( ACW_Constants::META_EXPIRES );
		$network = $order->get_meta( ACW_Constants::META_NETWORK );
		$info    = ACW_Constants::SUPPORTED_CRYPTOS[ $crypto ] ?? array( 'name' => $crypto );

		$qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' . rawurlencode( $address );

		echo '<div class="acw-payment-box cfp-payment-box" data-order="' . esc_attr( (string) $order_id ) . '" data-expires="' . esc_attr( (string) $expires ) . '">';
		echo '<h3>' . esc_html( sprintf( __( 'Send %s %s', 'aegis-coinbase' ), $amount, $crypto ) ) . '</h3>';
		echo '<p class="acw-amount cfp-amount"><strong>' . esc_html( $amount . ' ' . $crypto ) . '</strong></p>';

		$network_labels = array(
			'base'     => 'Base',
			'bitcoin'  => 'Bitcoin',
			'ethereum' => 'Ethereum',
			'dogecoin' => 'Dogecoin',
		);
		$network_label = $network_labels[ $network ] ?? ucfirst( (string) $network );

		echo '<div class="acw-network-notice cfp-network-notice">';
		echo '<strong>' . esc_html__( 'IMPORTANT — Correct network:', 'aegis-coinbase' ) . '</strong><br>';
		echo esc_html( sprintf( __( 'Send only %1$s on the %2$s network.', 'aegis-coinbase' ), $crypto, $network_label ) );
		echo '</div>';

		echo '<div class="acw-qr cfp-qr"><img src="' . esc_url( $qr_url ) . '" alt="QR code" width="220" height="220" /></div>';
		echo '<p class="acw-address cfp-address"><code id="acw-address">' . esc_html( $address ) . '</code> <button type="button" class="button acw-copy" data-copy="' . esc_attr( $address ) . '">' . esc_html__( 'Copy', 'aegis-coinbase' ) . '</button></p>';
		echo '<p class="acw-warning cfp-warning">' . esc_html__( 'Sending the wrong cryptocurrency or the wrong network will result in permanent loss of funds.', 'aegis-coinbase' ) . '</p>';
		echo '<p class="acw-timer cfp-timer">' . esc_html__( 'Quote expires in:', 'aegis-coinbase' ) . ' <span id="acw-countdown"></span></p>';
		echo '<p class="acw-status cfp-status" id="acw-status">' . esc_html__( 'Waiting for payment…', 'aegis-coinbase' ) . '</p>';
		echo '</div>';
	}

	public function ajax_check_payment(): void {
		if ( ! isset( $_POST['order_id'] ) ) {
			wp_send_json_error( array( 'message' => 'Missing order' ) );
		}

		$order_id = absint( $_POST['order_id'] );
		$order    = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( array( 'message' => 'Order not found' ) );
		}

		if ( 'aegis_coinbase' !== $order->get_payment_method() ) {
			wp_send_json_error( array( 'message' => 'Invalid order' ) );
		}

		$status = $this->check_and_update_order( $order );

		wp_send_json_success( array(
			'status'  => $order->get_status(),
			'paid'    => in_array( $order->get_status(), array( 'processing', 'completed' ), true ),
			'message' => $status,
		) );
	}

	public function check_and_update_order( WC_Order $order ): string {
		if ( in_array( $order->get_status(), array( 'processing', 'completed', 'cancelled', 'refunded', 'failed' ), true ) ) {
			return 'already_final';
		}

		if ( 'personal' !== $this->get_mode() ) {
			return 'not_personal';
		}

		$expires = (int) $order->get_meta( ACW_Constants::META_EXPIRES );
		if ( $expires && time() > $expires ) {
			$order->update_status( 'cancelled', __( 'Cryptocurrency payment quote expired.', 'aegis-coinbase' ) );
			return 'expired';
		}

		$account_id = $order->get_meta( ACW_Constants::META_ACCOUNT_ID );
		$address_id = $order->get_meta( ACW_Constants::META_ADDRESS_ID );
		$expected   = (float) $order->get_meta( ACW_Constants::META_EXPECTED );
		$crypto     = $order->get_meta( ACW_Constants::META_CRYPTO );

		if ( ! $account_id || ! $address_id || ! $expected ) {
			return 'missing_meta';
		}

		$key_name = $this->get_option( 'api_key_name' );
		$priv_key = $this->get_option( 'api_private_key' );
		$api      = new ACW_API_Personal( $key_name, $priv_key );

		$txs = $api->list_address_transactions( $account_id, $address_id );
		if ( is_wp_error( $txs ) ) {
			return 'api_error';
		}

		$received = 0.0;
		foreach ( $txs as $tx ) {
			$amount = (float) ( $tx['amount']['amount'] ?? 0 );
			$status = $tx['status'] ?? '';
			$type   = $tx['type'] ?? '';
			if ( $amount > 0 && in_array( $status, array( 'completed', 'pending' ), true ) && 'send' !== $type ) {
				$received += $amount;
			}
		}

		if ( $received + 0.00000001 >= $expected ) {
			$target = $this->get_option( 'order_status', 'processing' );
			$order->payment_complete();
			$order->update_status( $target, sprintf( __( 'Cryptocurrency payment received (%s %s).', 'aegis-coinbase' ), $received, $crypto ) );
			ACW_Fee_Collector::collect( $order, 'personal' );
			return 'paid';
		}

		return 'waiting';
	}

	public function admin_test_script(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'woocommerce_page_wc-settings' !== $screen->id ) {
			return;
		}
		?>
		<script>
		jQuery(function($){
			$('#acw-test-connection').on('click', function(){
				var $btn = $(this), $res = $('#acw-test-result');
				$btn.prop('disabled', true);
				$res.text('Testing…');
				$.post(ajaxurl, { action: 'acw_test_connection' })
					.done(function(r){
						$res.html(r.success ? '<span style="color:green">' + r.data.message + '</span>' : '<span style="color:red">' + (r.data.message || 'Failed') + '</span>');
					})
					.fail(function(){ $res.html('<span style="color:red">Request failed</span>'); })
					.always(function(){ $btn.prop('disabled', false); });
			});
		});
		</script>
		<?php
	}

	public function ajax_test_connection(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}

		$transient = 'acw_test_lock_' . get_current_user_id();
		if ( get_transient( $transient ) ) {
			wp_send_json_error( array( 'message' => 'Please wait a few seconds before testing again.' ) );
		}
		set_transient( $transient, 1, 8 );

		$mode = $this->get_mode();

		if ( 'personal' === $mode ) {
			$key_name = $this->get_option( 'api_key_name' );
			$secret   = $this->get_option( 'api_private_key' );
			if ( empty( $key_name ) || empty( $secret ) ) {
				wp_send_json_error( array( 'message' => 'Personal API key name and secret are required.' ) );
			}

			$api      = new ACW_API_Personal( $key_name, $secret );
			$accounts = $api->list_accounts();
			if ( is_wp_error( $accounts ) ) {
				wp_send_json_error( array( 'message' => $accounts->get_error_message() ) );
			}

			$found = array();
			foreach ( $accounts as $account ) {
				$code = strtoupper( (string) ( $account['currency']['code'] ?? '' ) );
				if ( $code && isset( ACW_Constants::SUPPORTED_CRYPTOS[ $code ] ) ) {
					$found[] = $code;
				}
			}
			$found = array_unique( $found );
			sort( $found );

			$msg = empty( $found )
				? 'Connected, but no USDC/USDT/BTC/ETH/DOGE wallets found.'
				: 'Connected (Personal). Found wallets for: ' . implode( ', ', $found );

			wp_send_json_success( array( 'message' => $msg, 'wallets' => $found ) );
		}

		$key_name = $this->get_option( 'cdp_key_name' );
		$priv     = $this->get_option( 'cdp_private_key' );
		if ( empty( $key_name ) || empty( $priv ) ) {
			wp_send_json_error( array( 'message' => 'Business CDP key name and private key are required.' ) );
		}

		ACW_API_Business::$cdp_key_name    = $key_name;
		ACW_API_Business::$cdp_private_key = $priv;

		list( $ok, $result ) = ACW_API_Business::send_request( ACW_Constants::BUSINESS_API_PATH, array( 'limit' => 1 ), 'GET' );

		if ( $ok ) {
			wp_send_json_success( array( 'message' => 'Connected (Business). Authenticated successfully with Coinbase Business API.' ) );
		}

		$msg = is_string( $result ) ? $result : 'Authentication check completed.';
		if ( false !== strpos( strtolower( $msg ), 'authentication' ) || false !== strpos( strtolower( $msg ), '401' ) ) {
			wp_send_json_error( array( 'message' => $msg ) );
		}

		wp_send_json_success( array( 'message' => 'Business credentials accepted (JWT generated). ' . $msg ) );
	}

	public function is_available(): bool {
		if ( 'yes' !== $this->enabled ) {
			return false;
		}

		if ( 'personal' === $this->get_mode() ) {
			if ( empty( $this->get_option( 'api_key_name' ) ) || empty( $this->get_option( 'api_private_key' ) ) ) {
				return false;
			}
		} else {
			if ( empty( $this->get_option( 'cdp_key_name' ) ) || empty( $this->get_option( 'cdp_private_key' ) ) ) {
				return false;
			}
		}

		return parent::is_available();
	}

	public function log( string $message, string $level = 'info' ): void {
		if ( 'yes' !== $this->get_option( 'debug' ) ) {
			return;
		}
		$logger = wc_get_logger();
		$logger->log( $level, $message, array( 'source' => 'aegis-coinbase' ) );
	}
}
