<?php
/**
 * NPay payment page template.
 *
 * @package NPay_LearnPress
 *
 * @var int    $order_id
 * @var string $amount_display
 * @var float  $amount
 * @var string $code
 * @var string $qr_url
 * @var string $bank_bin
 * @var string $bank_name
 * @var string $account_number
 * @var string $account_name
 * @var string $status_url
 * @var int    $expire_minutes
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="npay-lp-payment-wrapper" id="npay-lp-payment">
	<div class="npay-lp-payment-card">
		<header class="npay-lp-payment-header">
			<h1 class="npay-lp-title"><?php echo esc_html__( 'Thanh toán đơn hàng', 'npay-learnpress' ); ?> #<?php echo esc_html( $order_id ); ?></h1>
			<p class="npay-lp-amount"><?php echo esc_html__( 'Số tiền:', 'npay-learnpress' ); ?> <strong><?php echo esc_html( $amount_display ); ?></strong></p>
			<div class="npay-lp-countdown" id="npay-lp-countdown" data-expire="<?php echo esc_attr( $expire_minutes ); ?>">
				<span class="npay-lp-countdown-label"><?php echo esc_html__( 'Hết hạn sau:', 'npay-learnpress' ); ?></span>
				<span class="npay-lp-countdown-time">--:--</span>
			</div>
		</header>

		<div class="npay-lp-payment-body">
			<div class="npay-lp-qr">
				<img src="<?php echo esc_url( $qr_url ); ?>" alt="<?php echo esc_attr__( 'NPay QR Code', 'npay-learnpress' ); ?>" />
				<p class="npay-lp-qr-caption"><?php echo esc_html__( 'Quét mã QR bằng app ngân hàng để thanh toán nhanh', 'npay-learnpress' ); ?></p>
			</div>

			<div class="npay-lp-bank-info">
				<h2><?php echo esc_html__( 'Hoặc chuyển khoản thủ công', 'npay-learnpress' ); ?></h2>
				<table class="npay-lp-info-table">
					<tbody>
						<tr>
							<th><?php echo esc_html__( 'Ngân hàng', 'npay-learnpress' ); ?></th>
							<td><strong><?php echo esc_html( $bank_name ); ?></strong></td>
						</tr>
						<tr>
							<th><?php echo esc_html__( 'Số tài khoản', 'npay-learnpress' ); ?></th>
							<td>
								<strong class="npay-lp-copyable" data-copy="<?php echo esc_attr( $account_number ); ?>"><?php echo esc_html( $account_number ); ?></strong>
								<button type="button" class="npay-lp-copy-btn" data-target="<?php echo esc_attr( $account_number ); ?>"><?php echo esc_html__( 'Sao chép', 'npay-learnpress' ); ?></button>
							</td>
						</tr>
						<?php if ( ! empty( $account_name ) ) : ?>
						<tr>
							<th><?php echo esc_html__( 'Chủ tài khoản', 'npay-learnpress' ); ?></th>
							<td><?php echo esc_html( $account_name ); ?></td>
						</tr>
						<?php endif; ?>
						<tr>
							<th><?php echo esc_html__( 'Số tiền', 'npay-learnpress' ); ?></th>
							<td>
								<strong class="npay-lp-copyable" data-copy="<?php echo esc_attr( (int) $amount ); ?>"><?php echo esc_html( $amount_display ); ?></strong>
								<button type="button" class="npay-lp-copy-btn" data-target="<?php echo esc_attr( (int) $amount ); ?>"><?php echo esc_html__( 'Sao chép', 'npay-learnpress' ); ?></button>
							</td>
						</tr>
						<tr>
							<th><?php echo esc_html__( 'Nội dung chuyển khoản', 'npay-learnpress' ); ?></th>
							<td>
								<strong class="npay-lp-copyable npay-lp-code" data-copy="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $code ); ?></strong>
								<button type="button" class="npay-lp-copy-btn" data-target="<?php echo esc_attr( $code ); ?>"><?php echo esc_html__( 'Sao chép', 'npay-learnpress' ); ?></button>
							</td>
						</tr>
					</tbody>
				</table>
				<p class="npay-lp-warning">
					<?php echo esc_html__( 'Vui lòng giữ nguyên nội dung chuyển khoản để hệ thống tự động xác nhận.', 'npay-learnpress' ); ?>
				</p>
			</div>
		</div>

		<div class="npay-lp-status" id="npay-lp-status" data-status-url="<?php echo esc_url( $status_url ); ?>">
			<div class="npay-lp-spinner"></div>
			<p class="npay-lp-status-msg"><?php echo esc_html__( 'Đang chờ thanh toán... Hệ thống sẽ tự động xác nhận trong vài giây sau khi nhận được tiền.', 'npay-learnpress' ); ?></p>
		</div>

		<div class="npay-lp-instructions">
			<h3><?php echo esc_html__( 'Hướng dẫn', 'npay-learnpress' ); ?></h3>
			<ol>
				<li><?php echo esc_html__( 'Mở app ngân hàng và quét mã QR phía trên (hoặc chuyển khoản thủ công).', 'npay-learnpress' ); ?></li>
				<li><?php echo esc_html__( 'Đảm bảo nội dung chuyển khoản là mã đơn hàng:', 'npay-learnpress' ); ?> <strong><?php echo esc_html( $code ); ?></strong>.</li>
				<li><?php echo esc_html__( 'Sau khi chuyển khoản thành công, vui lòng giữ nguyên trang này. Hệ thống sẽ tự động xác nhận và mở khóa khoá học.', 'npay-learnpress' ); ?></li>
			</ol>
		</div>
	</div>
</div>
