<?php

namespace App\Models\Pos;

use App\Models\Master\BaseMaster;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * Where a sale happened — the restaurant, room service, the bar.
 *
 * Kept apart from *order type*: the restaurant sells both dine-in and take
 * away, so "which till rang it" and "how was it sold" are two questions and
 * the dashboard charts them separately.
 *
 * Deleting an outlet only hides it. Every bill it ever rang up names it, and a
 * hard delete would leave those bills pointing at nothing — so the row stays,
 * marked deleted, and can be brought back.
 */
class Outlet extends BaseMaster
{
    use SoftDeletes;

    protected $table = 'outlets';

    public const KINDS = [
        'restaurant' => 'Restaurant',
        'room_service' => 'Room Service',
        'bar' => 'Bar',
        'banquet' => 'Banquet',
        'other' => 'Other',
    ];

    /** Thermal roll widths, in millimetres. */
    public const PAGE_WIDTHS = [
        58 => '2 inch (58 mm)',
        80 => '3 inch (80 mm)',
        105 => '4 inch (105 mm)',
    ];

    public const PRINT_MARGINS = [
        0 => '0 mm',
        2 => '2 mm',
        4 => '4 mm',
        6 => '6 mm',
        8 => '8 mm',
        10 => '10 mm',
    ];

    /** Fonts a receipt printer can be trusted to have. */
    public const HEADER_FONTS = [
        'Arial' => 'Arial',
        'Courier New' => 'Courier New',
        'Georgia' => 'Georgia',
        'Tahoma' => 'Tahoma',
        'Times New Roman' => 'Times New Roman',
        'Verdana' => 'Verdana',
    ];

    /**
     * Which order types this till takes.
     *
     * The key is the flag column, the value is the matching PosOrder::TYPES
     * key, so the POS screen can ask one question — "what may I sell here?" —
     * instead of four.
     */
    public const ORDER_TYPE_FLAGS = [
        'pos_dine_in' => 'dine_in',
        'pos_room_service' => 'room_service',
        'pos_delivery' => 'delivery',
        'pos_take_away' => 'take_away',
    ];

    /**
     * Every yes/no setting on the Other Details column, in the order it is
     * shown. One list drives the form, the validator and the save, so a new
     * switch is one line here rather than three edits in three files.
     *
     * @var array<string, array{label: string, help?: string, group?: string}>
     */
    public const FLAGS = [
        'is_retail' => [
            'label' => 'Retail Outlet',
            'help' => 'Sells goods over a counter rather than food to a table — no KOT, no steward.',
        ],
        'tax_inclusive' => [
            'label' => 'Tax Inclusive',
            'help' => 'Menu prices already have GST in them; the bill shows the tax broken back out.',
        ],
        'discount_after_tax' => [
            'label' => 'Discount After Tax',
            'help' => 'Off the gross rather than the net. Changes what the guest pays — check with your accountant.',
        ],
        'allow_open_item' => [
            'label' => 'Allow Open Item',
            'help' => 'A cashier may type an item and a price that are not on the menu.',
        ],
        'pos_dine_in' => ['label' => 'POS - Dine In', 'group' => 'Order types'],
        'pos_room_service' => ['label' => 'POS - Room Service', 'group' => 'Order types'],
        'pos_delivery' => ['label' => 'POS - Delivery', 'group' => 'Order types'],
        'pos_take_away' => ['label' => 'POS - Take Away', 'group' => 'Order types'],
        'split_liquor_bill' => [
            'label' => 'Split Liquor Bill',
            'help' => 'Drinks are printed on a bill of their own — several states require it.',
        ],
        'diff_liquor_series' => [
            'label' => 'Diff. Liquor Bill Series',
            'help' => 'That liquor bill gets its own number series as well.',
        ],
        'auto_settle' => [
            'label' => 'Auto Settle',
            'help' => 'Printing the bill also marks it paid. For a counter that takes cash on the spot.',
        ],
        'show_order_notification' => [
            'label' => 'Show Order Notification',
            'help' => 'Pops a note on the till when the kitchen marks an order ready.',
        ],
        'show_last_orders' => [
            'label' => 'Show Last 5 Orders Items',
            'help' => 'Repeat regulars quickly — the guest\'s last five orders sit beside the menu.',
        ],
    ];

    protected $casts = [
        'is_retail' => 'boolean',
        'tax_inclusive' => 'boolean',
        'discount_after_tax' => 'boolean',
        'allow_open_item' => 'boolean',
        'pos_dine_in' => 'boolean',
        'pos_room_service' => 'boolean',
        'pos_delivery' => 'boolean',
        'pos_take_away' => 'boolean',
        'split_liquor_bill' => 'boolean',
        'diff_liquor_series' => 'boolean',
        'auto_settle' => 'boolean',
        'show_order_notification' => 'boolean',
        'show_last_orders' => 'boolean',
        'header_font_bold' => 'boolean',
        'guest_signature_print' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    /**
     * Who may bill through this till. Empty means everybody.
     *
     * Both pivot columns are named out loud because the users table's primary
     * key is `user_id`, not `id` — left to guess, Eloquent would look for a
     * `user_user_id` column that does not exist.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\User::class, 'outlet_user', 'outlet_id', 'user_id');
    }

    public function groups(): HasMany
    {
        return $this->hasMany(PosTableGroup::class, 'outlet_id');
    }

    public function tables(): HasMany
    {
        return $this->hasMany(PosTable::class, 'outlet_id');
    }

    public function slots(): HasMany
    {
        return $this->hasMany(PosReservationSlot::class, 'outlet_id');
    }

    public function ratePlans(): BelongsToMany
    {
        return $this->belongsToMany(PosRatePlan::class, 'pos_rate_plan_outlet', 'outlet_id', 'pos_rate_plan_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Reading one
    |--------------------------------------------------------------------------
    */

    /** Outlets a POS screen may sell through: live rows, switched on. */
    public function scopeSellable(Builder $query): Builder
    {
        return $query->where('status', 1);
    }

    /** @return list<string> PosOrder::TYPES keys this outlet takes. */
    public function orderTypes(): array
    {
        $types = [];

        foreach (self::ORDER_TYPE_FLAGS as $column => $type) {
            if ($this->{$column}) {
                $types[] = $type;
            }
        }

        return $types;
    }

    /** "KOLKATA, 700001" — the address lines that were actually filled in. */
    public function getAddressLineAttribute(): string
    {
        return collect([$this->address1, $this->address2, $this->address3])
            ->filter(fn ($line) => filled($line))
            ->implode(', ');
    }

    /** Open round the clock when neither end of the window is set. */
    public function getTimingLabelAttribute(): string
    {
        if (! $this->start_time && ! $this->end_time) {
            return '24 hours';
        }

        return trim(($this->start_time ? date('h:i A', strtotime($this->start_time)) : '—')
            . ' – ' . ($this->end_time ? date('h:i A', strtotime($this->end_time)) : '—'));
    }

    /**
     * A browsable URL for the logo, or null.
     *
     * Null both when no logo was ever uploaded and when the file has since gone
     * missing — a broken image on a settings page tells the user nothing.
     */
    public function logoUrl(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        return Storage::disk('public')->exists($this->logo_path)
            ? Storage::disk('public')->url($this->logo_path)
            : null;
    }

    public function isDeleted(): bool
    {
        return $this->trashed();
    }
}
