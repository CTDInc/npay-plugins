<?php

namespace Botble\NPay\Http\Controllers;

use Botble\Base\Http\Controllers\BaseController;
use Botble\NPay\Http\Requests\NPayPaymentRequest;
use Botble\NPay\Services\NPayService;
use Botble\Payment\Enums\PaymentStatusEnum;
use Botble\Payment\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NPayController extends BaseController
{
    public function __construct(protected NPayService $service)
    {
    }

    public function postPayment(NPayPaymentRequest $request)
    {
        $orderId = $request->input('order_id');
        $amount = (float) $request->input('amount', 0);
        $code = $request->input('code') ?: $this->service->generateOrderCode($orderId);

        $payment = Payment::query()->create([
            'amount' => $amount,
            'currency' => $request->input('currency', 'VND'),
            'payment_channel' => 'npay',
            'status' => PaymentStatusEnum::PENDING,
            'order_id' => $orderId,
            'charge_id' => $code,
            'description' => __('NPay payment for order :id', ['id' => $orderId]),
            'user_id' => auth()->id() ?: 0,
        ]);

        $qrUrl = $this->service->buildQrUrl([
            'amount' => $amount,
            'memo' => $code,
        ]);

        return redirect()->route('npay.display', [
            'payment' => $payment->getKey(),
        ])->with([
            'qr_url' => $qrUrl,
            'code' => $code,
        ]);
    }

    public function display(Request $request, $payment)
    {
        $payment = Payment::query()->findOrFail($payment);

        $qrUrl = $this->service->buildQrUrl([
            'amount' => $payment->amount,
            'memo' => $payment->charge_id,
        ]);

        return view('plugins/npay::payment-form', [
            'payment' => $payment,
            'qr_url' => $qrUrl,
            'code' => $payment->charge_id,
            'poll_interval' => config('plugins.npay.poll_interval', 5000),
            'status_url' => route('npay.status', ['payment' => $payment->getKey()]),
        ]);
    }

    public function status(Request $request, $payment): JsonResponse
    {
        $payment = Payment::query()->findOrFail($payment);

        return response()->json([
            'status' => $payment->status,
            'completed' => (string) $payment->status === (string) PaymentStatusEnum::COMPLETED,
            'amount' => $payment->amount,
            'code' => $payment->charge_id,
        ]);
    }
}
