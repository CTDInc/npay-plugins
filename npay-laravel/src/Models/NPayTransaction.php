<?php

namespace NPay\Laravel\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $gateway
 * @property string|null $transaction_date
 * @property string|null $account_number
 * @property string|null $sub_account
 * @property float $amount_in
 * @property float $amount_out
 * @property float $accumulated
 * @property string|null $code
 * @property string|null $transaction_content
 * @property string|null $reference_number
 * @property string|null $body
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class NPayTransaction extends Model
{
    /**
     * Tên bảng.
     */
    protected $table = 'npay_transactions';

    /**
     * Disable updated_at.
     */
    public const UPDATED_AT = null;

    /**
     * Mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'gateway',
        'transaction_date',
        'account_number',
        'sub_account',
        'amount_in',
        'amount_out',
        'accumulated',
        'code',
        'transaction_content',
        'reference_number',
        'body',
    ];

    /**
     * Casts.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount_in' => 'float',
        'amount_out' => 'float',
        'accumulated' => 'float',
        'transaction_date' => 'datetime',
    ];
}
