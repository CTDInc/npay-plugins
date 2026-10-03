# NPay PHP + MySQL — Webhook & Order Demo

Ví dụ **standalone** dùng PHP thuần (PDO) + MySQL để:

- Nhận webhook biến động số dư từ **NPay** (`api.npay.vn`)
- Tạo đơn hàng + sinh QR động qua **`qr.npay.vn`**
- Đối soát tự động: nội dung chuyển khoản chứa mã đơn (vd `NPAY12`)
- Trang **`pay.php`** cho khách quét VietQR + countdown + JS poll
- **`admin.php`** xem đơn / giao dịch / config

Triển khai trong **5 phút** trên shared hosting (`public_html/npay/`).

---

## 1. Yêu cầu

- PHP **>= 7.4** (đề nghị 8.x) + ext `pdo_mysql`, `json`, `curl`
- MySQL 5.7+ hoặc MariaDB 10.3+
- Tài khoản dashboard **<https://npay.vn>** đã cấu hình ngân hàng + webhook

---

## 2. Cài đặt

### 2.1. Tạo database

```bash
mysql -u root -p -e "CREATE DATABASE npay_demo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p npay_demo < db.sql
```

Hoặc dùng phpMyAdmin → Import `db.sql`.

### 2.2. Cấu hình

```bash
cp config.example.php config.php
nano config.php
```

Điền các giá trị:

| Khoá | Ý nghĩa |
|---|---|
| `db.dsn / username / password` | Thông tin DB |
| `webhook.api_token` | Token NPay (Authorization: Apikey ...) |
| `webhook.hmac_secret` | (Tuỳ chọn) webhook secret trên dashboard, verify `X-Npay-Signature` |
| `account.bank_short` / `bank_bin` | Mã NH (vd `MB` / `970422`) |
| `account.account_number` | STK nhận tiền |
| `account.account_holder` | Tên chủ TK |
| `account.qr_template` | `qr_only` = chỉ mã QR (`/qrpay`), còn lại = thẻ VietQR (`/qrcard`) |
| `admin.username / password` | HTTP Basic cho `admin.php` |
| `app.base_url` | URL công khai, vd `https://shop.example.com/npay` |

### 2.3. Deploy

Copy toàn bộ thư mục vào `public_html/npay/`:

```
public_html/npay/
├── webhook.php          ← endpoint nhận webhook
├── pay.php              ← trang QR cho khách
├── status.php           ← JSON status cho JS poll
├── create-order.php     ← tạo đơn (gọi từ shop)
├── admin.php            ← trang admin (HTTP Basic)
├── config.php           ← config (KHÔNG commit)
├── db.sql
├── lib/                 ← bị chặn qua web
├── tools/               ← bị chặn qua web
└── assets/
```

`.htaccess` đã chặn `lib/`, `tools/`, `config.php`, `*.sql`, `*.md`, `*.sh`.

### 2.4. Cấu hình webhook URL trên NPay

Vào **<https://npay.vn>** → *Webhook* → URL:

```
https://yourdomain.tld/npay/webhook.php
```

Kiểu xác thực **API Key** — NPay gửi `Authorization: Apikey <key>`; dán key đó vào `webhook.api_token`.

> Bật **Ký request** trên dashboard rồi chép **webhook secret** vào `webhook.hmac_secret`: plugin kiểm
> `X-Npay-Signature` = hex HMAC-SHA256 của raw body (không có tiền tố `sha256=`) và từ chối
> request có `X-Npay-Timestamp` lệch quá 5 phút.

### 2.5. Test bằng `npay-simulate.php`

CLI (chạy trên server):

```bash
php tools/npay-simulate.php https://yourdomain.tld/npay/webhook.php NPAY1 100000
```

Hoặc qua web (chỉ chạy được từ `127.0.0.1`):

```
http://localhost/npay/tools/npay-simulate.php?code=NPAY1&amount=100000
```

> ⚠️ `tools/` đã được khoá `.htaccess` mặc định. Mở tạm khi cần debug rồi đóng lại.

---

## 3. Luồng sử dụng

### 3.1. Tạo đơn từ phía shop

```bash
curl -X POST "https://yourdomain.tld/npay/create-order.php" \
  -d "amount=199000"
```

Response:

