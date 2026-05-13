<?php
/**
 * NPay Webhook Handler.
 *
 * @package NPay_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST endpoint that receives NPay webhook calls.
 */
class NPay_Webhook_Handler {

	/**
	 * Singleton instance.
	 *
	 * @var NPay_Webhook_Handler
	 */
	protected static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return NPay_Webhook_Handler
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes() {
		register_rest_route(
			'npay/v1',
			'/webhook',
			array(
				'methods'             => array( 'POST' ),
				'callback'            => array( $this, 'handle_webhook' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Load gateway settings.
	 *
	 * @return array
	 */
	protected function get_settings() {
		return get_option( 'woocommerce_npay_settings', array() );
	}

	/**
	 * Verify webhook authentication.
	 *
	 * Supports:
	 *  - Authorization: Apikey <token>
	 *  - X-Npay-Signature: HMAC-SHA256(body, webhook_secret)
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return true|WP_Error
	 */
	protected function authenticate( $request ) {
		$settings = $this->get_settings();
		$token    = isset( $settings['api_token'] ) ? trim( $settings['api_token'] ) : '';
		$secret   = isset( $settings['webhook_secret'] ) ? trim( $settings['webhook_secret'] ) : '';

		// Authorization header.
		$auth_header = (string) $request->get_header( 'authorization' );
		if ( '' !== $token && '' !== $auth_header ) {
			// Format: "Apikey <token>".
			if ( preg_match( '/^Apikey\s+(.+)$/i', $auth_header, $m ) ) {
				if ( hash_equals( $token, trim( $m[1] ) ) ) {
					return true;
				}
			}
			// Fallback bearer.
			if ( preg_match( '/^Bearer\s+(.+)$/i', $auth_header, $m ) ) {
				if ( hash_equals( $token, trim( $m[1] ) ) ) {
					return true;
				}
			}
		}

		// HMAC signature header.
		$signature = (string) $request->get_header( 'x_npay_signature' );
		if ( '' === $signature ) {
			$signature = (string) $request->get_header( 'x-npay-signature' );
		}

		if ( '' !== $secret && '' !== $signature ) {
			$body     = $request->get_body();
			$computed = hash_hmac( 'sha256', $body, $secret );
			if ( hash_equals( $computed, trim( $signature ) ) ) {
				return true;
			}
		}

		return new WP_Error(
			'npay_unauthorized',
			esc_html__( 'Invalid NPay credentials.', 'npay-woocommerce' ),
			array( 'status' => 401 )
		);
	}

	/**
	 * Handle webhook payload.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_webhook( $request ) {
		$auth = $this->authenticate( $request );
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}

		$payload = $request->get_json_params();
		if ( empty( $payload ) || ! is_array( $payload ) ) {
			return new WP_Error(
				'npay_bad_payload',
				esc_html__( 'Invalid payload.', 'npay-woocommerce' ),
				array( 'status' => 400 )
			);
		}

		$transaction_id    = isset( $payload['id'] ) ? sanitize_text_field( (string) $payload['id'] ) : '';
		$gateway_bank      = isset( $payload['gateway'] ) ? sanitize_text_field( $payload['gateway'] ) : '';
		$transfer_type     = isset( $payload['transferType'] ) ? sanitize_text_field( $payload['transferType'] ) : '';
		$transfer_amount   = isset( $payload['transferAmount'] ) ? floatval( $payload['transferAmount'] ) : 0;
		$content           = isset( $payload['content'] ) ? sanitize_text_field( $payload['content'] ) : '';
		$code_field        = isset( $payload['code'] ) ? sanitize_text_field( $payload['code'] ) : '';
		$reference_code    = isset( $payload['referenceCode'] ) ? sanitize_text_field( $payload['referenceCode'] ) : '';
		$transaction_date  = isset( $payload['transactionDate'] ) ? sanitize_text_field( $payload['transactionDate'] ) : '';
		$account_number    = isset( $payload['accountNumber'] ) ? sanitize_text_field( $payload['accountNumber'] ) : '';

		// Only process incoming transfers.
		if ( 'in' !== $transfer_type ) {
			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => 'Ignored non-incoming transfer.',
				),
				200
			);
		}

		// Find order: prefer explicit code field, else parse content.
		$order = null;
		if ( $code_field ) {
			$order = NPay_Order_Helper::find_order_by_content( $code_field );
		}
		if ( ! $order ) {
			$order = NPay_Order_Helper::find_order_by_content( $content );
		}

		if ( ! $order ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Order not found for content: ' . $content,
				),
				200
			);
		}

		// Idempotency: skip if same transaction already processed.
		$existing_txn = $order->get_meta( NPay_Order_Helper::META_TRANSACTION_ID );
		if ( $existing_txn && (string) $existing_txn === (string) $transaction_id ) {
			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => 'Already processed.',
					'order'   => $order->get_id(),
				),
				200
			);
		}

		// Verify amount.
		$expected = floatval( $order->get_total() );
		if ( $transfer_amount + 0.0001 < $expected ) {
			$order->add_order_note(
				sprintf(
					/* translators: 1: received amount, 2: expected amount, 3: txn id */
					__( 'NPay: received insufficient transfer %1$s (expected %2$s). Tx #%3$s', 'npay-woocommerce' ),
					wc_price( $transfer_amount ),
					wc_price( $expected ),
					$transaction_id
				)
			);
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Insufficient amount.',
				),
				200
			);
		}

		// Mark paid.
		$order->update_meta_data( NPay_Order_Helper::META_TRANSACTION_ID, $transaction_id );
		$order->set_transaction_id( $transaction_id );

		$note = sprintf(
			/* translators: 1: bank, 2: amount, 3: reference, 4: account, 5: date */
			__( 'NPay payment received. Bank: %1$s, Amount: %2$s, Reference: %3$s, Account: %4$s, Date: %5$s', 'npay-woocommerce' ),
			$gateway_bank,
			wc_price( $transfer_amount ),
			$reference_code,
			$account_number,
			$transaction_date
		);
		$order->add_order_note( $note );

		$order->payment_complete( $transaction_id );
		$order->save();

		/**
		 * Fires after NPay payment is successfully processed for an order.
		 *
		 * @param WC_Order $order   The order.
		 * @param array    $payload Raw payload.
		 */
		do_action( 'npay_payment_completed', $order, $payload );

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => 'Order updated.',
				'order'   => $order->get_id(),
			),
			200
		);
	}
}
