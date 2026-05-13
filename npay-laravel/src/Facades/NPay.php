<?php

namespace NPay\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string generateQrUrl(array $params)
 * @method static string generatePaymentCode(int $orderId, ?string $prefix = null)
 * @method static \NPay\Laravel\Api\TransactionApi transactions()
 * @method static bool verifyWebhook(\Illuminate\Http\Request $request)
 *
 * @see \NPay\Laravel\NPay
 */
class NPay extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'npay';
    }
}
