# npay-ladipage

> Plugin webhook receiver chuẩn **standalone** dùng để tích hợp [LadiPage](https://ladipage.vn) với cổng thanh toán **NPay** (`api.npay.vn`, `qr.npay.vn`, `my.npay.vn`).

Plugin gồm một bộ file PHP nhỏ gọn (không phụ thuộc framework, chỉ cần PHP ≥ 7.4 + SQLite). Bạn deploy thẳng vào `public_html/npay/` của host là chạy.

---

## 1. Sơ đồ luồng

```
[Khách hàng]
   │  điền form trên trang LadiPage
   ▼
[LadiPage Form]  ──POST──▶  /npay/webhook.php?source=ladipage
                              │  - tạo đơn pending, sinh ref_code (vd NP000123)
                              │  - lưu SQLite
                              ▼
                          302 redirect ──▶  /npay/qr-page.php?ref=NP000123
                                                │  hiển thị QR (qr.npay.vn),
                                                │  số TK, nội dung, đếm ngược
                                                │  JS poll /status.php mỗi 3.5s
[Khách hàng]  ──chuyển khoản──▶  Ngân hàng ──▶ NPay
                                                │
[NPay]  ──POST webhook──▶  /npay/webhook.php?source=npay
                              │  - verify chữ ký
                              │  - tìm ref_code trong nội dung CK
                              │  - UPDATE orders SET status='paid'
                              ▼
                          /status.php trả "paid"
                              ▼
                          qr-page.php hiển thị ✅
```

---

## 2. Cài đặt

### 2.1 Yêu cầu host

| Yêu cầu | Phiên bản |
| --- | --- |
| PHP | ≥ 7.4 (đã test trên 7.4, 8.0, 8.1, 8.2, 8.3) |
| Extension | `pdo`, `pdo_sqlite`, `json` |
| Web server | Apache (`.htaccess`) hoặc Nginx (xem snippet ở dưới) |
| Quyền ghi | Thư mục `data/` cần writable bởi PHP |

### 2.2 Triển khai trên shared hosting (cPanel / DirectAdmin)

```bash
cd ~/public_html
git clone https://github.com/your-org/npay-ladipage.git npay
cd npay
bash install.sh        # tạo data/, copy config, init SQLite, php -l
```

Hoặc thủ công:

1. Upload toàn bộ thư mục `npay-ladipage/` → đổi tên thành `npay/` đặt trong `public_html/`.
2. Copy `config.example.php` → `config.php`, sửa các giá trị.
3. `chmod 775 data/` để PHP có quyền ghi file `.sqlite`.
4. Truy cập `https://yourdomain.com/npay/admin.php?token=YOUR_TOKEN` để kiểm tra.

### 2.3 Cấu hình `config.php`

```php
return [
    'api_token'      => 'chuoi-random-dai-it-nhat-40-ky-tu',
    'account_number' => '0123456789',
    'bank_bin'       => '970422',        // MB Bank, đổi theo ngân hàng của bạn
    'account_holder' => 'NGUYEN VAN A',
    'qr_template'    => 'compact',
    'base_url'       => 'https://yourdomain.com/npay',
    // ...
];
```

> 🔐 **Quan trọng:** Đặt `api_token` thật mạnh — token này vừa bảo vệ `admin.php` vừa dùng để verify chữ ký webhook NPay (`Authorization: Bearer ...` hoặc `X-NPay-Signature: <hmac_sha256>`).

---

## 3. Cấu hình LadiPage

1. Mở landing page → chọn form → tab **Cài đặt** → mục **Hành động sau khi submit**.
2. Chọn **Gửi đến URL** (Webhook / Send to URL) và dán:

   ```
   https://yourdomain.com/npay/webhook.php?source=ladipage
   ```

3. Bật **Truyền các trường form** (Pass form fields). Đặt tên trường:
   - `name`, `phone`, `email` — thông tin khách.
   - `product` — tên sản phẩm.
   - `amount` — **bắt buộc** nếu mỗi đơn có số tiền khác nhau (đơn vị VND). Nếu không truyền, plugin dùng `default_amount` trong `config.php`.

4. (Tuỳ chọn) Nếu muốn LadiPage tự chuyển hướng thay vì để plugin redirect, đặt thêm field ẩn `redirect_url` = URL custom — plugin sẽ trả `Location:` về URL này.

> 💡 Mặc định plugin sẽ **redirect 302** sang `qr-page.php?ref=NP00xxxx` ngay sau khi LadiPage POST thành công. Nếu LadiPage gửi bằng AJAX (`X-Requested-With: XMLHttpRequest`), plugin trả về JSON `{ok, ref_code, redirect}` để bạn tự xử lý.

---

## 4. Cấu hình webhook NPay

1. Đăng nhập [my.npay.vn](https://my.npay.vn) → **Webhook / Tích hợp**.
2. Thêm webhook mới:
   - **URL**: `https://yourdomain.com/npay/webhook.php?source=npay`
   - **Method**: `POST`
   - **Authentication**: chọn **Bearer Token** rồi dán giá trị `api_token`.
     *Hoặc* dùng **HMAC SHA256** với secret = `api_token` (plugin verify cả 2).
3. Bấm **Test webhook** để gửi payload mẫu — plugin sẽ trả `{"ok":true,"matched":false}` nếu chữ ký hợp lệ nhưng chưa có đơn nào khớp.

### Payload mẫu plugin xử lý được

```json
{
    "id": "TX123456",
    "gateway": "MBBank",
    "transactionDate": "2026-05-13 17:30:00",
    "accountNumber": "0123456789",
    "content": "Khach hang chuyen khoan NP000123",
    "transferType": "in",
    "transferAmount": 100000,
    "referenceCode": "FT26134..."
}
```

Plugin tìm `ref_code` (vd `NP000123`) bằng `stripos` trong trường `content`/`description`/`transferContent`. Nếu có nhiều đơn `pending` cùng số tiền, plugin sẽ ưu tiên match theo cả `amount` lẫn `content`.

---

## 5. File / endpoint

| Đường dẫn | Vai trò |
| --- | --- |
| `index.php` | Demo landing + hướng dẫn paste URL vào LadiPage |
| `webhook.php?source=ladipage` | Nhận form từ LadiPage, sinh đơn pending, redirect QR |
| `webhook.php?source=npay` | Nhận webhook NPay, verify, đối soát, mark paid |
| `qr-page.php?ref=NP000123` | Trang QR cho khách hàng |
| `status.php?ref=NP000123` | JSON status (JS poll) |
| `admin.php?token=...` | Trang admin liệt kê đơn pending / paid |
| `lib/Database.php` | Wrapper PDO/SQLite |
| `lib/NPayClient.php` | Tạo URL QR, verify webhook |
| `data/npay.sqlite` | DB SQLite (tạo tự động) |

---

## 6. Nginx config tham khảo

```nginx
location ^~ /npay/ {
    try_files $uri /npay/index.php?$query_string;

    location ~ /npay/(data|lib)/        { deny all; return 403; }
    location ~ /npay/(config\.php|config\.example\.php|composer\.(json|lock)|install\.sh)$ { deny all; return 403; }
    location ~ /npay/.*\.sqlite$        { deny all; return 403; }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

---

## 7. Bảo mật

- Tất cả endpoint webhook xác thực **chữ ký** trước khi đọc payload.
- `admin.php` chỉ vào được khi đúng `api_token` (qua query, POST hoặc cookie `npay_admin`).
- `data/`, `lib/`, `config.php`, `*.sqlite` đều bị block bằng `.htaccess`.
- Khuyến nghị bật HTTPS và đặt `chmod 640 config.php`.

---

## 8. Lint

```bash
composer run lint
# hoặc
find . -maxdepth 2 -name '*.php' -print0 | xargs -0 -n1 php -l
```

---

## 9. License

MIT.
