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
            'id' => 'tx_abc123',
            'gateway' => 'MBBank',
            'transactionDate' => '2025-01-01 10:00:00',
            'accountNumber' => '0123456789',
            'subAccount' => null,
            'transferAmount' => 100000,
            'accumulated' => 1000000,
            'code' => null,
            'content' => 'NPAY42 thanh toan',
            'transferType' => 'in',
            'referenceCode' => 'FT123ABC',
        ], [
            'Authorization' => 'Apikey test-webhook-token',
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        Event::assertDispatched(TransactionReceived::class);

        $this->assertDatabaseHas('npay_transactions', [
            'npay_id' => 'tx_abc123',
            'gateway' => 'MBBank',
            'reference_number' => 'FT123ABC',
        ]);

        $tx = NPayTransaction::first();
        $this->assertNotNull($tx);
        $this->assertSame(100000.0, (float) $tx->amount_in);
    }

    public function test_duplicate_delivery_is_stored_once_without_second_event(): void
    {
        Event::fake([TransactionReceived::class]);
        $payload = [
            'id' => 'tx_dup1',
            'transferType' => 'in',
            'transferAmount' => 50000,
            'content' => 'NPAY7',
            'referenceCode' => null,
        ];
        $headers = ['Authorization' => 'Apikey test-webhook-token'];

        $this->postJson('/npay/webhook', $payload, $headers)->assertOk();
        $this->postJson('/npay/webhook', $payload, $headers)
            ->assertOk()
            ->assertJson(['success' => true, 'duplicated' => true]);

        $this->assertSame(1, NPayTransaction::where('npay_id', 'tx_dup1')->count());
        Event::assertDispatchedTimes(TransactionReceived::class, 1);
    }

    public function test_outgoing_transfer_is_counted_as_amount_out(): void
    {
        $this->postJson('/npay/webhook', [
            'id' => 'tx_out1',
            'transferType' => 'out',
            'transferAmount' => 20000,
        ], ['Authorization' => 'Apikey test-webhook-token'])->assertOk();

        $tx = NPayTransaction::where('npay_id', 'tx_out1')->firstOrFail();
        $this->assertSame(0.0, (float) $tx->amount_in);
        $this->assertSame(20000.0, (float) $tx->amount_out);
    }

    public function test_valid_signature_is_accepted_without_apikey(): void
    {
        $body = json_encode(['id' => 'tx_sig1', 'transferType' => 'in', 'transferAmount' => 1000]);

        $this->call('POST', '/npay/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_NPAY_SIGNATURE' => hash_hmac('sha256', $body, 'test-webhook-secret'),
            'HTTP_X_NPAY_TIMESTAMP' => (string) time(),
        ], $body)->assertOk();

        $this->assertDatabaseHas('npay_transactions', ['npay_id' => 'tx_sig1']);
    }

    public function test_bad_or_stale_signature_is_rejected(): void
    {
        $body = json_encode(['id' => 'tx_sig2', 'transferType' => 'in']);

        $this->call('POST', '/npay/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_NPAY_SIGNATURE' => hash_hmac('sha256', $body, 'wrong'),
        ], $body)->assertStatus(401);

        $this->call('POST', '/npay/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_NPAY_SIGNATURE' => hash_hmac('sha256', $body, 'test-webhook-secret'),
            'HTTP_X_NPAY_TIMESTAMP' => (string) (time() - 3600),
        ], $body)->assertStatus(401);
    }
}
