# NPay Integrations

Bộ tích hợp chính thức cho **NPay** — automate thanh toán Việt Nam.

| Plugin | Loại | Stack |
|---|---|---|
| [npay-woocommerce](./npay-woocommerce) | Cổng thanh toán | WordPress / WooCommerce / PHP |
| [npay-shopify](./npay-shopify) | Cổng thanh toán | Node.js / Shopify OAuth App |
| [npay-sapo](./npay-sapo) | Cổng thanh toán | PHP / Sapo OAuth App |
| [npay-haravan](./npay-haravan) | Cổng thanh toán | PHP / Haravan OAuth App |
| [npay-ladipage](./npay-ladipage) | Cổng thanh toán | PHP standalone webhook |
| [npay-learnpress](./npay-learnpress) | Cổng thanh toán LMS | WordPress / LearnPress / PHP |
| [npay-hostbill](./npay-hostbill) | Cổng thanh toán hosting | HostBill / PHP |
| [npay-nukeviet](./npay-nukeviet) | Cổng thanh toán CMS | NukeViet 4.x / PHP |
| [npay-botble](./npay-botble) | Cổng thanh toán CMS | Botble (Laravel) / PHP |
| [npay-ezyplatform](./npay-ezyplatform) | Cổng thanh toán CMS | Ezyplatform / Java |
| [npay-laravel](./npay-laravel) | SDK | Composer / Laravel package |
| [npay-php-mysql](./npay-php-mysql) | SDK / Mẫu mã | PHP + MySQL vanilla |

## Webhook payload chuẩn

Mọi plugin nhận webhook biến động số dư từ NPay. Body JSON (hoặc form-urlencoded nếu webhook
chọn kiểu đó):

```json
{
  "id": "tx_8f3k2m9q",
  "gateway": "VietinBank",
  "transactionDate": "2024-01-01 12:00:00",
  "accountNumber": "113366668888",
  "subAccount": null,
  "code": null,
  "content": "thanh toan don hang NPAY123",
  "transferType": "in",
  "transferAmount": 500000,
  "accumulated": 19077000,
  "referenceCode": null,
  "description": "thanh toan don hang NPAY123",
  "timestamp": 1704085200,
  "nonce": "9b2f0c4e6a1d4f0e8c3b5a7d9e1f2a3b"
}
```

- `id` là **chuỗi** công khai `tx_…` (không phải số) — dùng nó làm khoá chống trùng, NPay có thể
  gửi lại cùng giao dịch.
- `code` và `referenceCode` thường `null` (nguồn ngân hàng không gửi) — tìm mã đơn trong `content`,
  đừng bắt buộc hai trường này.
- Chỉ xử lý `transferType = "in"`; nhận `transferAmount` **≥** số tiền đơn.
- Giao dịch không khớp đơn nào trả **200** (kèm lý do) để NPay không gửi lại; chỉ trả 4xx khi sai
  xác thực / payload hỏng.

### Xác thực

| Header | Giá trị | Nguồn |
|---|---|---|
| `Authorization` | `Apikey <key>` — chọn kiểu xác thực **API Key** (plugin không đọc `Basic`) | API key khai khi tạo webhook |
| `X-Npay-Signature` | hex thường HMAC-SHA256 của **raw body**, **không** có tiền tố `sha256=` | khoá = **webhook secret** do NPay sinh cho từng webhook, hiện trên dashboard khi bật *Ký request* (xoay được) |
| `X-Npay-Timestamp` | Unix giây lúc gửi | plugin từ chối chữ ký lệch quá 5 phút; thiếu header thì bỏ qua kiểm |
| `X-Npay-Nonce` | chuỗi ngẫu nhiên | cũng nằm trong body |

Cả 12 plugin nhận `Authorization: Apikey` (so sánh hằng thời gian). Tất cả plugin trừ
`npay-php-mysql` coi request hợp lệ khi đúng **API key hoặc chữ ký**; `npay-php-mysql` bắt buộc
Apikey và kiểm thêm chữ ký nếu khai `hmac_secret`. Không khai gì → từ chối.

