# NPay Laravel

Gói tích hợp **NPay** (npay.vn) cho Laravel — sinh QR thanh toán VietQR, nhận webhook giao dịch, lưu lịch sử và phát event để xử lý đơn hàng tự động.

- API: `https://api.npay.vn`
- QR: `https://qr.npay.vn`
- Dashboard: `https://my.npay.vn`

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
2. Publish migration tạo bảng `npay_transactions`.
3. Publish view mẫu `resources/views/vendor/npay/payment.blade.php`.
4. Chạy `php artisan migrate`.

> Bỏ qua bước migrate: `php artisan npay:install --no-migrate`
> Ghi đè file đã publish: `php artisan npay:install --force`

## Cấu hình `.env`

```dotenv
NPAY_API_TOKEN=your-api-token-from-my.npay.vn
NPAY_ACCOUNT_NUMBER=0123456789
NPAY_BANK_BIN=970422
NPAY_ACCOUNT_HOLDER="NGUYEN VAN A"
NPAY_WEBHOOK_TOKEN=your-shared-secret
NPAY_DEFAULT_TEMPLATE=compact
NPAY_CODE_PREFIX=NPAY
NPAY_WEBHOOK_ROUTE=npay/webhook
```

## Cấu hình webhook trên NPay

Trong dashboard NPay → **Cài đặt webhook**:

- URL: `https://your-domain.tld/npay/webhook` (hoặc giá trị `NPAY_WEBHOOK_ROUTE` bạn đặt)
- Method: `POST`
- Header xác thực: `Authorization: Apikey <NPAY_WEBHOOK_TOKEN>`

Route đã được tự động đăng ký bởi service provider, có gắn middleware `npay.webhook` để xác thực header.

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
            $order->markAsPaid($tx->reference_number);
        }
    }
}
```

## Gọi API danh sách giao dịch

```php
$result = NPay::transactions()->list([
    'account_number' => config('npay.account_number'),
    'limit' => 20,
]);
```

## Testing

```bash
composer install
vendor/bin/phpunit
```

## License

MIT © NPay
