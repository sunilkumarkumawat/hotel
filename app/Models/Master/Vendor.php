<?php

namespace App\Models\Master;

use App\Models\Accounting\Ledger;
use App\Support\VendorLedger;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Somebody the hotel buys from or sends work to — the laundry, the linen
 * supplier, the plumber.
 *
 * Kept general rather than "laundry" on purpose: the same list is what
 * Accounting → Vendor Payment will pay against, so a laundry added from the
 * House Keeping issue screen is the same row the accountant later settles.
 * `ledger_id` is what makes that literally true rather than just intended —
 * see App\Support\VendorLedger, called below on every create and rename, to
 * keep it pointed at a matching Ledger under Sundry Creditors.
 */
class Vendor extends BaseMaster
{
    protected $table = 'vendors';

    protected static function booted(): void
    {
        static::created(fn (Vendor $vendor) => VendorLedger::sync($vendor));

        static::updated(function (Vendor $vendor) {
            if ($vendor->wasChanged('name')) {
                VendorLedger::sync($vendor);
            }
        });
    }

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class, 'ledger_id');
    }

    public function getLabelAttribute(): string
    {
        return trim($this->name . ($this->mobile ? ' · ' . $this->mobile : ''));
    }
}
