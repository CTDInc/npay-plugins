@extends('layouts.app', ['title' => __('plugins/npay::plugin.payment.title')])

@push('header')
    <link rel="stylesheet" href="{{ asset('vendor/core/plugins/npay/css/npay.css') }}">
@endpush

@php
    $accountNumber = setting('npay_account_number');
    $bankBin = setting('npay_bank_bin');
    $accountHolder = setting('npay_account_holder');
    $qr = $qr_url ?? session('qr_url');
    $code = $code ?? session('code') ?? ($payment->charge_id ?? '');
    $statusUrl = $status_url ?? route('npay.status', ['payment' => $payment->getKey() ?? 0]);
    $pollInterval = $poll_interval ?? config('plugins.npay.poll_interval', 5000);
@endphp

<div class="npay-payment-wrapper">
    <div class="npay-card">
        <h2 class="npay-title">{{ __('plugins/npay::plugin.payment.title') }}</h2>

        <div class="npay-grid">
            <div class="npay-qr">
                <p class="npay-step">{{ __('plugins/npay::plugin.payment.scan_qr') }}</p>
                @if ($qr)
                    <img src="{{ $qr }}" alt="NPay QR" class="npay-qr-image">
                @endif
            </div>

            <div class="npay-info">
                <p class="npay-step">{{ __('plugins/npay::plugin.payment.or_transfer') }}</p>
                <dl class="npay-info-list">
                    <dt>{{ __('plugins/npay::plugin.payment.bank') }}</dt>
                    <dd>{{ $bankBin }}</dd>

                    <dt>{{ __('plugins/npay::plugin.payment.account_number') }}</dt>
                    <dd><span data-copy="{{ $accountNumber }}">{{ $accountNumber }}</span></dd>

                    <dt>{{ __('plugins/npay::plugin.payment.account_holder') }}</dt>
                    <dd>{{ $accountHolder }}</dd>

                    <dt>{{ __('plugins/npay::plugin.payment.amount') }}</dt>
                    <dd><strong>{{ number_format($payment->amount ?? 0) }} {{ $payment->currency ?? 'VND' }}</strong></dd>

                    <dt>{{ __('plugins/npay::plugin.payment.memo') }}</dt>
                    <dd><span data-copy="{{ $code }}" class="npay-memo">{{ $code }}</span></dd>
                </dl>

                <p class="npay-warning">{{ __('plugins/npay::plugin.payment.memo_warning') }}</p>
            </div>
        </div>

        <div class="npay-status" id="npay-status"
             data-status-url="{{ $statusUrl }}"
             data-interval="{{ $pollInterval }}">
            <span class="npay-spinner"></span>
            <span class="npay-status-text">{{ __('plugins/npay::plugin.payment.waiting') }}</span>
        </div>
    </div>
</div>

@push('footer')
    <script src="{{ asset('vendor/core/plugins/npay/js/npay.js') }}"></script>
@endpush
