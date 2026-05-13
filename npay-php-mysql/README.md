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
- Tài khoản dashboard **<https://my.npay.vn>** đã cấu hình ngân hàng + API token

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
| `webhook.hmac_secret` | (Tuỳ chọn) HMAC-SHA256 secret cho `X-NPay-Signature` |
| `account.bank_short` / `bank_bin` | Mã NH (vd `MB` / `970422`) |
| `account.account_number` | STK nhận tiền |
| `account.account_holder` | Tên chủ TK |
| `account.qr_template` | `compact` \| `compact2` \| `qr_only` \| `print` |
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

Vào **<https://my.npay.vn>** → *Cấu hình webhook* → URL:

```
https://yourdomain.tld/npay/webhook.php
```

Phương thức: `POST`, header `Authorization: Apikey <api_token>` đã cấu hình ở `config.php`.

> Nếu kích hoạt HMAC: cấu hình thêm `X-NPay-Signature = HMAC_SHA256(body, hmac_secret)` ở phía NPay.

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
  "qr_url": "https://qr.npay.vn/img?bank=MB&acc=...&template=compact2&amount=199000&des=NPAY12",
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
2. (Tuỳ chọn) Verify `X-NPay-Signature` HMAC-SHA256.
3. Insert vào `tb_transactions` với UNIQUE(`reference_number`) → **idempotent**.
4. Trích mã `NPAYxx` từ `content`, tìm đơn pending khớp số tiền → set `paid`.

### 3.4. Admin

`https://yourdomain.tld/npay/admin.php` (HTTP Basic):

- Danh sách đơn / giao dịch / stats
- Mark paid thủ công, huỷ đơn
- Xem config (ẩn token/password)

---

## 4. Bảo mật

- ✅ `hash_equals` cho token & HMAC (constant-time).
- ✅ PDO prepared statements toàn bộ.
- ✅ UNIQUE `reference_number` đảm bảo idempotent.
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
A: An toàn — `UNIQUE(reference_number)` + `INSERT IGNORE`. Response `duplicated: true`.

**Q: Không có HMAC?**
A: Để `hmac_secret = ''` → tắt kiểm tra. Vẫn còn `Authorization: Apikey`.

**Q: Tích hợp framework khác (Laravel, CI, WP)?**
A: Đây là ví dụ vanilla. Logic cốt lõi nằm trong `lib/NPay.php` + `webhook.php`, dễ port.

---

## 7. License

MIT — © NPay.
