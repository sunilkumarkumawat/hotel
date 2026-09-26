@extends('layouts.app')

@section('title', 'Hall Bookings')

@section('content')
    <x-page-header
        title="Hall Bookings"
        subtitle="The banquet diary — what is held, by whom, and what is still owed."
        :crumbs="['Home' => url('/'), 'Banquet Hall', 'Hall Bookings']"
    >
        <x-slot:actions>
            @canView('hall/calendar')
                <a href="{{ route('hall.calendar', ['date' => $filters['from']]) }}" class="nv-btn nv-btn-outline">
                    <x-icon name="calendar" /> Week view
                </a>
            @endCanView

            @canAdd('hall/bookings')
                <a href="{{ route('hall.bookings.create') }}" class="nv-btn nv-btn-primary">
                    <x-icon name="plus" /> New booking
                </a>
            @endCanAdd
        </x-slot:actions>
    </x-page-header>

    <div class="nv-grid nv-grid-4">
        <x-stat label="On today" :value="$counts['today']" icon="calendar" />
        <x-stat label="Tentative holds" :value="$counts['tentative']" icon="clock" tone="warning" />
        <x-stat label="Still to come" :value="$counts['upcoming']" icon="trending-up" tone="info" />
        <x-stat label="Outstanding" :value="'₹ ' . number_format((float) $counts['due'], 2)" icon="wallet" tone="danger" />
    </div>

    <div class="nv-mt">
        <x-card>
            <form method="GET" class="nv-toolbar">
                <div class="nv-field-search">
                    <x-icon name="search" />
                    <input type="search" name="q" value="{{ $filters['q'] }}" class="nv-input"
                           placeholder="Guest, booking no. or event…" />
                </div>

                <input type="date" name="from" value="{{ $filters['from'] }}" class="nv-input" style="width:160px"
                       aria-label="From date" />
                <input type="date" name="to" value="{{ $filters['to'] }}" class="nv-input" style="width:160px"
                       aria-label="To date" />

                <select name="hall" class="nv-select" style="width:180px" aria-label="Hall">
                    <option value="">Every hall</option>
                    @foreach ($halls as $id => $name)
                        <option value="{{ $id }}" @selected($filters['hall'] === $id)>{{ $name }}</option>
                    @endforeach
                </select>

                <select name="status" class="nv-select" style="width:150px" aria-label="Status">
                    <option value="">Any status</option>
                    @foreach ($statuses as $key => $label)
                        <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>

                <button type="submit" class="nv-btn nv-btn-primary"><x-icon name="filter" /> Show</button>
                <a href="{{ route('hall.bookings') }}" class="nv-btn nv-btn-ghost">Reset</a>
            </form>
        </x-card>
    </div>

    <div class="nv-mt">
        <x-card flush>
            @if ($rows->isEmpty())
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="grid" /></span>
                    <strong>Nothing in the diary for this range</strong>
                    <p>Widen the dates, or take a booking.</p>
                </div>
            @else
                <div class="nv-table-wrap">
                    <table class="nv-table">
                        <thead>
                            <tr>
                                <th>Booking</th>
                                <th>Host</th>
                                <th>Hall</th>
                                <th>From — to</th>
                                <th class="is-num">Pax</th>
                                <th class="is-num">Total</th>
                                <th class="is-num">Due</th>
                                <th>Status</th>
                                <th style="width:120px"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    <td>
                                        <strong>{{ $row->booking_no }}</strong>
                                        @if ($row->event_type)
                                            <span class="nv-sub">{{ $row->event_type }}</span>
                                        @endif
                                    </td>

                                    <td>
                                        {{ $row->guest_name }}
                                        @if ($row->company)
                                            <span class="nv-sub">{{ $row->company->name }}</span>
                                        @elseif ($row->mobile)
                                            <span class="nv-sub">{{ $row->mobile }}</span>
                                        @endif
                                    </td>

                                    <td>{{ $row->hall?->name ?? '—' }}</td>

                                    <td>
                                        {{ $row->from_date->format('d M Y') }},
                                        {{ \Carbon\CarbonImmutable::parse((string) $row->from_time)->format('h:i A') }}
                                        <span class="nv-sub">
                                            to {{ $row->to_date->format('d M') }},
                                            {{ \Carbon\CarbonImmutable::parse((string) $row->to_time)->format('h:i A') }}
                                        </span>
                                    </td>

                                    <td class="is-num">{{ $row->pax ?: '—' }}</td>

                                    <td class="is-num">
                                        ₹ {{ number_format((float) $row->total_amount, 2) }}
                                        @if ($row->items->isNotEmpty())
                                            <span class="nv-sub">{{ $row->items->count() }} extra(s)</span>
                                        @endif
                                    </td>

                                    <td class="is-num">
                                        @if ($row->balance > 0)
                                            <strong>₹ {{ number_format($row->balance, 2) }}</strong>
                                        @else
                                            <span class="nv-muted">settled</span>
                                        @endif
                                    </td>

                                    <td><x-badge :tone="$row->status_tone">{{ $row->status_label }}</x-badge></td>

                                    <td class="nv-fac-row-actions">
                                        @if (! $row->isClosed())
                                            @canEdit('hall/bookings')
                                                <a href="{{ route('hall.bookings.edit', $row->id) }}" class="nv-icon-btn" title="Edit">
                                                    <x-icon name="pencil" />
                                                </a>

                                                <form method="POST" action="{{ route('hall.bookings.status', $row->id) }}">
                                                    @csrf
                                                    <input type="hidden" name="status"
                                                           value="{{ in_array($row->status, ['tentative', 'confirmed'], true) ? 'in_use' : 'completed' }}" />
                                                    <button type="submit" class="nv-icon-btn"
                                                            title="{{ in_array($row->status, ['tentative', 'confirmed'], true) ? 'The event has started' : 'Finished' }}">
                                                        <x-icon :name="in_array($row->status, ['tentative', 'confirmed'], true) ? 'arrow-right' : 'check'" />
                                                    </button>
                                                </form>
                                            @endCanEdit

                                            @canDelete('hall/bookings')
                                                <form method="POST" action="{{ route('hall.bookings.cancel', $row->id) }}"
                                                      data-confirm="Cancel {{ $row->booking_no }}? The hall is freed and any charge on the room bill comes off."
                                                      data-confirm-title="Cancel hall booking">
                                                    @csrf
                                                    <button type="submit" class="nv-icon-btn is-danger" title="Cancel">
                                                        <x-icon name="x-circle" />
                                                    </button>
                                                </form>
                                            @endCanDelete
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($rows->hasPages())
                <x-slot:footer>{{ $rows->links() }}</x-slot:footer>
            @endif
        </x-card>
    </div>
@endsection
