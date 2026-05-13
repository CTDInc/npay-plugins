<?php
/**
 * NPay for LearnPress uninstall handler.
 *
 * @package NPay_LearnPress
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove plugin options.
delete_option( 'learn_press_npay' );
delete_option( 'npay_learnpress_settings' );
delete_option( 'npay_learnpress_version' );

// Remove transaction meta saved on orders.
global $wpdb;
$wpdb->query(
	"DELETE FROM {$wpdb->postmeta} WHERE meta_key IN (
		'_npay_reference_code',
		'_npay_gateway',
		'_npay_transfer_amount',
		'_npay_transaction_date',
		'_npay_notes'
	)"
);
