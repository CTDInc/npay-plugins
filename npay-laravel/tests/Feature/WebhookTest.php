<?php

namespace NPay\Laravel\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use NPay\Laravel\Events\TransactionReceived;
use NPay\Laravel\Models\NPayTransaction;
use NPay\Laravel\Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_rejects_without_token(): void
    {
        $response = $this->postJson('/npay/webhook', [
            'gateway' => 'MBBank',
            'content' => 'NPAY1',
        ]);

        $response->assertStatus(401);
    }

    public function test_webhook_accepts_valid_token_and_dispatches_event(): void
    {
        Event::fake([TransactionReceived::class]);

        $response = $this->postJson('/npay/webhook', [
            'gateway' => 'MBBank',
            'transactionDate' => '2025-01-01 10:00:00',
            'accountNumber' => '0123456789',
            'subAccount' => null,
            'transferAmount' => 100000,
            'accumulated' => 1000000,
            'code' => null,
            'content' => 'NPAY42 thanh toan',
            'referenceCode' => 'FT123ABC',
        ], [
            'Authorization' => 'Apikey test-webhook-token',
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        Event::assertDispatched(TransactionReceived::class);

        $this->assertDatabaseHas('npay_transactions', [
            'gateway' => 'MBBank',
            'reference_number' => 'FT123ABC',
        ]);

        $tx = NPayTransaction::first();
        $this->assertNotNull($tx);
        $this->assertSame(100000.0, (float) $tx->amount_in);
    }
}
