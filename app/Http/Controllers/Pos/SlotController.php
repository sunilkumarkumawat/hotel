<?php

namespace App\Http\Controllers\Pos;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Pos\Outlet;
use App\Models\Pos\PosReservationSlot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Table Reservation — Slots.
 *
 * The sittings an outlet takes bookings for, and how many parties it will hold
 * in each. A slot is a time of day rather than a date: 7:30 PM is 7:30 PM every
 * day the restaurant opens.
 *
 * `max_booking` is the ceiling the booking screen counts down from. Zero means
 * nobody has set one, so nothing gets refused — which is the right default,
 * because a restaurant that has not thought about covers yet should not have
 * its phone bookings silently rejected.
 */
class SlotController extends Controller
{
    public function index(Request $request): View
    {
        $outlets = Outlet::query()->forBranch()->orderBy('name')->get();

        // Land on a real outlet rather than an empty screen: the one asked for,
        // else the first one this branch has.
        $outletId = $request->integer('outlet') ?: (int) ($outlets->first()->id ?? 0);
        $showDeleted = $request->boolean('deleted');

        $slots = $outletId
            ? PosReservationSlot::query()
                ->when($showDeleted, fn ($q) => $q->withTrashed())
                ->forBranch()
                ->where('outlet_id', $outletId)
                ->get()
            : collect();

        return view('pos.setup.slots', [
            'outlets' => $outlets,
            'outletId' => $outletId,
            'slots' => $slots,
            'showDeleted' => $showDeleted,
            'covers' => (int) $slots->whereNull('deleted_at')->sum('max_booking'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $id = $request->integer('id') ?: null;

        $data = $request->validate([
            'outlet_id' => 'required|integer',
            // Some browsers post HH:MM, others HH:MM:SS.
            'slot_time' => 'required|date_format:H:i,H:i:s',
            'max_booking' => 'nullable|integer|min:0|max:9999',
            'status' => 'nullable|boolean',
        ]);

        $outlet = Outlet::query()->forBranch()->findOrFail($data['outlet_id']);
        $time = date('H:i:s', strtotime($data['slot_time']));
        $branch = Helper::getActiveBranchId();

        /*
         * The unique index counts deleted rows too, so a time that was set up,
         * deleted and is now wanted again has to be revived rather than
         * inserted — otherwise the save dies on a duplicate key for a row
         * nobody can see.
         */
        $existing = PosReservationSlot::query()
            ->withTrashed()
            // Matched the way the unique index sees it: a NULL branch is its own
            // value, and `= NULL` would never find it.
            ->where(fn ($q) => $branch === null ? $q->whereNull('branch_id') : $q->where('branch_id', $branch))
            ->where('outlet_id', $outlet->id)
            ->where('slot_time', $time)
            ->when($id, fn ($q) => $q->whereKeyNot($id))
            ->first();

        if ($existing && ! $existing->trashed()) {
            return back()->with(
                'error',
                $existing->time_label . " is already a slot for {$outlet->name}. Edit that one instead."
            );
        }

        $slot = $id ? PosReservationSlot::query()->forBranch()->findOrFail($id) : null;

        if ($existing) {
            if ($slot) {
                /*
                 * A deleted row is squatting on the time this slot is being
                 * moved to. Nobody can see it and nothing points at it, but it
                 * holds the unique index — so it goes for good, and the slot the
                 * user is actually editing keeps its identity. Reviving it
                 * instead would leave the edited row sitting at its old time.
                 */
                $existing->forceDelete();
            } else {
                $slot = $existing;
                $slot->restore();
            }
        }

        $slot ??= new PosReservationSlot;

        $slot->fill([
            'branch_id' => $slot->exists ? $slot->branch_id : $branch,
            'outlet_id' => $outlet->id,
            'slot_time' => $time,
            'max_booking' => (int) ($data['max_booking'] ?? 0),
            'status' => $request->boolean('status', true) ? 1 : 0,
        ])->save();

        return redirect()
            ->route('point-of-sale.setup.slots', ['outlet' => $outlet->id])
            ->with('status', "Slot {$slot->time_label} has been saved.");
    }

    public function destroy(int $id): RedirectResponse
    {
        $slot = PosReservationSlot::query()->forBranch()->findOrFail($id);
        $slot->delete();

        return back()->with('status', "Slot {$slot->time_label} has been deleted. Show deleted brings it back.");
    }

    public function restore(int $id): RedirectResponse
    {
        $slot = PosReservationSlot::query()->onlyTrashed()->forBranch()->findOrFail($id);
        $slot->restore();

        return back()->with('status', "Slot {$slot->time_label} is back.");
    }
}
