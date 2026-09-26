<?php

namespace App\Support;

use App\Models\FrontOffice\CheckIn;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The small things the pool, the hall and the car park all need.
 *
 * Four screens ask the same four questions — what is the next number, what
 * does this time string really say, how long is this, and which guest is it
 * for — and getting any of them slightly different on one screen is how two
 * clerks end up holding the same ticket number, or how a booking blocks
 * itself. So they are answered once, here.
 */
class Facility
{
    /*
    |--------------------------------------------------------------------------
    | Numbering
    |--------------------------------------------------------------------------
    */

    /**
     * The next POOL-1-0001 / HALL-1-0001 / PRK-1-0001 / TRIP-1-0001.
     *
     * Same shape as a bill number or an arrival number, with one difference
     * that matters: the read takes `lockForUpdate()`, so two clerks pressing
     * Save in the same second queue behind one another instead of both reading
     * "0007" and both writing it. **It has to be called inside a transaction**
     * — a lock taken outside one is released the moment the statement ends and
     * buys nothing at all.
     */
    public static function nextNumber(string $table, string $column, string $prefix, int $branchId): string
    {
        $prefix = $prefix . '-' . $branchId . '-';

        $last = DB::table($table)
            ->where('branch_id', $branchId)
            ->where($column, 'like', $prefix . '%')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value($column);

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /*
    |--------------------------------------------------------------------------
    | Times
    |--------------------------------------------------------------------------
    */

    /**
     * Any time a form can send, as HH:MM:SS.
     *
     * Every clash test on these screens compares times as strings, and as
     * strings `'09:00'` sorts *before* `'09:00:00'`. Left alone that turns a
     * session ending at 09:00 into one still running at 09:00, and the next
     * booking is refused for merely touching it. So nothing reaches a query
     * until it has been through here.
     */
    public static function hms(?string $time, string $fallback = '00:00:00'): string
    {
        $time = trim((string) $time);

        if ($time === '') {
            return $fallback;
        }

        $parts = explode(':', $time);

        return sprintf(
            '%02d:%02d:%02d',
            min(23, max(0, (int) ($parts[0] ?? 0))),
            min(59, max(0, (int) ($parts[1] ?? 0))),
            min(59, max(0, (int) ($parts[2] ?? 0)))
        );
    }

    /** "09:00:00" → "09:00", which is what an `<input type="time">` wants. */
    public static function hm(?string $time): string
    {
        return substr(self::hms($time), 0, 5);
    }

    /** A date and a time as one comparable '2026-09-16 18:00:00'. */
    public static function stamp(string $date, ?string $time, string $fallback = '00:00:00'): string
    {
        return substr($date, 0, 10) . ' ' . self::hms($time, $fallback);
    }

    /** Minutes since midnight — what the pool calendar positions its bars with. */
    public static function minutes(?string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', self::hms($time)));

        return $h * 60 + $m;
    }

    /**
     * Whole hours between two moments, always rounded up.
     *
     * A car that sat in the bay for ten minutes has used an hour of it — that
     * is how every car park in the country counts, and rounding down would
     * make a fifty-nine minute stay free. Nothing here decides *whether* to
     * charge: that is the tick on the screen.
     */
    public static function hoursBetween(?string $from, ?string $to): float
    {
        if (! $from || ! $to) {
            return 0.0;
        }

        $minutes = (strtotime((string) $to) - strtotime((string) $from)) / 60;

        if ($minutes <= 0) {
            return 0.0;
        }

        return (float) max(1, (int) ceil($minutes / 60));
    }

    /**
     * How many hours, days or events a hall booking is worth.
     *
     * Worked out from the dates rather than taken from the form, because the
     * form is where somebody types 1 by mistake and bills a three-day wedding
     * as one hour. The screen shows this figure and lets the clerk change it,
     * which is a different thing from believing whatever arrives.
     *
     * A day is twenty-four hours from the start rather than a calendar date:
     * a reception that runs to 1am is one day of the hall, not two.
     */
    public static function hallQty(string $rateType, string $start, string $end): float
    {
        if ($rateType === 'event') {
            return 1.0;
        }

        $hours = self::hoursBetween($start, $end);

        if ($rateType === 'day') {
            return (float) max(1, (int) ceil($hours / 24));
        }

        return (float) max(1, (int) $hours);
    }

    /*
    |--------------------------------------------------------------------------
    | Who the booking is for
    |--------------------------------------------------------------------------
    */

    /**
     * The stays a booking screen may be attached to.
     *
     * In house, this branch, nothing else. A guest who has checked out cannot
     * be given a new charge, and another property's guests are none of this
     * branch's business.
     *
     * @return Collection<int, CheckIn>
     */
    public static function stays(int $branchId): Collection
    {
        return CheckIn::query()
            ->where('branch_id', $branchId)
            ->where('status', 'in_house')
            ->with('room')
            ->orderBy('guest_name')
            ->get(['id', 'folio_no', 'guest_name', 'mobile', 'room_id']);
    }

    /**
     * One stay, checked against the branch.
     *
     * An id that arrives from a form is checked against the branch, not just
     * against being a number — the dropdown it came from is not a permission
     * check, and nothing stops a posted id naming another hotel's guest.
     */
    public static function stay(int|string|null $checkInId, int $branchId): ?CheckIn
    {
        $id = (int) $checkInId;

        if ($id <= 0) {
            return null;
        }

        return CheckIn::query()
            ->where('branch_id', $branchId)
            ->where('status', 'in_house')
            ->with('room')
            ->find($id);
    }

    /**
     * Name, mobile and room for a booking — from the stay, or typed by hand.
     *
     * Either half works on its own: pick an in-house stay and the three fields
     * fill themselves in, or type a walk-in's details and leave the stay empty.
     *
     * @param  array<string, mixed>  $data
     * @return array{check_in_id: ?int, guest_name: string, mobile: ?string, room_no: ?string}
     */
    public static function guest(array $data, int $branchId): array
    {
        $stay = self::stay($data['check_in_id'] ?? null, $branchId);

        $typedName = trim((string) ($data['guest_name'] ?? ''));
        $typedMobile = trim((string) ($data['mobile'] ?? ''));

        return [
            'check_in_id' => $stay?->id,
            // What the clerk typed wins: the wife booking the hall on her
            // husband's room is still the person the banquet manager rings.
            'guest_name' => $typedName ?: (string) ($stay?->guest_name ?? ''),
            'mobile' => ($typedMobile ?: $stay?->mobile) ?: null,
            /*
             * The room number comes from the stay, never from the form, when a
             * stay is picked. It is what the folio line is read against, and a
             * typo there points the charge at the wrong door.
             */
            'room_no' => $stay
                ? ($stay->room?->room_no ?: $stay->folio_no)
                : (trim((string) ($data['room_no'] ?? '')) ?: null),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Dates
    |--------------------------------------------------------------------------
    */

    /** A date off a query string, or today when it is rubbish. */
    public static function date(?string $value, ?string $fallback = null): string
    {
        $fallback ??= today()->toDateString();

        return rescue(
            fn () => CarbonImmutable::parse(trim((string) $value) ?: $fallback)->toDateString(),
            $fallback,
            false
        );
    }

    /** Monday of the week a date falls in — the left edge of the hall calendar. */
    public static function weekStart(string $date): string
    {
        return CarbonImmutable::parse($date)->startOfWeek()->toDateString();
    }

    /** "₹ 1,250.00" — the one way money is written in this app. */
    public static function money(float|int|string|null $value): string
    {
        return '₹ ' . number_format((float) $value, 2);
    }

    /*
    |--------------------------------------------------------------------------
    | Putting it on the guest's bill
    |--------------------------------------------------------------------------
    */

    /**
     * Keep a booking's folio charge in step with the booking.
     *
     * One method for all four screens, and it is the only place any of them
     * writes to `folio_charges`. Called after every save, every cancellation
     * and every time a car leaves the park, it makes the folio agree with the
     * booking whatever just happened:
     *
     *   • ticked, attached to a stay, costs something  → write it, or update
     *     the one already written
     *   • not ticked, not attached, or free            → take the charge back off
     *
     * **It is idempotent**, which is the whole reason it exists. The row
     * remembers the charge it wrote in `folio_charge_id`, so saving the same
     * booking five times leaves one line on the bill rather than five. Posting
     * twice is the classic version of this bug and it is only ever found when a
     * guest reads their bill at the desk.
     *
     * A charge that has already been settled is history: it is left exactly as
     * it is and the caller is told, rather than the guest's paid bill being
     * quietly rewritten.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $row  a booking, ticket or trip
     * @return string|null  a sentence for the clerk when something could not be done
     */
    public static function syncFolio(
        \Illuminate\Database\Eloquent\Model $row,
        string $particulars,
        int $branchId,
        ?int $userId = null,
        ?string $chargeDate = null
    ): ?string {
        $existing = $row->folio_charge_id
            ? \App\Models\FrontOffice\FolioCharge::query()
                ->where('branch_id', $branchId)
                ->find($row->folio_charge_id)
            : null;

        // A settled line is part of a bill the guest has paid. Nothing here
        // touches it — and the clerk is told, because silently ignoring the
        // tick they just moved is worse than refusing it.
        if ($existing && (int) $existing->is_settled === 1) {
            return 'This was already billed and settled on folio ' . ($existing->checkIn?->folio_no ?? '—')
                . ', so the charge on the bill has been left alone.';
        }

        $wanted = (bool) $row->post_to_room
            && (int) $row->check_in_id > 0
            && round((float) $row->total_amount, 2) > 0;

        if (! $wanted) {
            if ($existing) {
                $existing->delete();
            }

            if ($row->folio_charge_id) {
                $row->forceFill(['folio_charge_id' => null])->save();
            }

            return null;
        }

        $line = [
            'branch_id' => $branchId,
            'check_in_id' => (int) $row->check_in_id,
            'charge_date' => $chargeDate ?: today()->toDateString(),
            'charge_type' => 'service',
            'particulars' => mb_substr($particulars, 0, 250),
            'qty' => 1,
            'price' => round((float) $row->amount, 2),
            'tax_choice' => $row->tax_choice ?: Tax::NONE,
            'tax_percent' => round((float) $row->tax_percent, 2),
            'tax_amount' => round((float) $row->tax_amount, 2),
            'amount' => round((float) $row->amount, 2),
            'total_amount' => round((float) $row->total_amount, 2),
            'created_by' => $userId,
        ];

        if ($existing) {
            $existing->update($line);

            return null;
        }

        $charge = \App\Models\FrontOffice\FolioCharge::create($line);

        $row->forceFill(['folio_charge_id' => $charge->id])->save();

        return null;
    }

    /**
     * Take a cancelled booking's charge back off the bill.
     *
     * Thin on purpose — it is syncFolio with the tick forced off — but it reads
     * at the call site as what it is, and a cancellation path that says
     * "post_to_room = false" three lines before cancelling is a cancellation
     * path somebody will eventually reorder.
     */
    public static function releaseFolio(
        \Illuminate\Database\Eloquent\Model $row,
        int $branchId
    ): ?string {
        $row->forceFill(['post_to_room' => false])->save();

        return self::syncFolio($row, '', $branchId);
    }
}
