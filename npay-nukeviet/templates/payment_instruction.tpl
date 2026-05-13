<!-- BEGIN: main -->
<link rel="stylesheet" href="{DATA.css_url}" />

<div class="npay-checkout" id="npay-checkout"
     data-poll-url="{DATA.poll_url}"
     data-pay-id="{DATA.pay_id}"
     data-order-id="{DATA.order_id}">

    <div class="npay-checkout__header">
        <h2 class="npay-checkout__title">{LANG.npay_instruction_heading}</h2>
        <p class="npay-checkout__intro">{LANG.npay_instruction_intro}</p>
    </div>

    <div class="npay-checkout__body">
        <div class="npay-checkout__qr">
            <img src="{DATA.qr_url}" alt="{LANG.npay_scan_qr}" class="npay-checkout__qr-img" />
            <p class="npay-checkout__qr-caption">{LANG.npay_scan_qr}</p>
        </div>

        <div class="npay-checkout__info">
            <dl class="npay-info-list">
                <dt>{LANG.npay_bank}</dt>
                <dd>{DATA.bank_code}</dd>

                <dt>{LANG.npay_account_number}</dt>
                <dd>
                    <span class="npay-copyable" data-copy="{DATA.account_number}">{DATA.account_number}</span>
                    <button type="button" class="npay-btn-copy" data-copy="{DATA.account_number}">{LANG.npay_copy}</button>
                </dd>

                <dt>{LANG.npay_account_name}</dt>
                <dd>{DATA.account_name}</dd>

                <dt>{LANG.npay_amount}</dt>
                <dd>
                    <strong class="npay-amount">{DATA.amount} {LANG.npay_currency}</strong>
                    <button type="button" class="npay-btn-copy" data-copy="{DATA.amount_raw}">{LANG.npay_copy}</button>
                </dd>

                <dt>{LANG.npay_description_label}</dt>
                <dd>
                    <code class="npay-description">{DATA.description}</code>
                    <button type="button" class="npay-btn-copy" data-copy="{DATA.description}">{LANG.npay_copy}</button>
                </dd>

                <dt>{LANG.npay_pay_id}</dt>
                <dd>{DATA.pay_id}</dd>
            </dl>

            <p class="npay-warning">{LANG.npay_description_warning}</p>

            <div class="npay-status" id="npay-status">
                <span class="npay-status__spinner" aria-hidden="true"></span>
                <span class="npay-status__label">{LANG.npay_waiting}</span>
            </div>
        </div>
    </div>
</div>

<script src="{DATA.js_url}" defer></script>
<!-- END: main -->
