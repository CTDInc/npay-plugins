{*
 * NPay payment instructions template
 * Variables (assigned by gateway):
 *   $qr_url, $account_number, $bank_short, $bank_bin,
 *   $amount, $transfer_code, $invoice_id
 *}
<div class="npay-payment-box" style="border:1px solid #e3e3e3;padding:16px;border-radius:8px;max-width:480px;font-family:Arial,Helvetica,sans-serif;">
    <h3 style="margin:0 0 12px;color:#1a73e8;">Thanh toán qua NPay</h3>
    <p style="margin:0 0 12px;">Quét mã QR bên dưới hoặc chuyển khoản theo thông tin sau. Hóa đơn sẽ được cập nhật tự động.</p>

    <div style="text-align:center;margin:12px 0;">
        <img src="{$qr_url|escape:'html'}" alt="NPay VietQR #{$invoice_id}" style="max-width:280px;width:100%;border:1px solid #eee;border-radius:6px;"/>
    </div>

    <table style="width:100%;font-size:14px;border-collapse:collapse;">
        <tr>
            <td style="padding:6px 4px;color:#555;">Ngân hàng</td>
            <td style="padding:6px 4px;text-align:right;"><strong>{$bank_short|escape:'html'}</strong> <span style="color:#888;">(BIN {$bank_bin|escape:'html'})</span></td>
        </tr>
        <tr>
            <td style="padding:6px 4px;color:#555;">Số tài khoản</td>
            <td style="padding:6px 4px;text-align:right;"><strong>{$account_number|escape:'html'}</strong></td>
        </tr>
        <tr>
            <td style="padding:6px 4px;color:#555;">Số tiền</td>
            <td style="padding:6px 4px;text-align:right;"><strong>{$amount|string_format:"%d"|number_format:0:",":"."} VND</strong></td>
        </tr>
        <tr>
            <td style="padding:6px 4px;color:#555;">Nội dung CK</td>
            <td style="padding:6px 4px;text-align:right;"><code style="background:#f5f5f5;padding:2px 6px;border-radius:4px;">{$transfer_code|escape:'html'}</code></td>
        </tr>
    </table>

    <p style="margin-top:12px;color:#888;font-size:12px;">
        Vui lòng giữ nguyên <strong>nội dung chuyển khoản</strong> để hệ thống NPay đối soát tự động.
        Nếu sau 5 phút giao dịch chưa được cập nhật, vui lòng liên hệ hỗ trợ kèm mã giao dịch.
    </p>
</div>
