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
                'message' => 'Invalid or missing Apikey token.',
            ], 401);
        }

        $payload = $request->all();

        $code = $payload['code']
            ?? $payload['memo']
            ?? $payload['content']
            ?? $payload['transferContent']
            ?? null;

        if (! $code) {
            return response()->json([
                'success' => false,
                'message' => 'Missing transfer code/memo.',
            ], 422);
        }

        // Extract NPAY-{order_id} from memo if present
        $orderPrefix = config('plugins.npay.order_prefix', 'NPAY');
        if (preg_match('/' . preg_quote($orderPrefix, '/') . '[-_]?([A-Za-z0-9]+)/', $code, $matches)) {
            $code = $orderPrefix . '-' . $matches[1];
        }

        $payment = $this->service->findPaymentByCode($code);

        if (! $payment) {
            Log::warning('[NPay] Webhook received but no matching payment found.', [
                'code' => $code,
                'payload' => $payload,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No matching payment for code: ' . $code,
            ], 404);
        }

        if ((string) $payment->status === (string) PaymentStatusEnum::COMPLETED) {
            return response()->json([
                'success' => true,
                'message' => 'Payment already completed.',
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
