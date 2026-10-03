<?php

namespace NPay\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use NPay\Laravel\NPay;
use Symfony\Component\HttpFoundation\Response;

class VerifyNPayWebhook
{
    /**
     * Webhook hợp lệ khi đúng `Authorization: Apikey <webhook_token>` hoặc đúng
     * chữ ký `X-Npay-Signature` (webhook_secret) — xem NPay::verifyWebhook().
     */
    public function handle(Request $request, Closure $next): Response
    {
        $npay = app(NPay::class);

        if (!$npay->hasWebhookCredentials()) {
            return response()->json([
                'success' => false,
                'message' => 'NPay webhook_token / webhook_secret chưa được cấu hình.',
            ], 500);
        }

        if (!$npay->verifyWebhook($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        return $next($request);
    }
}
