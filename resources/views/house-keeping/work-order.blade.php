@extends('layouts.app')

@section('title', 'Work Order')

@section('content')
    <x-page-header
        title="Work Order"
        subtitle="Maintenance jobs — what is broken, who is on it, by when."
        :crumbs="['Home' => url('/'), 'House Keeping', 'Work Order']"
    >
        <x-slot:actions>
            @canAdd('house-keeping/work-order')
                <a href="{{ route('house-keeping.work-order.create') }}" class="nv-btn nv-btn-primary">
                    <x-icon name="plus" /> Add Work Order
                </a>
            @endCanAdd
        </x-slot:actions>
    </x-page-header>

    @if (session('error'))
        <div class="nv-mt"><x-alert tone="danger" title="Not saved">{{ session('error') }}</x-alert></div>
    @endif

    {{-- session('warning') — "the job saved but the room it wanted to hold was
         already taken" — is rendered by the layout for every screen now, so it
         is deliberately not repeated here. --}}

    <div class="nv-grid nv-grid-4">
        <x-stat label="Open" :value="$counts['open']" icon="clock" />
        <x-stat label="Overdue" :value="$counts['overdue']" icon="alert" tone="danger" />
        <x-stat label="Urgent" :value="$counts['urgent']" icon="activity" tone="warning" />
        <x-stat label="Done" :value="$counts['done']" icon="check-circle" tone="success" />
    </div>

    {{-- ── Filters ───────────────────────────────────────────────────────── --}}
    <div class="nv-mt">
        <x-card>
            <form method="GET" class="nv-toolbar">
                <div class="nv-field-search">
                    <x-icon name="search" />
                    <input type="search" name="q" value="{{ $filters['q'] }}" class="nv-input"
                           placeholder="Order no. or job…" />
                </div>

                <input type="date" name="from" value="{{ $filters['from'] }}" class="nv-input" style="width:160px"
                       aria-label="From date" />
                <input type="date" name="to" value="{{ $filters['to'] }}" class="nv-input" style="width:160px"
                       aria-label="To date" />

                <select name="status" class="nv-select" style="width:150px" aria-label="Status">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>

                <select name="priority" class="nv-select" style="width:140px" aria-label="Priority">
                    <option value="">All priorities</option>
                    @foreach ($priorities as $value => $label)
                        <option value="{{ $value }}" @selected($filters['priority'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>

                <select name="employee" class="nv-select" style="width:180px" aria-label="Employee">
                    <option value="">Select Employee</option>
                    @foreach ($employees as $id => $name)
                        <option value="{{ $id }}" @selected($filters['employee'] === $id)>{{ $name }}</option>
                    @endforeach
                </select>

                <button type="submit" class="nv-btn nv-btn-primary"><x-icon name="search" /> Search</button>

                <a href="{{ route('house-keeping.work-order') }}" class="nv-btn nv-btn-ghost">Reset</a>
            </form>
        </x-card>
    </div>

    {{-- ── The list ──────────────────────────────────────────────────────── --}}
    <div class="nv-mt">
        <x-card flush>
            @if ($orders->count())
                <div class="nv-table-wrap">
                    <table class="nv-table nv-table-compact">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Unit/Room</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th>Priority</th>
                                <th>Assign To</th>
                                <th>Entered On</th>
                                <th>Deadline</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($orders as $order)
                                <tr class="nv-wo-row @if ($order->isOverdue()) is-overdue @elseif ($order->isClosed()) is-closed @endif">
                                    <td><strong class="nv-mono">{{ $order->order_no }}</strong></td>

                                    <td>
                                        @if ($order->room)
                                            <strong class="nv-mono">{{ $order->room->room_no }}</strong>
                                            @if ($order->room->floor)
                                                <span class="nv-sub">Floor {{ $order->room->floor }}</span>
                                            @endif
                                        @else
                                            <span class="nv-muted">Common area</span>
                                        @endif
                                    </td>

                                    <td>{{ $order->category_label }}</td>

                                    <td class="nv-wo-note">{{ \Illuminate\Support\Str::limit($order->description, 70) }}</td>

                                    <td>
                                        <span class="nv-wo-dot is-{{ $order->priority }}"></span>
                                        {{ $order->priority_label }}
                                    </td>

                                    <td>
                                        @if ($order->assignee)
                                            {{ $order->assignee->name }}
                                        @else
                                            <span class="nv-muted">Nobody yet</span>
                                        @endif
                                    </td>

                                    <td>{{ $order->created_at?->format('d M Y') ?? '—' }}</td>

                                    <td>
                                        {{ $order->due_date?->format('d M Y') ?? '—' }}
                                        @if ($order->isOverdue())
                                            <span class="nv-sub is-late">
                                                {{ (int) $order->due_date->diffInDays(today()) }} day(s) late
                                            </span>
                                        @endif
                                    </td>

                                    <td>
                                        <x-badge tone="{{ ['open' => 'info', 'in_progress' => 'warning', 'done' => 'success', 'cancelled' => ''][$order->status] ?? 'info' }}">
                                            {{ $order->status_label }}
                                        </x-badge>

                                        @if ($order->room_block_id)
                                            <span class="nv-sub">Room blocked</span>
                                        @endif
                                    </td>

                                    <td>
                                        <div class="nv-row-actions">
                                            @canEdit('house-keeping/work-order')
                                                <a href="{{ route('house-keeping.work-order.edit', $order) }}"
                                                   class="nv-btn nv-btn-ghost nv-btn-sm"
                                                   aria-label="Edit {{ $order->order_no }}">
                                                    <x-icon name="pencil" />
                                                </a>

                                                @unless ($order->isClosed())
                                                    <form method="POST"
                                                          action="{{ route('house-keeping.work-order.close', $order) }}"
                                                          data-confirm="{{ $order->room_block_id
                                                              ? 'The room it is holding goes back on sale.'
                                                              : 'It will be marked done, dated today.' }}"
                                                          data-confirm-title="Close {{ $order->order_no }}?"
                                                          data-confirm-action="Mark done">
                                                        @csrf
                                                        <button type="submit" class="nv-btn nv-btn-ghost nv-btn-sm"
                                                                aria-label="Close {{ $order->order_no }}">
                                                            <x-icon name="check" />
                                                        </button>
                                                    </form>
                                                @endunless
                                            @endCanEdit

                                            @canDelete('house-keeping/work-order')
                                                <form method="POST"
                                                      action="{{ route('house-keeping.work-order.destroy', $order) }}"
                                                      data-confirm="{{ $order->room_block_id
                                                          ? 'The room it is holding goes back on sale too.'
                                                          : 'The job card will be removed.' }}"
                                                      data-confirm-title="Delete {{ $order->order_no }}?"
                                                      data-confirm-action="Delete">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="nv-btn nv-btn-ghost nv-btn-sm"
                                                            aria-label="Delete {{ $order->order_no }}">
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

                <x-slot:footer>{{ $orders->links() }}</x-slot:footer>
            @else
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="cog" /></span>
                    <strong>No work orders found.</strong>
                    <p>
                        Press <b>Add Work Order</b> to raise the first one. Overdue jobs come to the
                        top of this list on their own, so nothing has to be chased by memory.
                    </p>
                </div>
            @endif
        </x-card>
    </div>
@endsection
