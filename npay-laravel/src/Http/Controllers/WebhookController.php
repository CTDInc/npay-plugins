<?php

namespace NPay\Laravel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use NPay\Laravel\Events\TransactionReceived;
use NPay\Laravel\Facades\NPay;
use NPay\Laravel\Models\NPayTransaction;

class WebhookController extends Controller
{
    /**
     * Xử lý webhook từ NPay.
     */
    public function __invoke(Request $request): JsonResponse
    {
        // Middleware npay.webhook đã xác thực; vẫn kiểm tra lại để đảm bảo.
        if (!NPay::verifyWebhook($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $payload = $request->all();

        $data = [
            'gateway' => $payload['gateway'] ?? null,
            'transaction_date' => $payload['transactionDate'] ?? $payload['transaction_date'] ?? null,
            'account_number' => $payload['accountNumber'] ?? $payload['account_number'] ?? null,
            'sub_account' => $payload['subAccount'] ?? $payload['sub_account'] ?? null,
            'amount_in' => (float) ($payload['transferAmount'] ?? $payload['amount_in'] ?? 0),
            'amount_out' => (float) ($payload['amount_out'] ?? 0),
            'accumulated' => (float) ($payload['accumulated'] ?? 0),
            'code' => $payload['code'] ?? null,
            'transaction_content' => $payload['content'] ?? $payload['transaction_content'] ?? null,
            'reference_number' => $payload['referenceCode'] ?? $payload['reference_number'] ?? null,
            'body' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ];

        $transaction = null;
        if (config('npay.store_transactions', true)) {
            $transaction = NPayTransaction::create($data);
        } else {
            $transaction = new NPayTransaction($data);
        }

        // Phát event
        event(new TransactionReceived($transaction, $payload));

        return response()->json([
            'success' => true,
            'message' => 'Webhook đã được tiếp nhận.',
        ]);
    }
}
