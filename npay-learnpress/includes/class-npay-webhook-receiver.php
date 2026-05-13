<?php
/**
 * NPay Webhook receiver - matches incoming transactions to LearnPress orders.
 *
 * @package NPay_LearnPress
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class NPay_Webhook_Receiver
 */
class NPay_Webhook_Receiver {

	/**
	 * Singleton instance.
	 *
	 * @var NPay_Webhook_Receiver|null
	 */
	private static $instance = null;

	/**
	 * Get singleton.
	 *
	 * @return NPay_Webhook_Receiver
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
		add_action( 'rest_api_init', array( $this, 'register_route' ) );
	}

	/**
	 * Register webhook REST route.
	 */
	public function register_route() {
		register_rest_route(
			'npay/v1',
			'/learnpress-webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_webhook' ),
				'permission_callback' => array( $this, 'check_authorization' ),
			)
		);
	}

	/**
	 * Authorize incoming webhook via Apikey header.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function check_authorization( $request ) {
		$gateway = new LP_Gateway_NPay();
		$token   = trim( (string) $gateway->api_token );

		if ( empty( $token ) ) {
			return new WP_Error(
				'npay_no_token',
				__( 'Chưa cấu hình NPay API token.', 'npay-learnpress' ),
				array( 'status' => 401 )
			);
		}

		$auth = (string) $request->get_header( 'authorization' );
		if ( empty( $auth ) && function_exists( 'getallheaders' ) ) {
			$h = getallheaders();
			if ( isset( $h['Authorization'] ) ) {
				$auth = $h['Authorization'];
			}
		}

		if ( stripos( $auth, 'Apikey ' ) !== 0 ) {
			return new WP_Error(
				'npay_invalid_auth',
				__( 'Sai định dạng Authorization header.', 'npay-learnpress' ),
				array( 'status' => 401 )
			);
		}

		$received = trim( substr( $auth, 7 ) );
		if ( ! hash_equals( $token, $received ) ) {
			return new WP_Error(
				'npay_bad_token',
				__( 'Token webhook không hợp lệ.', 'npay-learnpress' ),
				array( 'status' => 401 )
			);
		}

		return true;
	}

	/**
	 * Handle webhook payload.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function handle_webhook( $request ) {
		$payload = $request->get_json_params();
		if ( empty( $payload ) || ! is_array( $payload ) ) {
			$payload = $request->get_params();
		}

		$content         = isset( $payload['content'] ) ? (string) $payload['content'] : '';
		$transfer_type   = isset( $payload['transferType'] ) ? (string) $payload['transferType'] : '';
		$transfer_amount = isset( $payload['transferAmount'] ) ? (float) $payload['transferAmount'] : 0;
		$reference_code  = isset( $payload['referenceCode'] ) ? (string) $payload['referenceCode'] : '';
		$gateway_name    = isset( $payload['gateway'] ) ? (string) $payload['gateway'] : '';
		$transaction_date = isset( $payload['transactionDate'] ) ? (string) $payload['transactionDate'] : '';

		if ( 'in' !== $transfer_type ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Bỏ qua giao dịch không phải tiền vào.', 'npay-learnpress' ),
				),
				200
			);
		}

		$order_id = $this->extract_order_id( $content );
		if ( ! $order_id ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Không tìm thấy mã đơn (NPAY{order_id}) trong nội dung chuyển khoản.', 'npay-learnpress' ),
				),
				200
			);
		}

		$order = $this->get_order( $order_id );
		if ( ! $order ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => sprintf( /* translators: %d order id */ __( 'Không tìm thấy đơn hàng #%d.', 'npay-learnpress' ), $order_id ),
				),
				200
			);
		}

		// Idempotency: skip if already completed.
		$current_status = method_exists( $order, 'get_status' ) ? $order->get_status() : '';
		if ( 'completed' === $current_status ) {
			return new WP_REST_Response(
				array(
					'success'  => true,
					'message'  => __( 'Đơn hàng đã hoàn tất trước đó.', 'npay-learnpress' ),
					'order_id' => $order_id,
				),
				200
			);
		}

		// Optional: amount validation.
		$expected = $this->get_order_total( $order );
		if ( $expected > 0 && $transfer_amount > 0 && $transfer_amount + 0.01 < $expected ) {
			// Underpaid: log meta but do not auto-complete.
			$this->add_order_note( $order, sprintf( /* translators: 1 paid 2 expected */ __( 'NPay: nhận được %1$s nhưng cần %2$s - chờ kiểm tra thủ công.', 'npay-learnpress' ), $transfer_amount, $expected ) );
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Số tiền chuyển khoản thiếu so với đơn hàng.', 'npay-learnpress' ),
				),
				200
			);
		}

		// Save transaction meta.
		if ( function_exists( 'learn_press_update_order_item_meta' ) || function_exists( 'update_post_meta' ) ) {
			update_post_meta( $order_id, '_npay_reference_code', sanitize_text_field( $reference_code ) );
			update_post_meta( $order_id, '_npay_gateway', sanitize_text_field( $gateway_name ) );
			update_post_meta( $order_id, '_npay_transfer_amount', floatval( $transfer_amount ) );
			update_post_meta( $order_id, '_npay_transaction_date', sanitize_text_field( $transaction_date ) );
		}

		// Update status to completed.
		if ( method_exists( $order, 'update_status' ) ) {
			$order->update_status( 'completed' );
		}

		// Update user-item enrollment status.
		$this->complete_enrollments( $order );

		$this->add_order_note(
			$order,
			sprintf(
				/* translators: 1: amount, 2: reference */
				__( 'NPay: đã ghi nhận thanh toán %1$s (ref: %2$s).', 'npay-learnpress' ),
				$transfer_amount,
				$reference_code
			)
		);

		return new WP_REST_Response(
			array(
				'success'  => true,
				'order_id' => $order_id,
				'message'  => __( 'Đơn hàng đã được cập nhật thành công.', 'npay-learnpress' ),
			),
			200
		);
	}

	/**
	 * Extract order id from transfer content (looks for NPAY<digits>).
	 *
	 * @param string $content Content.
	 * @return int
	 */
	protected function extract_order_id( $content ) {
		if ( preg_match( '/NPAY[\s_-]*?(\d{1,12})/i', $content, $m ) ) {
			return absint( $m[1] );
		}
		return 0;
	}

	/**
	 * Mark all enrollments tied to the order completed.
	 *
	 * @param mixed $order LP order.
	 */
	protected function complete_enrollments( $order ) {
		if ( ! is_object( $order ) || ! function_exists( 'learn_press_update_user_item_meta' ) ) {
			return;
		}

		$user_id = 0;
		if ( method_exists( $order, 'get_user_id' ) ) {
			$user_id = absint( $order->get_user_id() );
		}

		$items = array();
		if ( method_exists( $order, 'get_items' ) ) {
			$items = (array) $order->get_items();
		}

		global $wpdb;
		foreach ( $items as $item ) {
			$course_id = isset( $item['course_id'] ) ? absint( $item['course_id'] ) : ( isset( $item['item_id'] ) ? absint( $item['item_id'] ) : 0 );
			if ( ! $course_id || ! $user_id ) {
				continue;
			}

			$enrollment = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT user_item_id FROM {$wpdb->prefix}learnpress_user_items WHERE user_id=%d AND item_id=%d AND item_type=%s ORDER BY user_item_id DESC LIMIT 1",
					$user_id,
					$course_id,
					'lp_course'
				)
			);

			if ( $enrollment ) {
				learn_press_update_user_item_meta( $enrollment, 'status', 'completed' );
				learn_press_update_user_item_meta( $enrollment, 'grade', 'passed' );
			}
		}
	}

	/**
	 * Resolve LP order.
	 *
	 * @param int $order_id Order id.
	 * @return mixed
	 */
	protected function get_order( $order_id ) {
		if ( function_exists( 'learn_press_get_order' ) ) {
			return learn_press_get_order( $order_id );
		}
		if ( class_exists( 'LP_Order' ) ) {
			return new LP_Order( $order_id );
		}
		return null;
	}

	/**
	 * Get order total.
	 *
	 * @param mixed $order Order.
	 * @return float
	 */
	protected function get_order_total( $order ) {
		if ( is_object( $order ) && method_exists( $order, 'get_total' ) ) {
			return (float) $order->get_total();
		}
		return 0;
	}

	/**
	 * Add a note to an order if possible.
	 *
	 * @param mixed  $order Order.
	 * @param string $note  Note.
	 */
	protected function add_order_note( $order, $note ) {
		if ( is_object( $order ) && method_exists( $order, 'add_note' ) ) {
			$order->add_note( $note );
		} elseif ( is_object( $order ) && method_exists( $order, 'get_id' ) ) {
			$existing = get_post_meta( $order->get_id(), '_npay_notes', true );
			if ( ! is_array( $existing ) ) {
				$existing = array();
			}
			$existing[] = array(
				'time' => current_time( 'mysql' ),
				'note' => $note,
			);
			update_post_meta( $order->get_id(), '_npay_notes', $existing );
		}
	}
}
