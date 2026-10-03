# NPay - Plugin thanh toán cho Botble CMS

Plugin tích hợp cổng thanh toán **NPay** (VietQR) cho Botble CMS, hỗ trợ tạo mã QR động và đối soát thanh toán tự động qua webhook.

## Tính năng

- ✅ Hiển thị mã QR VietQR động tại trang thanh toán (`https://qr.npay.vn/qrcard`, `/qrpay` khi chọn mẫu "Chỉ QR").
- ✅ Webhook đối soát tự động — cập nhật `Payment.status` thành `COMPLETED` khi khớp `charge_id` với mã `NPAY-{order_id}`.
- ✅ Trang chờ thanh toán có polling 5 giây để chuyển trạng thái tức thì.
- ✅ Xác thực webhook bằng header `Authorization: Apikey <token>` hoặc chữ ký `X-Npay-Signature`.
- ✅ Hỗ trợ song ngữ (Tiếng Việt / English).

## Yêu cầu

- PHP `>= 8.1`
- Botble CMS `>= 6.0`
- Plugin `botble/payment ^6.0`

## Cài đặt

1. Copy thư mục plugin vào `platform/plugins/npay/` của Botble:
   ```bash
   cp -r npay-botble /var/www/botble/platform/plugins/npay
   ```
2. Chạy composer dump-autoload:
   ```bash
   composer dump-autoload
   ```
3. Vào **Admin > Plugins**, tìm **NPay** và bấm **Activate**.
4. Chạy migration (nếu tự động không chạy):
   ```bash
   php artisan migrate
   ```
5. Publish assets:
   ```bash
   php artisan vendor:publish --tag=public --force
   ```

## Cấu hình

Truy cập **Admin > Settings > Payment Methods > NPay**, điền:

| Trường | Mô tả |
|---|---|
| API Token | API key của webhook trên dashboard NPay (<https://npay.vn>), kiểu xác thực **API Key** |
| Webhook secret | (Tuỳ chọn) webhook secret trên dashboard khi bật **Ký request** |
| Số tài khoản | Số tài khoản nhận tiền |
| Bank BIN | Mã BIN ngân hàng (vd. `970422` cho MB Bank) hoặc mã như `mbbank`, `VCB` |
| Chủ tài khoản | Tên chủ tài khoản |
| Mẫu QR | `compact` / `qr_only` / `print` |

## Webhook

Sau khi cài đặt, mở dashboard NPay và đăng ký webhook URL:

```
POST https://your-domain.com/api/webhooks/npay
Authorization: Apikey <NPAY_API_TOKEN>
Content-Type: application/json
```

Payload mẫu:

```json
{
  "id": "tx_8f3k2m9q",
  "gateway": "MBBank",
  "transactionDate": "2026-10-03 10:00:00",
  "accountNumber": "0123456789",
  "code": null,
  "content": "NPAY12345 thanh toan",
  "transferType": "in",
  "transferAmount": 100000,
  "referenceCode": null
}
```

Plugin sẽ:
1. Xác thực `Authorization: Apikey <token>` **hoặc** `X-Npay-Signature` = hex HMAC-SHA256 của raw
   body khoá bằng webhook secret (có `X-Npay-Timestamp` thì lệch tối đa 5 phút). Sai → 401.
2. Bỏ qua giao dịch `transferType` khác `"in"`.
3. Trích xuất mã `NPAY-{order_id}` từ `code` / `content` / `memo` / `transferContent` / `description`.
4. Tìm `Payment` có `charge_id` đúng bằng mã; `transferAmount` phải **≥** số tiền.
5. Cập nhật `status = COMPLETED`.

Giao dịch không khớp đơn nào (không có mã, không tìm thấy, chuyển thiếu) vẫn trả **200** kèm
`"message": "Ignored: …"` để NPay không gửi lại mãi.

## Cấu trúc plugin

```
npay-botble/
├── plugin.json
├── composer.json
├── README.md
├── config/npay.php
├── routes/{web,api}.php
├── src/
│   ├── Providers/{NPayServiceProvider,HookServiceProvider}.php
│   ├── Http/Controllers/{NPayController,NPayWebhookController}.php
│   ├── Http/Requests/NPayPaymentRequest.php
│   └── Services/NPayService.php
├── resources/
│   ├── views/{payment-form,settings}.blade.php
│   ├── lang/{en,vi}/plugin.php
│   └── assets/{css/npay.css,js/npay.js}
├── database/migrations/2025_01_01_000000_create_npay_table.php
└── screenshots/screenshot.png
```

## Thay đổi

### 1.1.0

- QR chuyển sang `https://qr.npay.vn/qrcard` (`/img` không còn); dashboard `https://npay.vn`.
- Webhook: thêm xác thực `X-Npay-Signature` (webhook secret), chỉ nhận `transferType = "in"`,
  đòi số tiền ≥ đơn, khớp `charge_id` chính xác thay vì `LIKE`, trả 200 cho giao dịch không khớp.
- Đọc cấu hình từ khoá `payment_npay_*` (khoá Botble thực lưu), vẫn đọc `npay_*` cũ.

## Giấy phép

MIT © NPay Team — https://npay.vn
