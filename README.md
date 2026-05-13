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
