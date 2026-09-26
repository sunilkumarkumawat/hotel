<?php

namespace App\Http\Controllers\Accounting;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Accounting\AccountGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GroupController extends Controller
{
    public function index(Request $request): View
    {
        $branchId = (int) Helper::getActiveBranchId();

        $groups = AccountGroup::query()
            ->forBranch($branchId)
            ->with('parent')
            ->withCount('ledgers')
            ->search($request->string('q')->toString())
            ->when($request->string('nature')->toString(), fn ($q, $n) => $q->where('nature', $n))
            ->orderBy('nature')
            ->orderBy('name')
            ->get();

        return view('accounting.group', [
            'groups' => $groups,
            'natures' => AccountGroup::NATURES,
            'parents' => AccountGroup::query()->forBranch($branchId)->active()
                ->orderBy('name')->pluck('name', 'id'),
            'editing' => $request->integer('edit')
                ? AccountGroup::query()->forBranch($branchId)->find($request->integer('edit'))
                : null,
            'filters' => [
                'q' => $request->string('q')->toString(),
                'nature' => $request->string('nature')->toString(),
            ],
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();

        $data = $request->validate([
            'id' => 'nullable|integer',
            'name' => 'required|string|max:100',
            'nature' => ['required', Rule::in(array_keys(AccountGroup::NATURES))],
            'parent_id' => 'nullable|integer',
            'status' => 'nullable|boolean',
        ]);

        $group = ! empty($data['id'])
            ? AccountGroup::query()->forBranch($branchId)->find($data['id'])
            : new AccountGroup;

        if (! empty($data['id']) && ! $group) {
            return back()->with('error', 'That group is not one of this branch\'s.');
        }

        if ($group->exists && $group->isSystem() && $group->nature !== $data['nature']) {
            return back()->with('error', sprintf(
                '%s is a standard group and its nature cannot be changed — every report in the module '
                . 'reads it as %s. Make a new group instead.',
                $group->name,
                $group->nature_label
            ));
        }

        $parentId = $data['parent_id'] ?: null;

        if ($parentId && $group->exists) {
            if ((int) $parentId === (int) $group->id) {
                return back()->with('error', 'A group cannot sit inside itself.');
            }

            if (in_array((int) $parentId, AccountGroup::subtreeIds($branchId, (int) $group->id), true)) {
                return back()->with('error', sprintf(
                    '%s already sits under %s, so it cannot also be its parent.',
                    AccountGroup::find($parentId)?->name ?? 'That group',
                    $group->name
                ));
            }
        }

        $group->fill([
            'branch_id' => $group->branch_id ?: $branchId,
            'name' => $data['name'],
            'nature' => $data['nature'],
            'parent_id' => $parentId,
            'status' => ($data['status'] ?? 1) ? 1 : 0,
        ])->save();

        return redirect()->route('accounting.group')->with('status', $group->name . ' saved.');
    }

    public function toggle(int $group): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();
        $row = AccountGroup::query()->forBranch($branchId)->findOrFail($group);

        if ($row->isSystem()) {
            return back()->with('error', $row->name . ' is a standard group and stays switched on.');
        }

        $row->update(['status' => $row->isActive() ? 0 : 1]);

        return back()->with('status', sprintf(
            '%s %s.',
            $row->name,
            $row->isActive() ? 'switched back on' : 'switched off — no new ledger can be filed under it'
        ));
    }
}
