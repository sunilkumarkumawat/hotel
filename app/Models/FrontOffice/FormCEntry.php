<?php

namespace App\Models\FrontOffice;

use App\Models\Country\Country;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One foreign guest, reported to the FRRO.
 *
 * Per person rather than per room: a couple in one room is two forms. The row
 * for the person the booking is in the name of has `check_in_pax_id` null;
 * everybody else's names theirs.
 *
 * `filed_at` is the only thing here that means anything to an inspector. It is
 * stamped when somebody actually files the form and carries the reference the
 * portal gave back, so "have we reported this guest?" is a question with a
 * dated, evidenced answer rather than a checkbox somebody ticked.
 */
class FormCEntry extends Model
{
    protected $table = 'form_c_entries';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'passport_issue_date' => 'date',
            'passport_expiry_date' => 'date',
            'visa_issue_date' => 'date',
            'visa_expiry_date' => 'date',
            'arrived_in_india_on' => 'date',
            'next_destination_on' => 'date',
            'employed_in_india' => 'boolean',
            'filed_at' => 'datetime',
        ];
    }

    public function checkIn(): BelongsTo
    {
        return $this->belongsTo(CheckIn::class, 'check_in_id');
    }

    public function pax(): BelongsTo
    {
        return $this->belongsTo(CheckInPax::class, 'check_in_pax_id');
    }

    public function nationality(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'nationality_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeFiled(Builder $query): Builder
    {
        return $query->whereNotNull('filed_at');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('filed_at');
    }

    public function isFiled(): bool
    {
        return $this->filed_at !== null;
    }

    /**
     * Is the visa still valid for the whole stay?
     *
     * A visa expiring mid-stay is the single thing on this form that needs
     * somebody to do something about it today, so it is asked here rather
     * than left for a reader to compare two dates on screen.
     */
    public function visaExpiresDuringStay(): bool
    {
        if (! $this->visa_expiry_date || ! $this->checkIn) {
            return false;
        }

        return $this->visa_expiry_date->toDateString() < $this->checkIn->departsOn();
    }

    public function getPersonAttribute(): string
    {
        return $this->name ?: ($this->pax?->name ?: $this->checkIn?->guest_name ?: 'Guest');
    }
}
