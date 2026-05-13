# NPay × Sapo (Bizweb) — Ứng dụng thanh toán

App OAuth chuẩn của **NPay** cho nền tảng **Sapo / Bizweb**. Khách hàng nhận
QR VietQR do NPay sinh, NPay đối soát giao dịch chuyển khoản và tự động đánh
dấu đơn hàng đã thanh toán trên Sapo qua REST API.

## Luồng hoạt động

```
Sapo Store ──install──▶ /install ──redirect──▶ Sapo OAuth ──code──▶ /oauth/callback
                                                                          │
                                                                          ▼
                                                        upsert stores (access_token)

Sapo (order/created webhook)  ──▶ /webhook/sapo  ──▶ tạo bản ghi pending,
                                                    sinh `NPAY-{order_id}`

Khách hàng                    ──▶ /payment/{id}  ──▶ trang QR + đếm ngược

NPay (transaction webhook)    ──▶ /webhook/npay  ──▶ match `NPAY-{order_id}`
                                                    ──▶ Sapo POST /admin/orders/{id}/transactions.json
                                                    ──▶ status = paid
```

## Cấu trúc thư mục

```
npay-sapo/
├── index.php                       # Front controller
├── config.example.php              # Mẫu cấu hình (copy → config.php)
├── composer.json
├── .htaccess
├── Dockerfile
├── migrations/
│   └── 001_init.sql                # Schema MySQL
├── src/
│   ├── Router.php
│   ├── Database.php
│   ├── SapoClient.php              # OAuth + REST API
│   ├── NPayClient.php              # QR + verify webhook
│   ├── Payment/PaymentPage.php
│   └── Webhook/
│       ├── SapoWebhook.php         # order/created, order/updated, ...
│       └── NPayWebhook.php         # transaction → mark paid
├── templates/
│   ├── payment.php                 # Trang QR cho khách
│   └── admin.php                   # Quản trị cửa hàng
└── public/assets/
    ├── css/style.css
    └── js/poll.js
```

## Yêu cầu

- PHP **8.1+**, ext-pdo, ext-pdo_mysql, ext-json
- MySQL 5.7+ / 8.0 (hoặc SQLite cho dev)
- Composer 2
- HTTPS endpoint công khai (Sapo yêu cầu `https://`)

## Cài đặt

```bash
cd /var/www/npay-sapo
composer install --no-dev -o
cp config.example.php config.php   # rồi sửa giá trị
mysql -u root -p npay_sapo < migrations/001_init.sql
```

### Biến cấu hình quan trọng (`config.php`)

| Khoá | Ý nghĩa |
| --- | --- |
| `app_url` | URL công khai của app, không có `/` cuối |
| `sapo_client_id` / `sapo_client_secret` | Lấy ở Sapo Developer Portal |
| `sapo_scopes` | Mặc định `read_orders,write_orders,read_products` |
| `npay_webhook_secret` | Khoá ký webhook NPay (fallback nếu không dùng Bearer) |
| `npay_qr_endpoint` | Endpoint sinh ảnh QR của NPay |
| `db_dsn` / `db_user` / `db_pass` | Kết nối PDO |
| `payment_ttl` | Thời gian sống trang QR (giây) |

## Đăng ký App trên Sapo

1. Truy cập <https://developers.sapo.vn/> → tạo app loại **Custom / Public**.
2. **Redirect URL** → `https://<APP_URL>/oauth/callback`.
3. **Webhook URL** → `https://<APP_URL>/webhook/sapo`, chọn topic
   `order/created`, `order/updated`, `order/cancelled`.
4. Copy `Client ID`, `Client Secret`, `Webhook secret` → điền vào `config.php`
   và (nếu là per-store) ở trang `/admin?store=<id>`.

## Cài app vào cửa hàng

Mở trình duyệt:

```
https://<APP_URL>/install?shop=yourstore.mysapo.net
```

App sẽ redirect sang trang xác thực Sapo. Sau khi chủ shop bấm "Cài đặt",
app nhận `code`, đổi lấy `access_token` và lưu vào bảng `stores`.

## Cấu hình NPay

Tại NPay Dashboard, đặt **Webhook URL** = `https://<APP_URL>/webhook/npay`,
và sao chép API token vào trang quản trị app:

```
https://<APP_URL>/admin?store=<store_id>
```

Khai báo:

- `NPay API token`
- Mã ngân hàng (VCB, TCB, MB, …)
- Số tài khoản nhận tiền
- Chủ tài khoản
- Sapo webhook HMAC secret

## Endpoint

| Method | Path | Mô tả |
| --- | --- | --- |
| GET  | `/install?shop=...` | Khởi tạo OAuth |
| GET  | `/oauth/callback`   | Sapo trả về với `code` |
| POST | `/webhook/sapo`     | Nhận webhook Sapo |
| POST | `/webhook/npay`     | Nhận giao dịch từ NPay |
| GET  | `/payment/{order}`  | Trang QR cho khách |
| GET  | `/status/{order}`   | JSON poll trạng thái |
| GET  | `/admin`            | Trang quản trị |
| POST | `/admin/save`       | Lưu cấu hình store |

## Docker

```bash
docker build -t npay/sapo .
docker run -d --name npay-sapo \
  -p 8080:80 \
  -e APP_URL=https://sapo.npay.vn \
  -e SAPO_CLIENT_ID=... \
  -e SAPO_CLIENT_SECRET=... \
  -e NPAY_WEBHOOK_SECRET=... \
  -e DB_DSN='mysql:host=db;dbname=npay_sapo;charset=utf8mb4' \
  -e DB_USER=npay -e DB_PASS=secret \
  npay/sapo
```

## Tham chiếu

- Sapo Developer: <https://developers.sapo.vn/>
- SePay integration guide (tham khảo flow QR): <https://docs.sepay.vn/tich-hop-sapo.html>

---

© NPay. License MIT.
