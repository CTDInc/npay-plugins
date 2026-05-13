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

Mọi plugin nhận webhook từ NPay với payload JSON:

```json
{
  "id": 123,
  "gateway": "VietinBank",
  "transactionDate": "2024-01-01 12:00:00",
  "accountNumber": "113366668888",
  "subAccount": null,
  "code": "NPAY123",
  "content": "thanh toan don hang NPAY123",
  "transferType": "in",
  "transferAmount": 500000,
  "accumulated": 19077000,
  "referenceCode": "MBVCB.3278907687",
  "description": "..."
}
```

Auth: `Authorization: Apikey <token>` (constant-time compare). Một số plugin hỗ trợ thêm HMAC `X-NPay-Signature: sha256=<hex>`.

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

- Dashboard: <https://my.npay.vn/integrations>
- Developer docs: <https://developer.minhanhfin.tech/>

## License

Mỗi plugin theo license riêng (GPL-2.0+ cho WordPress plugin, MIT cho SDK). Xem README/LICENSE từng plugin con.
