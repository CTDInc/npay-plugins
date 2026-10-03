<?php

namespace Botble\NPay\Services;

use Botble\Payment\Enums\PaymentStatusEnum;
use Botble\Payment\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class NPayService
{
    public function getApiBase(): string
    {
        return rtrim(config('plugins.npay.api_base'), '/');
    }

    public function getQrBase(): string
    {
        $base = rtrim((string) config('plugins.npay.qr_base', 'https://qr.npay.vn'), '/');

        return (string) preg_replace('#/(img|qrpay|qrcard)$#', '', $base);
    }

    public function generateOrderCode($orderId = null): string
    {
        $prefix = config('plugins.npay.order_prefix', 'NPAY');
        $suffix = $orderId ?: strtoupper(Str::random(8));

        return $prefix . '-' . $suffix;
    }

    private const BANK_ALIASES = [
        'vcb' => 'vietcombank', 'tcb' => 'techcombank', 'ctg' => 'vietinbank', 'icb' => 'vietinbank',
        'mb' => 'mbbank', 'vpb' => 'vpbank', 'tpb' => 'tpbank', 'stb' => 'sacombank',
        'hdb' => 'hdbank', 'eib' => 'eximbank', 'vba' => 'agribank', 'agr' => 'agribank',
        'lpb' => 'lienvietpostbank', 'lpbank' => 'lienvietpostbank', 'nab' => 'namabank',
        'abb' => 'abbank', 'bab' => 'bacabank', 'pvcb' => 'pvcombank', 'seab' => 'seabank',
        'klb' => 'kienlongbank', 'vab' => 'vietabank', 'sgicb' => 'saigonbank', 'bvb' => 'banviet',
    ];

    /**
     * Botble stores payment-method fields under `payment_npay_<key>`; older
     * installs of this plugin read `npay_<key>`, so fall back to that.
     */
    public static function setting(string $key, $default = null)
    {
        $value = setting('payment_npay_' . $key);
        if ($value === null || $value === '') {
            $value = setting('npay_' . $key, $default);
        }

        return $value ?? $default;
    }

    public function buildQrUrl(array $params): string
    {
        $template = $params['template'] ?? self::setting('qr_template', config('plugins.npay.default_template'));
        $bareQr = in_array($template, ['qr_only', 'qronly'], true);

        $query = array_filter(self::bankParam((string) ($params['bank_bin'] ?? self::setting('bank_bin'))) + [
            'tai_khoan' => $params['account_number'] ?? self::setting('account_number'),
            'so_tien' => (string) (int) round((float) ($params['amount'] ?? 0)),
            'noi_dung' => $params['memo'] ?? '',
            'chu_tai_khoan' => $bareQr ? null : ($params['account_holder'] ?? self::setting('account_holder')),
        ], fn ($v) => $v !== null && $v !== '');

        return $this->getQrBase() . ($bareQr ? '/qrpay?' : '/qrcard?') . http_build_query($query);
    }

    /**
     * gen-qr takes either a Napas BIN (`ma_bin`) or a vietnam-qr-pay key (`ngan_hang`).
     */
    public static function bankParam(string $bank): array
    {
        $bank = strtolower((string) preg_replace('/[\s_-]+/', '', trim($bank)));
        if (preg_match('/^\d{6}$/', $bank)) {
            return ['ma_bin' => $bank];
        }

        return ['ngan_hang' => self::BANK_ALIASES[$bank] ?? $bank];
    }

    /**
     * Accepts `Authorization: Apikey <api_token>` or, when a webhook secret is
     * configured, `X-Npay-Signature` = hex HMAC-SHA256 of the raw body
     * (X-Npay-Timestamp, if sent, must be within 5 minutes).
     */
    public function verifyWebhook($request): bool
    {
        $expectedToken = (string) self::setting('api_token', '');
        $authHeader = trim((string) $request->header('Authorization', ''));
        if ($expectedToken !== '' && preg_match('/^Apikey\s+(.+)$/i', $authHeader, $m)
            && hash_equals($expectedToken, trim($m[1]))) {
            return true;
        }

        $secret = (string) self::setting('webhook_secret', '');
        $signature = strtolower(trim((string) $request->header('X-Npay-Signature', '')));
        if ($secret === '' || $signature === '') {
            return false;
        }
        if (! hash_equals(hash_hmac('sha256', $request->getContent(), $secret), $signature)) {
            return false;
        }
        $timestamp = trim((string) $request->header('X-Npay-Timestamp', ''));

        return $timestamp === '' || (ctype_digit($timestamp) && abs(time() - (int) $timestamp) <= 300);
    }

    public function findPaymentByCode(string $code): ?Payment
    {
        return Payment::query()
            ->where('charge_id', $code)
            ->latest()
            ->first();
    }

    public function markPaymentCompleted(Payment $payment, array $data = []): Payment
    {
        $payment->status = PaymentStatusEnum::COMPLETED;
        $payment->payment_channel = 'npay';

        if (! empty($data['transaction_id'])) {
            $payment->charge_id = $data['transaction_id'];
        }

        $payment->save();

        return $payment;
    }

    public function getName(): string
    {
        return 'NPay';
    }
}
