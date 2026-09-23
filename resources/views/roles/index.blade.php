@extends('layouts.app')

@section('title', 'Roles')

@section('content')
    <x-page-header title="Roles" subtitle="A label for a job. Permissions are set per user, on the Users screen."
                   :crumbs="['Home' => url('/'), 'Roles']">
        <x-slot:actions>
            @canAdd('role')
                <a href="{{ route('role.create') }}" class="nv-btn nv-btn-primary"><x-icon name="plus" /> New role</a>
            @endCanAdd
        </x-slot:actions>
    </x-page-header>

    <x-card flush>
        <form method="GET" class="nv-toolbar">
            <div class="nv-field-search">
                <x-icon name="search" />
                <input type="search" name="q" value="{{ $term }}" class="nv-input" placeholder="Search roles…" />
            </div>
            <button type="submit" class="nv-btn nv-btn-outline"><x-icon name="filter" /> Filter</button>
            @if ($term)
                <a href="{{ route('role.index') }}" class="nv-btn nv-btn-ghost">Reset</a>
            @endif
        </form>

        @if ($roles->count())
            <div class="nv-table-wrap">
                <table class="nv-table">
                    <thead>
                        <tr>
                            <th>Role</th>
                            <th class="is-num">Users</th>
                            <th class="is-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($roles as $role)
                            <tr>
                                <td>
                                    <div class="nv-matrix-module">
                                        <span class="nv-matrix-icon">
                                            <x-icon :name="$role->isAdmin() ? 'shield' : 'user'" />
                                        </span>
                                        <div>
                                            <strong>
                                                {{ $role->name }}
                                                @if ($role->isAdmin())
                                                    <x-badge tone="primary" plain style="margin-left:6px">System</x-badge>
                                                @endif
                                                @if (auth()->user()->role_id === $role->id)
                                                    <x-badge tone="success" plain style="margin-left:4px">You</x-badge>
                                                @endif
                                            </strong>
                                            <span>{{ $role->description ?: 'No description' }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="is-num">{{ $role->users_count }}</td>
                                <td class="is-end">
                                    <div class="nv-row-actions">
                                        @unless ($role->isAdmin())
                                            @canEdit('role')
                                                <a href="{{ route('role.edit', $role) }}" class="nv-btn nv-btn-ghost nv-btn-sm"
                                                   aria-label="Edit {{ $role->name }}">
                                                    <x-icon name="pencil" />
                                                </a>
                                            @endCanEdit

                                            @canDelete('role')
                                            <form method="POST" action="{{ route('role.destroy', $role) }}"
                                                  data-confirm="Users assigned to {{ $role->name }} keep their own permissions, but lose this label."
                                                  data-confirm-title="Delete role?"
                                                  data-confirm-action="Delete role">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="nv-btn nv-btn-ghost nv-btn-sm"
                                                        aria-label="Delete {{ $role->name }}">
                                                    <x-icon name="trash" />
                                                </button>
                                            </form>
                                            @endCanDelete
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="nv-empty">
                <span class="nv-empty-icon"><x-icon name="shield" /></span>
                <strong>No roles found</strong>
                <p>Create one, or run <span class="nv-kbd-inline">php artisan migrate --seed</span> for the defaults.</p>
            </div>
        @endif
    </x-card>
@endsection
