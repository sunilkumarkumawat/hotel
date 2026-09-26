@extends('layouts.app')

@section('title', 'Pool Bookings')

@section('content')
    <x-page-header
        title="Pool Bookings"
        subtitle="Who has the water, and when."
        :crumbs="['Home' => url('/'), 'Pool', 'Pool Bookings']"
    >
        <x-slot:actions>
            @canView('pool/calendar')
                <a href="{{ route('pool.calendar', ['date' => $filters['from']]) }}" class="nv-btn nv-btn-outline">
                    <x-icon name="calendar" /> Day view
                </a>
            @endCanView

            @canAdd('pool/bookings')
                <a href="{{ route('pool.bookings.create') }}" class="nv-btn nv-btn-primary">
                    <x-icon name="plus" /> New booking
                </a>
            @endCanAdd
        </x-slot:actions>
    </x-page-header>

    <div class="nv-grid nv-grid-4">
        <x-stat label="Booked today" :value="$counts['today']" icon="calendar" />
        <x-stat label="In the pool now" :value="$counts['swimming']" icon="activity" tone="info" />
        <x-stat label="Still to come" :value="$counts['upcoming']" icon="clock" tone="warning" />
        <x-stat label="Value in range" :value="'₹ ' . number_format((float) $counts['value'], 2)" icon="wallet" tone="success" />
    </div>

    <div class="nv-mt">
        <x-card>
            <form method="GET" class="nv-toolbar">
                <div class="nv-field-search">
                    <x-icon name="search" />
                    <input type="search" name="q" value="{{ $filters['q'] }}" class="nv-input"
                           placeholder="Guest, booking no. or mobile…" />
                </div>

                <input type="date" name="from" value="{{ $filters['from'] }}" class="nv-input" style="width:160px"
                       aria-label="From date" />
                <input type="date" name="to" value="{{ $filters['to'] }}" class="nv-input" style="width:160px"
                       aria-label="To date" />

                <select name="pool" class="nv-select" style="width:170px" aria-label="Pool">
                    <option value="">Every pool</option>
                    @foreach ($pools as $id => $name)
                        <option value="{{ $id }}" @selected($filters['pool'] === $id)>{{ $name }}</option>
                    @endforeach
                </select>

                <select name="status" class="nv-select" style="width:150px" aria-label="Status">
                    <option value="">Any status</option>
                    @foreach ($statuses as $key => $label)
                        <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>

                <button type="submit" class="nv-btn nv-btn-primary"><x-icon name="filter" /> Show</button>
                <a href="{{ route('pool.bookings') }}" class="nv-btn nv-btn-ghost">Reset</a>
            </form>
        </x-card>
    </div>

    <div class="nv-mt">
        <x-card flush>
            @if ($rows->isEmpty())
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="globe" /></span>
                    <strong>Nothing booked in this range</strong>
                    <p>Widen the dates, or take a booking.</p>
                </div>
            @else
                <div class="nv-table-wrap">
                    <table class="nv-table">
                        <thead>
                            <tr>
                                <th>Booking</th>
                                <th>Guest</th>
                                <th>Pool</th>
                                <th>When</th>
                                <th class="is-num">People</th>
                                <th class="is-num">Amount</th>
                                <th>Status</th>
                                <th style="width:120px"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    <td>
                                        <strong>{{ $row->booking_no }}</strong>
                                        @if ($row->post_to_room && $row->folio_charge_id)
                                            <span class="nv-sub">on the room bill</span>
                                        @endif
                                    </td>

                                    <td>
                                        {{ $row->guest_name ?: '—' }}
                                        @if ($row->room_no)
                                            <span class="nv-sub">Room {{ $row->room_no }}</span>
                                        @elseif ($row->mobile)
                                            <span class="nv-sub">{{ $row->mobile }}</span>
                                        @endif
                                    </td>

                                    <td>{{ $row->pool?->name ?? '—' }}</td>

                                    <td>
                                        {{ $row->booking_date->format('d M Y') }}
                                        <span class="nv-sub">{{ $row->slot }}</span>
                                    </td>

                                    <td class="is-num">
                                        {{ $row->pax }}
                                        @if ((int) $row->children > 0)
                                            <span class="nv-sub">{{ $row->adults }} + {{ $row->children }} child</span>
                                        @endif
                                    </td>

                                    <td class="is-num">
                                        ₹ {{ number_format((float) $row->total_amount, 2) }}
                                        @if ((float) $row->tax_amount > 0)
                                            <span class="nv-sub">incl. tax ₹ {{ number_format((float) $row->tax_amount, 2) }}</span>
                                        @endif
                                    </td>

                                    <td><x-badge :tone="$row->status_tone">{{ $row->status_label }}</x-badge></td>

                                    <td class="nv-fac-row-actions">
                                        @if (! $row->isClosed())
                                            @canEdit('pool/bookings')
                                                <a href="{{ route('pool.bookings.edit', $row->id) }}" class="nv-icon-btn" title="Edit">
                                                    <x-icon name="pencil" />
                                                </a>

                                                <form method="POST" action="{{ route('pool.bookings.status', $row->id) }}">
                                                    @csrf
                                                    <input type="hidden" name="status"
                                                           value="{{ $row->status === 'booked' ? 'in_use' : 'completed' }}" />
                                                    <button type="submit" class="nv-icon-btn"
                                                            title="{{ $row->status === 'booked' ? 'They are in the pool' : 'Finished' }}">
                                                        <x-icon :name="$row->status === 'booked' ? 'arrow-right' : 'check'" />
                                                    </button>
                                                </form>
                                            @endCanEdit

                                            @canDelete('pool/bookings')
                                                <form method="POST" action="{{ route('pool.bookings.cancel', $row->id) }}"
                                                      data-confirm="Cancel {{ $row->booking_no }}? Any charge on the room bill comes off too.">
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
