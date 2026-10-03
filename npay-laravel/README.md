# NPay Laravel

Gói tích hợp **NPay** (npay.vn) cho Laravel — sinh QR thanh toán VietQR, nhận webhook giao dịch, lưu lịch sử và phát event để xử lý đơn hàng tự động.

- API: `https://api.npay.vn`
- QR: `https://qr.npay.vn`
- Dashboard: `https://npay.vn` — tài liệu: `https://docs.npay.vn`

## Yêu cầu

- PHP `>= 8.1`
- Laravel `10.x | 11.x | 12.x`

## Cài đặt

```bash
composer require npay/laravel
php artisan npay:install
```

Lệnh `npay:install` sẽ:

1. Publish file `config/npay.php`.
2. Publish migration tạo bảng `npay_transactions` (kèm migration thêm cột `npay_id` chống trùng).
3. Publish view mẫu `resources/views/vendor/npay/payment.blade.php`.
4. Chạy `php artisan migrate`.

> Bỏ qua bước migrate: `php artisan npay:install --no-migrate`
> Ghi đè file đã publish: `php artisan npay:install --force`

## Cấu hình `.env`

```dotenv
NPAY_API_TOKEN=zna_xxx              # API token (Cài đặt → API token trên npay.vn), gọi Public API
NPAY_ACCOUNT_NUMBER=0123456789
NPAY_BANK_BIN=970422
NPAY_ACCOUNT_HOLDER="NGUYEN VAN A"
NPAY_WEBHOOK_TOKEN=webhook-api-key   # Authorization: Apikey <...>
NPAY_WEBHOOK_SECRET=webhook-secret   # X-Npay-Signature (tuỳ chọn)
NPAY_DEFAULT_TEMPLATE=compact
NPAY_CODE_PREFIX=NPAY
NPAY_WEBHOOK_ROUTE=npay/webhook
```

## Cấu hình webhook trên NPay

Trong dashboard NPay (<https://npay.vn>) → **Webhook**:

- URL: `https://your-domain.tld/npay/webhook` (hoặc giá trị `NPAY_WEBHOOK_ROUTE` bạn đặt)
- Method: `POST`
- Xác thực **API Key**: NPay gửi `Authorization: Apikey <key>` → đặt vào `NPAY_WEBHOOK_TOKEN`
- Bật **Ký request** và chép webhook secret vào `NPAY_WEBHOOK_SECRET`: NPay gửi
  `X-Npay-Signature` = hex HMAC-SHA256 của raw body (không có tiền tố `sha256=`) kèm `X-Npay-Timestamp`.

Route đã được tự động đăng ký bởi service provider, có gắn middleware `npay.webhook`: request hợp lệ khi
khớp **một trong hai** cách đã cấu hình (chữ ký có timestamp lệch quá `NPAY_WEBHOOK_TOLERANCE` giây, mặc
định 300, bị từ chối).

Mỗi giao dịch lưu một lần theo `id` NPay (`tx_…`, cột `npay_id` unique): NPay gửi lại thì trả
`{"duplicated": true}` và **không** phát lại event `TransactionReceived`. `amount_in` chỉ nhận tiền khi
`transferType = "in"`, `amount_out` khi `"out"`.

## Sinh QR thanh toán

```php
use NPay\Laravel\Facades\NPay;

$orderId = 42;
$code = NPay::generatePaymentCode($orderId);            // NPAY42
$qrUrl = NPay::generateQrUrl([
    'amount' => 199000,
    'description' => $code,
]);

return view('npay::payment', [
    'qrUrl' => $qrUrl,
    'amount' => 199000,
    'description' => $code,
]);
```

## Lắng nghe giao dịch (event listener)

`app/Providers/EventServiceProvider.php`:

```php
use NPay\Laravel\Events\TransactionReceived;
use App\Listeners\HandleNPayTransaction;

protected $listen = [
    TransactionReceived::class => [
        HandleNPayTransaction::class,
    ],
];
```

`app/Listeners/HandleNPayTransaction.php`:

```php
namespace App\Listeners;

use App\Models\Order;
use NPay\Laravel\Events\TransactionReceived;
use NPay\Laravel\Facades\NPay;

class HandleNPayTransaction
{
    public function handle(TransactionReceived $event): void
    {
        $tx = $event->transaction;
        $orderId = NPay::parsePaymentCode((string) $tx->transaction_content);
        if (!$orderId) {
            return;
        }

        $order = Order::find($orderId);
        if ($order && (float) $tx->amount_in >= (float) $order->total) {
            $order->markAsPaid($tx->npay_id);
        }
    }
}
```

## Gọi API danh sách giao dịch

Public API v1 (`/api/v1/transactions/`, header `Authorization: Bearer <NPAY_API_TOKEN>`):

```php
$page = NPay::transactions()->list([
    'page' => 1,
    'page_size' => 20,          // tối đa 100
    'date_from' => '2026-10-01',
    'q' => 'NPAY42',            // tìm trong nội dung, mã thanh toán, mã tham chiếu…
]);
// ['items' => [['id' => 'tx_…', 'type' => 'in', 'amount' => 100000, 'description' => '…', …]],
//  'count' => 1, 'page' => 1, 'page_size' => 20]

$tx = NPay::transactions()->get_('tx_8f3k2m9q');
$byRef = NPay::transactions()->findByReference('FT26134ABC');
$accounts = NPay::transactions()->accounts();
```

## Testing

```bash
composer install
vendor/bin/phpunit
```

## Thay đổi

### 1.1.0

- `generateQrUrl()` dùng `https://qr.npay.vn/qrcard` / `/qrpay` (`/img` không còn).
- `TransactionApi` chuyển sang Public API v1 (`/api/v1/transactions/`, `Bearer`), id dạng chuỗi
  `tx_…` (`get_(string $id)`); thêm `accounts()`.
- Webhook: chống trùng theo `id` (migration mới), tách `amount_in`/`amount_out` theo `transferType`,
  thêm `NPAY_WEBHOOK_SECRET` để kiểm `X-Npay-Signature`.

## License

MIT © NPay
