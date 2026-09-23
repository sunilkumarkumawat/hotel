@extends('layouts.app')

@section('title', 'Tally Export')

@section('content')
    <x-page-header
        title="Tally Export"
        subtitle="The month's sales as vouchers, in a file Tally will import."
        :crumbs="['Home' => url('/'), 'Compliance', 'Tally Export']"
    />

    <div class="nv-mt">
        <x-card>
            <form method="GET" class="nv-toolbar">
                <x-field label="Month" name="month" help="Defaults to last month.">
                    <input type="month" name="month" value="{{ $month }}" class="nv-input" />
                </x-field>

                <button type="submit" class="nv-btn nv-btn-outline"><x-icon name="search" /> Show</button>
            </form>
        </x-card>
    </div>

    <div class="nv-grid nv-grid-3 nv-mt">
        <x-stat label="Vouchers" :value="$count" icon="file"
                :caption="\Carbon\CarbonImmutable::parse($from)->format('d M') . ' – ' . \Carbon\CarbonImmutable::parse($to)->format('d M Y')" />
        <x-stat label="Total sales" :value="'₹' . number_format($total, 2)" icon="wallet" tone="success" />
        <x-stat label="Voucher type" value="Sales" icon="chart" tone="info"
                caption="One per bill, ACTION Create" />
    </div>

    {{-- ── The download, and what has to exist before it ─────────────────── --}}
    <div class="nv-grid nv-grid-2 nv-mt">
        <x-card title="Download" subtitle="Gateway of Tally → Import Data → Vouchers.">
            <form method="GET" action="{{ route('compliance.tally.download') }}">
                <input type="hidden" name="month" value="{{ $month }}" />

                <x-field label="Company name in Tally" name="company"
                         help="Exactly as it is spelled there, or the import opens against the wrong company.">
                    <x-input name="company" :value="$branch?->branch_name" />
                </x-field>

                <button type="submit" @class(['nv-btn', 'nv-btn-primary', 'nv-btn-block', 'is-off' => $count === 0])>
                    <x-icon name="download" />
                    {{ $count === 0 ? 'Nothing to export this month' : 'Download ' . $count . ' vouchers' }}
                </button>
            </form>

            <p class="nv-help nv-mt">
                Take a backup of the Tally company first. An import cannot be undone from inside Tally, and a
                month imported twice is a month of doubled sales.
            </p>
        </x-card>

        <x-card title="Ledgers this file names"
                subtitle="Create any that are missing before importing — Tally invents its own otherwise.">
            <div class="nv-cm-ledgers">
                @foreach ($ledgers as $name => $under)
                    <div>
                        <strong>{{ $name }}</strong>
                        <span>{{ $under }}</span>
                    </div>
                @endforeach
            </div>
        </x-card>
    </div>

    <div class="nv-mt">
        <x-alert tone="info" title="What is in each voucher">
            The guest (or the company, where the bill names one) is debited for the invoice value. Room
            revenue, food and beverage, and other services are credited net of tax; CGST and SGST are
            credited separately, or IGST where a bill went out that way. A discount is debited to Discount
            Allowed. Every voucher balances before it is written — if a bill cannot be made to balance, the
            difference goes to the party ledger and the narration says so, so the file still imports and the
            one bad bill is findable.
        </x-alert>
    </div>
@endsection
