<?php

namespace App\Http\Controllers\Crm;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Crm\GuestNote;
use App\Models\Master\Guest;
use App\Support\GuestCrm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The guest, rather than the booking.
 *
 * Everything on these two screens already existed somewhere in the system —
 * the stays, the money, the complaint somebody remembers. What was missing was
 * a place where it is all one person.
 *
 * The profile is written so that the first thing a receptionist sees when a
 * name comes up is what they can do something about: what this guest asks for,
 * and anything that went wrong last time.
 */
class GuestController extends Controller
{
    /** GET crm/guests */
    public function index(Request $request): View
    {
        $branchId = (int) Helper::getActiveBranchId();

        $sort = $request->string('sort')->toString();
        $sort = in_array($sort, ['stays', 'total_spend', 'last_stay_at', 'name'], true) ? $sort : 'last_stay_at';

        $guests = Guest::query()
            ->forBranch($branchId)
            ->search($request->string('q')->toString() ?: null)
            ->ofTier($request->string('tier')->toString() ?: null)
            ->when($request->boolean('returning'), fn ($q) => $q->returning())
            ->when($request->boolean('blacklisted'), fn ($q) => $q->blacklisted())
            ->when($sort === 'name', fn ($q) => $q->orderBy('first_name'))
            ->when($sort !== 'name', fn ($q) => $q->orderByDesc($sort))
            ->paginate(30)
            ->withQueryString();

        return view('crm.guests', [
            'guests' => $guests,
            'filters' => [
                'q' => $request->string('q')->toString(),
                'tier' => $request->string('tier')->toString(),
                'returning' => $request->boolean('returning'),
                'blacklisted' => $request->boolean('blacklisted'),
                'sort' => $sort,
            ],
            'tiers' => GuestCrm::TIERS,
            'counts' => [
                'all' => Guest::query()->forBranch($branchId)->count(),
                'returning' => Guest::query()->forBranch($branchId)->returning()->count(),
                'blacklisted' => Guest::query()->forBranch($branchId)->blacklisted()->count(),
                'spend' => (float) Guest::query()->forBranch($branchId)->sum('total_spend'),
                // First stay this year — a new face on the book, not just a new row in it.
                'new' => Guest::query()->forBranch($branchId)
                    ->whereYear('first_stay_at', now()->year)->count(),
                // Gold and above: the two tiers a hotel actually treats differently.
                'vip' => Guest::query()->forBranch($branchId)
                    ->whereIn('tier', ['platinum', 'gold'])->count(),
            ],
        ]);
    }

    /** GET crm/guests/{guest} */
    public function show(Guest $guest): View
    {
        $branchId = (int) Helper::getActiveBranchId();

        /*
         * The totals are a cache, and a profile opened after a stay closed
         * would otherwise show yesterday's numbers. Recounting on the way in
         * costs four aggregate queries for one guest, which is cheap — the
         * cache exists for the LIST screen, not for this one.
         */
        GuestCrm::recount($guest);

        $stays = GuestCrm::stays($guest->id);

        return view('crm.guest', [
            'guest' => $guest->fresh(),
            'stays' => $stays,
            'notes' => GuestNote::query()
                ->where('guest_id', $guest->id)
                ->with('author')
                ->orderByDesc('pinned')
                ->orderByDesc('id')
                ->get(),
            'feedback' => \App\Models\Crm\GuestFeedback::query()
                ->where('guest_id', $guest->id)
                ->answered()
                ->orderByDesc('answered_at')
                ->limit(10)
                ->get(),
            'ledger' => GuestCrm::ledger($guest->id),
            'progress' => GuestCrm::progress((int) $guest->stays, (float) $guest->total_spend),
            'kinds' => GuestNote::KINDS,
            'branchId' => $branchId,
        ]);
    }

