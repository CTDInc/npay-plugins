<?php

namespace NPay\Laravel\Http\Controllers;

use Illuminate\Database\QueryException;
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

        $npayId = isset($payload['id']) && $payload['id'] !== '' ? (string) $payload['id'] : null;
        $transferType = strtolower((string) ($payload['transferType'] ?? 'in'));
        $amount = (float) ($payload['transferAmount'] ?? 0);

        $data = [
            'npay_id' => $npayId,
            'transfer_type' => $transferType,
            'gateway' => $payload['gateway'] ?? null,
            'transaction_date' => $payload['transactionDate'] ?? $payload['transaction_date'] ?? null,
            'account_number' => $payload['accountNumber'] ?? $payload['account_number'] ?? null,
            'sub_account' => $payload['subAccount'] ?? $payload['sub_account'] ?? null,
            'amount_in' => $transferType === 'in' ? $amount : (float) ($payload['amount_in'] ?? 0),
            'amount_out' => $transferType === 'out' ? $amount : (float) ($payload['amount_out'] ?? 0),
            'accumulated' => (float) ($payload['accumulated'] ?? 0),
            'code' => $payload['code'] ?? null,
            'transaction_content' => $payload['content'] ?? $payload['transaction_content'] ?? null,
            'reference_number' => $payload['referenceCode'] ?? $payload['reference_number'] ?? null,
            'body' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ];

        if (!config('npay.store_transactions', true)) {
            event(new TransactionReceived(new NPayTransaction($data), $payload));

            return response()->json([
                'success' => true,
                'message' => 'Webhook đã được tiếp nhận.',
            ]);
        }

        if ($npayId !== null && NPayTransaction::where('npay_id', $npayId)->exists()) {
            return $this->duplicate($npayId);
        }

        try {
            $transaction = NPayTransaction::create($data);
        } catch (QueryException $e) {
            // Hai lần gửi lại chạy song song: unique(npay_id) chặn bản thứ hai.
            if ($npayId !== null && NPayTransaction::where('npay_id', $npayId)->exists()) {
                return $this->duplicate($npayId);
            }
            throw $e;
        }

        event(new TransactionReceived($transaction, $payload));

        return response()->json([
            'success' => true,
            'message' => 'Webhook đã được tiếp nhận.',
        ]);
    }

    protected function duplicate(string $npayId): JsonResponse
    {
        return response()->json([
            'success' => true,
            'duplicated' => true,
            'message' => 'Giao dịch ' . $npayId . ' đã được ghi nhận trước đó.',
        ]);
    }
}
