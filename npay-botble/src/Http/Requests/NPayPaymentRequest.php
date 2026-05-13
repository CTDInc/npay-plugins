<?php

namespace Botble\NPay\Http\Requests;

use Botble\Support\Http\Requests\Request;

class NPayPaymentRequest extends Request
{
    public function rules(): array
    {
        return [
            'order_id' => ['required', 'string', 'max:64'],
            'amount' => ['required', 'numeric', 'min:0'],
            'code' => ['nullable', 'string', 'max:128'],
            'currency' => ['nullable', 'string', 'size:3'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
