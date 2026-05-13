<?php
/**
 * NPay Gateway for LearnPress.
 *
 * @package NPay_LearnPress
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'LP_Gateway_Abstract' ) ) {
	return;
}

/**
 * Class LP_Gateway_NPay
 *
 * Extends LearnPress payment gateway abstract.
 */
class LP_Gateway_NPay extends LP_Gateway_Abstract {

	/**
	 * Gateway ID.
	 *
	 * @var string
	 */
	public $id = 'npay';

	/**
	 * Method title (admin).
	 *
	 * @var string
	 */
	public $method_title = '';

	/**
	 * Method description.
	 *
	 * @var string
	 */
	public $method_description = '';

	/**
	 * Public title (checkout).
	 *
	 * @var string
	 */
	public $title = '';

	/**
	 * Public description (checkout).
	 *
	 * @var string
	 */
	public $description = '';

	/**
	 * Settings instance from LearnPress.
	 *
	 * @var LP_Settings|null
	 */
	public $settings = null;

	/**
	 * API token.
	 *
	 * @var string
	 */
	public $api_token = '';

	/**
	 * Bank account number.
	 *
	 * @var string
	 */
	public $account_number = '';

	/**
	 * Bank account name.
	 *
	 * @var string
	 */
	public $account_name = '';

	/**
	 * Bank BIN code.
	 *
	 * @var string
	 */
	public $bank_bin = '';

	/**
	 * QR template (compact, qr_only, print, ...).
	 *
	 * @var string
	 */
	public $qr_template = 'compact';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->method_title       = __( 'NPay', 'npay-learnpress' );
		$this->method_description = __( 'Thanh toán qua chuyển khoản ngân hàng - tự động xác nhận bằng NPay.', 'npay-learnpress' );
		$this->icon               = '';
		$this->title              = __( 'Chuyển khoản ngân hàng (NPay)', 'npay-learnpress' );
		$this->description        = __( 'Quét mã QR hoặc chuyển khoản thủ công - hệ thống tự động xác nhận trong vài giây.', 'npay-learnpress' );

		parent::__construct();

		$this->load_settings();

