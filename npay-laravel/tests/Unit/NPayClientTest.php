<?php

namespace NPay\Laravel\Tests\Unit;

use NPay\Laravel\NPay;
use PHPUnit\Framework\TestCase;

class NPayClientTest extends TestCase
{
    protected function npay(array $overrides = []): NPay
    {
        return new NPay(array_merge([
            'qr_base' => 'https://qr.npay.vn',
            'api_base' => 'https://api.npay.vn',
            'bank_bin' => '970422',
            'account_number' => '0123456789',
            'account_holder' => 'NGUYEN VAN A',
            'default_template' => 'compact',
            'code_prefix' => 'NPAY',
        ], $overrides));
    }

    public function test_generate_qr_url_builds_expected_url(): void
    {
        $npay = $this->npay();

        $url = $npay->generateQrUrl([
            'amount' => 100000,
            'description' => 'NPAY123',
        ]);

        $this->assertStringStartsWith('https://qr.npay.vn/img/970422/0123456789/compact.png', $url);
        $this->assertStringContainsString('amount=100000', $url);
        $this->assertStringContainsString('des=NPAY123', $url);
        $this->assertStringContainsString('accountName=', $url);
    }

    public function test_generate_qr_url_requires_bank_and_account(): void
    {
        $npay = $this->npay(['bank_bin' => null, 'account_number' => null]);

        $this->expectException(\InvalidArgumentException::class);
        $npay->generateQrUrl([]);
    }

    public function test_generate_payment_code(): void
    {
        $npay = $this->npay();

        $this->assertSame('NPAY42', $npay->generatePaymentCode(42));
        $this->assertSame('ORD42', $npay->generatePaymentCode(42, 'ORD'));
    }

    public function test_parse_payment_code(): void
    {
        $npay = $this->npay();

        $this->assertSame(42, $npay->parsePaymentCode('Chuyen khoan NPAY42 cam on'));
        $this->assertNull($npay->parsePaymentCode('no match here'));
    }
}
