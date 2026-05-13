<?php
/**
 * Plugin Name: NPay for LearnPress
 * Plugin URI: https://npay.vn
 * Description: Cổng thanh toán NPay cho LearnPress - tự động xác nhận thanh toán qua chuyển khoản ngân hàng bằng webhook NPay.
 * Version: 1.0.0
 * Author: NPay
 * Author URI: https://npay.vn
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: npay-learnpress
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: learnpress
 *
 * @package NPay_LearnPress
 */

defined( 'ABSPATH' ) || exit;

define( 'NPAY_LP_VERSION', '1.0.0' );
define( 'NPAY_LP_PLUGIN_FILE', __FILE__ );
define( 'NPAY_LP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'NPAY_LP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'NPAY_LP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class.
 */
final class NPay_LearnPress_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var NPay_LearnPress_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return NPay_LearnPress_Plugin
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
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'plugins_loaded', array( $this, 'init' ), 20 );
		add_action( 'admin_notices', array( $this, 'maybe_show_learnpress_notice' ) );
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'npay-learnpress', false, dirname( NPAY_LP_PLUGIN_BASENAME ) . '/languages' );
	}

	/**
	 * Initialize plugin (after LearnPress).
	 */
	public function init() {
		if ( ! $this->is_learnpress_active() ) {
			return;
		}

		require_once NPAY_LP_PLUGIN_DIR . 'includes/class-lp-gateway-npay.php';
		require_once NPAY_LP_PLUGIN_DIR . 'includes/class-npay-payment-controller.php';
		require_once NPAY_LP_PLUGIN_DIR . 'includes/class-npay-webhook-receiver.php';

		add_filter( 'learn-press/payment-gateway/methods', array( $this, 'register_gateway' ) );

		// Boot helper singletons.
		NPay_Payment_Controller::instance();
		NPay_Webhook_Receiver::instance();

		// Assets.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register gateway with LearnPress.
	 *
	 * @param array $methods Existing methods.
	 * @return array
	 */
	public function register_gateway( $methods ) {
		$methods['npay'] = 'LP_Gateway_NPay';
		return $methods;
	}

	/**
	 * Enqueue assets only on payment page.
	 */
	public function enqueue_assets() {
		if ( ! isset( $_GET['npay-payment'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		wp_enqueue_style(
			'npay-lp-payment',
			NPAY_LP_PLUGIN_URL . 'assets/css/npay-payment.css',
			array(),
			NPAY_LP_VERSION
		);

		wp_enqueue_script(
			'npay-lp-poll',
			NPAY_LP_PLUGIN_URL . 'assets/js/npay-poll.js',
			array( 'jquery' ),
			NPAY_LP_VERSION,
			true
		);
	}

	/**
	 * Check if LearnPress is active.
	 *
	 * @return bool
	 */
	public function is_learnpress_active() {
		return class_exists( 'LearnPress' ) || class_exists( 'LP_Gateway_Abstract' );
	}

	/**
	 * Admin notice if LearnPress missing.
	 */
	public function maybe_show_learnpress_notice() {
		if ( $this->is_learnpress_active() ) {
			return;
		}
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'NPay for LearnPress yêu cầu plugin LearnPress được kích hoạt.', 'npay-learnpress' );
		echo '</p></div>';
	}
}

NPay_LearnPress_Plugin::instance();
