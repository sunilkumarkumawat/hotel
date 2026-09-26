<?php

namespace App\Models\Master;

use App\Support\RecordsActivity;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Service extends BaseMaster
{
    use RecordsActivity;

    protected $table = 'services';

    public function tax(): BelongsTo
    {
        return $this->belongsTo(TaxMaster::class, 'tax_master_id');
    }
}
