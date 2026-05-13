# NPay — Cổng thanh toán cho NukeViet 4.x

Tích hợp **NPay** (https://npay.vn) — nền tảng tự động hoá thanh toán chuyển khoản ngân hàng — vào module **Shops** của NukeViet 4.x.

Module hỗ trợ:

- Hiển thị mã **VietQR** + thông tin tài khoản ngay trên trang checkout.
- Tự động đối soát giao dịch qua webhook NPay (Authorization: `Apikey <token>`).
- Tự động cập nhật trạng thái đơn hàng sang `paid` khi nhận được tiền.
- JavaScript poll trạng thái thanh toán, chuyển hướng tự động khi thành công.
- Ghi log mọi giao dịch nhận được vào bảng `nv_<lang>_shops_payment_log`.

---

## 1. Yêu cầu

- NukeViet 4.4+ (kèm module **Shops**).
- PHP 7.2+ (khuyến nghị PHP 8.x).
- Một tài khoản NPay với API token đã được tạo ở https://my.npay.vn.

## 2. Cài đặt

### 2.1. Copy file

Copy toàn bộ thư mục plugin vào NukeViet:

```
<NUKEVIET_ROOT>/modules/shops/payment_gateway/npay/
```

Cấu trúc sau khi copy:

```
modules/shops/payment_gateway/
├── npay.php                          ← bắt buộc nằm ở thư mục cha
└── npay/
    ├── webhook.php
    ├── install.php
    ├── uninstall.php
    ├── composer.json
    ├── README.md
    ├── lang/
    │   ├── vi.php
    │   └── en.php
    ├── templates/
    │   └── payment_instruction.tpl
    └── assets/
        ├── css/npay.css
        └── js/npay-poll.js
```

> Lưu ý: file `npay.php` đặt trực tiếp trong `modules/shops/payment_gateway/`, các tài nguyên phụ trợ nằm trong thư mục con `npay/`. Đây là quy ước NukeViet dùng để tự động nạp gateway.

### 2.2. Chạy installer

Có 2 cách:

**Cách A — qua CLI:**

```bash
php modules/shops/payment_gateway/npay/install.php
```

**Cách B — qua admin:**

Đăng nhập admin NukeViet → Quản trị module → vào module **Shops** → chọn **Thanh toán** → trình duyệt sẽ tự gọi installer khi gateway lần đầu được kích hoạt. Nếu cần ép chạy, mở `admin.php?nv=shops&op=payment&active=npay`.

Installer sẽ:

- Thêm các dòng cấu hình mặc định vào `nv4_config` (lang=`sys`, module=`shops`, config_name=`npay_*`).
- Tạo bảng `nv_<lang>_shops_payment_log` (nếu chưa có) để lưu log webhook.

### 2.3. Cấu hình trong admin shops

Trong admin NukeViet → **Shops** → **Phương thức thanh toán** → **NPay**, điền:

| Trường | Mô tả |
|---|---|
| `npay_api_base` | Endpoint NPay, mặc định `https://api.npay.vn`. |
| `npay_api_token` | API token (Apikey) lấy trong dashboard NPay. |
| `npay_bank_code` | Mã ngân hàng VietQR (VCB, VTB, TCB, MB, ...). |
| `npay_account_number` | Số tài khoản nhận tiền. |
| `npay_account_name` | Tên chủ tài khoản (in hoa, không dấu). |
| `npay_prefix` | Tiền tố mã chuyển khoản, mặc định `NPAY`. |
| `npay_qr_template` | Mẫu QR: `compact` / `qr_only` / `print`. |

### 2.4. Cấu hình webhook tại NPay

Vào dashboard NPay → **Webhooks** → tạo mới:

```
URL:     https://your-site.com/modules/shops/payment_gateway/npay/webhook.php
Method:  POST
Header:  Authorization: Apikey <token bạn vừa cấu hình ở 2.3>
```

NPay sẽ POST JSON theo định dạng:

```json
{
  "gateway": "VietinBank",
  "transactionDate": "2023-04-05 14:30:00",
  "accountNumber": "113366668888",
  "code": "NPAY12",
  "content": "Khach hang chuyen khoan NPAY12",
  "transferType": "in",
  "transferAmount": 2277000,
  "accumulated": 19077000,
  "referenceCode": "MBVCB.3278907687"
}
```

Webhook sẽ:

1. Xác thực `Authorization: Apikey <token>` (so sánh bằng `hash_equals`).
2. Bỏ qua giao dịch `transferType != "in"`.
3. Tìm mã `NPAY<order_id>` trong `code` rồi đến `content`.
4. Tra đơn hàng theo cột `pay_id` trong `nv_<lang>_shops_orders`.
5. Nếu đủ tiền → `UPDATE ... SET status='paid'`.
6. Ghi log vào `nv_<lang>_shops_payment_log` (nếu bảng tồn tại).

## 3. Trang checkout

Khi khách chọn **NPay** ở bước thanh toán, module gọi `npay_payment_render($order)` để render template `templates/payment_instruction.tpl` gồm:

- Ảnh QR từ `https://qr.npay.vn/img?acc=...&bank=...&amount=...&des=...&template=compact`.
- Thông tin tài khoản (có nút copy).
- Khối trạng thái + JS poll `webhook.php?action=status&pay_id=...` mỗi 5 giây.
- Tự reload trang khi đơn hàng được đánh dấu `paid`.

## 4. Gỡ cài đặt

```bash
php modules/shops/payment_gateway/npay/uninstall.php
```

hoặc xoá phương thức trong admin. Uninstaller chỉ xoá các dòng cấu hình `npay_*`. Bảng `nv_<lang>_shops_payment_log` được giữ lại để bảo toàn lịch sử — drop thủ công nếu cần.

## 5. Bảo mật

- Toàn bộ output đi qua `nv_htmlspecialchars()`.
- Mọi câu query tham số hoá bằng `prepare()` hoặc `$db->quote()`.
- Webhook chỉ chấp nhận POST + so sánh token bằng `hash_equals` (chống timing attack).
- Endpoint `?action=status` chỉ trả mã `pay_id`, `order_id`, `status` — không lộ thông tin nhạy cảm.

## 6. Hỗ trợ

- Tài liệu: https://docs.npay.vn
- Email: support@npay.vn
- Issue tracker: https://github.com/npay/npay-nukeviet/issues

---

© 2024 NPay. Phát hành theo giấy phép GPL-2.0-or-later.
