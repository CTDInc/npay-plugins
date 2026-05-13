<?php
/**
 * NPay for WooCommerce uninstall.
 *
 * Cleans up plugin options when uninstalled.
 *
 * @package NPay_WooCommerce
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete gateway settings.
delete_option( 'woocommerce_npay_settings' );

// Delete any other plugin options.
delete_option( 'npay_wc_version' );

// Note: We intentionally do NOT remove order meta (_npay_payment_code, _npay_transaction_id)
// so completed orders keep their audit trail.
