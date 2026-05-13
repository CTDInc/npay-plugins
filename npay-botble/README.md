# NPay - Plugin thanh toán cho Botble CMS

Plugin tích hợp cổng thanh toán **NPay** (VietQR) cho Botble CMS, hỗ trợ tạo mã QR động và đối soát thanh toán tự động qua webhook.

## Tính năng

- ✅ Hiển thị mã QR VietQR động tại trang thanh toán (`https://qr.npay.vn/img`).
- ✅ Webhook đối soát tự động — cập nhật `Payment.status` thành `COMPLETED` khi khớp `charge_id` với mã `NPAY-{order_id}`.
- ✅ Trang chờ thanh toán có polling 5 giây để chuyển trạng thái tức thì.
- ✅ Xác thực webhook bằng header `Authorization: Apikey <token>`.
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
| API Token | Token cấp từ dashboard NPay (`https://app.npay.vn`) |
| Số tài khoản | Số tài khoản nhận tiền |
| Bank BIN | Mã BIN ngân hàng (vd. `970422` cho MB Bank) |
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
  "transaction_id": "TXN123456",
  "amount": 100000,
  "memo": "NPAY-12345",
  "code": "NPAY-12345",
  "bank_bin": "970422",
  "account_number": "0123456789"
}
```

Plugin sẽ:
1. Xác thực header `Authorization: Apikey <token>`.
2. Trích xuất mã `NPAY-{order_id}` từ `memo` / `code` / `content` / `transferContent`.
3. Tìm `Payment` có `charge_id` chứa mã.
4. Cập nhật `status = COMPLETED`.

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

## Giấy phép

MIT © NPay Team — https://npay.vn
