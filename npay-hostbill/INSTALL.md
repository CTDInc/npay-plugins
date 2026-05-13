# Cài đặt NPay Gateway cho HostBill

Tài liệu này hướng dẫn cài đặt module thanh toán **NPay** vào hệ thống HostBill.

## 1. Yêu cầu

- HostBill đã cài đặt và hoạt động bình thường.
- PHP >= 7.2.
- Tài khoản NPay đã được kích hoạt tại <https://npay.vn> và đã có **API Token (Apikey)**.
- Số tài khoản ngân hàng + mã **BIN ngân hàng** (theo chuẩn NAPAS, VD: VietinBank = `970415`, MBBank = `970422`, Vietcombank = `970436`).

## 2. Sao chép module vào HostBill

Giả sử HostBill được cài tại `/var/www/hostbill/`. Copy thư mục `npay/` (KHÔNG copy thư mục cha `npay-hostbill`) vào:

```
<hostbill_root>/includes/modules/gateways/
```

Ví dụ:

```bash
cp -r npay/ /var/www/hostbill/includes/modules/gateways/
```

Cấu trúc sau khi copy phải là:

```
<hostbill_root>/includes/modules/gateways/npay/
├── npay.php
├── icon.png
├── lang/
│   ├── english.php
│   └── vietnamese.php
└── templates/
    └── payment-form.tpl
```

Đảm bảo quyền đọc cho user web server (thường là `www-data`):

```bash
chown -R www-data:www-data /var/www/hostbill/includes/modules/gateways/npay
chmod -R 755 /var/www/hostbill/includes/modules/gateways/npay
```

## 3. Kích hoạt module trong HostBill Admin

1. Đăng nhập **HostBill Admin**.
2. Vào **Settings → Payments → Modules**.
3. Tìm dòng **NPay** trong danh sách module chưa kích hoạt, bấm **Activate**.
4. Sau khi kích hoạt, bấm **Configure / Manage**.

## 4. Cấu hình module

Điền các trường cấu hình:

| Trường | Mô tả | Ví dụ |
|---|---|---|
| **API Token (Apikey)** | Token xác thực webhook do NPay cấp | `npay_live_xxxxxxxx` |
| **Số tài khoản ngân hàng** | Số TK nhận tiền | `113366668888` |
| **Mã BIN ngân hàng** | Mã NAPAS | `970415` |
| **Tên viết tắt ngân hàng** | Hiển thị cho khách | `VietinBank` |
| **Mẫu QR** | `compact` / `qronly` / `print` | `compact` |
| **Tiền tố mã giao dịch** | Prefix cho mã đối soát | `NPAY-` |

Bấm **Save Changes**.

## 5. Cấu hình Webhook trên dashboard NPay

Đăng nhập <https://my.npay.vn> → **Webhook / Cấu hình đối soát**, thêm endpoint:

```
https://your-hostbill.com/includes/modules/gateways/callback/npay.php
```

Phương thức: `POST`, Content-Type: `application/json`, Header xác thực:

```
Authorization: Apikey <API_TOKEN của bạn>
```

## 6. Kiểm thử

1. Tạo hóa đơn test, chọn phương thức **NPay**.
2. Trang thanh toán hiển thị mã QR và nội dung chuyển khoản dạng `NPAY-INV<invoice_id>`.
3. Chuyển khoản số tiền đúng với nội dung trên.
4. Trong vài giây, NPay sẽ POST webhook về HostBill và hóa đơn chuyển trạng thái **Paid**.

## 7. Gỡ lỗi

- Log của HostBill: **System → Logs → Modules**.
- Webhook NPay trả về JSON `{"success":true,...}` nếu xử lý thành công, mã HTTP `401` nếu token sai, `422` nếu không khớp hóa đơn.
- Test webhook nhanh bằng `curl`:

```bash
curl -X POST https://your-hostbill.com/includes/modules/gateways/callback/npay.php \
     -H "Authorization: Apikey $TOKEN" \
     -H "Content-Type: application/json" \
     -d '{"gateway":"VietinBank","transactionDate":"2025-05-13 17:00:00","accountNumber":"113366668888","code":"NPAY-INV123","content":"thanh toan NPAY-INV123","transferType":"in","transferAmount":250000,"accumulated":1000000,"referenceCode":"TEST.123"}'
```

## 8. Gỡ cài đặt

1. Admin → **Settings → Payments → Modules → NPay → Deactivate**.
2. Xóa thư mục `<hostbill_root>/includes/modules/gateways/npay/` nếu muốn loại bỏ hoàn toàn.
