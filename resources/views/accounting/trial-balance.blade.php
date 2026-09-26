@extends('layouts.app')

@section('title', 'Trial Balance')

@section('content')
    <x-page-header
        title="Trial Balance"
        subtitle="Every ledger with a balance, and proof the two sides agree."
        :crumbs="['Home' => url('/'), 'Accounting' => route('accounting.day-book'), 'Trial Balance']"
    >
        <x-slot:actions>
            <a href="{{ route('accounting.trial-balance.export', ['upto' => $uptoDate]) }}" class="nv-btn nv-btn-outline">
                <x-icon name="download" /> Export
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="nv-mt">
        <x-card>
            <form method="GET" class="nv-toolbar">
                <x-field label="As on" name="upto" for="upto">
                    <input type="date" name="upto" id="upto" value="{{ $uptoDate }}" class="nv-input" />
                </x-field>

                <button type="submit" class="nv-btn nv-btn-primary"><x-icon name="filter" /> Show</button>

                <span class="nv-muted" style="margin-left:auto">{{ $count }} ledger(s) with a balance</span>
            </form>
        </x-card>
    </div>

    @if (abs($difference) >= 0.005)
        {{--
            This cannot happen through the app: Vouchers::post() refuses an
            unbalanced voucher, so the only way here is a hand-written row in the
            database. Saying it loudly is the point — a trial balance that
            quietly rounds its difference away is worse than useless.
        --}}
        <div class="nv-mt">
            <x-alert tone="danger" title="The books do not balance">
                Debit and credit are out by <strong>₹ {{ number_format(abs($difference), 2) }}</strong>.
                Nothing in this system can post an unbalanced voucher, so this means a row was written to
                the database directly. Check <code>voucher_entries</code> for a voucher whose lines do not
                add up.
            </x-alert>
        </div>
    @endif

    <div class="nv-grid nv-grid-3 nv-mt">
        <x-stat label="Debit" :value="'₹ ' . number_format($debit, 2)" icon="arrow-down" tone="info" />
        <x-stat label="Credit" :value="'₹ ' . number_format($credit, 2)" icon="arrow-up" tone="warning" />
        <x-stat label="Difference"
                :value="'₹ ' . number_format(abs($difference), 2)"
                icon="activity"
                :tone="abs($difference) < 0.005 ? 'success' : 'danger'" />
    </div>

    <div class="nv-mt">
        <x-card flush>
            @if (empty($groups))
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="chart" /></span>
                    <strong>Nothing has a balance yet</strong>
                    <p>Post a voucher, or give the ledgers their opening balances.</p>
                </div>
            @else
                <div class="nv-table-wrap">
                    <table class="nv-table">
                        <thead>
                            <tr>
                                <th>Ledger</th>
                                <th class="is-num" style="width:180px">Debit</th>
                                <th class="is-num" style="width:180px">Credit</th>
                            </tr>
                        </thead>

                        @foreach ($groups as $group)
                            <tbody>
                                <tr class="nv-led-day">
                                    <th>{{ $group['name'] }} <span class="nv-sub">{{ ucfirst($group['nature']) }}</span></th>
                                    <th class="is-num">{{ $group['debit'] > 0 ? '₹ ' . number_format($group['debit'], 2) : '' }}</th>
                                    <th class="is-num">{{ $group['credit'] > 0 ? '₹ ' . number_format($group['credit'], 2) : '' }}</th>
                                </tr>

                                @foreach ($group['rows'] as $row)
                                    <tr>
                                        <td style="padding-left:26px">{{ $row['ledger']->name }}</td>
                                        <td class="is-num">{{ $row['debit'] > 0 ? '₹ ' . number_format($row['debit'], 2) : '' }}</td>
                                        <td class="is-num">{{ $row['credit'] > 0 ? '₹ ' . number_format($row['credit'], 2) : '' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        @endforeach

                        <tfoot>
                            <tr>
                                <th>Total</th>
                                <th class="is-num">₹ {{ number_format($debit, 2) }}</th>
                                <th class="is-num">₹ {{ number_format($credit, 2) }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </x-card>
    </div>
@endsection
