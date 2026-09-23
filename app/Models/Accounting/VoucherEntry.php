<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a voucher: this ledger, this much, on one side.
 *
 * A line is either a debit or a credit, never both — the other column is zero.
 * Storing both would let one line silently balance itself and a voucher made of
 * such lines would pass the balance check while saying nothing at all.
 */
class VoucherEntry extends Model
{
    protected $table = 'voucher_entries';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
        ];
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class, 'voucher_id');
    }

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class, 'ledger_id');
    }

    /** Positive is Dr, negative is Cr — the module's one sign convention. */
    public function signedAmount(): float
    {
        return round((float) $this->debit - (float) $this->credit, 2);
    }
}