		// Admin settings hook.
		if ( is_admin() ) {
			add_filter( 'learn-press/payment-gateway/' . $this->id . '/settings', array( $this, 'admin_settings' ) );
		}
	}

	/**
	 * Load configured settings.
	 */
	protected function load_settings() {
		if ( function_exists( 'LP' ) ) {
			$this->settings = LP()->settings()->get_group( 'payments_' . $this->id, '' );
		}

		$this->api_token      = (string) $this->_get_setting( 'api_token' );
		$this->account_number = (string) $this->_get_setting( 'account_number' );
		$this->account_name   = (string) $this->_get_setting( 'account_name' );
		$this->bank_bin       = (string) $this->_get_setting( 'bank_bin' );
		$qr_template          = (string) $this->_get_setting( 'qr_template' );
		$this->qr_template    = $qr_template ? $qr_template : 'compact';

		$custom_title = $this->_get_setting( 'title' );
		if ( ! empty( $custom_title ) ) {
			$this->title = $custom_title;
		}
		$custom_description = $this->_get_setting( 'description' );
		if ( ! empty( $custom_description ) ) {
			$this->description = $custom_description;
		}
	}

	/**
	 * Generic setting fetcher.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	protected function _get_setting( $key ) {
		if ( $this->settings && method_exists( $this->settings, 'get' ) ) {
			$val = $this->settings->get( $key );
			if ( null !== $val && '' !== $val ) {
				return $val;
			}
		}
		// Fallback to wp options.
		$options = get_option( 'learn_press_npay', array() );
		if ( is_array( $options ) && isset( $options[ $key ] ) ) {
			return $options[ $key ];
		}
		return '';
	}

	/**
	 * Whether gateway is available for checkout.
	 *
	 * @return bool
	 */
	public function is_available() {
		if ( empty( $this->account_number ) || empty( $this->bank_bin ) ) {
			return false;
		}
		$enabled = $this->_get_setting( 'enable' );
		if ( '' === $enabled ) {
			return true;
		}
		return in_array( $enabled, array( 'yes', '1', 1, true, 'true' ), true );
	}

	/**
	 * Build payment URL to redirect customer to after placing order.
	 *
	 * @param int $order_id Order ID.
	 * @return string
	 */
	public function get_payment_url( $order_id ) {
		$url = add_query_arg(
			array(
				'npay-payment' => 1,
				'order_id'     => absint( $order_id ),
				'key'          => $this->get_order_key( $order_id ),
			),
			home_url( '/' )
		);
		return $url;
	}

	/**
	 * Process the payment - redirect to NPay payment page.
	 *
	 * @param mixed $order LearnPress order or order_id.
	 * @return array
	 */
	public function process_payment( $order ) {
		$order_id = is_object( $order ) && method_exists( $order, 'get_id' ) ? $order->get_id() : absint( $order );

		// Mark as pending.
		if ( is_object( $order ) && method_exists( $order, 'update_status' ) ) {
			$order->update_status( 'pending' );
		}

		return array(
			'result'             => 'success',
			'redirect'           => $this->get_payment_url( $order_id ),
			'npay_payment_url'   => $this->get_payment_url( $order_id ),
		);
	}

	/**
	 * Compute order key (used to mildly authenticate the polling endpoint).
	 *
	 * @param int $order_id Order id.
	 * @return string
	 */
	public function get_order_key( $order_id ) {
		return substr( md5( 'npay_' . $order_id . wp_salt( 'auth' ) ), 0, 16 );
	}

	/**
	 * Build NPay code from order id.
	 *
	 * @param int $order_id Order id.
	 * @return string
	 */
	public static function build_code( $order_id ) {
		return 'NPAY' . absint( $order_id );
	}

	/**
	 * Build QR image URL.
	 *
	 * @param int    $order_id Order id.
	 * @param string $amount Amount.
	 * @return string
	 */
	public function build_qr_url( $order_id, $amount ) {
		$args = array(
			'acc'      => $this->account_number,
			'bank'     => $this->bank_bin,
			'amount'   => $amount,
			'des'      => self::build_code( $order_id ),
			'template' => $this->qr_template,
		);
		if ( ! empty( $this->account_name ) ) {
			$args['accountName'] = rawurlencode( $this->account_name );
		}
		return add_query_arg( $args, 'https://qr.npay.vn/img' );
	}

	/**
	 * Admin settings schema.
	 *
	 * @param array $settings Existing settings.
	 * @return array
	 */
	public function admin_settings( $settings ) {
		return array(
			array(
				'title'   => __( 'Bật cổng NPay', 'npay-learnpress' ),
				'id'      => '[enable]',
				'default' => 'yes',
				'type'    => 'yes-no',
			),
			array(
				'title'   => __( 'Tiêu đề', 'npay-learnpress' ),
				'id'      => '[title]',
				'default' => __( 'Chuyển khoản ngân hàng (NPay)', 'npay-learnpress' ),
				'type'    => 'text',
			),
			array(
				'title'   => __( 'Mô tả', 'npay-learnpress' ),
				'id'      => '[description]',
				'default' => __( 'Quét mã QR hoặc chuyển khoản thủ công - hệ thống tự động xác nhận trong vài giây.', 'npay-learnpress' ),
				'type'    => 'textarea',
			),
			array(
				'title'   => __( 'NPay API Token', 'npay-learnpress' ),
				'id'      => '[api_token]',
				'default' => '',
				'type'    => 'text',
				'desc'    => __( 'Token dùng để xác thực webhook gửi từ NPay (Authorization: Apikey ...).', 'npay-learnpress' ),
			),
			array(
				'title'   => __( 'Số tài khoản ngân hàng', 'npay-learnpress' ),
				'id'      => '[account_number]',
				'default' => '',
				'type'    => 'text',
			),
			array(
				'title'   => __( 'Tên chủ tài khoản', 'npay-learnpress' ),
				'id'      => '[account_name]',
				'default' => '',
				'type'    => 'text',
			),
			array(
				'title'   => __( 'Mã ngân hàng (BIN)', 'npay-learnpress' ),
				'id'      => '[bank_bin]',
				'default' => '',
				'type'    => 'text',
				'desc'    => __( 'Ví dụ: 970436 cho Vietcombank, 970422 cho MBBank.', 'npay-learnpress' ),
			),
			array(
				'title'   => __( 'Mẫu QR', 'npay-learnpress' ),
				'id'      => '[qr_template]',
				'default' => 'compact',
				'type'    => 'select',
				'options' => array(
					'compact'  => __( 'Compact', 'npay-learnpress' ),
					'compact2' => __( 'Compact 2', 'npay-learnpress' ),
					'qr_only'  => __( 'QR only', 'npay-learnpress' ),
					'print'    => __( 'Print', 'npay-learnpress' ),
				),
			),
		);
	}
}
