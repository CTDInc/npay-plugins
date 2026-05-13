<?php

namespace NPay\Laravel\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use NPay\Laravel\Models\NPayTransaction;

class TransactionReceived
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Bản ghi giao dịch (đã lưu hoặc instance tạm).
     */
    public NPayTransaction $transaction;

    /**
     * Payload gốc từ NPay.
     *
     * @var array<string, mixed>
     */
    public array $payload;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(NPayTransaction $transaction, array $payload = [])
    {
        $this->transaction = $transaction;
        $this->payload = $payload;
    }
}
