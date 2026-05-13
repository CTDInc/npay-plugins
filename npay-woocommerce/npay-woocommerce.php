<?php
/**
 * Plugin Name: NPay for WooCommerce
 * Plugin URI: https://npay.vn/
 * Description: NPay payment gateway integration for WooCommerce. Accept bank transfer payments with automatic reconciliation via NPay webhooks and QR codes.
 * Version: 1.0.0
 * Author: NPay
 * Author URI: https://npay.vn/
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: npay-woocommerce
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 4.0
 * WC tested up to: 8.5
 *
 * @package NPay_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'NPAY_WC_VERSION', '1.0.0' );
define( 'NPAY_WC_PLUGIN_FILE', __FILE__ );
define( 'NPAY_WC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'NPAY_WC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'NPAY_WC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

if ( ! defined( 'NPAY_API_BASE' ) ) {
	define( 'NPAY_API_BASE', 'https://api.npay.vn' );
}
if ( ! defined( 'NPAY_QR_BASE' ) ) {
	define( 'NPAY_QR_BASE', 'https://qr.npay.vn' );
}
if ( ! defined( 'NPAY_DASHBOARD_URL' ) ) {
	define( 'NPAY_DASHBOARD_URL', 'https://my.npay.vn' );
}

/**
 * Main plugin bootstrap class.
 */
final class NPay_WooCommerce {

	/**
	 * Singleton instance.
	 *
	 * @var NPay_WooCommerce
	 */
	protected static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return NPay_WooCommerce
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
		add_action( 'plugins_loaded', array( $this, 'init' ) );
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
	}

	/**
	 * Initialize plugin.
	 */
	public function init() {
		// Load text domain.
		load_plugin_textdomain( 'npay-woocommerce', false, dirname( NPAY_WC_PLUGIN_BASENAME ) . '/languages' );

		// Check WooCommerce.
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			return;
		}

		// Load files.
		require_once NPAY_WC_PLUGIN_DIR . 'includes/class-npay-order-helper.php';
		require_once NPAY_WC_PLUGIN_DIR . 'includes/class-wc-gateway-npay.php';
		require_once NPAY_WC_PLUGIN_DIR . 'includes/class-npay-webhook-handler.php';

		// Register gateway.
		add_filter( 'woocommerce_payment_gateways', array( $this, 'register_gateway' ) );

		// Plugin action links.
		add_filter( 'plugin_action_links_' . NPAY_WC_PLUGIN_BASENAME, array( $this, 'plugin_action_links' ) );

		// Boot webhook handler.
		NPay_Webhook_Handler::instance();

		// Enqueue assets on order received page.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register payment gateway.
	 *
	 * @param array $gateways Existing gateways.
	 * @return array
	 */
	public function register_gateway( $gateways ) {
		$gateways[] = 'WC_Gateway_NPay';
		return $gateways;
	}

	/**
	 * Add plugin settings link.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=npay' ) ),
			esc_html__( 'Settings', 'npay-woocommerce' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Enqueue front-end assets.
	 */
	public function enqueue_assets() {
		if ( ! function_exists( 'is_wc_endpoint_url' ) ) {
			return;
		}

		if ( is_wc_endpoint_url( 'order-received' ) || is_wc_endpoint_url( 'view-order' ) ) {
			wp_enqueue_style(
				'npay-checkout',
				NPAY_WC_PLUGIN_URL . 'assets/css/checkout.css',
				array(),
				NPAY_WC_VERSION
			);

			wp_enqueue_script(
				'npay-checkout-poll',
				NPAY_WC_PLUGIN_URL . 'assets/js/checkout-poll.js',
				array( 'jquery' ),
				NPAY_WC_VERSION,
				true
			);

			$order_id = absint( get_query_var( 'order-received' ) );
			if ( ! $order_id ) {
				global $wp;
				if ( isset( $wp->query_vars['view-order'] ) ) {
					$order_id = absint( $wp->query_vars['view-order'] );
				}
			}

			wp_localize_script(
				'npay-checkout-poll',
				'NPayCheckout',
				array(
					'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
					'orderId'  => $order_id,
					'nonce'    => wp_create_nonce( 'npay-check-status-' . $order_id ),
					'interval' => 5000,
					'i18n'     => array(
						'paid'    => esc_html__( 'Payment received. Reloading...', 'npay-woocommerce' ),
						'pending' => esc_html__( 'Waiting for payment...', 'npay-woocommerce' ),
					),
				)
			);
		}
	}

	/**
	 * Admin notice when WooCommerce missing.
	 */
	public function woocommerce_missing_notice() {
		echo '<div class="error"><p>';
		echo esc_html__( 'NPay for WooCommerce requires WooCommerce to be installed and active.', 'npay-woocommerce' );
		echo '</p></div>';
	}

	/**
	 * Activation hook.
	 */
	public function activate() {
		// Flush rewrite rules so REST endpoint is registered.
		flush_rewrite_rules();
	}
}

// AJAX endpoint to poll order status.
add_action( 'wp_ajax_npay_check_order_status', 'npay_ajax_check_order_status' );
add_action( 'wp_ajax_nopriv_npay_check_order_status', 'npay_ajax_check_order_status' );

/**
 * AJAX handler: return order status.
 */
function npay_ajax_check_order_status() {
	$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
	$nonce    = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

	if ( ! $order_id || ! wp_verify_nonce( $nonce, 'npay-check-status-' . $order_id ) ) {
		wp_send_json_error( array( 'message' => esc_html__( 'Invalid request.', 'npay-woocommerce' ) ) );
	}

	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		wp_send_json_error( array( 'message' => esc_html__( 'Order not found.', 'npay-woocommerce' ) ) );
	}

	wp_send_json_success(
		array(
			'status'    => $order->get_status(),
			'is_paid'   => $order->is_paid(),
			'needs_pay' => $order->needs_payment(),
		)
	);
}

NPay_WooCommerce::instance();
