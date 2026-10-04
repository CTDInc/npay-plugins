<?php
/**
 * NPay Order Helper.
 *
 * @package NPay_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helper utilities for NPay orders.
 */
class NPay_Order_Helper {

	/**
	 * Meta key for payment code.
	 */
	const META_PAYMENT_CODE = '_npay_payment_code';

	/**
	 * Meta key for transaction reference.
	 */
	const META_TRANSACTION_ID = '_npay_transaction_id';

	/**
	 * Generate a payment code for an order.
	 *
	 * Format: <PREFIX><ORDER_ID>. Example: NPAY123.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $prefix   Optional override prefix.
	 * @return string
	 */
	public static function generate_code( $order_id, $prefix = '' ) {
		if ( empty( $prefix ) ) {
			$gateway_settings = get_option( 'woocommerce_npay_settings', array() );
			$prefix           = isset( $gateway_settings['prefix_code'] ) && '' !== $gateway_settings['prefix_code']
				? $gateway_settings['prefix_code']
				: 'NPAY';
		}

		$prefix = self::sanitize_prefix( $prefix );
		$code   = $prefix . absint( $order_id );

		/**
		 * Filter the generated NPay payment code.
		 *
		 * @param string $code     Generated code.
		 * @param int    $order_id Order ID.
		 * @param string $prefix   Prefix used.
		 */
		return apply_filters( 'npay_payment_code', $code, $order_id, $prefix );
	}

	/**
	 * Sanitize prefix: A-Z 0-9 only, uppercase, max 10 chars.
	 *
	 * @param string $prefix Raw prefix.
	 * @return string
	 */
	public static function sanitize_prefix( $prefix ) {
		$prefix = preg_replace( '/[^A-Za-z0-9]/', '', (string) $prefix );
		$prefix = strtoupper( substr( $prefix, 0, 10 ) );
		if ( '' === $prefix ) {
			$prefix = 'NPAY';
		}
		return $prefix;
	}

	/**
	 * Get or create payment code for an order, persisted in order meta.
	 *
	 * @param WC_Order $order Order.
	 * @return string
	 */
	public static function get_or_create_code( $order ) {
		$code = $order->get_meta( self::META_PAYMENT_CODE );
		if ( ! $code ) {
			$code = self::generate_code( $order->get_id() );
			$order->update_meta_data( self::META_PAYMENT_CODE, $code );
			$order->save();
		}
		return $code;
	}

	/**
	 * Find a pending order by payment code matching anywhere in the transfer content.
	 *
	 * @param string $content Transfer description / content.
	 * @return WC_Order|null
	 */
	public static function find_order_by_content( $content ) {
		if ( empty( $content ) ) {
			return null;
		}

		$content_upper = strtoupper( (string) $content );

		// Try direct meta lookup first.
		$query = new WC_Order_Query(
			array(
				'limit'    => 50,
				'status'   => array( 'pending', 'on-hold' ),
				'meta_key' => self::META_PAYMENT_CODE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'orderby'  => 'date',
				'order'    => 'DESC',
				'return'   => 'objects',
			)
		);

		$orders = $query->get_orders();
		foreach ( $orders as $order ) {
			$code = $order->get_meta( self::META_PAYMENT_CODE );
			if ( $code && false !== strpos( $content_upper, strtoupper( $code ) ) ) {
				return $order;
			}
		}

		// Fallback: try to extract numeric order id by prefix from settings.
		$settings = get_option( 'woocommerce_npay_settings', array() );
		$prefix   = isset( $settings['prefix_code'] ) ? self::sanitize_prefix( $settings['prefix_code'] ) : 'NPAY';

		if ( preg_match( '/' . preg_quote( $prefix, '/' ) . '(\d+)/i', $content_upper, $m ) ) {
			$order_id = absint( $m[1] );
			$order    = wc_get_order( $order_id );
			if ( $order && in_array( $order->get_status(), array( 'pending', 'on-hold' ), true ) ) {
				return $order;
			}
		}

		return null;
	}

	/**
	 * Common short codes → vietnam-qr-pay slug accepted by qr.npay.vn (`ngan_hang`).
	 * qr.npay.vn rejects short codes such as "vcb" / "mb" with HTTP 400.
	 */
	const BANK_ALIASES = array(
		'vcb'    => 'vietcombank',
		'tcb'    => 'techcombank',
		'ctg'    => 'vietinbank',
		'icb'    => 'vietinbank',
		'vtb'    => 'vietinbank',
		'mb'     => 'mbbank',
		'vpb'    => 'vpbank',
		'tpb'    => 'tpbank',
		'stb'    => 'sacombank',
		'hdb'    => 'hdbank',
		'eib'    => 'eximbank',
		'vba'    => 'agribank',
		'agr'    => 'agribank',
		'lpb'    => 'lienvietpostbank',
		'lpbank' => 'lienvietpostbank',
		'nab'    => 'namabank',
		'abb'    => 'abbank',
		'bab'    => 'bacabank',
		'pvcb'   => 'pvcombank',
		'seab'   => 'seabank',
		'klb'    => 'kienlongbank',
		'vab'    => 'vietabank',
		'sgicb'  => 'saigonbank',
		'bvb'    => 'banviet',
	);

	/**
	 * Bank setting → qr.npay.vn query param: 6-digit Napas BIN → `ma_bin`,
	 * otherwise `ngan_hang` with short codes mapped to slugs.
	 *
	 * @param string $bank Bank BIN, short code (VCB, MB) or slug (vietcombank).
	 * @return array<string, string>
	 */
	public static function bank_param( $bank ) {
		$bank = strtolower( (string) preg_replace( '/[\s_-]+/', '', trim( (string) $bank ) ) );
		if ( preg_match( '/^\d{6}$/', $bank ) ) {
			return array( 'ma_bin' => $bank );
		}
		return array( 'ngan_hang' => isset( self::BANK_ALIASES[ $bank ] ) ? self::BANK_ALIASES[ $bank ] : $bank );
	}

	/**
	 * Build QR image URL.
	 *
	 * @param array $args {
	 *     @type string $account_number Bank account number.
	 *     @type string $bank           Bank BIN (970436), short code (VCB) or slug (vietcombank).
	 *     @type float  $amount         Amount.
	 *     @type string $description    Transfer description (payment code).
	 *     @type string $template       Template (compact, qronly, etc).
	 * }
	 * @return string
	 */
	public static function build_qr_url( $args ) {
		$defaults = array(
			'account_number' => '',
			'account_name'   => '',
			'bank'           => '',
			'amount'         => 0,
			'description'    => '',
			'template'       => 'compact',
		);
		$args     = wp_parse_args( $args, $defaults );

		// NPay gen-qr-service: /qrcard = VietQR card, /qrpay = bare QR.
		$route  = ( 'qronly' === $args['template'] ) ? '/qrpay' : '/qrcard';
		$params = self::bank_param( $args['bank'] ) + array(
			'tai_khoan' => $args['account_number'],
			'so_tien'   => number_format( (float) $args['amount'], 0, '.', '' ),
			'noi_dung'  => $args['description'],
		);
		if ( '/qrcard' === $route && '' !== $args['account_name'] ) {
			$params['chu_tai_khoan'] = $args['account_name'];
		}

		return NPAY_QR_BASE . $route . '?' . http_build_query( $params );
	}
}
