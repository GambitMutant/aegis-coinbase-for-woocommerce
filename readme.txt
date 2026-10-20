=== AEGIS Coinbase for WooCommerce ===
Contributors: gambitmutant
Tags: woocommerce, coinbase, usdc, usdt, bitcoin, ethereum, dogecoin, crypto, payment gateway
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Author: Gambit Mutant
Author URI: https://github.com/GambitMutant

Accept cryptocurrency payments via personal Coinbase account or Coinbase Business Checkouts. Disclosed 0.5% fee. Donations support The AEGIS Alliance.

== Description ==

Unified payment gateway that supports two mutually exclusive modes:

**Personal mode**
* Uses the Coinbase App API
* Creates a unique receive address per order
* Shows exact amount, address and QR code
* Polls for payment (AJAX + WP-Cron)
* Supports USDC (Base), USDT (Ethereum), Bitcoin, Ethereum, Dogecoin

**Business mode**
* Uses the Coinbase Business Checkouts API
* Redirects customer to a secure Coinbase checkout
* Verifies webhooks (X-Hook0-Signature)
* Configurable order status, minimum amount, USDC pricing display

**Fee**
A disclosed 0.5% fee is collected on successful transactions to support continued development. In Personal mode the fee is sent automatically from the merchant’s Coinbase account to the author’s personal Coinbase account. In Business mode the fee is recorded on the order.

**Donations**
Donations support The AEGIS Alliance’s project funding, with a secondary purpose of business operations funding.

* Website: [AEGIS News](https://theaegisalliance.com) · [AEGIS Shop](https://shop.theaegisalliance.com)
* PayPal / Venmo / Zelle: `aegisalliance`
* Cash App: `$aegisalliance`
* Crypto accepted at Coinbase:
* USDC: `0x39C7e115C4184f2E646D44E3681A87a22a93341B`
* BTC: `3PCJwFfVytUCW2mfgUgKbjWmqsYvTzgCH1`
* ETH: `0xcD8B620962e9ff4a098c4134f1C82fDf96236F87`
* DOGE: `DAo22guf8pmNcF1V7FvuqrXTgy2khuPysB`
* USDT: `0x4aF2eE05A313c8a8dd53EdA87DDA69F3E375c3B5`

**Compatibility**
Tested and written to avoid common conflicts with popular WooCommerce extensions and themes (Elementor, Astra, GeneratePress, Storefront, Kadence, Divi, Flatsome, Woodmart, and most standard payment/shipping plugins). Uses unique prefixes, standard WooCommerce hooks, HPOS and Checkout Blocks declarations, and late loading.

**Requirements**
* PHP 7.4+ (works on 8.0–8.4+; sodium extension required for Personal mode)
* WordPress 6.0+ (tested up to current 6.9 / 7.x series)
* WooCommerce 8.0+ (tested through 12.1+)
* CDP API keys with appropriate scopes
* For Personal: Ed25519 key with Receive + View (and Send for fee transfer)
* For Business: ECDSA (ES256) key with Checkouts scope + webhook secret

== Installation ==

1. Upload the plugin ZIP and activate.
2. Go to WooCommerce → Settings → Payments → AEGIS Coinbase.
3. Choose mode (Personal or Business).
4. Enter the corresponding API credentials.
5. Click “Test API connection”.
6. Save.

== Changelog ==

= 1.0.1 =
* Updated the Coinbase donation addresses. Bitcoin is `3PCJwFfVytUCW2mfgUgKbjWmqsYvTzgCH1`. Dogecoin is `DAo22guf8pmNcF1V7FvuqrXTgy2khuPysB`.

= 1.0.0 =
* Initial combined release of Personal and Business Coinbase support.
* Mode selector (only one mode active).
* Test connection button for both modes.
* Disclosed 0.5% development fee.
* Author credentials encrypted in the plugin.
* Broader PHP / WordPress / WooCommerce compatibility and security hardening.
* Added USDT (Ethereum) support in Personal mode.
