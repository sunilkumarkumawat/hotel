<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Models\Master\Room;
use App\Models\Reservation\Reservation;
use App\Models\Reservation\ReservationRoom;
use App\Support\Money;
use App\Support\RoomTimeline;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Reservation Calendar New — the tape chart.
 *
 * One row per room, one column per night. Drag across free nights to start a
 * booking or block the room; drop an unassigned booking onto a free room.
 */
class ReservationCalendarController extends Controller
{
    private const SPANS = [7 => '7 days', 15 => '15 days', 30 => '30 days'];

    public function index(Request $request): View
    {
        [$start, $days] = $this->window($request);

        $timeline = RoomTimeline::for(Helper::getActiveBranchId(), $start->toDateString(), $days);
        $chart = $timeline->build();

        return view('reservation.calendar', [
            'start' => $start,
            'days' => $days,
            'spans' => self::SPANS,
            'dates' => $timeline->dates(),
            'chart' => $chart,
            'today' => CarbonImmutable::today()->toDateString(),
            'prev' => $start->subDays($days)->toDateString(),
            'next' => $start->addDays($days)->toDateString(),
        ]);
    }

    /**
     * Take a room out of service for a run of nights.
     */
    public function block(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'room_id' => 'required|integer',
            'from_date' => 'required|date',
            'to_date' => 'required|date|after:from_date',
            'reason' => 'nullable|string|max:255',
            'block_type' => 'nullable|in:management,maintenance',
        ], [
            'to_date.after' => 'A block has to cover at least one night.',
        ]);

        $room = Room::query()->forBranch()->findOrFail($data['room_id']);

        if ($clash = $this->clashOn($room->id, $data['from_date'], $data['to_date'])) {
            return back()->with('error', "Room {$room->room_no} is not free then — {$clash}.");
        }

        DB::table('room_blocks')->insert([
            'branch_id' => Helper::getActiveBranchId(),
            'room_id' => $room->id,
            'from_date' => $data['from_date'],
            'to_date' => $data['to_date'],
            'reason' => $data['reason'] ?: 'Blocked',
            'block_type' => $data['block_type'] ?? 'management',
            'status' => 'blocked',
            'created_by' => $request->user()->user_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('status', "Room {$room->room_no} blocked from {$data['from_date']}.");
    }

    /** Put a blocked room back into the pool. */
    public function unblock(Request $request): RedirectResponse
    {
        $data = $request->validate(['block_id' => 'required|integer']);

        $updated = DB::table('room_blocks')
            ->where('id', $data['block_id'])
            ->where('branch_id', Helper::getActiveBranchId())
            ->update(['status' => 'released', 'updated_at' => now()]);

        return $updated
            ? back()->with('status', 'Room released — it can be sold again.')
            : back()->with('error', 'That block no longer exists.');
    }

    /**
     * Put an unassigned booking into a specific room.
     */
    public function assign(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'line_id' => 'required|integer',
            'room_id' => 'required|integer',
        ]);

        $branchId = Helper::getActiveBranchId();

        $line = DB::table('reservation_rooms as rr')
            ->join('reservations as r', 'r.id', '=', 'rr.reservation_id')
            ->where('rr.id', $data['line_id'])
            ->where('r.branch_id', $branchId)
            ->first(['rr.id', 'rr.arrival_date', 'rr.checkout_date', 'rr.no_of_rooms', 'r.reservation_no', 'r.status']);

        abort_unless($line, 404);

        // The same guard move() has: a cancelled or already-departed booking
        // is not a live hold on a room, and allotting one from the chart would
        // let it sit there as if it were still waiting to arrive.
        if (! in_array($line->status, ['confirmed', 'tentative'], true)) {
            return back()->with('error', sprintf(
                '%s is %s — only a booking that has not arrived can be allotted from the chart.',
                $line->reservation_no,
                strtolower(Reservation::STATUSES[$line->status] ?? $line->status)
            ));
        }

        $room = Room::query()->forBranch()->findOrFail($data['room_id']);

        $from = substr($line->arrival_date, 0, 10);
        $to = substr($line->checkout_date, 0, 10);

        if ($clash = $this->clashOn($room->id, $from, $to)) {
            return back()->with('error', "Room {$room->room_no} is not free then — {$clash}.");
        }

        // The same guard the drag has: a guest who has arrived is already in a
        // room, and re-allotting the booking under them would leave the
        // calendars naming one room while the folio bills another.
        if ($this->hasArrived((int) $line->id)) {
            return back()->with(
                'error',
                "{$line->reservation_no} has already checked in — change the room from Check Out Guest."
            );
        }

        /*
         * A family's booking is ONE row saying five rooms, so allotting has to
         * be able to take them one at a time: this peels a single room off the
         * row rather than refusing the whole thing.
         *
         * The row is split — its count drops by one and a new one-room row is
         * created carrying the allotted room — so five drags produce five rows
         * of one, the reservation still totals five rooms, and the queue counts
         * down as the clerk works. The money moves with it, priced off the same
         * per-room figures the booking was taken at, so the reservation's total
         * is the same before and after.
         */
        if ((int) $line->no_of_rooms > 1) {
            $allotted = $this->peelOneRoom((int) $line->id, $room);

            return back()->with('status', sprintf(
                '%s — room %s allotted. %d of %d still to allot.',
                $line->reservation_no,
                $room->room_no,
                $allotted,
                (int) $line->no_of_rooms
            ));
        }

        DB::table('reservation_rooms')
            ->where('id', $line->id)
            ->update(['room_id' => $room->id, 'room_no' => $room->room_no, 'updated_at' => now()]);

        return back()->with('status', "{$line->reservation_no} allotted room {$room->room_no}.");
    }

    /**
     * Take one room off a multi-room booking row and allot it.
     *
     * The row keeps everything that priced it — dates, plan, rent, discount,
     * pax — and only the count moves: the original drops by one, and a new row
     * of exactly one room is created in the allotted room. Both rows are then
     * re-priced from the stored per-room figures with App\Support\Money, which
     * is the same arithmetic the booking screen used, so five separate rows of
     * one add up to what one row of five did.
     *
     * @return int how many rooms are still waiting on the original row
     */
    private function peelOneRoom(int $lineId, Room $room): int
    {
        return DB::transaction(function () use ($lineId, $room) {
            /** @var ReservationRoom $line */
            $line = ReservationRoom::query()->lockForUpdate()->findOrFail($lineId);

            $branchId = $line->reservation?->branch_id;

            // What one room of this row costs, using the figures it was taken
            // at rather than today's rate card.
            $perRoom = fn (int $count) => Money::roomRow([
                'room_rent' => (float) $line->room_rent,
                'discount' => (float) $line->discount,
                'plan_charge' => (float) $line->plan_charge,
                'no_of_days' => (int) $line->no_of_days,
                'no_of_rooms' => $count,
                'tax_type' => $line->tax_type,
                // Both halves of the split keep the row's own tax choice.
                'tax_choice' => $line->tax_choice,
                'tax_percent' => (float) $line->tax_percent,
            ], $branchId);

            $left = max(1, (int) $line->no_of_rooms - 1);
            $one = $perRoom(1);
            $rest = $perRoom($left);

            // The pax on the row belong to the whole party; the room being
            // peeled off takes a fair share and the rest stays behind, so a
            // family of ten in five rooms does not become fifty people.
            $share = fn (int $total) => (int) floor($total / max(1, (int) $line->no_of_rooms));

            ReservationRoom::create([
                'reservation_id' => $line->reservation_id,
                'arrival_date' => $line->arrival_date,
                'arrival_time' => $line->arrival_time,
                'checkout_date' => $line->checkout_date,
                'checkout_time' => $line->checkout_time,
                'guest_type' => $line->guest_type,
                'room_category_id' => $line->room_category_id,
                'room_type_id' => $line->room_type_id,
                'plan_type_id' => $line->plan_type_id,
                'room_id' => $room->id,
                'room_no' => $room->room_no,
                'no_of_days' => $line->no_of_days,
                'no_of_rooms' => 1,
                'tax_type' => $line->tax_type,
                'tax_choice' => $line->tax_choice,
                'room_rent' => $line->room_rent,
                'discount' => $line->discount,
                'plan_charge' => $line->plan_charge,
                'male' => $share((int) $line->male),
                'female' => $share((int) $line->female),
                'child' => $share((int) $line->child),
                'amount' => $one['amount'],
                'tax_percent' => $one['tax_percent'],
                'tax_amount' => $one['tax_amount'],
                'net_amount' => $one['net_amount'],
            ]);

            $line->update([
                'no_of_rooms' => $left,
                // Whatever the split left over stays on the waiting row, so no
                // guest is lost between the two.
                'male' => max(0, (int) $line->male - $share((int) $line->male)),
                'female' => max(0, (int) $line->female - $share((int) $line->female)),
                'child' => max(0, (int) $line->child - $share((int) $line->child)),
                'amount' => $rest['amount'],
                'tax_percent' => $rest['tax_percent'],
                'tax_amount' => $rest['tax_amount'],
                'net_amount' => $rest['net_amount'],
            ]);

            return $left;
        });
    }

    /**
     * Drag a booking to different nights, or to a different room.
     *
     * "Meri booking agli date pe kar do" is the whole point: the stay keeps
     * its length, so the number of nights, the rent and the tax are untouched
     * and only the dates move. That is why this does not re-price anything.
     */
    public function move(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'line_id' => 'required|integer',
            'room_id' => 'required|integer',
            'arrival_date' => 'required|date',
        ]);

        $branchId = Helper::getActiveBranchId();

        $line = DB::table('reservation_rooms as rr')
            ->join('reservations as r', 'r.id', '=', 'rr.reservation_id')
            ->where('rr.id', $data['line_id'])
            ->where('r.branch_id', $branchId)
            ->first([
                'rr.id', 'rr.arrival_date', 'rr.checkout_date', 'rr.no_of_days',
                'rr.no_of_rooms', 'rr.room_id', 'rr.room_no',
                'r.id as reservation_id', 'r.reservation_no', 'r.status',
                'r.title', 'r.first_name', 'r.last_name',
            ]);

        abort_unless($line, 404);

        if (! in_array($line->status, ['confirmed', 'tentative'], true)) {
            return back()->with('error', sprintf(
                '%s is %s — only a booking that has not arrived can be moved from the chart.',
                $line->reservation_no,
                strtolower(Reservation::STATUSES[$line->status] ?? $line->status)
            ));
        }

        if ((int) $line->no_of_rooms > 1) {
            return back()->with('error', "{$line->reservation_no} is for {$line->no_of_rooms} rooms — open the booking to change its dates.");
        }

        /*
         * The booking's own status is not enough. A booking with two rows —
         * one arrived, one still to come — stays "confirmed", and dragging the
         * arrived row would leave the calendars showing one room while the
         * folio billed another. A guest already in a room is moved from Check
         * Out Guest, not by dragging their booking.
         */
        if ($this->hasArrived((int) $line->id)) {
            return back()->with('error', sprintf(
                '%s has already checked in%s — extend or move them from Check Out Guest, not from the chart.',
                $line->reservation_no,
                $line->room_no ? " to room {$line->room_no}" : ''
            ));
        }

        $room = Room::query()->forBranch()->findOrFail($data['room_id']);

        // Keep the stay exactly as long as it was; only the dates slide.
        $nights = max(1, Money::nights(substr($line->arrival_date, 0, 10), substr($line->checkout_date, 0, 10)));
        $from = CarbonImmutable::parse($data['arrival_date'])->toDateString();
        $to = CarbonImmutable::parse($from)->addDays($nights)->toDateString();

        if ($clash = $this->clashOn($room->id, $from, $to, (int) $line->id)) {
            return back()->with('error', "Room {$room->room_no} is not free {$from} → {$to} — {$clash}.");
        }

        $was = substr($line->arrival_date, 0, 10) . ' → ' . substr($line->checkout_date, 0, 10)
            . ($line->room_no ? ' in ' . $line->room_no : '');

        DB::table('reservation_rooms')->where('id', $line->id)->update([
            'arrival_date' => $from,
            'checkout_date' => $to,
            'no_of_days' => $nights,
            'room_id' => $room->id,
            'room_no' => $room->room_no,
            'updated_at' => now(),
        ]);

        $guest = trim(($line->title ? $line->title . ' ' : '') . $line->first_name . ' ' . $line->last_name);

        return back()->with('status', sprintf(
            '%s (%s) moved from %s to %s → %s in %s. %d night(s), same rate.',
            $guest,
            $line->reservation_no,
            $was,
            $from,
            $to,
            $room->room_no,
            $nights
        ));
    }

    /** Free rooms for a set of dates — feeds the assign dropdown. */
    public function freeRooms(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after:from',
            'room_type_id' => 'nullable|integer',
        ]);

        $rooms = Room::query()
            ->forBranch()
            ->availableBetween($data['from'], $data['to'])
            ->when($data['room_type_id'] ?? null, fn ($q, $id) => $q->where('room_type_id', $id))
            ->with('type')
            ->orderBy('room_no')
            ->get()
            ->map(fn (Room $r) => [
                'id' => $r->id,
                'room_no' => $r->room_no,
                'type' => $r->type?->name,
            ]);

        return response()->json($rooms);
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /** Is somebody from this booking row already in a room? */
    private function hasArrived(int $rowId): bool
    {
        return DB::table('check_ins')
            ->where('reservation_room_id', $rowId)
            ->where('status', 'in_house')
            ->exists();
    }

    /**
     * Is anything already sitting in this room over these nights?
     *
     * Returns a human reason, or null when the room is free. Checked here as
     * well as in the UI because the chart the browser is looking at may be
     * seconds out of date.
     */
    /**
     * Why the room is not free over [$from, $to), or null if it is.
     *
     * `$exceptLine` leaves one booking line out of the test — without it,
     * nudging a stay one night to the right would clash with itself.
     */
    private function clashOn(int $roomId, string $from, string $to, ?int $exceptLine = null): ?string
    {
        $booking = DB::table('reservation_rooms as rr')
            ->join('reservations as r', 'r.id', '=', 'rr.reservation_id')
            ->where('rr.room_id', $roomId)
            ->when($exceptLine, fn ($q, $id) => $q->where('rr.id', '!=', $id))
            ->whereIn('r.status', ['confirmed', 'tentative', 'checked_in'])
            ->where('rr.arrival_date', '<', $to)
            ->where('rr.checkout_date', '>', $from)
            ->value('r.reservation_no');

        if ($booking) {
            return "{$booking} is in it";
        }

        // A guest already checked in holds the room even when the booking
        // line does not name it — see Room::scopeAvailableBetween.
        $guest = DB::table('check_ins')
            ->where('room_id', $roomId)
            ->where('status', 'in_house')
            ->where('checkin_date', '<', $to)
            ->whereRaw('COALESCE(actual_checkout_date, expected_checkout_date) > ?', [$from])
            ->value('guest_name');

        if ($guest) {
            return "{$guest} is checked into it";
        }

        $blocked = DB::table('room_blocks')
            ->where('room_id', $roomId)
            ->where('status', 'blocked')
            ->where('from_date', '<', $to)
            ->where('to_date', '>', $from)
            ->exists();

        return $blocked ? 'it is already blocked' : null;
    }

    /** @return array{0: CarbonImmutable, 1: int} */
    private function window(Request $request): array
    {
        try {
            $start = CarbonImmutable::parse($request->string('date')->toString() ?: 'today')->startOfDay();
        } catch (\Throwable) {
            $start = CarbonImmutable::today();
        }

        $days = (int) $request->integer('days', 15);

        if (! array_key_exists($days, self::SPANS)) {
            $days = 15;
        }

        return [$start, $days];
    }
}
