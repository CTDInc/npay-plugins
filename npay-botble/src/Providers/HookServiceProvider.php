<?php

namespace Botble\NPay\Providers;

use Botble\Base\Supports\ServiceProvider;
use Botble\Payment\Enums\PaymentMethodEnum;
use Botble\Payment\Services\Gateways\PaymentMethodInterface;
use Botble\NPay\Services\NPayService;
use Illuminate\Http\Request;

class HookServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        add_filter(PAYMENT_FILTER_ADDITIONAL_PAYMENT_METHODS, [$this, 'registerNPayMethod'], 14, 2);
        add_filter(PAYMENT_FILTER_AFTER_POST_CHECKOUT, [$this, 'checkoutWithNPay'], 14, 2);
        add_filter(PAYMENT_METHODS_SETTINGS_PAGE, [$this, 'addPaymentSettings'], 99);
        add_filter(BASE_FILTER_ENUM_ARRAY, [$this, 'addPaymentMethodToEnum'], 99, 2);
        add_filter(PAYMENT_FILTER_PAYMENT_INFO_DETAIL, [$this, 'addPaymentDetails'], 20, 2);
        add_filter(PAYMENT_FILTER_GET_SERVICE_CLASS, [$this, 'paymentService'], 20, 2);
    }

    public function registerNPayMethod(?string $html, array $data): string
    {
        PaymentMethods::method('npay', [
            'html' => view('plugins/npay::settings', $data)->render(),
        ]);

        return $html . '';
    }

    public function checkoutWithNPay(array $data, Request $request)
    {
        if ($data['type'] !== 'npay') {
            return $data;
        }

        $service = app(NPayService::class);
        $orderCode = $service->generateOrderCode($data['order_id'] ?? null);

        $data['checkoutUrl'] = route('npay.payment', [
            'order_id' => $data['order_id'] ?? null,
            'amount' => $data['amount'] ?? 0,
            'code' => $orderCode,
        ]);

        return $data;
    }

    public function addPaymentSettings(?string $settings): string
    {
        return $settings . view('plugins/npay::settings')->render();
    }

    public function addPaymentMethodToEnum(array $values, $class): array
    {
        if ($class === PaymentMethodEnum::class) {
            $values[] = 'npay';
        }

        return $values;
    }

    public function addPaymentDetails(?string $html, $payment): string
    {
        if ($payment && $payment->payment_channel === 'npay') {
            return view('plugins/npay::payment-form', compact('payment'))->render();
        }

        return (string) $html;
    }

    public function paymentService(?string $class, string $type): ?string
    {
        if ($type === 'npay') {
            return NPayService::class;
        }

        return $class;
    }
}
