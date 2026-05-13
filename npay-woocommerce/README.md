# NPay for WooCommerce

Plugin WordPress/WooCommerce giúp tích hợp cổng thanh toán **NPay** vào website của bạn. Khách hàng quét mã QR (VietQR) hoặc chuyển khoản tay; đơn hàng sẽ được tự động xác nhận khi NPay nhận được giao dịch qua webhook.

- **Plugin Name:** NPay for WooCommerce
- **Version:** 1.0.0
- **Requires:** WordPress 5.8+, WooCommerce 4.0+, PHP 7.4+
- **License:** GPL-2.0+
- **Text Domain:** `npay-woocommerce`

## Tính năng

- Sinh QR code (VietQR) trực tiếp từ `qr.npay.vn` với số tiền và nội dung chuyển khoản đã được điền sẵn.
- Sinh mã thanh toán dạng `NPAY{order_id}` (prefix có thể tùy chỉnh).
- Webhook REST API: `wp-json/npay/v1/webhook` — hỗ trợ xác thực **Apikey** (`Authorization: Apikey <token>`) **và/hoặc** **HMAC SHA-256** (`X-Npay-Signature`).
- Tự động phát hiện thanh toán trên trang cảm ơn (poll mỗi 5 giây qua AJAX), không cần khách tải lại trang.
- Đếm ngược thời gian thanh toán, nút copy nhanh số tài khoản/nội dung.
- Mẫu QR có thể chọn: compact, compact2, qronly, print.
- Hỗ trợ ghi đè template trong theme: `yourtheme/npay-woocommerce/payment-instructions.php`.

## Cài đặt

### Cách 1 — Upload file ZIP

1. Nén thư mục `npay-woocommerce/` thành `npay-woocommerce.zip`.
2. Trong wp-admin, vào **Plugins → Add New → Upload Plugin**.
3. Chọn file zip, nhấn **Install Now** rồi **Activate**.

### Cách 2 — Copy thủ công

1. Copy thư mục `npay-woocommerce/` vào `wp-content/plugins/` trên server.
2. Vào **Plugins** trong wp-admin, kích hoạt **NPay for WooCommerce**.

> Yêu cầu: WooCommerce đã được cài đặt và kích hoạt.

## Cấu hình

Vào **WooCommerce → Settings → Payments → NPay** (hoặc click "Settings" ngay tại trang Plugins).

| Mục | Mô tả |
| --- | --- |
| Enable/Disable | Bật/tắt cổng thanh toán |
| Title / Description | Tên và mô tả hiển thị ở trang checkout |
| API Base URL | Mặc định `https://api.npay.vn` |
| API Token | Token NPay cấp (dùng cho header `Authorization: Apikey ...`) |
| Webhook HMAC Secret | Tùy chọn — nếu set, sẽ kiểm tra `X-Npay-Signature` |
| Account Number | Số tài khoản ngân hàng nhận tiền |
| Account Holder | Chủ tài khoản |
| Bank Code | Mã ngân hàng (VCB, MB, TCB, ACB, VTB, ...) |
| Payment Code Prefix | Tiền tố mã thanh toán (mặc định `NPAY`) |
| QR Template | Mẫu QR hiển thị |
| Payment Expiry | Thời gian đếm ngược (phút) |

## Cấu hình webhook trên dashboard NPay

1. Đăng nhập [my.npay.vn](https://my.npay.vn).
2. Vào **Cài đặt → Webhook → Thêm webhook**.
3. Điền **URL** là webhook của site bạn:
   ```
   https://your-domain.com/wp-json/npay/v1/webhook
   ```
4. **Xác thực:** chọn **Apikey** và dán đúng token đã điền trong settings của plugin. (Hoặc chọn HMAC và điền secret.)
5. Lưu lại — NPay sẽ test ping endpoint.

## Cách hoạt động

1. Khách chọn "Bank transfer via NPay" tại checkout → đơn hàng chuyển sang trạng thái **On hold** và sinh mã `NPAY{order_id}`.
2. Trang **Order received** hiển thị QR code + thông tin tài khoản + đồng hồ đếm ngược.
3. Khi khách chuyển khoản với nội dung chứa mã thanh toán, NPay gửi webhook về site.
4. Plugin xác thực → tìm đơn theo nội dung → so khớp số tiền → đánh dấu **Paid** + thêm note giao dịch.
5. Trang thank-you tự động phát hiện trạng thái mới và reload sau ~1 giây.

## Troubleshooting

**Webhook trả 401 Unauthorized**
- Kiểm tra header `Authorization: Apikey <token>` hoặc `X-Npay-Signature`.
- Token / secret trong cài đặt plugin phải khớp với dashboard NPay.

**Webhook trả 200 nhưng đơn không cập nhật**
- Mã thanh toán trong nội dung chuyển khoản phải đúng prefix + order ID, ví dụ `NPAY123`.
- Đơn hàng phải đang ở trạng thái `pending` hoặc `on-hold`.
- Kiểm tra số tiền chuyển ≥ tổng đơn (plugin từ chối nếu thiếu).

**Trang thank-you không tự cập nhật**
- Bật DevTools → Network, đảm bảo `admin-ajax.php?action=npay_check_order_status` trả `is_paid: true`.
- Một số cache plugin (LiteSpeed, WP-Rocket) cache cả AJAX; thêm exclude cho `admin-ajax.php`.

**REST endpoint 404**
- Vào **Settings → Permalinks** và bấm **Save** để flush rewrite rules.

**Test thủ công webhook bằng curl**

```bash
curl -X POST https://your-domain.com/wp-json/npay/v1/webhook \
  -H "Authorization: Apikey YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "id": 123,
    "gateway": "VietinBank",
    "transactionDate": "2026-05-13 14:30:00",
    "accountNumber": "113366668888",
    "subAccount": null,
    "code": "NPAY42",
    "content": "chuyen tien NPAY42",
    "transferType": "in",
    "transferAmount": 250000,
    "accumulated": 19077000,
    "referenceCode": "MBVCB.3278907687",
    "description": "manual test"
  }'
```

## Hooks / Filters

- `npay_payment_code` — `apply_filters( 'npay_payment_code', $code, $order_id, $prefix )` để tự sinh mã.
- `npay_gateway_icon` — đổi URL logo.
- `npay_payment_completed` — `do_action( 'npay_payment_completed', $order, $payload )` sau khi đánh dấu paid.

## Hỗ trợ

- Website: <https://npay.vn>
- Tài liệu: <https://docs.npay.vn>
- Dashboard: <https://my.npay.vn>
