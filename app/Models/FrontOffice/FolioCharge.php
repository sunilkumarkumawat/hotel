<?php

namespace App\Models\FrontOffice;

use App\Models\Master\Service;
use App\Models\User;
use App\Support\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line on a guest's folio — a night of room rent, a service, a sundry.
 *
 * Room rent is posted a night at a time rather than as one lump for the stay.
 * That is what makes extending a checkout simply add nights, and what lets the
 * bill name the date of every night it is charging for.
 */
class FolioCharge extends Model
{
    use RecordsActivity;

    protected $guarded = ['id'];

    public const TYPES = [
        'room' => 'Room Details',
        'service' => 'Services',
        'misc' => 'Miscellaneous',
        'discount' => 'Discount',
        'tax' => 'Tax',
    ];

    /** Lines the desk may not touch by hand — the system posts them. */
    public const SYSTEM_TYPES = ['room'];

    protected function casts(): array
    {
        return [
            'charge_date' => 'date',
            'qty' => 'decimal:2',
            'price' => 'decimal:2',
            'tax_percent' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function checkIn(): BelongsTo
    {
        return $this->belongsTo(CheckIn::class, 'check_in_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('charge_type', $type);
    }

    /** A room night is posted by the system, so it is not editable by hand. */
    public function isSystem(): bool
    {
        return in_array($this->charge_type, self::SYSTEM_TYPES, true);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->charge_type] ?? ucfirst((string) $this->charge_type);
    }
}
