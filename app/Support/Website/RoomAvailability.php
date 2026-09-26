<?php

namespace App\Support\Website;

use App\Models\Master\Room;
use Illuminate\Support\Facades\DB;

/**
 * "How many rooms of this type are still free for these dates?" — the number
 * the public website prices, sells and must never oversell.
 *
 * Deliberately NOT Room::scopeAvailableBetween(). That scope answers a
 * different question — which specific physical room rows are free of a
 * clashing room_id — and most bookings in this system never set
 * reservation_rooms.room_id at all (a room number is only assigned at
 * check-in, see ReservationController::saveRooms()). Counted that way, a
 * type with five rooms and three already sold as "3 Deluxe" on one
 * unassigned booking line would still show all five as available.
 *
 * This class instead mirrors how App\Support\Availability counts the
 * Reservation Calendar / Status View grid: total rooms of the type, minus
 * the sum of reservation_rooms.no_of_rooms across every live, clashing
 * booking line, minus anything blocked. Same half-open interval
 * (`arrival < to AND checkout > from`) as everywhere else in this codebase,
 * so this can never disagree with the calendar or the booking form about
 * what a night is.
 */
class RoomAvailability
{
    /** Reservation statuses that hold a room against inventory. */
    private const HOLDS_ROOM = ['confirmed', 'tentative', 'checked_in'];

    /**
     * Rooms of this type still free for [$from, $to).
     *
     * Pass `lock: true` only from inside a DB transaction that is about to
     * act on the answer (see BookingService::assertAvailable()) — it takes
     * row locks that must be released quickly by a commit or rollback, not
     * held open for a page render.
     */
    public static function left(int $roomTypeId, int $branchId, string $from, string $to, bool $lock = false): int
    {
        $roomsQuery = Room::query()
            ->forBranch($branchId)
            ->where('room_type_id', $roomTypeId)
            ->active();

        if ($lock) {
            $roomsQuery->lockForUpdate();
        }

        $roomIds = $roomsQuery->pluck('id');
        $total = $roomIds->count();

        if ($total === 0) {
            return 0;
        }

        $soldQuery = DB::table('reservation_rooms as rr')
            ->join('reservations as r', 'r.id', '=', 'rr.reservation_id')
            ->where('r.branch_id', $branchId)
            ->where('rr.room_type_id', $roomTypeId)
            ->whereIn('r.status', self::HOLDS_ROOM)
            ->where('rr.arrival_date', '<', $to)
            ->where('rr.checkout_date', '>', $from);

        if ($lock) {
            $soldQuery->lockForUpdate();
        }

        $sold = (int) $soldQuery->sum('rr.no_of_rooms');

        $blockedQuery = DB::table('room_blocks as b')
            ->whereIn('b.room_id', $roomIds)
            ->where('b.status', 'blocked')
            ->where('b.from_date', '<', $to)
            ->where('b.to_date', '>', $from);

        if ($lock) {
            $blockedQuery->lockForUpdate();
        }

        $blocked = (int) $blockedQuery->count();

        return max(0, $total - $sold - $blocked);
    }

    /**
     * The same count, for every active room type in a branch at once — one
     * query per table rather than one per card, for the rooms listing page.
     *
     * @return array<int, int> room_type_id => rooms left
     */
    public static function leftByType(int $branchId, string $from, string $to): array
    {
        $rooms = Room::query()->forBranch($branchId)->active()->get(['id', 'room_type_id']);

        $totals = $rooms->countBy('room_type_id');
        $roomIds = $rooms->pluck('id');

        if ($roomIds->isEmpty()) {
            return [];
        }

        $sold = DB::table('reservation_rooms as rr')
            ->join('reservations as r', 'r.id', '=', 'rr.reservation_id')
            ->where('r.branch_id', $branchId)
            ->whereIn('r.status', self::HOLDS_ROOM)
            ->where('rr.arrival_date', '<', $to)
            ->where('rr.checkout_date', '>', $from)
            ->groupBy('rr.room_type_id')
            ->pluck(DB::raw('SUM(rr.no_of_rooms) as sold'), 'rr.room_type_id');

        $blocked = DB::table('room_blocks as b')
            ->join('rooms as ro', 'ro.id', '=', 'b.room_id')
            ->whereIn('b.room_id', $roomIds)
            ->where('b.status', 'blocked')
            ->where('b.from_date', '<', $to)
            ->where('b.to_date', '>', $from)
            ->groupBy('ro.room_type_id')
            ->pluck(DB::raw('COUNT(*) as blocked'), 'ro.room_type_id');

        return $totals->mapWithKeys(fn ($total, $typeId) => [
            (int) $typeId => max(0, (int) $total - (int) ($sold[$typeId] ?? 0) - (int) ($blocked[$typeId] ?? 0)),
        ])->all();
    }

    /**
     * Lock the type's inventory and throw if fewer than $need rooms are free.
     *
     * Called twice in the life of one public booking: once (unlocked, via
     * left()) when the guest is just browsing prices, and once here — locked,
     * inside the same transaction that is about to insert the booking — the
     * instant before it becomes real. Every concurrent attempt to book the
     * same room type serialises on the row lock this takes, so two guests
     * paying for the last room at the same moment cannot both win it: the
     * second one's lock waits for the first to commit, then re-counts and
     * correctly sees zero left.
     */
    public static function assertAvailable(int $roomTypeId, int $branchId, string $from, string $to, int $need): void
    {
        $left = self::left($roomTypeId, $branchId, $from, $to, lock: true);

        if ($left < $need) {
            throw new RoomsUnavailableException(
                $left,
                $left > 0
                    ? "Only {$left} room(s) of this type are left for these dates."
                    : 'No rooms of this type are left for these dates.'
            );
        }
    }
}
