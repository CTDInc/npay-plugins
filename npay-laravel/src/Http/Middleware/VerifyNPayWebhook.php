<?php

namespace NPay\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyNPayWebhook
{
    /**
     * Xác thực header Authorization: Apikey <token> trùng với cấu hình NPay.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('npay.webhook_token', '');

        if ($expected === '') {
            return response()->json([
                'success' => false,
                'message' => 'NPay webhook_token chưa được cấu hình.',
            ], 500);
        }

        $auth = (string) $request->header('Authorization', '');
        $token = null;

        if (preg_match('/^(Apikey|Bearer)\s+(.+)$/i', $auth, $m)) {
            $token = trim($m[2]);
        } elseif ($auth !== '') {
            $token = $auth;
        }

        if ($token === null || !hash_equals($expected, $token)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        return $next($request);
    }
}
