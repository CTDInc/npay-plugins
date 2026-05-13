=== NPay for WooCommerce ===
Contributors: npay
Tags: woocommerce, payment, gateway, vietnam, bank transfer, qr code, vietqr, npay
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.txt

Accept Vietnamese bank transfer payments in WooCommerce with NPay — QR code, auto-reconciliation via webhook.

== Description ==

NPay for WooCommerce lets your customers pay by scanning a VietQR code or doing a manual bank transfer. Orders are confirmed automatically the moment NPay detects the matching transaction.

Features:

* VietQR code generated on the fly with order amount and payment code pre-filled.
* Automatic payment code generation `NPAY{order_id}` (prefix configurable).
* Webhook endpoint at `wp-json/npay/v1/webhook` with Apikey or HMAC SHA-256 verification.
* Thank-you page auto-polls order status every 5 seconds — no refresh needed.
* Countdown timer and one-click copy for account number and transfer content.
* Theme override for the payment instructions template.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, or upload the zip via **Plugins → Add New → Upload**.
2. Activate the plugin via the **Plugins** menu in WordPress.
3. Go to **WooCommerce → Settings → Payments → NPay** to configure.
4. In your NPay dashboard, add the webhook URL shown on the plugin settings page.

== Frequently Asked Questions ==

= Does it work with VietQR? =

Yes — QR images are served from `qr.npay.vn` which produces standard VietQR codes compatible with all Vietnamese banking apps.

= How is the webhook authenticated? =

Either `Authorization: Apikey <token>` or `X-Npay-Signature: <hmac-sha256>`. Configure your preferred method in the settings.

= Can I customize the thank-you page layout? =

Copy `templates/payment-instructions.php` to `yourtheme/npay-woocommerce/payment-instructions.php` and edit.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
First public release.
