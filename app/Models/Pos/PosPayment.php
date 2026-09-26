<?php

namespace App\Models\Pos;

use App\Models\Master\PayMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Money taken against a POS invoice, one row per pay mode. */
class PosPayment extends Model
{
    protected $table = 'pos_payments';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['paid_at' => 'datetime', 'amount' => 'decimal:2'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(PosInvoice::class, 'pos_invoice_id');
    }

    public function payMode(): BelongsTo
    {
        return $this->belongsTo(PayMode::class, 'pay_mode_id');
    }
}
