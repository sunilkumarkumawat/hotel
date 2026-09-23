<?php

namespace App\Http\Controllers\Authenticate;

use App\Http\Controllers\Controller;
use App\Models\Role\Role;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index(Request $request): View
    {
        $roles = Role::query()
            ->withCount('users')
            ->when($request->query('q'), fn ($q, $term) => $q->where('name', 'like', "%{$term}%"))
            ->orderBy('id')
            ->get();

        return view('roles.index', [
            'roles' => $roles,
            'term' => $request->query('q'),
        ]);
    }

    public function create(): View
    {
        return view('roles.create', ['role' => new Role]);
    }

    public function store(Request $request): RedirectResponse
    {
        $role = Role::create($this->validated($request));

        return redirect()
            ->route('role.index')
            ->with('status', "Role \"{$role->name}\" has been created.");
    }

    public function edit(Role $role): View
    {
        return view('roles.edit', ['role' => $role]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        if ($role->isAdmin()) {
            return back()->with('error', 'The Administrator role cannot be renamed.');
        }

        $role->update($this->validated($request, $role));

        return redirect()
            ->route('role.index')
            ->with('status', "Role \"{$role->name}\" has been updated.");
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        if ($role->isAdmin()) {
            return back()->with('error', 'The Administrator role cannot be deleted.');
        }

        if ($request->user()->role_id === $role->id) {
            return back()->with('error', 'You cannot delete the role you are signed in with.');
        }

        if (User::where('role_id', $role->id)->exists()) {
            return back()->with('error', "\"{$role->name}\" still has users assigned. Move them first.");
        }

        $name = $role->name;
        $role->delete();

        return redirect()
            ->route('role.index')
            ->with('status', "Role \"{$name}\" has been deleted.");
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('role', 'name')->ignore($role)],
            'description' => ['nullable', 'string', 'max:200'],
        ]);
    }
}
