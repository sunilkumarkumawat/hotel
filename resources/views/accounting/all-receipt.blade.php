@extends('layouts.app')

@section('title', 'All Receipt')

@section('content')
    <x-page-header
        title="All Receipt"
        subtitle="Every rupee that came in, whichever till it was taken at."
        :crumbs="['Home' => url('/'), 'Accounting' => route('accounting.day-book'), 'All Receipt']"
    />

    <div class="nv-mt">
        <x-card>
            <form method="GET" class="nv-toolbar">
                <input type="date" name="from" value="{{ $from }}" class="nv-input" style="width:160px" aria-label="From" />
                <input type="date" name="to" value="{{ $to }}" class="nv-input" style="width:160px" aria-label="To" />

                <select name="pay_mode" class="nv-select" style="width:180px" aria-label="Pay mode">
                    <option value="">Every pay mode</option>
                    @foreach ($payModes as $id => $name)
                        <option value="{{ $id }}" @selected($payMode === $id)>{{ $name }}</option>
                    @endforeach
                </select>

                <button type="submit" class="nv-btn nv-btn-primary"><x-icon name="filter" /> Show</button>
                <a href="{{ route('accounting.all-receipt') }}" class="nv-btn nv-btn-ghost">Today</a>
            </form>

            {{--
                The three totals are deliberately NOT added together. A guest
                settling their bill at the desk and the receipt voucher an
                accountant posts for it afterwards are the same money seen
                twice; one grand total would count it twice and nobody would
                ever find out why the day never tied.
            --}}
            <p class="nv-help">
                Three sources, totalled separately on purpose. A front-office settlement and the receipt
                voucher later posted for it are the same money seen twice — adding the three figures
                together would count it twice.
            </p>
        </x-card>
    </div>

    <div class="nv-grid nv-grid-3 nv-mt">
        <x-stat label="Front office" :value="'₹ ' . number_format($totals['frontOffice'], 2)" icon="desktop" />
        <x-stat label="Point of sale" :value="'₹ ' . number_format($totals['pos'], 2)" icon="bag" />
        <x-stat label="Receipt vouchers" :value="'₹ ' . number_format($totals['vouchers'], 2)" icon="file" tone="success" />
    </div>

    <div class="nv-grid nv-grid-2 nv-mt">
        <x-card flush>
            <x-slot:title>Front office settlements</x-slot:title>

            @if ($frontOffice->isEmpty())
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="desktop" /></span>
                    <strong>Nothing taken at the desk</strong>
                </div>
            @else
                <div class="nv-table-wrap">
                    <table class="nv-table">
                        <thead>
                            <tr><th>Date</th><th>Folio</th><th>Guest</th><th>Mode</th><th class="is-num">Amount</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($frontOffice as $row)
                                <tr>
                                    <td>{{ \Carbon\CarbonImmutable::parse($row->settle_date)->format('d M') }}</td>
                                    <td>{{ $row->folio_no ?: '—' }}</td>
                                    <td>{{ $row->guest_name ?: '—' }}</td>
                                    <td>{{ $row->pay_mode ?: '—' }}</td>
                                    <td class="is-num">₹ {{ number_format((float) $row->amount, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        <x-card flush>
            <x-slot:title>Point of sale</x-slot:title>

            @if ($pos->isEmpty())
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="bag" /></span>
                    <strong>Nothing taken at the till</strong>
                </div>
            @else
                <div class="nv-table-wrap">
                    <table class="nv-table">
                        <thead>
                            <tr><th>When</th><th>Invoice</th><th>Mode</th><th class="is-num">Amount</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($pos as $row)
                                <tr>
                                    <td>{{ \Carbon\CarbonImmutable::parse($row->paid_at)->format('d M, h:i A') }}</td>
                                    <td>{{ $row->invoice_no ?: '—' }}</td>
                                    <td>{{ $row->pay_mode ?: '—' }}</td>
                                    <td class="is-num">₹ {{ number_format((float) $row->amount, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>
    </div>

    <div class="nv-mt">
        <x-card flush>
            <x-slot:title>Receipt vouchers</x-slot:title>

            @if ($vouchers->isEmpty())
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="file" /></span>
                    <strong>No receipt vouchers posted</strong>
                </div>
            @else
                <div class="nv-table-wrap">
                    <table class="nv-table">
                        <thead>
                            <tr><th>Date</th><th>Voucher</th><th>From</th><th>Narration</th><th class="is-num">Amount</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($vouchers as $voucher)
                                <tr>
                                    <td>{{ $voucher->voucher_date->format('d M') }}</td>
                                    <td><strong>{{ $voucher->voucher_no }}</strong></td>
                                    <td>
                                        {{ $voucher->entries->filter(fn ($e) => (float) $e->credit > 0)
                                            ->map(fn ($e) => $e->ledger?->name)->filter()->implode(', ') ?: '—' }}
                                    </td>
                                    <td>{{ $voucher->narration ?: '—' }}</td>
                                    <td class="is-num">₹ {{ number_format((float) $voucher->amount, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>
    </div>
@endsection
