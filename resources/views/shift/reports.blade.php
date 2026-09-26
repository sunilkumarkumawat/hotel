@extends('layouts.app')

@section('title', 'Shift Reports')

@php
    $money = fn ($n) => '₹' . number_format((float) $n, 2);
@endphp

@section('content')
    <x-page-header
        title="Shift Reports"
        subtitle="Every drawer, and whether it balanced."
        :crumbs="['Home' => url('/'), 'Shift', 'Shift Reports']"
    >
        <x-slot:actions>
            @canView('shift/my-shift')
                <a href="{{ route('shift.my-shift') }}" class="nv-btn nv-btn-outline">
                    <x-icon name="wallet" /> My shift
                </a>
            @endCanView
        </x-slot:actions>
    </x-page-header>

    <div class="nv-grid nv-grid-4">
        <x-stat label="Shifts" :value="$summary['shifts']" icon="clock"
                :caption="$summary['open'] . ' still open'" />
        <x-stat label="Cash counted" :value="$money($summary['counted'])" icon="wallet" tone="success"
                :caption="'against ' . $money($summary['expected']) . ' expected'" />
        <x-stat label="Short" :value="$money($summary['short'])" icon="arrow-down"
                :tone="$summary['short'] > 0 ? 'danger' : 'success'"
                caption="Money that was in a drawer and is not" />
        <x-stat label="Over" :value="$money($summary['over'])" icon="arrow-up"
                :tone="$summary['over'] > 0 ? 'warning' : 'success'"
                caption="Usually a payment nobody entered" />
    </div>

    {{-- ── The control report: money taken outside any shift ─────────────── --}}
    @if ($unattached !== [])
        <div class="nv-mt">
            <x-alert tone="warning" title="Money taken while no shift was open">
                @foreach ($unattached as $row)
                    <strong>{{ $row['user'] }}</strong> took {{ $row['count'] }}
                    {{ \Illuminate\Support\Str::plural('payment', $row['count']) }}
                    ({{ $money($row['amount']) }}) outside any shift{{ ! $loop->last ? '; ' : '. ' }}
                @endforeach
                It is all on the guests' bills and in the day book — it simply belongs to no drawer, so
                nobody counted it at the end of the day.
            </x-alert>
        </div>
    @endif

    <div class="nv-mt">
        <x-card flush>
            <form method="GET" class="nv-toolbar">
                <x-field label="From" name="from"><x-input type="date" name="from" :value="$from" /></x-field>
                <x-field label="To" name="to"><x-input type="date" name="to" :value="$to" /></x-field>

                <x-field label="Cashier" name="user">
                    <select name="user" class="nv-select">
                        <option value="">Everybody</option>
                        @foreach ($users as $id => $name)
                            <option value="{{ $id }}" @selected((int) $filters['user'] === (int) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </x-field>

                <x-field label="Status" name="status">
                    <select name="status" class="nv-select">
                        <option value="">Any</option>
                        <option value="open" @selected($filters['status'] === 'open')>Still open</option>
                        <option value="closed" @selected($filters['status'] === 'closed')>Closed</option>
                    </select>
                </x-field>

                <button type="submit" class="nv-btn nv-btn-primary"><x-icon name="search" /> Show</button>
            </form>

            @if ($shifts->isEmpty())
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="clock" /></span>
                    <strong>No shifts in this window</strong>
                    <p>Nobody opened a drawer between these two dates.</p>
                </div>
            @else
                <div class="nv-table-wrap">
                    <table class="nv-table">
                        <thead>
                            <tr>
                                <th>Shift</th>
                                <th>Cashier</th>
                                <th>Opened</th>
                                <th>Closed</th>
                                <th class="is-end">Expected</th>
                                <th class="is-end">Counted</th>
                                <th class="is-end">Difference</th>
                                <th class="is-end">&nbsp;</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($shifts as $shift)
                                <tr @class(['nv-sh-row-off' => $shift->isOpen()])>
                                    <td>
                                        <strong>{{ $shift->shift_no }}</strong>
                                        <span class="nv-sub">{{ $shift->name }}</span>
                                    </td>
                                    <td>{{ $shift->cashier }}</td>
                                    <td>
                                        {{ $shift->opened_at->format('d M, h:i A') }}
                                        <span class="nv-sub">{{ $shift->length }}</span>
                                    </td>
                                    <td>
                                        @if ($shift->isOpen())
                                            <x-badge tone="info">Still open</x-badge>
                                        @else
                                            {{ $shift->closed_at?->format('d M, h:i A') }}
                                        @endif
                                    </td>
                                    <td class="is-end">{{ $shift->isOpen() ? '—' : $money($shift->cash_expected) }}</td>
                                    <td class="is-end">{{ $shift->isOpen() ? '—' : $money($shift->cash_counted) }}</td>
                                    <td class="is-end">
                                        @if ($shift->isOpen())
                                            —
                                        @else
                                            <x-badge :tone="$shift->variance_tone">
                                                {{ abs((float) $shift->variance) < 0.005 ? 'Balanced'
                                                    : ($shift->isShort() ? 'Short ' : 'Over ') . $money(abs((float) $shift->variance)) }}
                                            </x-badge>
                                        @endif
                                    </td>
                                    <td class="is-end">
                                        <a href="{{ route('shift.reports.show', $shift->id) }}"
                                           class="nv-btn nv-btn-sm nv-btn-ghost">
                                            <x-icon name="external" /> Open
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>
    </div>

    <div class="nv-mt">
        <x-alert tone="info" title="Anything more than {{ number_format($tolerance, 2) }} either way is worth asking about">
            A drawer that is a rupee out is a rounding argument; one that is five hundred out is a
            conversation. Short and over are shown as different things on purpose — over usually means a
            payment was never entered, which is a different problem from money that has gone missing.
        </x-alert>
    </div>
@endsection
