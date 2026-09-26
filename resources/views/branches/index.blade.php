@extends('layouts.app')

@section('title', 'Branches')

@section('content')
    <x-page-header title="Branches" subtitle="Every location this system runs for."
                   :crumbs="['Home' => url('/'), 'Branches']">
        <x-slot:actions>
            @canAdd('viewBranch')
                <a href="{{ route('viewBranch.create') }}" class="nv-btn nv-btn-primary"><x-icon name="plus" /> Add branch</a>
            @endCanAdd
        </x-slot:actions>
    </x-page-header>

    <div class="nv-grid nv-grid-3">
        <x-stat label="All branches" :value="$counts['all']" icon="globe" />
        <x-stat label="Active" :value="$counts['active']" icon="check-circle" tone="success" />
        <x-stat label="Inactive" :value="$counts['inactive']" icon="x-circle" tone="danger" />
    </div>

    <div class="nv-mt">
        <x-card flush>
            <form method="GET" class="nv-toolbar">
                <div class="nv-field-search">
                    <x-icon name="search" />
                    <input type="search" name="q" value="{{ $term }}" class="nv-input" placeholder="Search name or code…" />
                </div>
                <button type="submit" class="nv-btn nv-btn-outline"><x-icon name="filter" /> Filter</button>
                @if ($term)
                    <a href="{{ route('viewBranch.index') }}" class="nv-btn nv-btn-ghost">Reset</a>
                @endif
            </form>

            @if ($branches->count())
                <div class="nv-table-wrap">
                    <table class="nv-table">
                        <thead>
                            <tr>
                                <th>Branch</th>
                                <th>Location</th>
                                <th>Contact</th>
                                <th class="is-num">Users</th>
                                <th>Status</th>
                                <th class="is-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($branches as $branch)
                                <tr>
                                    <td>
                                        <div class="nv-matrix-module">
                                            <span class="nv-matrix-icon"><x-icon name="globe" /></span>
                                            <div>
                                                <strong>{{ $branch->branch_name }}</strong>
                                                <span class="nv-mono">{{ $branch->branch_code }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="nv-muted">
                                        {{ collect([$branch->city?->name, $branch->state?->name, $branch->country?->name])->filter()->implode(', ') ?: '—' }}
                                    </td>
                                    <td class="nv-muted nv-nowrap">{{ $branch->mobile_number ?: '—' }}</td>
                                    <td class="is-num">{{ $branch->users_count }}</td>
                                    <td>
                                        @canEdit('viewBranch')
                                            <form method="POST" action="{{ route('branch-status', $branch) }}">
                                                @csrf
                                                <button type="submit" style="background:none;border:0;padding:0;cursor:pointer"
                                                        title="Click to toggle">
                                                    <x-badge :tone="$branch->isActive() ? 'success' : 'danger'">
                                                        {{ $branch->isActive() ? 'Active' : 'Inactive' }}
                                                    </x-badge>
                                                </button>
                                            </form>
                                        @else
                                            <x-badge :tone="$branch->isActive() ? 'success' : 'danger'">
                                                {{ $branch->isActive() ? 'Active' : 'Inactive' }}
                                            </x-badge>
                                        @endCanEdit
                                    </td>
                                    <td class="is-end">
                                        <div class="nv-row-actions">
                                            @canEdit('viewBranch')
                                                <a href="{{ route('viewBranch.edit', $branch) }}" class="nv-btn nv-btn-ghost nv-btn-sm"
                                                   aria-label="Edit {{ $branch->branch_name }}">
                                                    <x-icon name="pencil" />
                                                </a>
                                            @endCanEdit

                                            @canDelete('viewBranch')
                                            <form method="POST" action="{{ route('viewBranch.destroy', $branch) }}"
                                                  data-confirm="{{ $branch->branch_name }} will be removed. Users must be moved first."
                                                  data-confirm-title="Delete branch?"
                                                  data-confirm-action="Delete branch">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="nv-btn nv-btn-ghost nv-btn-sm"
                                                        aria-label="Delete {{ $branch->branch_name }}">
                                                    <x-icon name="trash" />
                                                </button>
                                            </form>
                                            @endCanDelete
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="globe" /></span>
                    <strong>No branches found</strong>
                    <p>Add the first one — every user needs a branch.</p>
                    @canAdd('viewBranch')
                        <a href="{{ route('viewBranch.create') }}" class="nv-btn nv-btn-primary"><x-icon name="plus" /> Add branch</a>
                    @endCanAdd
                </div>
            @endif

            <x-slot:footer>
                {{ $branches->links() }}
            </x-slot:footer>
        </x-card>
    </div>
@endsection
