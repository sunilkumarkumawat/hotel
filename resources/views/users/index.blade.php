@extends('layouts.app')

@section('title', 'Users')

@section('content')
    <x-page-header
        title="Users"
        subtitle="Everyone who can sign in, and what each of them may reach."
        :crumbs="['Home' => url('/'), 'Users']"
    >
        <x-slot:actions>
            @canAdd('users')
                <a href="{{ route('users.create') }}" class="nv-btn nv-btn-primary">
                    <x-icon name="plus" /> Add user
                </a>
            @endCanAdd
        </x-slot:actions>
    </x-page-header>

    <div class="nv-grid nv-grid-3">
        <x-stat label="All users" :value="$counts['all']" icon="users" />
        <x-stat label="Active" :value="$counts['active']" icon="check-circle" tone="success" />
        <x-stat label="Inactive" :value="$counts['inactive']" icon="x-circle" tone="danger" />
    </div>

    <div class="nv-mt">
        <x-card flush>
            <form method="GET" class="nv-toolbar">
                <div class="nv-field-search">
                    <x-icon name="search" />
                    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="nv-input"
                           placeholder="Search name, username or mobile…" />
                </div>

                <select name="role" class="nv-select" style="width:160px" onchange="this.form.submit()">
                    <option value="">All roles</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->id }}" @selected(($filters['role'] ?? null) == $role->id)>{{ $role->name }}</option>
                    @endforeach
                </select>

                <select name="branch" class="nv-select" style="width:180px" onchange="this.form.submit()">
                    <option value="">All branches</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected(($filters['branch'] ?? null) == $branch->id)>{{ $branch->branch_name }}</option>
                    @endforeach
                </select>

                <button type="submit" class="nv-btn nv-btn-outline"><x-icon name="filter" /> Filter</button>

                @if (array_filter($filters))
                    <a href="{{ route('users.index') }}" class="nv-btn nv-btn-ghost">Reset</a>
                @endif
            </form>

            @if ($users->count())
                <div class="nv-table-wrap">
                    <table class="nv-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th>Branch</th>
                                <th>Mobile</th>
                                <th>Status</th>
                                <th class="is-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($users as $user)
                                <tr>
                                    <td>
                                        <div class="nv-user-cell">
                                            <x-avatar :name="$user->name ?: $user->username" />
                                            <div>
                                                <strong>{{ $user->name ?: $user->username }}</strong>
                                                <span>{{ '@' . $user->username }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <x-badge :tone="$user->isAdmin() ? 'primary' : null" plain>
                                            {{ $user->role_name }}
                                        </x-badge>
                                    </td>
                                    <td class="nv-muted nv-nowrap">{{ $user->branch?->branch_name ?? '—' }}</td>
                                    <td class="nv-mono">{{ $user->mobile }}</td>
                                    <td>
                                        @php $isMe = auth()->id() === $user->user_id; @endphp

                                        @if (! $isMe) @canEdit('users')
                                            <form method="POST" action="{{ route('user-status') }}">
                                                @csrf
                                                <input type="hidden" name="user_id" value="{{ $user->user_id }}" />
                                                <button type="submit" style="background:none;border:0;padding:0;cursor:pointer"
                                                        title="Click to toggle">
                                                    <x-badge :tone="$user->isActive() ? 'success' : 'danger'">
                                                        {{ $user->isActive() ? 'Active' : 'Inactive' }}
                                                    </x-badge>
                                                </button>
                                            </form>
                                        @endCanEdit @endif

                                        @if ($isMe || ! can_do('users', 'edit'))
                                            <x-badge :tone="$user->isActive() ? 'success' : 'danger'">
                                                {{ $user->isActive() ? 'Active' : 'Inactive' }}
                                            </x-badge>
                                        @endif
                                    </td>
                                    <td class="is-end">
                                        <div class="nv-row-actions">
                                            @canEdit('users')
                                                <a href="{{ route('users.edit', $user) }}" class="nv-btn nv-btn-ghost nv-btn-sm"
                                                   aria-label="Edit {{ $user->name }}">
                                                    <x-icon name="pencil" />
                                                </a>
                                            @endCanEdit

                                            @if (! $isMe) @canDelete('users')
                                            <form method="POST" action="{{ route('users.destroy', $user) }}"
                                                  data-confirm="{{ $user->name ?: $user->username }} will lose access and the account will be removed."
                                                  data-confirm-title="Delete user?"
                                                  data-confirm-action="Delete user">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="nv-btn nv-btn-ghost nv-btn-sm"
                                                        aria-label="Delete {{ $user->name }}">
                                                    <x-icon name="trash" />
                                                </button>
                                            </form>
                                            @endCanDelete @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="users" /></span>
                    <strong>No users found</strong>
                    <p>Nothing matches these filters. Try a different search, or add someone new.</p>
                    @canAdd('users')
                        <a href="{{ route('users.create') }}" class="nv-btn nv-btn-primary"><x-icon name="plus" /> Add user</a>
                    @endCanAdd
                </div>
            @endif

            <x-slot:footer>
                {{ $users->links() }}
            </x-slot:footer>
        </x-card>
    </div>
@endsection
