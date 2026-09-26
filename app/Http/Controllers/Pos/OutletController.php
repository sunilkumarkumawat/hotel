<?php

namespace App\Http\Controllers\Pos;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Pos\Outlet;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;


class OutletController extends Controller
{
    private const LOGO_DIR = 'outlets';

    public function index(Request $request): View
    {
        $filters = [
            'name' => trim($request->string('name')->toString()),
            'address' => trim($request->string('address')->toString()),
            'phone' => trim($request->string('phone')->toString()),
            'status' => $request->string('status')->toString(),
        ];

        $rows = Outlet::query()
            ->withTrashed()
            ->forBranch()
            ->when($filters['name'], fn ($q, $term) => $q->where('name', 'like', "%{$term}%"))
            ->when($filters['address'], function ($q, $term) {
                $q->where(function ($inner) use ($term) {
                    foreach (['address1', 'address2', 'address3'] as $column) {
                        $inner->orWhere($column, 'like', "%{$term}%");
                    }
                });
            })
            ->when($filters['phone'], function ($q, $term) {
                $q->where(function ($inner) use ($term) {
                    $inner->where('phone1', 'like', "%{$term}%")
                        ->orWhere('phone2', 'like', "%{$term}%");
                });
            })
            ->when($filters['status'] === 'active', fn ($q) => $q->whereNull('deleted_at')->where('status', 1))
            ->when($filters['status'] === 'inactive', fn ($q) => $q->whereNull('deleted_at')->where('status', 0))
            ->when($filters['status'] === 'deleted', fn ($q) => $q->whereNotNull('deleted_at'))
            ->orderByRaw('CASE WHEN deleted_at IS NULL THEN 0 ELSE 1 END')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $all = Outlet::query()->withTrashed()->forBranch();

        return view('pos.setup.outlets', [
            'rows' => $rows,
            'filters' => $filters,
            'counts' => [
                'all' => (clone $all)->count(),
                'active' => (clone $all)->whereNull('deleted_at')->where('status', 1)->count(),
                'inactive' => (clone $all)->whereNull('deleted_at')->where('status', 0)->count(),
                'deleted' => (clone $all)->whereNotNull('deleted_at')->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('pos.setup.outlet-form', [
            'outlet' => new Outlet([
                'kind' => 'restaurant',
                'status' => 1,
                'page_width' => 80,
                'print_margin' => 6,
                'header_font' => 'Arial',
                'header_font_size' => 0,
                'header_font_bold' => 1,
                'guest_signature_print' => 1,
                'bill_start_no' => 1,
                'pos_dine_in' => 1,
            ]),
            'users' => $this->users(),
            'picked' => [],
        ]);
    }

    public function edit(int $id): View
    {
        $outlet = Outlet::query()->withTrashed()->forBranch()->findOrFail($id);

        return view('pos.setup.outlet-form', [
            'outlet' => $outlet,
            'users' => $this->users(),
            'picked' => $outlet->users->pluck('user_id')->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $outlet = new Outlet($data);
        $outlet->branch_id = Helper::getActiveBranchId();
        $outlet->logo_path = $this->storeLogo($request, null);
        $outlet->save();

        $outlet->users()->sync($request->input('users', []));

        return redirect()
            ->route('point-of-sale.setup.outlets')
            ->with('status', "Outlet \"{$outlet->name}\" has been added.");
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $outlet = Outlet::query()->withTrashed()->forBranch()->findOrFail($id);

        $data = $this->validated($request, $outlet->id);

        $outlet->fill($data);
        $outlet->logo_path = $this->storeLogo($request, $outlet);
        $outlet->save();

        $outlet->users()->sync($request->input('users', []));

        return redirect()
            ->route('point-of-sale.setup.outlets')
            ->with('status', "Outlet \"{$outlet->name}\" has been saved.");
    }

    public function destroy(int $id): RedirectResponse
    {
        $outlet = Outlet::query()->forBranch()->findOrFail($id);
        $outlet->delete();

        return back()->with(
            'status',
            "Outlet \"{$outlet->name}\" has been deleted. Its past bills still name it, and Restore brings it back."
        );
    }

    public function restore(int $id): RedirectResponse
    {
        $outlet = Outlet::query()->onlyTrashed()->forBranch()->findOrFail($id);
        $outlet->restore();

        return back()->with('status', "Outlet \"{$outlet->name}\" is back.");
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?int $id = null): array
    {
        $branch = Helper::getActiveBranchId();

        $rules = [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('outlets', 'name')
                    ->where(fn ($query) => $branch === null
                        ? $query->whereNull('branch_id')->whereNull('deleted_at')
                        : $query->where('branch_id', $branch)->whereNull('deleted_at'))
                    ->ignore($id),
            ],
            'status' => 'required|boolean',

            'address1' => 'nullable|string|max:255',
            'address2' => 'nullable|string|max:255',
            'address3' => 'nullable|string|max:255',
            'phone1' => 'nullable|string|max:30',
            'phone2' => 'nullable|string|max:30',
            'website' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'gst_no' => 'nullable|string|max:20',
            'cin_no' => 'nullable|string|max:30',
            'pan_no' => 'nullable|string|max:20',
            'sac_code' => 'nullable|string|max:20',

            'start_time' => 'nullable|date_format:H:i,H:i:s',
            'end_time' => 'nullable|date_format:H:i,H:i:s',

            'bill_series' => 'nullable|string|max:20',
            'bill_start_no' => 'nullable|integer|min:1|max:99999999',
            'liquor_bill_series' => 'nullable|string|max:20|required_if:diff_liquor_series,1',

            'page_width' => ['required', Rule::in(array_keys(Outlet::PAGE_WIDTHS))],
            'print_margin' => ['required', Rule::in(array_keys(Outlet::PRINT_MARGINS))],
            'print_header' => 'nullable|string|max:255',
            'tax_invoice_name' => 'nullable|string|max:255',
            'header_font' => ['nullable', Rule::in(array_keys(Outlet::HEADER_FONTS))],
            'header_font_size' => 'nullable|integer|min:0|max:72',
            'header_font_bold' => 'nullable|boolean',
            'print_footer' => 'nullable|string|max:255',
            'guest_signature_print' => 'nullable|boolean',

            'logo' => 'nullable|image|mimes:png,jpg,jpeg|max:1024',
            'remove_logo' => 'nullable|boolean',

            'users' => 'nullable|array',
            'users.*' => 'integer|exists:users,user_id',
        ];

        foreach (array_keys(Outlet::FLAGS) as $flag) {
            $rules[$flag] = 'nullable|boolean';
        }

        $data = $request->validate($rules, [
            'liquor_bill_series.required_if' => 'A separate liquor series needs a series to use.',
            'logo.max' => 'The logo must be 1 MB or smaller.',
        ]);

        foreach (array_keys(Outlet::FLAGS) as $flag) {
            $data[$flag] = $request->boolean($flag);
        }

        $data['header_font_bold'] = $request->boolean('header_font_bold');
        $data['guest_signature_print'] = $request->boolean('guest_signature_print');
        $data['bill_start_no'] = (int) ($data['bill_start_no'] ?? 1) ?: 1;
        $data['header_font_size'] = (int) ($data['header_font_size'] ?? 0);

        if (! $data['diff_liquor_series']) {
            $data['liquor_bill_series'] = null;
        }

        unset($data['logo'], $data['remove_logo'], $data['users']);

        return $data;
    }

    private function storeLogo(Request $request, ?Outlet $outlet): ?string
    {
        $current = $outlet?->logo_path;

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store(self::LOGO_DIR, 'public');

            if ($path && $current && $current !== $path) {
                Storage::disk('public')->delete($current);
            }

            return $path ?: $current;
        }

        if ($request->boolean('remove_logo') && $current) {
            Storage::disk('public')->delete($current);

            return null;
        }

        return $current;
    }

    private function users()
    {
        return User::query()
            ->where('status', 1)
            ->orderBy('name')
            ->get(['user_id', 'name', 'username']);
    }
}
