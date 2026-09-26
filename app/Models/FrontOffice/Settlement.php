<?php

namespace App\Models\FrontOffice;

use App\Models\Master\PayMode;
use App\Models\Reservation\AdvanceDeposit;
use App\Models\User;
use App\Support\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money taken against a bill at checkout.
 *
 * A bill can have several of these — that is what "Multiple Pay Mode" means:
 * ₹2,000 on a card and ₹100 in cash is two rows, not one.
 *
 * Card details follow the same rule as an advance deposit: the last four
 * digits and nothing more.
 */
class Settlement extends Model
{
    use RecordsActivity;

    protected $guarded = ['id'];

    /** The same instrument list the advance deposit screen uses. */
    public const PAY_TYPES = AdvanceDeposit::PAY_TYPES;

    public const CARD_TYPES = AdvanceDeposit::CARD_TYPES;

    public const CARD_TYPES_NEED_CARD = AdvanceDeposit::CARD_TYPES_NEED_CARD;

    protected function casts(): array
    {
        return [
            'settle_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class, 'bill_id');
    }

    public function checkIn(): BelongsTo
    {
        return $this->belongsTo(CheckIn::class, 'check_in_id');
    }

    public function payMode(): BelongsTo
    {
        return $this->belongsTo(PayMode::class, 'pay_mode_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    /** Masked for display — only ever four digits are stored. */
    public function getCardNumberAttribute(): ?string
    {
        return $this->card_last4 ? '•••• ' . $this->card_last4 : null;
    }

    public function getPayTypeLabelAttribute(): string
    {
        return self::PAY_TYPES[$this->pay_type] ?? '—';
    }
}
