<?php

namespace Botble\NPay\Http\Controllers;

use Botble\Base\Http\Controllers\BaseController;
use Botble\NPay\Services\NPayService;
use Botble\Payment\Enums\PaymentStatusEnum;
use Botble\Payment\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NPayWebhookController extends BaseController
{
    public function __construct(protected NPayService $service)
    {
    }

    public function handle(Request $request): JsonResponse
    {
        if (! $this->service->verifyWebhook($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or missing Apikey token / X-Npay-Signature.',
            ], 401);
        }

        $payload = $request->all();

        if (strtolower((string) ($payload['transferType'] ?? 'in')) !== 'in') {
            return response()->json([
                'success' => true,
                'message' => 'Ignored: not an incoming transfer.',
            ]);
        }

        $orderPrefix = config('plugins.npay.order_prefix', 'NPAY');
        $code = null;
        foreach (['code', 'content', 'memo', 'transferContent', 'description'] as $field) {
            $text = (string) ($payload[$field] ?? '');
            if ($text !== '' && preg_match('/' . preg_quote($orderPrefix, '/') . '[-_ ]?([A-Za-z0-9]+)/i', $text, $matches)) {
                $code = $orderPrefix . '-' . $matches[1];

                break;
            }
        }

        if (! $code) {
            return response()->json([
                'success' => true,
                'message' => 'Ignored: no order code in transfer content.',
            ]);
        }

        $payment = $this->service->findPaymentByCode($code);

        if (! $payment) {
            Log::warning('[NPay] Webhook received but no matching payment found.', [
                'code' => $code,
                'payload' => $payload,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Ignored: no matching payment for code ' . $code . '.',
            ]);
        }

        if ((string) $payment->status === (string) PaymentStatusEnum::COMPLETED) {
            return response()->json([
                'success' => true,
                'message' => 'Payment already completed.',
                'payment_id' => $payment->getKey(),
            ]);
        }

        $received = (float) ($payload['transferAmount'] ?? $payload['amount'] ?? 0);
        if ($received + 0.01 < (float) $payment->amount) {
            Log::warning('[NPay] Underpaid transfer, payment left pending.', [
                'payment_id' => $payment->getKey(),
                'expected' => $payment->amount,
                'received' => $received,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Ignored: transferred amount is less than the payment amount.',
                'payment_id' => $payment->getKey(),
            ]);
        }

        $this->service->markPaymentCompleted($payment, [
            'transaction_id' => $payload['transaction_id'] ?? $payload['id'] ?? null,
        ]);

        Log::info('[NPay] Payment completed via webhook.', [
            'payment_id' => $payment->getKey(),
            'code' => $code,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payment marked as completed.',
            'payment_id' => $payment->getKey(),
        ]);
    }
}
