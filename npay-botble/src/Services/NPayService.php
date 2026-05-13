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
        return rtrim(config('plugins.npay.qr_base'), '/');
    }

    public function generateOrderCode($orderId = null): string
    {
        $prefix = config('plugins.npay.order_prefix', 'NPAY');
        $suffix = $orderId ?: strtoupper(Str::random(8));

        return $prefix . '-' . $suffix;
    }

    public function buildQrUrl(array $params): string
    {
        $query = http_build_query(array_filter([
            'acc' => $params['account_number'] ?? setting('npay_account_number'),
            'bank' => $params['bank_bin'] ?? setting('npay_bank_bin'),
            'name' => $params['account_holder'] ?? setting('npay_account_holder'),
            'amount' => $params['amount'] ?? 0,
            'memo' => $params['memo'] ?? '',
            'template' => $params['template'] ?? setting('npay_qr_template', config('plugins.npay.default_template')),
        ]));

        return $this->getQrBase() . '?' . $query;
    }

    public function verifyWebhook($request): bool
    {
        $authHeader = $request->header('Authorization', '');
        $expectedToken = setting('npay_api_token', '');

        if (! $expectedToken || ! $authHeader) {
            return false;
        }

        if (! Str::startsWith($authHeader, 'Apikey ')) {
            return false;
        }

        $token = trim(substr($authHeader, 7));

        return hash_equals($expectedToken, $token);
    }

    public function findPaymentByCode(string $code): ?Payment
    {
        return Payment::query()
            ->where('charge_id', 'LIKE', '%' . $code . '%')
            ->orWhere('description', 'LIKE', '%' . $code . '%')
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