### QR

Ảnh QR lấy từ dịch vụ gen-qr `https://qr.npay.vn` (endpoint `/img` cũ **không còn**):

- `/qrcard` — thẻ VietQR đầy đủ (logo, chủ TK); `/qrpay` — chỉ mã QR.
- Tham số: `ngan_hang` (khoá ngân hàng: `mbbank`, `vietcombank`…) **hoặc** `ma_bin` (BIN Napas,
  vd `970422`), `tai_khoan`, `so_tien`, `noi_dung`, `chu_tai_khoan` (chỉ `/qrcard`).

```
https://qr.npay.vn/qrcard?ma_bin=970422&tai_khoan=0123456789&so_tien=500000&noi_dung=NPAY123&chu_tai_khoan=NGUYEN+VAN+A
```

### Public API

`GET https://api.npay.vn/api/v1/transactions/` và `/api/v1/transactions/{tx_id}/`,
`GET /api/v1/accounts/` — header `Authorization: Bearer <api token zna_…>` (tạo trong dashboard).
Danh sách trả `{"items": [...], "count", "page", "page_size"}`. SDK `npay-laravel` bọc sẵn.

## Cài đặt nhanh

### Tải release đóng gói sẵn (khuyến nghị)

Mỗi tag `v*` đẩy lên repo sẽ tự build 12 zip tương ứng và đính kèm vào GitHub Release. Tải tại: <https://github.com/CTDInc/npay-plugins/releases/latest>

| File zip | Dùng cho |
|---|---|
| `npay-woocommerce-<version>.zip` | WP Admin → Plugins → Upload → chọn zip |
| `npay-learnpress-<version>.zip` | WP Admin → Plugins → Upload |
| `npay-hostbill-<version>.zip` | giải nén vào `<root>/includes/modules/gateways/` |
| `npay-nukeviet-<version>.zip` | giải nén vào `modules/shops/payment_gateway/` |
| `npay-botble-<version>.zip` | giải nén vào `platform/plugins/` |
| `npay-sapo-<version>.zip` | deploy lên host PHP (đã bundle `vendor/`) |
| `npay-haravan-<version>.zip` | deploy lên host PHP (đã bundle `vendor/`) |
| `npay-ladipage-<version>.zip` | deploy lên host PHP (đã bundle `vendor/`) |
| `npay-php-mysql-<version>.zip` | deploy lên host PHP, import `db.sql` (đã bundle `vendor/`) |
| `npay-shopify-<version>.zip` | deploy Node app (đã bundle `node_modules/`) |
| `npay-ezyplatform-<version>.zip` | copy `target/*.jar` vào plugins folder của Ezyplatform |
| `npay-laravel-<version>.zip` | `composer require npay/laravel` hoặc giải nén thủ công |

Mỗi zip đính kèm file `.sha256` để xác minh tính toàn vẹn:

```bash
sha256sum -c npay-woocommerce-1.0.0.zip.sha256
```

### Build từ source

Mỗi plugin có README riêng (tiếng Việt) trong thư mục con. Nguyên tắc chung:

- **WordPress / WooCommerce / LearnPress**: zip thư mục → upload qua WP Admin.
- **HostBill**: copy thư mục `npay/` vào `<root>/includes/modules/gateways/`.
- **NukeViet**: copy vào `modules/shops/payment_gateway/`.
- **Botble**: copy vào `platform/plugins/`.
- **Sapo / Haravan / Shopify**: deploy app PHP/Node, set OAuth callback + webhook URL.
- **Laravel SDK**: `composer require npay/laravel`, `php artisan npay:install`.
- **PHP & MySQL**: deploy PHP vào hosting, import `db.sql`, sửa `config.php`.

## Tài liệu tích hợp

- Dashboard: <https://npay.vn>
- Tài liệu: <https://docs.npay.vn>

## License

Mỗi plugin theo license riêng (GPL-2.0+ cho WordPress plugin, MIT cho SDK). Xem README/LICENSE từng plugin con.
