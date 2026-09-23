<?php

namespace App\Http\Controllers\Pos;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Pos\Outlet;
use App\Models\Pos\PosTable;
use App\Models\Pos\PosTableGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The floor plan: outlet → group → seat.
 *
 * A **group** is a section — Non AC, Terrace, Pool Side. What sits inside it is
 * named by the group's `kind`, because the same screen lays out a restaurant's
 * tables, a resort's villas and a service apartment's flats; only the word on
 * the button changes.
 *
 * A group is never deleted out from under its tables. Emptying it first is one
 * extra step, and it is the step that stops somebody wiping the terrace and
 * losing which orders were sat on it.
 */
class TableController extends Controller
{
    public function index(Request $request): View
    {
        $outletId = $request->integer('outlet') ?: null;
        $showDeleted = $request->boolean('deleted');

        $outlets = Outlet::query()
            ->forBranch()
            ->when($outletId, fn ($q) => $q->where('id', $outletId))
            ->orderBy('name')
            ->get();

        $groups = PosTableGroup::query()
            ->when($showDeleted, fn ($q) => $q->withTrashed())
            ->forBranch()
            ->whereIn('outlet_id', $outlets->pluck('id'))
            ->with(['tables' => fn ($q) => $showDeleted ? $q->withTrashed() : $q])
            ->orderBy('sort')
            ->orderBy('name')
            ->get()
            ->groupBy('outlet_id');

        return view('pos.setup.tables', [
            'outlets' => $outlets,
            'allOutlets' => Outlet::query()->forBranch()->orderBy('name')->pluck('name', 'id'),
            'groups' => $groups,
            'outletId' => $outletId,
            'showDeleted' => $showDeleted,
            'counts' => [
                'groups' => PosTableGroup::query()->forBranch()->count(),
                'tables' => PosTable::query()->forBranch()->count(),
                'seats' => (int) PosTable::query()->forBranch()->sum('capacity'),
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Groups
    |--------------------------------------------------------------------------
    */

    public function storeGroup(Request $request): RedirectResponse
    {
        $id = $request->integer('id') ?: null;
        $branch = Helper::getActiveBranchId();

        $data = $request->validate([
            'outlet_id' => ['required', Rule::exists('outlets', 'id')->whereNull('deleted_at')],
            'name' => [
                'required', 'string', 'max:255',
                // One "Non AC" per outlet. Two would be indistinguishable on
                // the POS screen, where a group is picked by its name alone.
                Rule::unique('pos_table_groups', 'name')
                    ->where(fn ($q) => $q->where('outlet_id', $request->integer('outlet_id'))->whereNull('deleted_at'))
                    ->ignore($id),
            ],
            'kind' => ['required', Rule::in(array_keys(PosTableGroup::KINDS))],
            'status' => 'nullable|boolean',
        ]);

        $this->assertOutletIsOurs((int) $data['outlet_id']);

        $group = $id
            ? PosTableGroup::query()->forBranch()->findOrFail($id)
            : new PosTableGroup(['branch_id' => $branch]);

        $group->fill([
            'outlet_id' => $data['outlet_id'],
            'name' => $data['name'],
            'kind' => $data['kind'],
            'status' => $request->boolean('status', true) ? 1 : 0,
        ]);

        $group->save();

        // Moving a group to another outlet takes its tables with it, or they
        // would be seats in a room that no longer exists. Written straight off
        // the model so no ORDER BY rides along into the UPDATE — MySQL allows
        // that, SQLite refuses it.
        PosTable::query()
            ->withTrashed()
            ->where('pos_table_group_id', $group->id)
            ->update(['outlet_id' => $group->outlet_id]);

        return back()->with('status', "Group \"{$group->name}\" has been saved.");
    }

    public function destroyGroup(int $id): RedirectResponse
    {
        $group = PosTableGroup::query()->forBranch()->findOrFail($id);

        $left = $group->tables()->count();

        if ($left > 0) {
            return back()->with(
                'error',
                "\"{$group->name}\" still has {$left} " . strtolower($group->kindLabel())
                    . ($left === 1 ? '' : 's') . ' in it. Delete those first.'
            );
        }

        $group->delete();

        return back()->with('status', "Group \"{$group->name}\" has been deleted. Show deleted brings it back.");
    }

    public function restoreGroup(int $id): RedirectResponse
    {
        $group = PosTableGroup::query()->onlyTrashed()->forBranch()->findOrFail($id);
        $group->restore();

        return back()->with('status', "Group \"{$group->name}\" is back.");
    }

    /*
    |--------------------------------------------------------------------------
    | Tables
    |--------------------------------------------------------------------------
    */

    public function storeTable(Request $request): RedirectResponse
    {
        $id = $request->integer('id') ?: null;

        $data = $request->validate([
            'pos_table_group_id' => 'required|integer',
            'name' => 'required|string|max:60',
            'capacity' => 'nullable|integer|min:0|max:999',
            'status' => 'nullable|boolean',
        ]);

        $group = PosTableGroup::query()->forBranch()->findOrFail($data['pos_table_group_id']);

        // Table "7" twice in one section is a waiter's nightmare and a
        // duplicated bill waiting to happen.
        $clash = PosTable::query()
            ->forBranch()
            ->where('pos_table_group_id', $group->id)
            ->where('name', $data['name'])
            ->when($id, fn ($q) => $q->whereKeyNot($id))
            ->exists();

        if ($clash) {
            return back()->with('error', "\"{$data['name']}\" already exists in {$group->name}.");
        }

        $table = $id
            ? PosTable::query()->forBranch()->findOrFail($id)
            : new PosTable(['branch_id' => Helper::getActiveBranchId()]);

        $table->fill([
            'outlet_id' => $group->outlet_id,
            'pos_table_group_id' => $group->id,
            'name' => $data['name'],
            'capacity' => (int) ($data['capacity'] ?? 0),
            'status' => $request->boolean('status', true) ? 1 : 0,
        ])->save();

        return back()->with('status', "{$group->kindLabel()} \"{$table->name}\" has been saved.");
    }

    public function destroyTable(int $id): RedirectResponse
    {
        $table = PosTable::query()->forBranch()->findOrFail($id);
        $table->delete();

        return back()->with('status', "\"{$table->name}\" has been deleted. Show deleted brings it back.");
    }

    public function restoreTable(int $id): RedirectResponse
    {
        $table = PosTable::query()->onlyTrashed()->forBranch()->findOrFail($id);

        // A seat cannot come back into a section that is still deleted.
        $group = PosTableGroup::query()->withTrashed()->forBranch()->find($table->pos_table_group_id);

        if (! $group) {
            return back()->with('error', "\"{$table->name}\" belongs to a group that is no longer there.");
        }

        if ($group->trashed()) {
            return back()->with('error', "Restore the group \"{$group->name}\" first.");
        }

        $table->restore();

        return back()->with('status', "\"{$table->name}\" is back.");
    }

    /** A posted outlet id has to belong to the branch the user is looking at. */
    private function assertOutletIsOurs(int $outletId): void
    {
        Outlet::query()->forBranch()->findOrFail($outletId);
    }
}
