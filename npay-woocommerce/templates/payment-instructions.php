<?php
/**
 * NPay payment instructions template.
 *
 * Variables available:
 *
 * @var WC_Order        $order
 * @var WC_Gateway_NPay $gateway
 * @var string          $code
 * @var float           $amount
 * @var string          $qr_url
 * @var string          $account_number
 * @var string          $account_name
 * @var string          $bank_code
 * @var string          $instructions
 * @var int             $expire_minutes
 *
 * Override this template by copying it to yourtheme/npay-woocommerce/payment-instructions.php.
 *
 * @package NPay_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$expires_ts = $expire_minutes > 0
	? ( strtotime( $order->get_date_created()->date( 'Y-m-d H:i:s' ) ) + ( $expire_minutes * 60 ) )
	: 0;
?>
<div class="npay-payment-box" id="npay-payment-box">
	<h2><?php esc_html_e( 'Pay via NPay bank transfer', 'npay-woocommerce' ); ?></h2>

	<?php if ( $instructions ) : ?>
		<div class="npay-instructions">
			<?php echo wp_kses_post( wpautop( wptexturize( $instructions ) ) ); ?>
		</div>
	<?php endif; ?>

	<div class="npay-payment-grid">
		<div class="npay-qr">
			<img src="<?php echo esc_url( $qr_url ); ?>" alt="<?php esc_attr_e( 'NPay QR code', 'npay-woocommerce' ); ?>" />
			<p><?php esc_html_e( 'Scan with your banking app to pay instantly.', 'npay-woocommerce' ); ?></p>
		</div>

		<div class="npay-bank-info">
			<table>
				<tbody>
					<tr>
						<td class="label"><?php esc_html_e( 'Bank', 'npay-woocommerce' ); ?></td>
						<td class="value">
							<?php echo esc_html( $bank_code ); ?>
							<button type="button" class="npay-copy-btn" data-copy="<?php echo esc_attr( $bank_code ); ?>"><?php esc_html_e( 'Copy', 'npay-woocommerce' ); ?></button>
						</td>
					</tr>
					<tr>
						<td class="label"><?php esc_html_e( 'Account holder', 'npay-woocommerce' ); ?></td>
						<td class="value">
							<?php echo esc_html( $account_name ); ?>
						</td>
					</tr>
					<tr>
						<td class="label"><?php esc_html_e( 'Account number', 'npay-woocommerce' ); ?></td>
						<td class="value">
							<?php echo esc_html( $account_number ); ?>
							<button type="button" class="npay-copy-btn" data-copy="<?php echo esc_attr( $account_number ); ?>"><?php esc_html_e( 'Copy', 'npay-woocommerce' ); ?></button>
						</td>
					</tr>
					<tr>
						<td class="label"><?php esc_html_e( 'Amount', 'npay-woocommerce' ); ?></td>
						<td class="value">
							<?php echo wp_kses_post( wc_price( $amount ) ); ?>
							<button type="button" class="npay-copy-btn" data-copy="<?php echo esc_attr( number_format( (float) $amount, 0, '.', '' ) ); ?>"><?php esc_html_e( 'Copy', 'npay-woocommerce' ); ?></button>
						</td>
					</tr>
					<tr>
						<td class="label"><?php esc_html_e( 'Transfer content', 'npay-woocommerce' ); ?></td>
						<td class="value">
							<strong><?php echo esc_html( $code ); ?></strong>
							<button type="button" class="npay-copy-btn" data-copy="<?php echo esc_attr( $code ); ?>"><?php esc_html_e( 'Copy', 'npay-woocommerce' ); ?></button>
						</td>
					</tr>
				</tbody>
			</table>

			<div class="npay-status pending">
				<?php esc_html_e( 'Waiting for payment...', 'npay-woocommerce' ); ?>
			</div>

			<?php if ( $expires_ts > 0 ) : ?>
				<div class="npay-countdown"
					data-expires="<?php echo esc_attr( $expires_ts ); ?>"
					data-label="<?php esc_attr_e( 'Time remaining', 'npay-woocommerce' ); ?>"
					data-expired-text="<?php esc_attr_e( 'Payment window expired. Please contact us if you have already transferred.', 'npay-woocommerce' ); ?>">
					<?php
					printf(
						/* translators: %d: minutes */
						esc_html__( 'Time remaining: %d minutes', 'npay-woocommerce' ),
						absint( $expire_minutes )
					);
					?>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>
