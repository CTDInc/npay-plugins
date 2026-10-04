# NPay × Haravan

Tích hợp thanh toán **NPay** (QR ngân hàng VietQR + webhook) cho gian hàng **Haravan**, dưới dạng OAuth app độc lập viết bằng PHP.

> Tham khảo: <https://docs.sepay.vn/tich-hop-haravan.html> và <https://docs.haravan.com/>.

## Luồng hoạt động

1. Chủ shop cài app từ `https://{app}/install?shop={shop}.myharavan.com` → OAuth Haravan → app lưu `access_token`.
2. Haravan gửi webhook `orders/create` → app tạo payment `pending` với mã `NPAY-{order_number}` và sinh QR VietQR.
3. Khách thanh toán qua QR (trang `/payment/{order_id}` do app host, có polling tự động).
4. NPay gọi webhook `/webhook/npay` khi nhận được tiền → app gọi `POST /admin/orders/{id}/transactions.json` (`kind=capture, status=success`) đánh dấu đơn đã thanh toán.

## Yêu cầu

- PHP **>= 8.1** (`pdo`, `pdo_mysql` hoặc `pdo_sqlite`, `json`, `openssl`).
- Composer.
- MySQL 5.7+/8.0 (khuyến nghị) hoặc SQLite cho dev.
- Tài khoản **Haravan Partner** để đăng ký app OAuth.

## Cài đặt nhanh

```bash
git clone <repo> npay-haravan
cd npay-haravan
composer install
cp config.example.php config.php
# Sửa config.php: haravan_client_id, haravan_client_secret, app_url, db_dsn, npay.*
```

Tạo schema (MySQL):

```bash
mysql -u user -p npay_haravan < migrations/001_init.sql
```

Với SQLite, schema sẽ được khởi tạo tự động khi chạy lần đầu.

### Chạy bằng Docker

```bash
docker build -t npay-haravan .
docker run -d -p 8080:80 -v $PWD/config.php:/var/www/html/config.php npay-haravan
```

## Đăng ký Haravan app

1. Vào <https://partners.haravan.com> → tạo **App** mới (loại Public/Private đều được).
2. Lấy **Client ID** và **Client Secret** điền vào `config.php`.
3. **Whitelisted redirect URL**: `{app_url}/oauth/callback`.
4. **Scopes** tối thiểu: `read_orders, write_orders`.
5. Đăng ký **Webhook**:
   - Topic: `orders/create`
   - URL: `{app_url}/webhook/haravan`
   - Format: JSON.
   - Haravan ký bằng header `X-Haravan-Hmac-Sha256` (base64 HMAC-SHA256 body với `client_secret`).

## Cấu hình NPay

Trong `config.php`:

```php
'npay' => [
    'bank_id'        => 'BIDV',   // BIN Napas (970418) hoặc mã/slug: BIDV, VCB, mbbank…
    'account_number' => '0123456789',
    'account_name'   => 'CONG TY NPAY',
    'api_key'        => '',   // Authorization: Apikey <api_key>
    'webhook_secret' => '',   // X-Npay-Signature
    'qr_template'    => 'compact',            // 'qr_only' = chỉ mã QR
    'qr_endpoint'    => 'https://qr.npay.vn',
],
```

Ảnh QR lấy từ `https://qr.npay.vn/qrcard` (thẻ VietQR) hoặc `/qrpay` khi `qr_template = 'qr_only'`.
`bank_id` 6 chữ số gửi thành `ma_bin`; còn lại gửi `ngan_hang`, mã viết tắt phổ biến (`VCB`, `MB`,
`TCB`, `VTB`…) tự đổi sang slug (`vietcombank`, `mbbank`…).

Trên dashboard NPay (<https://npay.vn>), tạo webhook với **URL** `{app_url}/webhook/npay`.

App chấp nhận xác thực qua **một trong hai** cơ chế (không khai gì → từ chối mọi webhook):

- `Authorization: Apikey <api_key>` — kiểu xác thực **API Key** trên dashboard. Bản cấu hình
  cũ chưa có `api_key` thì so với `webhook_secret`.
- `X-Npay-Signature` = hex HMAC-SHA256 của raw body (không tiền tố `sha256=`), khoá là
  **webhook secret** hiện trên dashboard khi bật **Ký request**. `X-Npay-Timestamp` lệch quá
  5 phút bị từ chối.

> Webhook secret do NPay sinh cho từng webhook, không tự đặt được — trước 1.1.0 app dùng một
> giá trị cho cả API key lẫn khoá HMAC nên chữ ký thật của NPay không bao giờ khớp.

## Các route

| Route | Method | Mô tả |
|-------|--------|-------|
| `/install?shop=...` | GET | Bắt đầu OAuth |
| `/oauth/callback` | GET | Callback OAuth Haravan |
| `/webhook/haravan` | POST | Nhận webhook Haravan (`orders/create`) |
| `/webhook/npay` | POST | Nhận webhook NPay (giao dịch về) |
| `/payment/{order_id}` | GET | Trang QR cho khách |
| `/status/{order_id}` | GET | JSON trạng thái (poll) |
| `/admin` | GET | Dashboard đơn giản |

## Schema DB

- `haravan_shops` — token OAuth từng shop.
- `payments` — trạng thái thanh toán mỗi đơn (`order_code = NPAY-{order_number}`).

Xem `migrations/001_init.sql`.

## Bảo mật

- Verify HMAC Haravan (`X-Haravan-Hmac-Sha256` base64).
- Verify NPay webhook (apikey hoặc HMAC).
- `.htaccess` chặn truy cập trực tiếp `config.php`, `composer.*`, `migrations/*`.
- Cookie OAuth `state` HTTPOnly + Secure.

## Phát triển

```bash
php -S 0.0.0.0:8080 index.php
```

(Bạn cần Apache/Nginx với rewrite để route đẹp; built-in server chỉ phục vụ debug.)

## Thay đổi

### 1.1.1

- QR chuyển từ `img.vietqr.io` sang `https://qr.npay.vn/qrcard` (`/qrpay` khi `qr_template = 'qr_only'`);
  thêm `npay.qr_endpoint`. Template kiểu VietQR.io cũ (`compact2`…) giờ ra thẻ `/qrcard`.

### 1.1.0

- Tách `npay.api_key` (Apikey) khỏi `npay.webhook_secret` (HMAC); kiểm `X-Npay-Timestamp`.

## Giấy phép

MIT © NPay
