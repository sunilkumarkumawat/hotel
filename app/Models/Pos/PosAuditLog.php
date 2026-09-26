<?php

namespace App\Models\Pos;

use Illuminate\Database\Eloquent\Model;

/**
 * The four things a till gets skimmed through.
 *
 * None of them is wrong on its own — a guest does send a dish back, a printer
 * does jam. They are counted because a pattern in them is the first sign of a
 * problem, which is why the dashboard puts them on their own line under the
 * heading "watch these for leakage" rather than burying them in a report.
 */
class PosAuditLog extends Model
{
    protected $table = 'pos_audit_logs';

    protected $guarded = ['id'];

    public const ACTIONS = [
        'invoice_deleted' => 'Invoice Deleted',
        'item_removed' => 'Order Item Removed',
        'item_modified' => 'Order Item Modified',
        'invoice_reprinted' => 'Invoice Re-Printed',
    ];

    protected function casts(): array
    {
        return ['happened_at' => 'datetime', 'amount' => 'decimal:2'];
    }
}
