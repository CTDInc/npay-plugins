=== NPay for LearnPress ===
Contributors: npay
Tags: learnpress, payment-gateway, bank-transfer, vietnam, qr-code, sepay, npay
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

NPay - Cổng thanh toán chuyển khoản ngân hàng tự động cho LearnPress. Tự động xác nhận đơn hàng và mở khóa khoá học khi nhận được tiền.

== Description ==

NPay for LearnPress là cổng thanh toán giúp tích hợp dịch vụ **NPay** (nền tảng tự động hoá thanh toán ngân hàng tại Việt Nam) vào LearnPress LMS:

* Sinh QR chuyển khoản theo chuẩn VietQR (qua `qr.npay.vn`).
* Mã giao dịch dạng `NPAY{order_id}`.
* Webhook tự động cập nhật đơn hàng → `completed` và `learn_press_update_user_item_meta($enrollment, 'status', 'completed')` để mở khoá học.
* Trang thanh toán có đếm ngược + polling 4s/lần.

== Installation ==

1. Tải lên thư mục `npay-learnpress` vào `/wp-content/plugins/`.
2. Kích hoạt plugin trong menu **Plugins**.
3. Vào **LearnPress → Settings → Payments → NPay** để cấu hình.
4. Cấu hình webhook trên dashboard NPay với URL `https://your-site.com/wp-json/npay/v1/learnpress-webhook` và header `Authorization: Apikey <token>`.

== Frequently Asked Questions ==

= Cần dùng những API gì của NPay? =

Plugin chỉ nhận webhook từ NPay, không gọi ngược lại. Bạn chỉ cần token Apikey để xác thực webhook.

= Nội dung chuyển khoản có bắt buộc đúng định dạng? =

Plugin tìm chuỗi `NPAY<số>` trong trường `content` của webhook, không phân biệt hoa thường, cho phép có dấu cách / `-` / `_` giữa `NPAY` và phần số.

== Changelog ==

= 1.0.0 =
* Bản phát hành đầu tiên.

== Upgrade Notice ==

= 1.0.0 =
Bản phát hành đầu tiên.
