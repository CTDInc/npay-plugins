@extends('plugins/payment::settings.payment-method-template')

@section('payment_method')
    @php
        $accountNumber = \Botble\NPay\Services\NPayService::setting('account_number');
        $bankBin = \Botble\NPay\Services\NPayService::setting('bank_bin');
        $accountHolder = \Botble\NPay\Services\NPayService::setting('account_holder');
        $apiToken = \Botble\NPay\Services\NPayService::setting('api_token');
        $webhookSecret = \Botble\NPay\Services\NPayService::setting('webhook_secret');
        $qrTemplate = \Botble\NPay\Services\NPayService::setting('qr_template', config('plugins.npay.default_template', 'compact'));
        $webhookUrl = url(config('plugins.npay.webhook_path', 'api/webhooks/npay'));
    @endphp

    <div class="payment-name-label-wrapper">
        <label class="ws-payment-name">
            <img src="{{ asset('vendor/core/plugins/npay/images/npay-logo.png') }}"
                 alt="NPay" onerror="this.style.display='none'">
            {{ __('plugins/npay::plugin.name') }}
        </label>
    </div>

    <div class="row mt-3">
        <div class="col-md-6 form-group">
            <label class="text-title-field" for="npay_api_token">
                {{ __('plugins/npay::plugin.settings.api_token') }}
            </label>
            <input type="text"
                   class="form-control"
                   id="npay_api_token"
                   name="payment_npay_api_token"
                   value="{{ $apiToken }}"
                   placeholder="npay_xxx">
            <small class="text-muted">
                {{ __('plugins/npay::plugin.settings.api_token_helper') }}
            </small>
        </div>

        <div class="col-md-6 form-group">
            <label class="text-title-field" for="npay_webhook_secret">
                {{ __('plugins/npay::plugin.settings.webhook_secret') }}
            </label>
            <input type="text"
                   class="form-control"
                   id="npay_webhook_secret"
                   name="payment_npay_webhook_secret"
                   value="{{ $webhookSecret }}">
            <small class="text-muted">
                {{ __('plugins/npay::plugin.settings.webhook_secret_helper') }}
            </small>
        </div>

        <div class="col-md-6 form-group">
            <label class="text-title-field" for="npay_account_number">
                {{ __('plugins/npay::plugin.settings.account_number') }}
            </label>
            <input type="text"
                   class="form-control"
                   id="npay_account_number"
                   name="payment_npay_account_number"
                   value="{{ $accountNumber }}">
        </div>

        <div class="col-md-6 form-group">
            <label class="text-title-field" for="npay_bank_bin">
                {{ __('plugins/npay::plugin.settings.bank_bin') }}
            </label>
            <input type="text"
                   class="form-control"
                   id="npay_bank_bin"
                   name="payment_npay_bank_bin"
                   value="{{ $bankBin }}"
                   placeholder="970422">
        </div>

        <div class="col-md-6 form-group">
            <label class="text-title-field" for="npay_account_holder">
                {{ __('plugins/npay::plugin.settings.account_holder') }}
            </label>
            <input type="text"
                   class="form-control"
                   id="npay_account_holder"
                   name="payment_npay_account_holder"
                   value="{{ $accountHolder }}">
        </div>

        <div class="col-md-6 form-group">
            <label class="text-title-field" for="npay_qr_template">
                {{ __('plugins/npay::plugin.settings.qr_template') }}
            </label>
            <select class="form-control" id="npay_qr_template" name="payment_npay_qr_template">
                @foreach (__('plugins/npay::plugin.settings.qr_template_options') as $key => $label)
                    <option value="{{ $key }}" @selected($qrTemplate === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-md-12 form-group">
            <label class="text-title-field">{{ __('Webhook URL') }}</label>
            <input type="text" class="form-control" value="{{ $webhookUrl }}" readonly>
            <small class="text-muted">
                {{ __('Configure this URL in your NPay dashboard. Auth header: Authorization: Apikey <token>') }}
            </small>
        </div>
    </div>
@stop
