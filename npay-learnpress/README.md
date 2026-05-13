# NPay for LearnPress

Cổng thanh toán **NPay** cho **LearnPress** - tự động xác nhận thanh toán qua chuyển khoản ngân hàng bằng webhook NPay.

## Tính năng

- Hiển thị mã QR chuyển khoản (qua `https://qr.npay.vn/img`) ngay sau khi đặt mua khoá học.
- Sinh mã giao dịch `NPAY{order_id}` để khớp nội dung chuyển khoản.
- Webhook nhận giao dịch từ NPay → tự động cập nhật đơn LearnPress thành `completed` và mở khoá học cho học viên.
- Tự động polling trạng thái đơn hàng mỗi 4 giây, đếm ngược thời gian hết hạn.
- Bộ cài đặt đầy đủ: số tài khoản, mã BIN ngân hàng, tên chủ TK, mẫu QR, API token.

## Yêu cầu

- WordPress >= 5.8
- PHP >= 7.4
- LearnPress >= 4.0

## Cài đặt

1. Tải/upload thư mục `npay-learnpress` vào `wp-content/plugins/`.
2. Kích hoạt **NPay for LearnPress** trong **Plugins**.
3. Vào **LearnPress → Settings → Payments → NPay**, điền:
   - **API Token**: token Apikey nhận từ dashboard NPay.
   - **Số tài khoản ngân hàng** + **Mã BIN** (ví dụ `970436` cho Vietcombank).
   - **Tên chủ tài khoản** (không dấu, khuyến nghị).
   - **Mẫu QR**: `compact` / `compact2` / `qr_only` / `print`.

## Cấu hình webhook trên NPay

Trên trang quản trị NPay, thêm webhook với:

- **URL**: `https://your-site.com/wp-json/npay/v1/learnpress-webhook`
- **Method**: `POST`
- **Header**: `Authorization: Apikey <API_TOKEN>` (đúng token đã cấu hình trong plugin)
- **Body** (JSON, NPay tự gửi):

```json
{
  "gateway": "VietinBank",
  "transactionDate": "2023-04-05 14:30:00",
  "accountNumber": "113366668888",
  "code": "NPAY123",
  "content": "chuyen tien NPAY123",
  "transferType": "in",
  "transferAmount": 2277000,
  "accumulated": 19077000,
  "referenceCode": "MBVCB.3278907687"
}
```

Plugin sẽ:

1. Xác thực header `Authorization: Apikey ...` (so khớp với token đã lưu).
2. Trích `NPAY<số>` từ trường `content` để tìm `order_id`.
3. Kiểm tra số tiền (thiếu sẽ ghi log, không tự complete).
4. Gọi `$order->update_status('completed')` + `learn_press_update_user_item_meta($enrollment, 'status', 'completed')` cho từng khoá học trong đơn.

## REST endpoints

| Endpoint | Method | Mô tả |
|---|---|---|
| `/wp-json/npay/v1/learnpress-webhook` | POST | Nhận giao dịch từ NPay (xác thực Apikey). |
| `/wp-json/npay/v1/learnpress/order-status/{order_id}` | GET | Trả về trạng thái đơn hàng cho JS polling. |

## Trang thanh toán

Sau khi đặt mua, học viên được chuyển đến URL dạng:

```
https://your-site.com/?npay-payment=1&order_id=123&key=<hash>
```

Trang sẽ hiển thị: mã QR, số TK, số tiền, nội dung chuyển khoản, đồng hồ đếm ngược 30 phút, polling tự động.

## Giấy phép

GPL-2.0-or-later
