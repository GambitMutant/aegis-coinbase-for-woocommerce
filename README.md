# AEGIS Coinbase for WooCommerce

A unified WooCommerce payment gateway that lets customers pay with cryptocurrency through either a **personal Coinbase account** or **Coinbase Business Checkouts**.

Only one mode is active at a time. The plugin is HPOS and Checkout Blocks compatible.

**Author:** [Gambit Mutant](https://github.com/GambitMutant)

---

## Features

### Personal mode
- Uses the Coinbase App API
- Creates a unique receive address for every order
- Displays the exact amount, address, and QR code
- Polls for payment (AJAX on the payment page + WP-Cron)
- Supports USDC (Base), USDT (Ethereum), Bitcoin, Ethereum, and Dogecoin

### Business mode
- Uses the Coinbase Business Checkouts API
- Redirects the customer to a secure Coinbase-hosted checkout
- Verifies webhooks with X-Hook0-Signature
- Configurable order status, minimum order amount, and optional USDC pricing display on products

### Development fee
A disclosed **0.5% fee** is collected on successful transactions to support continued development of the plugin.

- In **Personal mode** the fee is sent automatically from the merchant’s Coinbase account to the author’s personal Coinbase account.
- In **Business mode** the fee is recorded on the order.

---

## Requirements

- PHP 7.4+ (works on 8.0–8.4+; the `sodium` extension is required for Personal mode)
- WordPress 6.0+
- WooCommerce 8.0+ (tested through 12.1+)
- CDP API keys with the correct scopes

| Mode | Key type | Required permissions |
|------|----------|----------------------|
| Personal | Ed25519 | Receive + View (and Send for the fee transfer) |
| Business | ECDSA (ES256) | Checkouts + webhook secret |

---

## Installation

1. Download the latest release (or clone this repository) and upload the `aegis-coinbase-for-woocommerce` folder to `/wp-content/plugins/`.
2. Activate the plugin in WordPress.
3. Go to **WooCommerce → Settings → Payments → AEGIS Coinbase**.
4. Choose **Personal** or **Business** mode.
5. Enter the corresponding API credentials.
6. Click **Test API connection** to verify.
7. Save changes.

---

## Setup

### Personal mode
1. Create a CDP API key (Ed25519) in the Coinbase Developer Platform.
2. Grant **Receive** and **View** permissions (add **Send** if you want the automatic 0.5% fee transfer to work).
3. Paste the full API key name and the base64 Ed25519 secret into the plugin settings.
4. Select which cryptocurrencies to offer (USDC, USDT, BTC, ETH, DOGE).
5. Test the connection.

### Business mode
1. Create a CDP API key (ECDSA / ES256) with the **Checkouts** scope.
2. Paste the key name and PEM private key into the plugin settings.
3. Copy the Webhook URL shown in the settings and create a webhook subscription in Coinbase Business.
4. Paste the webhook secret into the plugin.
5. Test the connection.

---

## Donations

Donations support **The AEGIS Alliance’s project funding**, with a secondary purpose of business operations funding.

- **AEGIS News:** https://theaegisalliance.com
- **AEGIS Shop:** https://shop.theaegisalliance.com
- **PayPal / Venmo / Zelle:** `aegisalliance`
- **Cash App:** `$aegisalliance`
- **USDC:** `0x39C7e115C4184f2E646D44E3681A87a22a93341B`
- **BTC:** `0x876dA7d647C37FB7A8E94Fa287FefeB192dd49fc`
- **DOGE:** `0x94ef8226B0409f382CAb78D2695FD3d7384f6bd6`
- **ETH:** `0xcD8B620962e9ff4a098c4134f1C82fDf96236F87`
- **USDT:** `0x4aF2eE05A313c8a8dd53EdA87DDA69F3E375c3B5`

---

## Compatibility

The plugin uses unique prefixes, standard WooCommerce hooks, and declares HPOS and Checkout Blocks compatibility. It is designed to avoid common conflicts with popular themes and page builders, including:

- **WP Bakery Page Builder** (tested with 8.x–9.0+ and WC Builder Pro layouts)
- Elementor
- Astra, GeneratePress, Storefront, Kadence, Divi, Flatsome, Woodmart
- Most standard payment and shipping plugins

Assets load correctly on custom checkout pages built with WP Bakery shortcodes.

---

## License

GPL-2.0-or-later
