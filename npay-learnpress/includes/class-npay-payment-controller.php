<?php
/**
 * NPay Payment Page Controller - renders QR + polls status.
 *
 * @package NPay_LearnPress
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class NPay_Payment_Controller
 */
class NPay_Payment_Controller {

	/**
	 * Singleton.
	 *
	 * @var NPay_Payment_Controller|null
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return NPay_Payment_Controller
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
		add_action( 'template_redirect', array( $this, 'maybe_render_payment_page' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Display payment page when ?npay-payment=1.
	 */
	public function maybe_render_payment_page() {
		if ( ! isset( $_GET['npay-payment'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$key      = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! $order_id ) {
			wp_die( esc_html__( 'Thiếu thông tin đơn hàng.', 'npay-learnpress' ) );
		}

		$gateway = new LP_Gateway_NPay();
		if ( ! hash_equals( $gateway->get_order_key( $order_id ), $key ) ) {
			wp_die( esc_html__( 'Mã xác thực đơn hàng không hợp lệ.', 'npay-learnpress' ) );
		}

		$order = $this->get_order( $order_id );
		if ( ! $order ) {
			wp_die( esc_html__( 'Không tìm thấy đơn hàng.', 'npay-learnpress' ) );
		}

		$amount     = $this->get_order_total( $order );
		$code       = LP_Gateway_NPay::build_code( $order_id );
		$qr_url     = $gateway->build_qr_url( $order_id, $amount );
		$status_url = rest_url( 'npay/v1/learnpress/order-status/' . $order_id );

		$data = array(
			'order'          => $order,
			'order_id'       => $order_id,
			'amount'         => $amount,
			'amount_display' => $this->format_amount( $amount ),
			'code'           => $code,
			'qr_url'         => $qr_url,
			'bank_bin'       => $gateway->bank_bin,
			'bank_name'      => $this->bank_name( $gateway->bank_bin ),
			'account_number' => $gateway->account_number,
			'account_name'   => $gateway->account_name,
			'status_url'     => $status_url,
			'order_key'      => $key,
			'expire_minutes' => 30,
		);

		// Localize JS.
		wp_localize_script(
			'npay-lp-poll',
			'NPayLP',
			array(
				'statusUrl'      => $status_url,
				'orderId'        => $order_id,
				'orderKey'       => $key,
				'pollInterval'   => 4000,
				'expireMinutes'  => 30,
				'redirectOnPaid' => $this->thankyou_url( $order ),
				'i18n'           => array(
					'paid'         => __( 'Thanh toán thành công! Đang chuyển trang...', 'npay-learnpress' ),
					'expired'      => __( 'Phiên thanh toán đã hết hạn, vui lòng tạo đơn mới.', 'npay-learnpress' ),
					'errorChecking' => __( 'Lỗi khi kiểm tra trạng thái...', 'npay-learnpress' ),
				),
			)
		);

		$template = NPAY_LP_PLUGIN_DIR . 'templates/payment-page.php';
		if ( file_exists( $template ) ) {
			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
			extract( $data, EXTR_SKIP );
			get_header();
			include $template;
			get_footer();
			exit;
		}
	}

	/**
	 * REST routes.
	 */
	public function register_rest_routes() {
		register_rest_route(
			'npay/v1',
			'/learnpress/order-status/(?P<order_id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_order_status' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'order_id' => array(
						'validate_callback' => function ( $v ) {
							return is_numeric( $v );
						},
					),
				),
			)
		);
	}

	/**
	 * REST order status callback.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function rest_order_status( $request ) {
		$order_id = absint( $request['order_id'] );
		$order    = $this->get_order( $order_id );
		if ( ! $order ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Không tìm thấy đơn hàng.', 'npay-learnpress' ),
				),
				404
			);
		}

		$status = method_exists( $order, 'get_status' ) ? $order->get_status() : '';

		return new WP_REST_Response(
			array(
				'success'  => true,
				'order_id' => $order_id,
				'status'   => $status,
				'paid'     => in_array( $status, array( 'completed', 'processing' ), true ),
			),
			200
		);
	}

	/**
	 * Resolve LP order.
	 *
	 * @param int $order_id Order ID.
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
		if ( is_object( $order ) ) {
			if ( method_exists( $order, 'get_total' ) ) {
				return (float) $order->get_total();
			}
			if ( method_exists( $order, 'get_data' ) ) {
				$d = $order->get_data();
				if ( isset( $d['total'] ) ) {
					return (float) $d['total'];
				}
			}
		}
		return 0;
	}

	/**
	 * Format amount.
	 *
	 * @param float $amount Amount.
	 * @return string
	 */
	protected function format_amount( $amount ) {
		if ( function_exists( 'learn_press_format_price' ) ) {
			return learn_press_format_price( $amount, true );
		}
		return number_format( (float) $amount, 0, ',', '.' ) . ' đ';
	}

	/**
	 * Thank-you URL.
	 *
	 * @param mixed $order Order.
	 * @return string
	 */
	protected function thankyou_url( $order ) {
		if ( is_object( $order ) && method_exists( $order, 'get_checkout_order_received_url' ) ) {
			return $order->get_checkout_order_received_url();
		}
		if ( function_exists( 'learn_press_get_endpoint_url' ) ) {
			return learn_press_get_endpoint_url( 'lp-order-received', '', learn_press_get_page_link( 'checkout' ) );
		}
		return home_url( '/' );
	}

	/**
	 * Bank BIN to name (small map).
	 *
	 * @param string $bin BIN.
	 * @return string
	 */
	protected function bank_name( $bin ) {
		$map = array(
			'970436' => 'Vietcombank',
			'970422' => 'MBBank',
			'970418' => 'BIDV',
			'970415' => 'VietinBank',
			'970405' => 'Agribank',
			'970407' => 'Techcombank',
			'970432' => 'VPBank',
			'970416' => 'ACB',
			'970423' => 'TPBank',
			'970403' => 'Sacombank',
			'970448' => 'OCB',
			'970454' => 'VietCapitalBank',
			'970441' => 'VIB',
			'970437' => 'HDBank',
		);
		return isset( $map[ $bin ] ) ? $map[ $bin ] : $bin;
	}
}
