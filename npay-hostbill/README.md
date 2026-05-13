# NPay Gateway cho HostBill

Module thanh toán chính thức của **NPay** dành cho nền tảng quản lý hosting **HostBill**. Hỗ trợ chuyển khoản ngân hàng nội địa Việt Nam kèm mã **VietQR** động, đối soát giao dịch tức thì qua webhook.

## ✨ Tính năng

- 🇻🇳 Hỗ trợ tất cả ngân hàng Việt Nam qua chuẩn **VietQR / NAPAS**.
- 🔄 **Đối soát tự động**: Webhook từ NPay → cộng tiền vào hóa đơn HostBill ngay lập tức.
- 🖼️ Sinh mã **QR động** qua `https://qr.npay.vn` với số tiền + nội dung chuyển khoản đã điền sẵn.
- 🔐 Xác thực webhook bằng header `Authorization: Apikey <token>` + so sánh hằng thời gian (`hash_equals`).
- 🌐 Hỗ trợ song ngữ: **Tiếng Việt** & **English**.
- 🧩 Cấu hình linh hoạt: số TK, BIN ngân hàng, mẫu QR (`compact` / `qronly` / `print`), tiền tố mã giao dịch.
- 🧠 Trích xuất ID hóa đơn thông minh: ưu tiên trường `code`, fallback sang `content`.
- 💡 Bảo vệ idempotent: ghi nhận giao dịch theo `referenceCode` để tránh cộng tiền trùng.

## 📦 Cấu trúc

```
npay-hostbill/
├── composer.json
├── INSTALL.md
├── README.md
└── npay/
    ├── npay.php                 # Module chính (class npay extends HostBillPaymentGateway)
    ├── icon.png                 # Logo hiển thị trong HostBill admin
    ├── lang/
    │   ├── english.php
    │   └── vietnamese.php
    └── templates/
        └── payment-form.tpl     # Smarty template hiển thị QR cho khách
```

## 🚀 Cài đặt nhanh

1. Copy thư mục `npay/` vào `<hostbill_root>/includes/modules/gateways/`.
2. Vào **HostBill Admin → Settings → Payments → Modules**, kích hoạt **NPay**.
3. Cấu hình **API Token, số tài khoản, BIN ngân hàng**.
4. Trỏ webhook trên NPay dashboard về:

   ```
   https://your-hostbill.com/includes/modules/gateways/callback/npay.php
   ```

Chi tiết xem [INSTALL.md](./INSTALL.md).

## 🔌 Webhook payload

NPay POST JSON tới callback URL với header `Authorization: Apikey <token>`:

```json
{
  "gateway": "VietinBank",
  "transactionDate": "2023-04-05 14:30:00",
  "accountNumber": "113366668888",
  "code": "NPAY-INV123",
  "content": "thanh toan don hang NPAY-INV123",
  "transferType": "in",
  "transferAmount": 2277000,
  "accumulated": 19077000,
  "referenceCode": "MBVCB.3278907687"
}
```

Module sẽ:
1. Kiểm tra header `Authorization: Apikey ...` so với `api_token` đã cấu hình (`hash_equals`).
2. Bỏ qua nếu `transferType != "in"`.
3. Trích ID hóa đơn từ `code` (ví dụ `NPAY-INV123` → invoice `123`).
4. Gọi `$this->addInvoicePayment($invoiceId, $referenceCode, $amount, 0, 'npay')`.
5. Trả JSON kết quả + HTTP status (`200` / `401` / `422` / `500`).

## 🏦 Mã BIN một số ngân hàng phổ biến

| Ngân hàng | BIN |
|---|---|
| Vietcombank | 970436 |
| VietinBank  | 970415 |
| BIDV        | 970418 |
| Agribank    | 970405 |
| MBBank      | 970422 |
| Techcombank | 970407 |
| ACB         | 970416 |
| TPBank      | 970423 |
| Sacombank   | 970403 |
| VPBank      | 970432 |

Danh sách đầy đủ: <https://api.npay.vn/banks>.

## 🛠️ Phát triển

- File chính: `npay/npay.php` – class `npay extends HostBillPaymentGateway`.
- Các hook chuẩn HostBill: `getName()`, `getDescription()`, `getDescriptionFull()`, `setSettings()`, `drawForm()`, `callback()`.
- Kiểm tra cú pháp: `php -l npay/npay.php`.

## 📜 Giấy phép

MIT © NPay Team – <https://npay.vn>
