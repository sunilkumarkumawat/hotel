@extends('layouts.app')

@section('title', 'Tables')

@php
    $mayAdd = can_do('point-of-sale/setup/tables', 'add');
    $mayEdit = can_do('point-of-sale/setup/tables', 'edit');
    $mayDelete = can_do('point-of-sale/setup/tables', 'delete');
    $keep = array_filter(['outlet' => $outletId, 'deleted' => $showDeleted ? 1 : null]);
@endphp

@section('content')
    <x-page-header
        title="Tables"
        subtitle="The floor plan. A group is a section — Non AC, Terrace — and what sits inside it is a table, a room, a villa or an apartment."
        :crumbs="['Home' => url('/'), 'Point Of Sale' => route('point-of-sale.setup'), 'Setup' => route('point-of-sale.setup'), 'Tables']"
    >
        <x-slot:actions>
            <a href="{{ route('point-of-sale.setup') }}" class="nv-btn nv-btn-outline">
                <x-icon name="chevron-left" /> Back to Setup
            </a>

            <button type="button" class="nv-btn nv-btn-ghost" data-collapse-all hidden>
                <x-icon name="chevron-down" /> <span data-collapse-label>Collapse All</span>
            </button>
        </x-slot:actions>
    </x-page-header>

    @if (session('error'))
        <div class="nv-mt"><x-alert tone="danger" title="Not saved">{{ session('error') }}</x-alert></div>
    @endif

    @if ($errors->any())
        <div class="nv-mt">
            <x-alert tone="danger" title="Please fix {{ $errors->count() }} thing(s)">{{ $errors->first() }}</x-alert>
        </div>
    @endif

    <div class="nv-grid nv-grid-3">
        <x-stat label="Groups" :value="$counts['groups']" icon="layers" />
        <x-stat label="Tables" :value="$counts['tables']" icon="grid" />
        <x-stat label="Seats" :value="$counts['seats']" icon="users" tone="info" />
    </div>

    <div class="nv-mt">
        <x-card>
            <form method="GET" class="nv-toolbar">
                <select name="outlet" class="nv-select" style="width:220px" aria-label="Outlet">
                    <option value="">All Outlets</option>
                    @foreach ($allOutlets as $id => $name)
                        <option value="{{ $id }}" @selected($outletId === $id)>{{ $name }}</option>
                    @endforeach
                </select>

                <label class="nv-check-inline">
                    <input type="checkbox" name="deleted" value="1" @checked($showDeleted) />
                    Show deleted
                </label>

                <button type="submit" class="nv-btn nv-btn-primary"><x-icon name="search" /> Search</button>

                @if ($keep)
                    <a href="{{ route('point-of-sale.setup.tables') }}" class="nv-btn nv-btn-ghost">Reset</a>
                @endif
            </form>
        </x-card>
    </div>

    @forelse ($outlets as $outlet)
        @php $outletGroups = $groups[$outlet->id] ?? collect(); @endphp

        <details class="nv-outlet-block" open data-outlet-block>
            <summary class="nv-outlet-bar">
                <span class="nv-outlet-name">
                    <x-icon name="chevron-right" />
                    Outlet — {{ $outlet->name }}
                </span>

                <span class="nv-muted">{{ $outletGroups->count() }} group(s)</span>
            </summary>

            @if ($mayAdd)
                <div class="nv-outlet-tools">
                    <button type="button" class="nv-btn nv-btn-outline nv-btn-sm"
                            data-group-form
                            data-outlet="{{ $outlet->id }}"
                            data-outlet-name="{{ $outlet->name }}">
                        <x-icon name="plus" /> Create Group
                    </button>
                </div>
            @endif

            @forelse ($outletGroups as $group)
                <div @class(['nv-group-block', 'is-trashed' => $group->trashed()])>
                    <div class="nv-group-bar">
                        <span>
                            <strong>Group — {{ $group->name }}</strong>
                            <em class="nv-muted">(Type — {{ $group->kindLabel() }})</em>
                            @if ($group->trashed())
                                <x-badge tone="warning">Deleted</x-badge>
                            @elseif (! $group->isActive())
                                <x-badge tone="danger">Inactive</x-badge>
                            @endif
                        </span>

                        <span class="nv-row-actions">
                            @if ($group->trashed())
                                @if ($mayDelete)
                                    <form method="POST" action="{{ route('point-of-sale.setup.tables.group.restore', $group->id) }}">
                                        @csrf
                                        <button type="submit" class="nv-btn nv-btn-outline nv-btn-sm">
                                            <x-icon name="refresh" /> Restore
                                        </button>
                                    </form>
                                @endif
                            @else
                                @if ($mayEdit)
                                    <button type="button" class="nv-btn nv-btn-ghost nv-btn-sm"
                                            data-group-form
                                            data-id="{{ $group->id }}"
                                            data-outlet="{{ $outlet->id }}"
                                            data-outlet-name="{{ $outlet->name }}"
                                            data-name="{{ $group->name }}"
                                            data-kind="{{ $group->kind }}"
                                            data-status="{{ (int) $group->status }}">
                                        <x-icon name="pencil" /> Edit
                                    </button>
                                @endif

                                @if ($mayDelete)
                                    <form method="POST" action="{{ route('point-of-sale.setup.tables.group.destroy', $group->id) }}"
                                          data-confirm="{{ $group->name }} will be hidden. It has to be empty first."
                                          data-confirm-title="Delete group?"
                                          data-confirm-action="Delete">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="nv-btn nv-btn-ghost nv-btn-sm">
                                            <x-icon name="trash" /> Delete
                                        </button>
                                    </form>
                                @endif

                                @if ($mayAdd)
                                    <button type="button" class="nv-btn nv-btn-outline nv-btn-sm"
                                            data-table-form
                                            data-group="{{ $group->id }}"
                                            data-group-name="{{ $group->name }}"
                                            data-outlet-name="{{ $outlet->name }}"
                                            data-kind-label="{{ $group->kindLabel() }}"
                                            data-capacity="0">
                                        <x-icon name="plus" /> Create {{ $group->kindLabel() }}
                                    </button>
                                @endif
                            @endif
                        </span>
                    </div>

                    @if ($group->tables->count())
                        <div class="nv-table-wrap">
                            <table class="nv-table">
                                <thead>
                                    <tr>
                                        <th>{{ strtoupper($group->kindLabel()) }}</th>
                                        <th class="is-num" style="width:140px">Capacity</th>
                                        <th style="width:120px">Active</th>
                                        <th class="is-end" style="width:190px">Actions</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    @foreach ($group->tables as $table)
                                        <tr @class(['is-trashed' => $table->trashed()])>
                                            <td><strong>{{ $table->name }}</strong></td>

                                            <td class="is-num">
                                                {{ $table->capacity ?: '—' }}
                                            </td>

                                            <td>
                                                @if ($table->trashed())
                                                    <x-badge tone="warning">Deleted</x-badge>
                                                @else
                                                    <x-badge :tone="$table->isActive() ? 'success' : 'danger'">
                                                        {{ $table->isActive() ? 'Active' : 'Inactive' }}
                                                    </x-badge>
                                                @endif
                                            </td>

                                            <td class="is-end">
                                                <div class="nv-row-actions">
                                                    @if ($table->trashed())
                                                        @if ($mayDelete)
                                                            <form method="POST" action="{{ route('point-of-sale.setup.tables.table.restore', $table->id) }}">
                                                                @csrf
                                                                <button type="submit" class="nv-btn nv-btn-outline nv-btn-sm">
                                                                    <x-icon name="refresh" /> Restore
                                                                </button>
                                                            </form>
                                                        @endif
                                                    @else
                                                        @if ($mayEdit)
                                                            <button type="button" class="nv-btn nv-btn-ghost nv-btn-sm"
                                                                    data-table-form
                                                                    data-id="{{ $table->id }}"
                                                                    data-group="{{ $group->id }}"
                                                                    data-group-name="{{ $group->name }}"
                                                                    data-outlet-name="{{ $outlet->name }}"
                                                                    data-kind-label="{{ $group->kindLabel() }}"
                                                                    data-name="{{ $table->name }}"
                                                                    data-capacity="{{ $table->capacity }}"
                                                                    data-status="{{ (int) $table->status }}">
                                                                <x-icon name="pencil" /> Edit
                                                            </button>
                                                        @endif

                                                        @if ($mayDelete)
                                                            <form method="POST" action="{{ route('point-of-sale.setup.tables.table.destroy', $table->id) }}"
                                                                  data-confirm="{{ $table->name }} will be hidden from the POS screens."
                                                                  data-confirm-title="Delete {{ strtolower($group->kindLabel()) }}?"
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
                        <p class="nv-group-empty nv-muted">
                            Nothing in this group yet.
                            @if ($mayAdd)
                                Use <strong>Create {{ $group->kindLabel() }}</strong> above.
                            @endif
                        </p>
                    @endif
                </div>
            @empty
                <p class="nv-group-empty nv-muted">
                    No groups in {{ $outlet->name }} yet. A group is a section of the floor — Non AC,
                    Terrace, Pool Side — and the tables go inside it.
                </p>
            @endforelse
        </details>
    @empty
        <div class="nv-mt">
            <x-card>
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="grid" /></span>
                    <strong>No outlets to lay out</strong>
                    <p>Seating belongs to an outlet, so there has to be one first.</p>
                    @canView('point-of-sale/setup/outlets')
                        <a href="{{ route('point-of-sale.setup.outlets') }}" class="nv-btn nv-btn-primary">
                            <x-icon name="inbox" /> Go to Outlets
                        </a>
                    @endCanView
                </div>
            </x-card>
        </div>
    @endforelse

    {{-- ── Group dialog ──────────────────────────────────────────────────── --}}
    @if ($mayAdd || $mayEdit)
        <div class="nv-modal-backdrop" data-modal="group">
            <div class="nv-modal" role="dialog" aria-modal="true" aria-labelledby="group-title">
                <div class="nv-modal-head">
                    <strong id="group-title">Group</strong>
                    <button type="button" class="nv-icon-btn" data-modal-close aria-label="Close">
                        <x-icon name="x" />
                    </button>
                </div>

                <form method="POST" action="{{ route('point-of-sale.setup.tables.group') }}">
                    @csrf
                    <input type="hidden" name="id" value="" data-field="id" />
                    <input type="hidden" name="outlet_id" value="" data-field="outlet_id" />
                    <input type="hidden" name="status" value="0" />

                    <div class="nv-form-grid">
                        <x-field label="Outlet">
                            <input type="text" class="nv-input" data-field="outlet_name" readonly />
                        </x-field>

                        <x-field label="Name" name="name" required>
                            <input type="text" name="name" id="group_name" class="nv-input"
                                   data-field="name" placeholder="Non AC" />
                        </x-field>

                        <x-field label="Type" name="kind" required
                                 help="What the things inside are called — and what the button under this group will say.">
                            <select name="kind" id="group_kind" class="nv-select" data-field="kind">
                                <option value="">Select</option>
                                @foreach (\App\Models\Pos\PosTableGroup::KINDS as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </x-field>

                        <label class="nv-check" for="group_status">
                            <input type="checkbox" name="status" id="group_status" value="1"
                                   data-field="status" checked />
                            <span>Active</span>
                        </label>
                    </div>

                    <div class="nv-actions" style="justify-content:flex-end;margin-top:16px">
                        <button type="button" class="nv-btn nv-btn-ghost" data-modal-close>Cancel</button>
                        <button type="submit" class="nv-btn nv-btn-primary"><x-icon name="check" /> Save</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ── Table dialog ──────────────────────────────────────────────── --}}
        <div class="nv-modal-backdrop" data-modal="table">
            <div class="nv-modal" role="dialog" aria-modal="true" aria-labelledby="table-title">
                <div class="nv-modal-head">
                    <strong id="table-title">Table</strong>
                    <button type="button" class="nv-icon-btn" data-modal-close aria-label="Close">
                        <x-icon name="x" />
                    </button>
                </div>

                <form method="POST" action="{{ route('point-of-sale.setup.tables.table') }}">
                    @csrf
                    <input type="hidden" name="id" value="" data-field="id" />
                    <input type="hidden" name="pos_table_group_id" value="" data-field="pos_table_group_id" />
                    <input type="hidden" name="status" value="0" />

                    <div class="nv-form-grid">
                        <x-field label="Outlet">
                            <input type="text" class="nv-input" data-field="outlet_name" readonly />
                        </x-field>

                        <x-field label="Group">
                            <input type="text" class="nv-input" data-field="group_name" readonly />
                        </x-field>

                        <x-field label="Name" name="name" required>
                            <input type="text" name="name" id="table_name" class="nv-input"
                                   data-field="name" placeholder="7" />
                        </x-field>

                        <x-field label="Capacity" name="capacity" help="How many it seats. 0 means nobody has said.">
                            <input type="number" name="capacity" id="table_capacity" min="0" max="999"
                                   class="nv-input" data-field="capacity" value="0" />
                        </x-field>

                        <label class="nv-check" for="table_status">
                            <input type="checkbox" name="status" id="table_status" value="1"
                                   data-field="status" checked />
                            <span>Active</span>
                        </label>
                    </div>

                    <div class="nv-actions" style="justify-content:flex-end;margin-top:16px">
                        <button type="button" class="nv-btn nv-btn-ghost" data-modal-close>Cancel</button>
                        <button type="submit" class="nv-btn nv-btn-primary"><x-icon name="check" /> Save</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script src="{{ asset('js/pos-tables.js') }}?v={{ filemtime(public_path('js/pos-tables.js')) }}" defer></script>
@endpush
