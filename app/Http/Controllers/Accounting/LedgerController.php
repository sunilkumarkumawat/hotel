<?php

namespace App\Http\Controllers\Accounting;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Accounting\AccountGroup;
use App\Models\Accounting\Ledger;
use App\Support\Ledgers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LedgerController extends Controller
{
    public function index(Request $request): View
    {
        $branchId = (int) Helper::getActiveBranchId();

        $ledgers = Ledger::query()
            ->forBranch($branchId)
            ->with('group')
            ->search($request->string('q')->toString())
            ->when($request->integer('group'), fn ($q, $id) => $q->where('account_group_id', $id))
            ->when($request->string('cash')->toString(), fn ($q, $t) => $q->where('cash_type', $t))
            ->orderBy('name')
            ->get();

        $balances = Ledgers::balancesFor(
            $branchId,
            $ledgers->pluck('id')->map(fn ($id) => (int) $id)->all(),
            today()->toDateString()
        );

        return view('accounting.ledger', [
            'ledgers' => $ledgers,
            'balances' => $balances,
            'groups' => AccountGroup::query()->forBranch($branchId)->active()
                ->orderBy('nature')->orderBy('name')->get(),
            'cashTypes' => Ledger::CASH_TYPES,
            'balanceTypes' => Ledger::BALANCE_TYPES,
            'editing' => $request->integer('edit')
                ? Ledger::query()->forBranch($branchId)->find($request->integer('edit'))
                : null,
            'filters' => [
                'q' => $request->string('q')->toString(),
                'group' => $request->integer('group'),
                'cash' => $request->string('cash')->toString(),
            ],
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();

        $data = $request->validate([
            'id' => 'nullable|integer',
            'name' => 'required|string|max:120',
            'code' => 'nullable|string|max:30',
            'account_group_id' => ['required', 'integer'],
            'opening_balance' => 'nullable|numeric|min:0|max:9999999999',
            'balance_type' => ['required', Rule::in(array_keys(Ledger::BALANCE_TYPES))],
            'cash_type' => ['required', Rule::in(array_keys(Ledger::CASH_TYPES))],
            'gst_no' => 'nullable|string|max:20',
            'bank_account_no' => 'nullable|string|max:40',
            'bank_ifsc' => 'nullable|string|max:20',
            'mobile' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:150',
            'address' => 'nullable|string|max:255',
            'status' => 'nullable|boolean',
        ]);

        $group = AccountGroup::query()->forBranch($branchId)->find($data['account_group_id']);

        if (! $group) {
            return back()->with('error', 'That group is not one of this branch\'s.')->withInput();
        }

        $ledger = ! empty($data['id'])
            ? Ledger::query()->forBranch($branchId)->find($data['id'])
            : new Ledger;

        if (! empty($data['id']) && ! $ledger) {
            return back()->with('error', 'That ledger is not one of this branch\'s.');
        }

        $openingMoved = $ledger->exists
            && $ledger->hasEntries()
            && (
                round((float) $ledger->opening_balance, 2) !== round((float) ($data['opening_balance'] ?? 0), 2)
                || $ledger->balance_type !== $data['balance_type']
            );

        $ledger->fill([
            'branch_id' => $ledger->branch_id ?: $branchId,
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'account_group_id' => $group->id,
            'opening_balance' => round((float) ($data['opening_balance'] ?? 0), 2),
            'balance_type' => $data['balance_type'],
            'cash_type' => $data['cash_type'],
            'gst_no' => $data['gst_no'] ?? null,
            'bank_account_no' => $data['bank_account_no'] ?? null,
            'bank_ifsc' => $data['bank_ifsc'] ?? null,
            'mobile' => $data['mobile'] ?? null,
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'status' => ($data['status'] ?? 1) ? 1 : 0,
        ])->save();

        return redirect()->route('accounting.ledger')
            ->with('status', $ledger->name . ' saved.')
            ->with($openingMoved ? 'warning' : 'ignored', $openingMoved
                ? 'The opening balance changed on a ledger that already has entries — every figure in its '
                    . 'statement, including closed months, has moved with it.'
                : null);
    }

    public function toggle(int $ledger): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();
        $row = Ledger::query()->forBranch($branchId)->findOrFail($ledger);

        if ($row->isSystem() && $row->isActive()) {
            return back()->with('error', $row->name . ' is a standard ledger and stays switched on.');
        }

        $row->update(['status' => $row->isActive() ? 0 : 1]);

        return back()->with('status', sprintf(
            '%s %s.',
            $row->name,
            $row->isActive() ? 'switched back on' : 'switched off — nothing new can be posted to it'
        ));
    }

    public function destroy(int $ledger): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();
        $row = Ledger::query()->forBranch($branchId)->findOrFail($ledger);

        if ($row->isSystem()) {
            return back()->with('error', $row->name . ' is a standard ledger and cannot be removed.');
        }

        if ($row->hasEntries()) {
            return back()->with('error', sprintf(
                '%s has vouchers posted against it, so it is part of the books now. Switch it off instead — '
                . 'it then cannot be picked for anything new, and last year\'s reports still name it.',
                $row->name
            ));
        }

        $name = $row->name;
        $row->delete();

        return back()->with('status', $name . ' removed.');
    }
}