```json
{
  "success": true,
  "order_id": 12,
  "code": "NPAY12",
  "amount": 199000,
  "qr_url": "https://qr.npay.vn/qrcard?ma_bin=970422&tai_khoan=...&so_tien=199000&noi_dung=NPAY12&chu_tai_khoan=...",
  "pay_url": "https://yourdomain.tld/npay/pay.php?code=NPAY12",
  "status_url": "https://yourdomain.tld/npay/status.php?code=NPAY12",
  "expires_in": 900
}
```

### 3.2. Hiển thị QR cho khách

Redirect khách tới `pay_url`. Trang sẽ:

- Hiển thị VietQR (qr.npay.vn)
- Countdown TTL
- JS poll `status_url` mỗi 4s
- Khi `status = paid` → tự reload sang trạng thái thành công

### 3.3. Webhook → đối soát

Khi khách chuyển khoản, NPay POST tới `webhook.php`:

1. Verify `Authorization: Apikey ...` bằng `hash_equals` (constant-time).
2. (Tuỳ chọn) Verify `X-Npay-Signature` HMAC-SHA256.
3. Insert vào `tb_transactions` với UNIQUE(`npay_id`) — `id` của webhook (`tx_…`) → **idempotent**.
   `referenceCode` có thể `null`, không bắt buộc.
4. Trích mã `NPAYxx` từ `code`/`content`, tìm đơn pending, tiền vào **≥** số tiền đơn → set `paid`.

### 3.4. Admin

`https://yourdomain.tld/npay/admin.php` (HTTP Basic):

- Danh sách đơn / giao dịch / stats
- Mark paid thủ công, huỷ đơn
- Xem config (ẩn token/password)

---

## 4. Bảo mật

- ✅ `hash_equals` cho token & HMAC (constant-time).
- ✅ PDO prepared statements toàn bộ.
- ✅ UNIQUE `npay_id` đảm bảo idempotent.
- ✅ `.htaccess` chặn `config.php`, `lib/`, `tools/`, `*.sql`, `*.md`.
- ✅ (Tuỳ chọn) `webhook.allow_ip` CIDR allowlist.
- ✅ HTTP Basic cho `admin.php`.

> Khuyến nghị: chạy sau HTTPS (Let's Encrypt) và đổi `admin.password` ngay sau cài đặt.

---

## 5. Cấu trúc

```
npay-php-mysql/
├── README.md
├── db.sql
├── config.example.php
├── config.php             (bạn tạo từ template)
├── composer.json
├── install.sh
├── .htaccess
├── webhook.php
├── create-order.php
├── pay.php
├── status.php
├── admin.php
├── lib/
│   ├── .htaccess
│   ├── Database.php       (PDO singleton)
│   └── NPay.php           (QR, verify, helpers)
├── assets/
│   ├── css/style.css
│   └── js/poll.js
└── tools/
    ├── .htaccess
    └── npay-simulate.php
```

---

## 6. FAQ

**Q: Endpoint nào cần public?**
A: `webhook.php`, `pay.php`, `status.php`, `create-order.php`, `admin.php` (HTTP Basic), `assets/`.

**Q: Đổi prefix mã đơn?**
A: Sửa `app.code_prefix` trong `config.php` (mặc định `NPAY` → mã sẽ là `NPAY12`).

**Q: Webhook trùng lặp?**
A: An toàn — `UNIQUE(npay_id)` + `INSERT IGNORE`. Response `duplicated: true`.

**Q: Không có HMAC?**
A: Để `hmac_secret = ''` → tắt kiểm tra. Vẫn còn `Authorization: Apikey`.

**Q: Tích hợp framework khác (Laravel, CI, WP)?**
A: Đây là ví dụ vanilla. Logic cốt lõi nằm trong `lib/NPay.php` + `webhook.php`, dễ port.

---

## 7. License

MIT — © NPay.

---

## 7. Thay đổi

### 1.1.0

- QR chuyển sang `https://qr.npay.vn/qrcard` / `/qrpay` (`/img` không còn).
- Chống trùng theo `id` webhook (`npay_id`) thay vì `referenceCode` — trước đây mọi giao dịch
  `referenceCode = null` bị trả 422. **Nâng cấp từ 1.0.x**: chạy `migrations/001_npay_id.sql`.
- Chuyển dư vẫn đánh dấu đơn `paid` (trước đây đòi khớp chính xác).
- `X-Npay-Signature` kiểm thêm `X-Npay-Timestamp` (nếu có) lệch tối đa 5 phút.