    /** POST crm/guests/{guest}/note */
    public function addNote(Request $request, Guest $guest): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(GuestNote::KINDS))],
            'body' => ['required', 'string', 'max:2000'],
            'check_in_id' => ['nullable', 'integer'],
        ]);

        GuestNote::create($data + [
            'branch_id' => (int) Helper::getActiveBranchId(),
            'guest_id' => $guest->id,
            'pinned' => $request->boolean('pinned'),
            'created_by' => $request->user()?->user_id,
        ]);

        return back()->with('status', 'Note added to ' . $guest->name . '.');
    }

    /** POST crm/guests/note/{note}/pin — pin it, or unpin it. */
    public function pinNote(GuestNote $note): RedirectResponse
    {
        $note->update(['pinned' => ! $note->pinned]);

        return back()->with('status', $note->pinned
            ? 'Pinned — whoever checks them in next will see it.'
            : 'Unpinned.');
    }

    /** DELETE crm/guests/note/{note} */
    public function deleteNote(GuestNote $note): RedirectResponse
    {
        $note->delete();

        return back()->with('status', 'Note removed.');
    }

    /** POST crm/guests/{guest}/preferences */
    public function savePreferences(Request $request, Guest $guest): RedirectResponse
    {
        $data = $request->validate([
            'preferences' => ['nullable', 'string', 'max:2000'],
            'anniversary' => ['nullable', 'date'],
            'dob' => ['nullable', 'date', 'before:today'],
        ]);

        $guest->update($data);

        return back()->with('status', 'Saved.');
    }

    /**
     * POST crm/guests/{guest}/blacklist
     *
     * Blacklisting is a decision somebody made on a date for a reason, so it
     * takes a reason. Lifting it clears all three, because a guest who is back
     * on the books should not carry a half-erased accusation.
     */
    public function blacklist(Request $request, Guest $guest): RedirectResponse
    {
        if ($guest->is_blacklisted) {
            $guest->update([
                'is_blacklisted' => false,
                'blacklist_reason' => null,
                'blacklisted_on' => null,
                'blacklisted_by' => null,
            ]);

            return back()->with('status', $guest->name . ' is off the blacklist.');
        }

        $data = $request->validate([
            'blacklist_reason' => ['required', 'string', 'max:255'],
        ]);

        $guest->update([
            'is_blacklisted' => true,
            'blacklist_reason' => $data['blacklist_reason'],
            'blacklisted_on' => today()->toDateString(),
            'blacklisted_by' => $request->user()?->user_id,
        ]);

        return back()->with('warning', $guest->name
            . ' is blacklisted. The booking screen will warn whoever tries to take a reservation.');
    }

    /** POST crm/guests/{guest}/points — a manual adjustment, with a reason. */
    public function adjustPoints(Request $request, Guest $guest): RedirectResponse
    {
        $data = $request->validate([
            'points' => ['required', 'integer', 'min:-100000', 'max:100000', 'not_in:0'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        DB::table('loyalty_entries')->insert([
            'branch_id' => (int) Helper::getActiveBranchId(),
            'guest_id' => $guest->id,
            'entry_date' => today()->toDateString(),
            'kind' => $data['points'] > 0 ? 'adjusted' : 'redeemed',
            'points' => $data['points'],
            'reason' => $data['reason'],
            'created_by' => $request->user()?->user_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        GuestCrm::recount($guest);

        return back()->with('status', ($data['points'] > 0 ? '+' : '') . $data['points'] . ' points. '
            . 'Balance is now ' . number_format((int) $guest->fresh()->loyalty_points) . '.');
    }

    /** GET crm/occasions — birthdays and anniversaries coming up. */
    public function occasions(Request $request): View
    {
        $branchId = (int) Helper::getActiveBranchId();
        $days = (int) $request->integer('days');
        $days = in_array($days, [7, 14, 30], true) ? $days : 14;

        return view('crm.occasions', [
            'occasions' => GuestCrm::occasions($branchId, $days),
            'days' => $days,
        ]);
    }
}
