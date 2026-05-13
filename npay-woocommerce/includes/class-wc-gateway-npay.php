<?php
/**
 * NPay Payment Gateway.
 *
 * @package NPay_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Gateway_NPay class.
 */
class WC_Gateway_NPay extends WC_Payment_Gateway {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id                 = 'npay';
		$this->icon               = apply_filters( 'npay_gateway_icon', NPAY_WC_PLUGIN_URL . 'assets/images/logo.svg' );
		$this->has_fields         = false;
		$this->method_title       = __( 'NPay', 'npay-woocommerce' );
		$this->method_description = __( 'Accept bank transfer payments with automatic reconciliation via NPay (QR + webhook).', 'npay-woocommerce' );
		$this->supports           = array( 'products' );

		// Load settings.
		$this->init_form_fields();
		$this->init_settings();

		$this->title          = $this->get_option( 'title' );
		$this->description    = $this->get_option( 'description' );
		$this->api_token      = $this->get_option( 'api_token' );
		$this->webhook_secret = $this->get_option( 'webhook_secret' );
		$this->account_number = $this->get_option( 'account_number' );
		$this->account_name   = $this->get_option( 'account_name' );
		$this->bank_code      = $this->get_option( 'bank_code' );
		$this->prefix_code    = $this->get_option( 'prefix_code', 'NPAY' );
		$this->qr_template    = $this->get_option( 'qr_template', 'compact' );
		$this->api_base       = $this->get_option( 'api_base', NPAY_API_BASE );
		$this->expire_minutes = absint( $this->get_option( 'expire_minutes', 30 ) );
		$this->instructions   = $this->get_option( 'instructions' );

