# NPay · Shopify Plugin

Tích hợp cổng thanh toán QR **NPay** vào cửa hàng Shopify dưới dạng **Custom App OAuth** (Node.js / Express).

Khách hàng đặt đơn trên Shopify → ứng dụng tạo mã `NPAY-{order_id}` và sinh QR thanh toán → khi NPay nhận được tiền, webhook đối soát và gọi Shopify Admin API để **capture** đơn hàng (đánh dấu đã thanh toán).

Tham khảo: <https://docs.npay.vn> · <https://shopify.dev/docs/api>

---

## 1. Cài đặt

```bash
git clone <repo>
cd npay-shopify
cp .env.example .env
npm install
npm run dev    # dev (auto-reload)
# npm start    # production
```

Yêu cầu: **Node.js 20+**.

### Biến môi trường (`.env`)

| Biến | Ý nghĩa |
| --- | --- |
| `SHOPIFY_API_KEY` | Client ID của Custom App |
| `SHOPIFY_API_SECRET` | Client Secret |
| `SCOPES` | `read_orders,write_orders` |
| `HOST` | URL công khai (HTTPS) của app, vd `https://npay-shop.example.com` |
| `SHOPIFY_API_VERSION` | Mặc định `2024-10` |
| `DATABASE_PATH` | Đường dẫn SQLite |
| `PORT` | Cổng HTTP, mặc định `3000` |

---

## 2. Tạo Custom App trên Shopify Partners

1. Vào <https://partners.shopify.com> → **Apps** → **Create app** → **Create app manually**.
2. **App URL**: `https://<HOST>/`
3. **Allowed redirection URL(s)**: `https://<HOST>/auth/callback`
4. Lấy **Client ID** + **Client secret** điền vào `.env`.
5. Cấu hình scopes: `read_orders, write_orders`.

### Cài lên cửa hàng dev

Truy cập trên trình duyệt:

```
https://<HOST>/auth?shop=<your-store>.myshopify.com
```

Sau khi cấp quyền:
- Access token được lưu vào DB.
- Webhook `orders/create` được đăng ký tự động về `/webhooks/shopify`.
- Trình duyệt được chuyển sang trang **Admin** của plugin.

---

## 3. Cấu hình NPay

Mở trang admin: `https://<HOST>/admin?shop=<your-store>.myshopify.com`

Khai báo:
- **Số tài khoản**, **BIN ngân hàng** (vd `970422` = MB, hoặc mã `mbbank`, `VCB`), **Chủ tài khoản**.
- **NPay API key**: API key của webhook trên NPay (`Authorization: Apikey <key>`).
- **NPay webhook secret** (tuỳ chọn): secret hiện khi bật **Ký request** trên dashboard.
- **QR template**: `compact` (mặc định, thẻ VietQR `qr.npay.vn/qrcard`) / `qr_only` (chỉ mã QR, `/qrpay`).

### Cấu hình webhook NPay

Trên dashboard NPay, thêm webhook về URL:

```
https://<HOST>/webhooks/npay
```

Loại xác thực: **API Key** — NPay gửi `Authorization: Apikey <key>`, khớp với **NPay API key** đã lưu.
Bật thêm **Ký request** thì NPay gửi `X-Npay-Signature` = hex HMAC-SHA256 của raw body (không có
tiền tố `sha256=`) + `X-Npay-Timestamp`; app chấp nhận nếu đúng **một trong hai** (chữ ký lệch quá
5 phút bị từ chối). Không khai cả hai thì mọi webhook bị từ chối.

---

## 4. Luồng thanh toán

1. Khách đặt đơn trên Shopify (`orders/create`).
2. Plugin nhận webhook, lưu đơn vào DB, sinh `ref_code = NPAY-{order_id}`.
3. Bạn chuyển khách sang trang QR: `https://<HOST>/payment/NPAY-{order_id}` (ví dụ qua thank-you script, email, hoặc redirect tại checkout extension).
4. Trang QR hiển thị mã NPay (VietQR), thông tin tài khoản, đếm ngược + JS poll `/status/:refCode`.
5. Khi khách chuyển khoản, NPay gọi `/webhooks/npay`.
6. Plugin chỉ xét `transferType = "in"`, tìm `NPAY-{order_id}` trong `code`/`content` (ngân hàng bỏ
   dấu `-` thành `NPAY1001` vẫn khớp), xác thực `Apikey` hoặc chữ ký, đòi số tiền ≥ đơn, rồi gọi Shopify:
   `POST /admin/api/2024-10/orders/{id}/transactions.json` với `kind=capture`.
7. Đơn hàng chuyển trạng thái **paid** trên Shopify.

---

## 5. Cấu trúc thư mục

```
npay-shopify/
├── server.js
├── package.json
├── .env.example
├── Dockerfile
├── src/
│   ├── lib/
│   │   ├── db.js
│   │   ├── npay-client.js
│   │   └── shopify-client.js
│   ├── routes/
│   │   ├── auth.js
│   │   ├── webhooks.js
│   │   ├── payment.js
│   │   └── admin.js
│   └── templates/
│       ├── payment.html
│       └── admin.html
├── public/assets/{css,js}
└── tests/
    ├── test-qr.js
    └── test-hmac.js
```

---

## 6. Kiểm thử

```bash
npm test
# hoặc
node --test tests/
```

Bao gồm:
- `test-qr.js`: builder URL QR + parse `NPAY-xxx` từ nội dung CK.
- `test-hmac.js`: xác thực HMAC Shopify (raw body + OAuth query) và header `Apikey` của NPay.

---

## 7. Docker

```bash
docker build -t npay-shopify .
docker run --rm -p 3000:3000 --env-file .env npay-shopify
```

---

## Thay đổi

### 1.1.0

- QR chuyển từ `qr.sepay.vn` sang `https://qr.npay.vn/qrcard` (`/qrpay` khi `qr_only`).
- Webhook NPay: chỉ nhận `transferType = "in"`, mã đơn không cần dấu `-`, thêm webhook secret
  (`X-Npay-Signature`), giao dịch không khớp / chuyển thiếu trả 200 thay vì 400/404.

## Bản quyền

© NPay. Tên Shopify thuộc về Shopify Inc.
