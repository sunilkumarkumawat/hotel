<?php

namespace App\Models\Website;

use App\Models\Branch\Branch;
use App\Models\Reservation\Reservation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WebsitePayment extends Model
{
    protected $table = 'website_payments';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'booking_snapshot' => 'array',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'reservation_id');
    }

    /** A 40-character token on the same terms as guest-doc and feedback links. */
    public static function newToken(): string
    {
        return Str::random(40);
    }
}