		// Hooks.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
		add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
		add_action( 'woocommerce_view_order', array( $this, 'thankyou_page' ), 5 );
	}

	/**
	 * Define admin form fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'        => array(
				'title'   => __( 'Enable/Disable', 'npay-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable NPay Payment', 'npay-woocommerce' ),
				'default' => 'no',
			),
			'title'          => array(
				'title'       => __( 'Title', 'npay-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Title shown to customer during checkout.', 'npay-woocommerce' ),
				'default'     => __( 'Bank transfer via NPay (QR)', 'npay-woocommerce' ),
				'desc_tip'    => true,
			),
			'description'    => array(
				'title'       => __( 'Description', 'npay-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Description shown to customer during checkout.', 'npay-woocommerce' ),
				'default'     => __( 'Pay by scanning the QR code or transferring to the bank account below. Your order will be confirmed automatically.', 'npay-woocommerce' ),
			),
			'instructions'   => array(
				'title'       => __( 'Instructions', 'npay-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Instructions shown on the thank-you page and order emails.', 'npay-woocommerce' ),
				'default'     => __( 'Please transfer the exact amount and include the payment code in the transfer content. Your order will be confirmed within seconds.', 'npay-woocommerce' ),
			),
			'api_section'    => array(
				'title' => __( 'NPay API', 'npay-woocommerce' ),
				'type'  => 'title',
			),
			'api_base'       => array(
				'title'       => __( 'API Base URL', 'npay-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'NPay API base URL. Default: https://api.npay.vn', 'npay-woocommerce' ),
				'default'     => NPAY_API_BASE,
				'desc_tip'    => true,
			),
			'api_token'      => array(
				'title'       => __( 'API Token', 'npay-woocommerce' ),
				'type'        => 'password',
				'description' => __( 'Token used to authenticate webhook requests from NPay (Authorization: Apikey ...).', 'npay-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'webhook_secret' => array(
				'title'       => __( 'Webhook HMAC Secret', 'npay-woocommerce' ),
				'type'        => 'password',
				'description' => __( 'Optional HMAC SHA-256 secret. If set, X-Npay-Signature header is validated.', 'npay-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'webhook_info'   => array(
				'title'       => __( 'Webhook URL', 'npay-woocommerce' ),
				'type'        => 'title',
				/* translators: %s: webhook URL */
				'description' => sprintf( __( 'Add this URL in your NPay dashboard: <code>%s</code>', 'npay-woocommerce' ), esc_url( rest_url( 'npay/v1/webhook' ) ) ),
			),
			'bank_section'   => array(
				'title' => __( 'Bank Account', 'npay-woocommerce' ),
				'type'  => 'title',
			),
			'account_number' => array(
				'title'       => __( 'Account Number', 'npay-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Bank account number to receive payments.', 'npay-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'account_name'   => array(
				'title'       => __( 'Account Holder', 'npay-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Account holder name.', 'npay-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'bank_code'      => array(
				'title'       => __( 'Bank Code', 'npay-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Bank short code (e.g. VCB, MB, TCB, ACB, VTB).', 'npay-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'prefix_code'    => array(
				'title'       => __( 'Payment Code Prefix', 'npay-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Prefix prepended to order id to form the payment code, e.g. NPAY for NPAY123. Max 10 chars A-Z/0-9.', 'npay-woocommerce' ),
				'default'     => 'NPAY',
				'desc_tip'    => true,
			),
			'qr_template'    => array(
				'title'       => __( 'QR Template', 'npay-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'QR image template style.', 'npay-woocommerce' ),
				'default'     => 'compact',
				'options'     => array(
					'compact'  => __( 'Compact', 'npay-woocommerce' ),
					'compact2' => __( 'Compact 2', 'npay-woocommerce' ),
					'qronly'   => __( 'QR Only', 'npay-woocommerce' ),
					'print'    => __( 'Print', 'npay-woocommerce' ),
				),
				'desc_tip'    => true,
			),
			'expire_minutes' => array(
				'title'       => __( 'Payment Expiry (minutes)', 'npay-woocommerce' ),
				'type'        => 'number',
				'description' => __( 'Countdown timer shown on thank-you page. Set 0 to disable.', 'npay-woocommerce' ),
				'default'     => 30,
				'desc_tip'    => true,
			),
		);
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return array( 'result' => 'failure' );
		}

		// Generate payment code & persist.
		$code = NPay_Order_Helper::get_or_create_code( $order );

		// Mark as on-hold (waiting for transfer).
		$order->update_status(
			'on-hold',
			/* translators: %s: payment code */
			sprintf( __( 'Awaiting NPay bank transfer. Payment code: %s', 'npay-woocommerce' ), $code )
		);

		// Reduce stock.
		wc_reduce_stock_levels( $order_id );

		// Empty cart.
		if ( WC()->cart ) {
			WC()->cart->empty_cart();
		}

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	/**
	 * Render content on thank-you page.
	 *
	 * @param int $order_id Order ID.
	 */
	public function thankyou_page( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_payment_method() !== $this->id ) {
			return;
		}

		if ( ! $order->needs_payment() ) {
			echo '<p class="npay-paid-message">' . esc_html__( 'Thank you. Your payment has been received.', 'npay-woocommerce' ) . '</p>';
			return;
		}

		$code   = NPay_Order_Helper::get_or_create_code( $order );
		$amount = $order->get_total();

		$qr_url = NPay_Order_Helper::build_qr_url(
			array(
				'account_number' => $this->account_number,
				'bank'           => $this->bank_code,
				'amount'         => $amount,
				'description'    => $code,
				'template'       => $this->qr_template,
			)
		);

		$data = array(
			'order'          => $order,
			'gateway'        => $this,
			'code'           => $code,
			'amount'         => $amount,
			'qr_url'         => $qr_url,
			'account_number' => $this->account_number,
			'account_name'   => $this->account_name,
			'bank_code'      => $this->bank_code,
			'instructions'   => $this->instructions,
			'expire_minutes' => $this->expire_minutes,
		);

		// Allow theme override.
		$located = locate_template( 'npay-woocommerce/payment-instructions.php' );
		if ( ! $located ) {
			$located = NPAY_WC_PLUGIN_DIR . 'templates/payment-instructions.php';
		}

		// Extract variables for template.
		extract( $data, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		include $located;
	}

	/**
	 * Output instructions in customer emails.
	 *
	 * @param WC_Order $order         Order.
	 * @param bool     $sent_to_admin Whether sent to admin.
	 * @param bool     $plain_text    Plain text.
	 */
	public function email_instructions( $order, $sent_to_admin = false, $plain_text = false ) {
		if ( $sent_to_admin || $order->get_payment_method() !== $this->id || ! $order->needs_payment() ) {
			return;
		}

		$code = NPay_Order_Helper::get_or_create_code( $order );

		if ( $this->instructions ) {
			echo wp_kses_post( wpautop( wptexturize( $this->instructions ) ) );
		}

		echo '<p><strong>' . esc_html__( 'Payment code', 'npay-woocommerce' ) . ':</strong> ' . esc_html( $code ) . '<br/>';
		echo '<strong>' . esc_html__( 'Bank', 'npay-woocommerce' ) . ':</strong> ' . esc_html( $this->bank_code ) . '<br/>';
		echo '<strong>' . esc_html__( 'Account number', 'npay-woocommerce' ) . ':</strong> ' . esc_html( $this->account_number ) . '<br/>';
		echo '<strong>' . esc_html__( 'Account holder', 'npay-woocommerce' ) . ':</strong> ' . esc_html( $this->account_name ) . '<br/>';
		echo '<strong>' . esc_html__( 'Amount', 'npay-woocommerce' ) . ':</strong> ' . wp_kses_post( wc_price( $order->get_total() ) ) . '</p>';
	}
}
