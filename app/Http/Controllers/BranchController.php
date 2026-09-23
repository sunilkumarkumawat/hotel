<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Models\Branch\Branch;
use App\Models\City\City;
use App\Models\Country\Country;
use App\Models\State\State;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BranchController extends Controller
{
    public function index(Request $request): View
    {
        $branches = Branch::query()
            ->withCount('users')
            ->with(['country', 'state', 'city'])
            ->when($request->query('q'), fn ($q, $term) => $q->where(function ($q) use ($term) {
                $q->where('branch_name', 'like', "%{$term}%")
                    ->orWhere('branch_code', 'like', "%{$term}%");
            }))
            ->orderBy('branch_name')
            ->paginate(10)
            ->withQueryString();

        return view('branches.index', [
            'branches' => $branches,
            'term' => $request->query('q'),
            'counts' => [
                'all' => Branch::count(),
                'active' => Branch::where('status', 1)->count(),
                'inactive' => Branch::where('status', 0)->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('branches.create', [
            'branch' => new Branch,
            'countries' => Helper::getCountries(),
            'states' => collect(),
            'cities' => collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['status'] = $request->boolean('status') ? 1 : 0;

        $branch = Branch::create($data);

        return redirect()
            ->route('viewBranch.index')
            ->with('status', "Branch \"{$branch->branch_name}\" has been created.");
    }

    public function edit(Branch $viewBranch): View
    {
        return view('branches.edit', [
            'branch' => $viewBranch,
            'countries' => Helper::getCountries(),
            'states' => State::where('country_id', $viewBranch->country_id)->orderBy('name')->get(),
            'cities' => City::where('state_id', $viewBranch->state_id)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Branch $viewBranch): RedirectResponse
    {
        $data = $this->validated($request, $viewBranch);
        $data['status'] = $request->boolean('status') ? 1 : 0;

        $viewBranch->update($data);

        return redirect()
            ->route('viewBranch.index')
            ->with('status', "Branch \"{$viewBranch->branch_name}\" has been updated.");
    }

    public function destroy(Branch $viewBranch): RedirectResponse
    {
        if (User::where('branch_id', $viewBranch->id)->exists()) {
            return back()->with('error', "\"{$viewBranch->branch_name}\" still has users. Move them first.");
        }

        if (Branch::count() <= 1) {
            return back()->with('error', 'This is the last branch — the system needs at least one.');
        }

        $name = $viewBranch->branch_name;
        $viewBranch->delete();

        return redirect()
            ->route('viewBranch.index')
            ->with('status', "Branch \"{$name}\" has been deleted.");
    }

    public function updateStatus(Branch $branch): RedirectResponse
    {
        $branch->update(['status' => $branch->isActive() ? 0 : 1]);

        return back()->with(
            'status',
            "\"{$branch->branch_name}\" is now " . ($branch->isActive() ? 'active' : 'inactive') . '.'
        );
    }

    /** Switch the branch the signed-in user is working in. */
    public function changeBranch(Request $request): RedirectResponse
    {
        $request->validate(['branch_id' => ['required', Rule::exists('branches', 'id')]]);

        $allowed = Helper::availableBranches()->pluck('id')->all();

        if (! in_array((int) $request->input('branch_id'), $allowed, true)) {
            return back()->with('error', 'You do not have access to that branch.');
        }

        session(['active_branch_id' => (int) $request->input('branch_id')]);
        Helper::flushAccess();

        return back()->with('status', 'Branch switched.');
    }

    /*
    |--------------------------------------------------------------------------
    | Cascading dropdowns
    |--------------------------------------------------------------------------
    */

    public function getState(int $countryId): JsonResponse
    {
        return response()->json(Helper::getStates($countryId));
    }

    public function getCity(int $stateId): JsonResponse
    {
        return response()->json(Helper::getCities($stateId));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Branch $branch = null): array
    {
        return $request->validate([
            'branch_code' => ['required', 'string', 'max:40', Rule::unique('branches', 'branch_code')->ignore($branch)],
            'branch_name' => ['required', 'string', 'max:120'],
            // Printed on the registration card and every bill after it.
            'legal_name' => ['nullable', 'string', 'max:160'],
            'gst_no' => ['nullable', 'string', 'max:20', 'regex:/^[0-9]{2}[A-Za-z]{5}[0-9]{4}[A-Za-z][0-9A-Za-z][Zz][0-9A-Za-z]$/'],
            'sac_code' => ['nullable', 'string', 'max:12'],
            'reg_card_terms' => ['nullable', 'string', 'max:4000'],
            'director_administrator' => ['nullable', 'string', 'max:120'],
            'mobile_number' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'country_id' => ['required', Rule::exists('countries', 'id')],
            'state_id' => ['required', Rule::exists('states', 'id')],
            'city_id' => ['required', Rule::exists('cities', 'id')],
            'pin_code' => ['nullable', 'string', 'max:12'],
            'expert_name' => ['nullable', 'string', 'max:120'],
            'business_type' => ['nullable', 'string', 'max:120'],
        ], [
            'gst_no.regex' => 'A GSTIN looks like 08ABCDE1234F1Z5 — 15 characters.',
        ]);
    }
}
