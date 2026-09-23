@extends('layouts.app')

@section('title', 'Outlets')

@php
    $mayAdd = can_do('point-of-sale/setup/outlets', 'add');
    $mayEdit = can_do('point-of-sale/setup/outlets', 'edit');
    $mayDelete = can_do('point-of-sale/setup/outlets', 'delete');
@endphp

@section('content')
    <x-page-header
        title="Outlets"
        subtitle="Every till the hotel sells through. What an outlet is, how it sells and what it prints all live on one form."
        :crumbs="['Home' => url('/'), 'Point Of Sale' => route('point-of-sale.setup'), 'Setup' => route('point-of-sale.setup'), 'Outlets']"
    >
        <x-slot:actions>
            <a href="{{ route('point-of-sale.setup') }}" class="nv-btn nv-btn-outline">
                <x-icon name="chevron-left" /> Back to Setup
            </a>

            @if ($mayAdd)
                <a href="{{ route('point-of-sale.setup.outlets.create') }}" class="nv-btn nv-btn-primary">
                    <x-icon name="plus" /> Create Outlet
                </a>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if (session('error'))
        <div class="nv-mt"><x-alert tone="danger" title="Not saved">{{ session('error') }}</x-alert></div>
    @endif

    <div class="nv-grid nv-grid-4">
        <x-stat label="All outlets" :value="$counts['all']" icon="inbox" />
        <x-stat label="Active" :value="$counts['active']" icon="check-circle" tone="success" />
        <x-stat label="Inactive" :value="$counts['inactive']" icon="x-circle" tone="danger" />
        <x-stat label="Deleted" :value="$counts['deleted']" icon="trash" tone="warning" />
    </div>

    <div class="nv-mt">
        <x-card flush>
            <form method="GET" class="nv-toolbar">
                <input type="text" name="name" value="{{ $filters['name'] }}" class="nv-input"
                       style="width:200px" placeholder="Name" aria-label="Name" />

                <input type="text" name="address" value="{{ $filters['address'] }}" class="nv-input"
                       style="width:200px" placeholder="Address" aria-label="Address" />

                <input type="text" name="phone" value="{{ $filters['phone'] }}" class="nv-input"
                       style="width:170px" placeholder="Phone" aria-label="Phone" />

                <select name="status" class="nv-select" style="width:160px" aria-label="Status">
                    <option value="">All Status</option>
                    <option value="active" @selected($filters['status'] === 'active')>Active</option>
                    <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
                    <option value="deleted" @selected($filters['status'] === 'deleted')>Deleted</option>
                </select>

                <button type="submit" class="nv-btn nv-btn-primary"><x-icon name="search" /> Search</button>

                @if (array_filter($filters))
                    <a href="{{ route('point-of-sale.setup.outlets') }}" class="nv-btn nv-btn-ghost">Reset</a>
                @endif
            </form>

            @if ($rows->count())
                <div class="nv-table-wrap">
                    <table class="nv-table">
                        <thead>
                            <tr>
                                <th>Outlet</th>
                                <th>Address</th>
                                <th>Phone</th>
                                <th>Sells</th>
                                <th>Bill series</th>
                                <th>Status</th>
                                <th class="is-end" style="width:210px">Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($rows as $outlet)
                                <tr @class(['is-trashed' => $outlet->trashed()])>
                                    <td class="nv-nowrap">
                                        <strong>{{ $outlet->name }}</strong>
                                        <div class="nv-muted" style="font-size:12px">{{ $outlet->timing_label }}</div>
                                    </td>

                                    <td>{{ $outlet->address_line ?: '—' }}</td>

                                    <td class="nv-nowrap">
                                        {{ collect([$outlet->phone1, $outlet->phone2])->filter()->implode(' / ') ?: '—' }}
                                    </td>

                                    <td>
                                        @php $types = $outlet->orderTypes(); @endphp

                                        @forelse ($types as $type)
                                            <x-badge plain>{{ \App\Models\Pos\PosOrder::TYPES[$type] ?? $type }}</x-badge>
                                        @empty
                                            <span class="nv-muted" title="No order type is ticked, so this outlet cannot take an order yet.">
                                                nothing yet
                                            </span>
                                        @endforelse
                                    </td>

                                    <td class="nv-nowrap">
                                        {{ $outlet->bill_series ?: '—' }}
                                        @if ($outlet->diff_liquor_series && $outlet->liquor_bill_series)
                                            <span class="nv-muted"> / {{ $outlet->liquor_bill_series }}</span>
                                        @endif
                                    </td>

                                    <td>
                                        @if ($outlet->trashed())
                                            <x-badge tone="warning">Deleted</x-badge>
                                        @else
                                            <x-badge :tone="$outlet->isActive() ? 'success' : 'danger'">
                                                {{ $outlet->isActive() ? 'Active' : 'Inactive' }}
                                            </x-badge>
                                        @endif
                                    </td>

                                    <td class="is-end">
                                        <div class="nv-row-actions">
                                            @if ($outlet->trashed())
                                                @if ($mayDelete)
                                                    <form method="POST" action="{{ route('point-of-sale.setup.outlets.restore', $outlet->id) }}">
                                                        @csrf
                                                        <button type="submit" class="nv-btn nv-btn-outline nv-btn-sm">
                                                            <x-icon name="refresh" /> Restore
                                                        </button>
                                                    </form>
                                                @else
                                                    <span class="nv-muted">—</span>
                                                @endif
                                            @else
                                                @canView('point-of-sale/setup/tables')
                                                    <a href="{{ route('point-of-sale.setup.tables', ['outlet' => $outlet->id]) }}"
                                                       class="nv-btn nv-btn-ghost nv-btn-sm" title="Seating in this outlet">
                                                        <x-icon name="grid" /> Tables
                                                    </a>
                                                @endCanView

                                                @if ($mayEdit)
                                                    <a href="{{ route('point-of-sale.setup.outlets.edit', $outlet->id) }}"
                                                       class="nv-btn nv-btn-ghost nv-btn-sm">
                                                        <x-icon name="pencil" /> Edit
                                                    </a>
                                                @endif

                                                @if ($mayDelete)
                                                    <form method="POST" action="{{ route('point-of-sale.setup.outlets.destroy', $outlet->id) }}"
                                                          data-confirm="{{ $outlet->name }} will stop appearing on the POS screens. Its past bills keep its name, and Restore brings it back."
                                                          data-confirm-title="Delete outlet?"
                                                          data-confirm-action="Delete">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="nv-btn nv-btn-ghost nv-btn-sm">
                                                            <x-icon name="trash" />
                                                        </button>
                                                    </form>
                                                @endif
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="inbox" /></span>
                    <strong>No outlets yet</strong>
                    <p>
                        An outlet is a till — the restaurant, room service, the bar. Nothing else in
                        Point Of Sale can be set up until there is one.
                    </p>
                    @if ($mayAdd)
                        <a href="{{ route('point-of-sale.setup.outlets.create') }}" class="nv-btn nv-btn-primary">
                            <x-icon name="plus" /> Create Outlet
                        </a>
                    @endif
                </div>
            @endif

            <x-slot:footer>
                {{ $rows->links() }}
            </x-slot:footer>
        </x-card>
    </div>
@endsection
