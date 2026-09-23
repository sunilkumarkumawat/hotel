@extends('layouts.app')

@section('title', 'Profit & Loss')

@section('content')
    <x-page-header
        title="Profit &amp; Loss"
        subtitle="What was earned against what was spent, between two dates."
        :crumbs="['Home' => url('/'), 'Accounting' => route('accounting.day-book'), 'Profit and Loss']"
    />

    <div class="nv-mt">
        <x-card>
            <form method="GET" class="nv-toolbar">
                <input type="date" name="from" value="{{ $from }}" class="nv-input" style="width:160px" aria-label="From" />
                <input type="date" name="to" value="{{ $to }}" class="nv-input" style="width:160px" aria-label="To" />
                <button type="submit" class="nv-btn nv-btn-primary"><x-icon name="filter" /> Show</button>
            </form>

            {{--
                Saying which groups were read is not decoration. A profit figure
                nobody can trace is a profit figure nobody believes, and the
                first question a hotel owner asks is "does that include the
                banquet".
            --}}
            <p class="nv-help">
                Read from the groups
                <strong>{{ $read['income'] ? implode(', ', $read['income']) : 'none marked as income' }}</strong>
                against
                <strong>{{ $read['expense'] ? implode(', ', $read['expense']) : 'none marked as expense' }}</strong>.
                Only what was posted between these dates counts — opening balances belong to an earlier year.
            </p>
        </x-card>
    </div>

    <div class="nv-grid nv-grid-3 nv-mt">
        <x-stat label="Income" :value="'₹ ' . number_format($income['total'], 2)" icon="trending-up" tone="success" />
        <x-stat label="Expenses" :value="'₹ ' . number_format($expense['total'], 2)" icon="arrow-down" tone="warning" />
        <x-stat :label="$net >= 0 ? 'Profit' : 'Loss'"
                :value="'₹ ' . number_format(abs($net), 2)"
                icon="wallet"
                :tone="$net >= 0 ? 'success' : 'danger'" />
    </div>

    <div class="nv-grid nv-grid-2 nv-mt">
        @foreach ([['Income', $income, 'success'], ['Expenses', $expense, 'warning']] as [$heading, $sideData, $tone])
            <x-card flush>
                <x-slot:title>{{ $heading }}</x-slot:title>
                <x-slot:actions>
                    <strong>₹ {{ number_format($sideData['total'], 2) }}</strong>
                </x-slot:actions>

                @if (empty($sideData['groups']))
                    <div class="nv-empty">
                        <span class="nv-empty-icon"><x-icon name="inbox" /></span>
                        <strong>Nothing here</strong>
                        <p>No ledger under a {{ strtolower($heading) }} group moved between these dates.</p>
                    </div>
                @else
                    <div class="nv-table-wrap">
                        <table class="nv-table">
                            <tbody>
                                @foreach ($sideData['groups'] as $group)
                                    <tr class="nv-led-day">
                                        <th>{{ $group['name'] }}</th>
                                        <th class="is-num">₹ {{ number_format($group['total'], 2) }}</th>
                                    </tr>

                                    @foreach ($group['rows'] as $row)
                                        <tr>
                                            <td style="padding-left:26px">{{ $row['ledger']->name }}</td>
                                            <td class="is-num">₹ {{ number_format($row['amount'], 2) }}</td>
                                        </tr>
                                    @endforeach
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        @endforeach
    </div>
@endsection
