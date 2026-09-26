<?php

namespace App\Models\Reservation;

use App\Models\Master\PayMode;
use App\Models\User;
use App\Support\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdvanceDeposit extends Model
{
    use RecordsActivity;

    protected $guarded = ['id'];

    /** Money in or money back out. */
    public const TYPES = [
        'deposit' => 'Receipt (money in)',
        'refund' => 'Refund (money out)',
    ];

    /**
     * How the money actually moved. `pay_mode_id` is the branch's own list
     * (Cash, UPI, Bank Transfer…); this is the settlement instrument, which is
     * what a card or cheque reconciliation needs.
     */
    public const PAY_TYPES = [
        'cash' => 'Cash',
        'credit_card' => 'Credit Card',
        'debit_card' => 'Debit Card',
        'upi' => 'UPI',
        'net_banking' => 'Net Banking',
        'neft_rtgs' => 'NEFT / RTGS',
        'cheque' => 'Cheque',
        'wallet' => 'Wallet',
        'ota' => 'OTA / Agent',
    ];

    /** Pay types that need the card fields. */
    public const CARD_TYPES_NEED_CARD = ['credit_card', 'debit_card'];

    public const CARD_TYPES = [
        'visa' => 'Visa',
        'mastercard' => 'MasterCard',
        'rupay' => 'RuPay',
        'amex' => 'American Express',
        'maestro' => 'Maestro',
        'diners' => 'Diners Club',
        'other' => 'Other',
    ];

    protected function casts(): array
    {
        return [
            'deposit_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function payMode(): BelongsTo
    {
        return $this->belongsTo(PayMode::class, 'pay_mode_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, fn (Builder $q) => $q->whereHas(
            'reservation',
            fn (Builder $r) => $r->where('reservation_no', 'like', "%{$term}%")
                ->orWhere('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('mobile', 'like', "%{$term}%")
        ));
    }

    public function isRefund(): bool
    {
        return $this->type === 'refund';
    }

    /** Signed amount: a refund reduces what the guest has paid. */
    public function getSignedAmountAttribute(): float
    {
        return $this->isRefund() ? -(float) $this->amount : (float) $this->amount;
    }

    public function getPayTypeLabelAttribute(): string
    {
        return self::PAY_TYPES[$this->pay_type] ?? '—';
    }

    public function getCardTypeLabelAttribute(): string
    {
        return self::CARD_TYPES[$this->card_type] ?? '—';
    }

    /** Masked for display — only ever four digits are stored. */
    public function getCardNumberAttribute(): ?string
    {
        return $this->card_last4 ? '•••• ' . $this->card_last4 : null;
    }
}
