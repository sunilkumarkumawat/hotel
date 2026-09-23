<?php

namespace App\Support;

use App\Models\Crm\GuestFeedback;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The things on today's board a duty manager would want pointed out.
 *
 * This is deliberately not an external AI call: every line here is a
 * threshold on data the dashboard already trusts (the room board, guest
 * feedback, tomorrow's bookings), so it costs nothing, has no network
 * dependency, and can never invent a number nobody can find in the system.
 * The AI Assistant panel is free to *say* these out loud in its own words —
 * this class is where the facts come from.
 *
 * Each insight is `['tone', 'icon', 'title', 'detail', 'href']` — `href` is
 * null when there is nowhere useful to send the click.
 */
class HotelInsights
{
    /** @return list<array{tone: string, icon: string, title: string, detail: string, href: ?string}> */
    public static function build(int $branchId, HotelDashboard $today): array
    {
        $out = [];
        $date = $today->date();

        $out = array_merge($out, self::housekeeping($today));
        $out = array_merge($out, self::feedback($branchId));
        $out = array_merge($out, self::occupancyTrend($branchId, $date, $today));
        $out = array_merge($out, self::demandTomorrow($branchId, $date, $today));
        $out = array_merge($out, self::collections($today));

        return $out;
    }

    /**
     * Rooms a departed or dirty guest has left behind that housekeeping has
     * not turned around yet. Blocked/repair rooms are a different problem —
     * somebody already knows about those — so only the states that mean
     * "should be sellable but isn't yet" are counted here.
     */
    private static function housekeeping(HotelDashboard $today): array
    {
        $dirtyStates = ['dirty', 'checkout', 'reserved_dirty', 'checkin_dirty'];
        $count = collect($today->board())->filter(fn ($cell) => in_array($cell['state'], $dirtyStates, true))->count();

        if ($count === 0) {
            return [];
        }

        return [[
            'tone' => $count >= 5 ? 'danger' : 'warning',
            'icon' => 'alert',
            'title' => $count . ' ' . str('room')->plural($count) . ' waiting on housekeeping',
            'detail' => 'Checked-out or dirty rooms that have not been marked clean yet — each one is lost sellable inventory until it is.',
            'href' => null,
        ]];
    }

    /** Low-score feedback nobody has followed up on — the one panel that is a to-do, not a stat. */
    private static function feedback(int $branchId): array
    {
        $count = GuestFeedback::forBranch($branchId)->needsAttention()->count();

        if ($count === 0) {
            return [];
        }

        return [[
            'tone' => 'danger',
            'icon' => 'inbox',
            'title' => $count . ' guest ' . str('response')->plural($count) . ' need a follow-up call',
            'detail' => 'A score of ' . GuestFeedback::DETRACTOR_AT . ' or below with nobody marked as having handled it yet.',
            'href' => null,
        ]];
    }

    /** Today's occupancy against yesterday's same figure — not a forecast, just what already happened. */
    private static function occupancyTrend(int $branchId, string $date, HotelDashboard $today): array
    {
        $yesterday = new HotelDashboard($branchId, CarbonImmutable::parse($date)->subDay()->toDateString());

        $rooms = $today->headline()['rooms'];
        if ($rooms === 0) {
            return [];
        }

        // round() hands back a float even for a whole number, and float 0.0
        // is never === int 0 — casting here is what makes the "nothing
        // changed" guard below actually fire instead of showing a
        // "0 points different" insight on every quiet day.
        $pctToday = (int) round($today->headline()['occupied'] / $rooms * 100);
        $prevRooms = $yesterday->headline()['rooms'];
        $pctYesterday = $prevRooms ? (int) round($yesterday->headline()['occupied'] / $prevRooms * 100) : $pctToday;
        $diff = $pctToday - $pctYesterday;

        if ($diff === 0) {
            return [];
        }

        return [[
            'tone' => $diff > 0 ? 'success' : 'info',
            'icon' => $diff > 0 ? 'arrow-up' : 'arrow-down',
            'title' => 'Occupancy is ' . abs($diff) . ' point' . (abs($diff) === 1 ? '' : 's') . ($diff > 0 ? ' higher' : ' lower') . ' than yesterday',
            'detail' => "{$pctToday}% occupied today versus {$pctYesterday}% yesterday, same time of day.",
            'href' => null,
        ]];
    }

    /** Tomorrow's confirmed arrivals against tonight's vacant count — the plainest demand signal there is. */
    private static function demandTomorrow(int $branchId, string $date, HotelDashboard $today): array
    {
        $tomorrow = CarbonImmutable::parse($date)->addDay()->toDateString();
        $dayAfter = CarbonImmutable::parse($date)->addDays(2)->toDateString();

        $arriving = DB::table('reservation_rooms as rr')
            ->join('reservations as r', 'r.id', '=', 'rr.reservation_id')
            ->where('r.branch_id', $branchId)
            ->whereIn('r.status', ['confirmed', 'tentative'])
            ->where('rr.arrival_date', '>=', $tomorrow)
            ->where('rr.arrival_date', '<', $dayAfter)
            ->count();

        $vacant = $today->headline()['vacant'];

        if ($arriving === 0 || $vacant === 0) {
            return [];
        }

        $ratio = $arriving / $vacant;

        if ($ratio < 0.7) {
            return [];
        }

        return [[
            'tone' => $ratio >= 1 ? 'warning' : 'info',
            'icon' => 'calendar',
            'title' => $ratio >= 1
                ? 'Tomorrow already has more arrivals than vacant rooms tonight'
                : 'Tomorrow is filling up fast',
            'detail' => "{$arriving} confirmed arrival" . ($arriving === 1 ? '' : 's') . " against {$vacant} vacant room" . ($vacant === 1 ? '' : 's') . ' tonight — worth a look at rates before it sells out on its own.',
            'href' => null,
        ]];
    }

    /** In-house folios still owing money — already computed by HotelDashboard, just surfaced here. */
    private static function collections(HotelDashboard $today): array
    {
        $pending = $today->overview()['pending'];

        if ($pending === 0) {
            return [];
        }

        return [[
            'tone' => 'warning',
            'icon' => 'wallet',
            'title' => $pending . ' in-house ' . str('folio')->plural($pending) . ' not fully settled',
            'detail' => 'Balance still due against a stay currently in the house — easiest to collect before the guest checks out.',
            'href' => null,
        ]];
    }
}
