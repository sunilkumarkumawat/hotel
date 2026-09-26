<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Models\Master\RoomType;
use App\Models\Website\BranchWebsite;
use App\Support\Website\BookingService;
use App\Support\Website\RoomAvailability;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * The public, no-account pages: the hotel picker, a hotel's own landing
 * page, its rooms with live availability, and a single room's detail page.
 *
 * Every route here sits outside the `auth` middleware group in routes/web.php
 * — the same place guest-doc, feedback and guest-order already live — and
 * every method resolves the branch from the URL itself rather than from
 * Helper::getActiveBranchId(), which reads a signed-in admin's session and
 * is never set for an anonymous visitor.
 */
class HotelSiteController extends Controller
{
    /** GET /hotels — every hotel the group is ready to sell online. */
    public function picker(): View
    {
        $hotels = BranchWebsite::query()
            ->where('is_published', true)
            ->whereHas('branch', fn ($q) => $q->where('status', 1))
            ->with('branch')
            ->get()
            ->sortBy(fn ($site) => $site->branch->branch_name);

        return view('site.picker', compact('hotels'));
    }

    /** GET /hotels/{slug} */
    public function home(string $slug): View
    {
        $site = $this->resolveSite($slug);

        $roomTypes = RoomType::query()
            ->forBranch($site->branch_id)
            ->active()
            ->with(['website', 'images'])
            ->orderBy('base_rent')
            ->get();

        return view('site.home', [
            'site' => $site,
            'roomTypes' => $roomTypes,
        ]);
    }

    /** GET /hotels/{slug}/rooms */
    public function rooms(string $slug, Request $request): View
    {
        $site = $this->resolveSite($slug);
        $dates = self::resolveSearch($request);

        $roomTypes = RoomType::query()
            ->forBranch($site->branch_id)
            ->active()
            ->with(['website', 'images'])
            ->orderBy('base_rent')
            ->get();

        $left = RoomAvailability::leftByType($site->branch_id, $dates['arrival_date'], $dates['checkout_date']);

        return view('site.rooms', [
            'site' => $site,
            'roomTypes' => $roomTypes,
            'left' => $left,
            'dates' => $dates,
        ]);
    }

    /** GET /hotels/{slug}/rooms/{roomType} */
    public function roomDetail(string $slug, RoomType $roomType, Request $request): View
    {
        $site = $this->resolveSite($slug);
        $this->guardRoomType($roomType, $site);

        $dates = self::resolveSearch($request);
        $roomType->load(['website', 'images']);

        $left = RoomAvailability::left($roomType->id, $site->branch_id, $dates['arrival_date'], $dates['checkout_date']);
        $quote = BookingService::quote($roomType->id, $site->branch_id, $dates['arrival_date'], $dates['checkout_date'], min(max(1, $dates['no_of_rooms']), max(1, $left)));

        return view('site.room-detail', [
            'site' => $site,
            'roomType' => $roomType,
            'dates' => $dates,
            'left' => $left,
            'quote' => $quote,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Shared helpers — also used by BookingController
    |--------------------------------------------------------------------------
    */

    public function resolveSite(string $slug): BranchWebsite
    {
        return BranchWebsite::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->whereHas('branch', fn ($q) => $q->where('status', 1))
            ->with('branch')
            ->firstOrFail();
    }

    /** A room type belongs to this site if it is shared (no branch) or this branch's own, and active. */
    public function guardRoomType(RoomType $roomType, BranchWebsite $site): void
    {
        $belongs = $roomType->branch_id === null || (int) $roomType->branch_id === (int) $site->branch_id;

        abort_unless($belongs && $roomType->isActive(), 404);
    }

    /**
     * Dates + party size from the query string, gently defaulted rather than
     * rejected — this is a page somebody can land on from a bookmark or a
     * search-engine link with no query string at all, not a form post.
     *
     * @return array{arrival_date: string, checkout_date: string, adults: int, children: int, no_of_rooms: int}
     */
    public static function resolveSearch(Request $request): array
    {
        $today = now()->toDateString();

        $arrival = rescue(
            fn () => max($today, Carbon::parse($request->string('checkin')->toString() ?: $today)->toDateString()),
            $today,
            false
        );

        $checkout = rescue(
            fn () => $request->filled('checkout')
                ? Carbon::parse($request->string('checkout')->toString())->toDateString()
                : Carbon::parse($arrival)->addDay()->toDateString(),
            Carbon::parse($arrival)->addDay()->toDateString(),
            false
        );

        // A checkout on or before arrival is not a stay — push it out rather
        // than showing a search that can never return a room.
        if ($checkout <= $arrival) {
            $checkout = Carbon::parse($arrival)->addDay()->toDateString();
        }

        return [
            'arrival_date' => $arrival,
            'checkout_date' => $checkout,
            'adults' => max(1, min(20, $request->integer('adults', 2))),
            'children' => max(0, min(20, $request->integer('children', 0))),
            'no_of_rooms' => max(1, min(10, $request->integer('rooms', 1))),
        ];
    }
}
