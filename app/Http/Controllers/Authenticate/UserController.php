<?php

namespace App\Http\Controllers\Authenticate;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Branch\Branch;
use App\Models\Common\SubModule;
use App\Models\Role\Role;
use App\Models\User;
use App\Models\UserPermission\UserPermission;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()
            ->with(['role', 'branch'])
            ->search($request->query('q'))
            ->when($request->query('role'), fn ($q, $role) => $q->where('role_id', $role))
            ->when($request->query('branch'), fn ($q, $branch) => $q->where('branch_id', $branch))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->orderByDesc('user_id')
            ->paginate(10)
            ->withQueryString();

        return view('users.index', [
            'users' => $users,
            'roles' => Role::orderBy('name')->get(),
            'branches' => Branch::orderBy('branch_name')->get(),
            'filters' => $request->only('q', 'role', 'branch', 'status'),
            'counts' => [
                'all' => User::count(),
                'active' => User::where('status', 1)->count(),
                'inactive' => User::where('status', 0)->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('users.create', $this->formData(new User));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['status'] = $request->boolean('status') ? 1 : 0;

        $user = DB::transaction(function () use ($request, $data) {
            $user = User::create($data);
            $this->syncPermissions($user, $request);

            return $user;
        });

        return redirect()
            ->route('users.index')
            ->with('status', "User \"{$user->name}\" has been created.");
    }

    public function edit(User $user): View
    {
        return view('users.edit', $this->formData($user));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);
        $data['status'] = $request->boolean('status') ? 1 : 0;

        if ($request->user()->user_id === $user->user_id && (int) $data['role_id'] !== (int) $user->role_id) {
            return back()
                ->withInput()
                ->with('error', 'You cannot change the role on the account you are signed in with.');
        }

        if (! $request->filled('password')) {
            unset($data['password']);
        }

        DB::transaction(function () use ($request, $user, $data) {
            $user->update($data);
            $this->syncPermissions($user, $request);
        });

        Helper::flushAccess();

        return redirect()
            ->route('users.index')
            ->with('status', "User \"{$user->name}\" has been updated.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->user_id === $user->user_id) {
            return back()->with('error', 'You cannot delete the account you are signed in with.');
        }

        if ($user->isAdmin() && User::where('role_id', 1)->count() <= 1) {
            return back()->with('error', 'This is the last administrator — create another one first.');
        }

        $name = $user->name;

        DB::transaction(function () use ($user) {
            UserPermission::where('user_id', $user->user_id)->delete();
            $user->delete();
        });

        return redirect()
            ->route('users.index')
            ->with('status', "User \"{$name}\" has been deleted.");
    }

    public function changeStatus(Request $request): RedirectResponse
    {
        $request->validate(['user_id' => ['required', Rule::exists('users', 'user_id')]]);

        $user = User::where('user_id', $request->input('user_id'))->firstOrFail();

        if ($request->user()->user_id === $user->user_id) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        $user->update(['status' => $user->isActive() ? 0 : 1]);

        return back()->with(
            'status',
            "\"{$user->name}\" is now " . ($user->isActive() ? 'active' : 'inactive') . '.'
        );
    }

    public function userProfile(Request $request): View
    {
        return view('users.profile', ['user' => $request->user()->load(['role', 'branch'])]);
    }

    public function userProfileUpdate(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'mobile' => ['required', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'pincode' => ['nullable', 'string', 'max:12'],
            'dob' => ['nullable', 'date'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'transgender', 'group'])],
        ]);

        $user->update($data);

        return back()->with('status', 'Your profile has been updated.');
    }

    /** @return array<string, mixed> */
    private function formData(User $user): array
    {
        $permission = $user->exists
            ? UserPermission::where('user_id', $user->user_id)
                ->where('branch_id', $user->branch_id)
                ->first()
            : null;

        return [
            'user' => $user,
            'roles' => Role::orderBy('name')->get(),
            'branches' => Branch::orderBy('branch_name')->get(),
            'menus' => Helper::allMenus(),
            'granted' => $permission?->permissions ?? [],
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => [
                'required', 'string', 'max:60', 'regex:/^[A-Za-z0-9._@-]+$/',
                Rule::unique('users', 'username')->ignore($user?->user_id, 'user_id'),
            ],
            'mobile' => ['required', 'string', 'max:20'],

            'email' => ['nullable', 'email', 'max:150'],
            'whatsapp_no' => ['nullable', 'string', 'max:20'],
            'notify_web' => ['nullable', 'boolean'],
            'notify_mail' => ['nullable', 'boolean'],
            'role_id' => ['required', Rule::exists('role', 'id')],
            'branch_id' => ['required', Rule::exists('branches', 'id')],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(4)],
            'gender' => ['nullable', Rule::in(['male', 'female', 'transgender', 'group'])],
            'dob' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:255'],
        ], [
            'username.regex' => 'The username may only contain letters, numbers and . _ @ -',
        ]);
    }

    private function syncPermissions(User $user, Request $request): void
    {
        /** @var array<string, array<string, string>> $matrix */
        $matrix = $request->input('permissions', []);

        $submoduleIds = [];
        $permissions = [];

        $valid = SubModule::pluck('module_id', 'id');

        foreach ($matrix as $submoduleId => $actions) {
            if (! $valid->has((int) $submoduleId)) {
                continue;
            }

            $row = [
                'view' => isset($actions['view']) ? 1 : 0,
                'add' => isset($actions['add']) ? 1 : 0,
                'edit' => isset($actions['edit']) ? 1 : 0,
                'delete' => isset($actions['delete']) ? 1 : 0,
            ];

            if (array_sum($row) === 0) {
                continue;
            }

            $row['view'] = 1;

            $submoduleIds[] = (int) $submoduleId;
            $permissions[(string) $submoduleId] = $row;
        }

        $moduleIds = $valid->only($submoduleIds)->unique()->values()->all();

        UserPermission::updateOrCreate(
            ['user_id' => $user->user_id, 'branch_id' => $user->branch_id],
            [
                'module_id' => implode(',', $moduleIds),
                'submodule_id' => implode(',', $submoduleIds),
                'permissions' => $permissions,
            ]
        );
    }
}
