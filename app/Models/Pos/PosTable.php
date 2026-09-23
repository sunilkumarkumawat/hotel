<?php

namespace App\Models\Pos;

use App\Models\Master\BaseMaster;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One seat-able thing: table 7, villa 3, apartment 1.
 *
 * `capacity` is how many covers it holds. Zero means nobody has said — the
 * table still works, table reservations just cannot warn that a party of six
 * will not fit.
 *
 * `outlet_id` is carried here as well as on the group. It is redundant on
 * purpose: the POS screen asks "which tables does this outlet have" on every
 * single order, and one column saves that question a join.
 */
class PosTable extends BaseMaster
{
    use SoftDeletes;

    protected $table = 'pos_tables';

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(PosTableGroup::class, 'pos_table_group_id');
    }

    /**
     * A short code that belongs to this one table on this one installation.
     *
     * Derived from the app key rather than stored, so a printed card stays
     * valid for ever without a column to keep in step — and a card printed
     * for one hotel cannot be photographed and used at another. The single
     * source of truth for it: the staff shortcut (PosController::scan) and
     * the public guest menu (GuestOrderController) both check a scanned code
     * against this same formula, so it only exists in one place.
     */
    public function code(): string
    {
        return strtoupper(substr(
            hash_hmac('sha256', 'pos-table:' . $this->id, (string) config('app.key')),
            0,
            6
        ));
    }
}
