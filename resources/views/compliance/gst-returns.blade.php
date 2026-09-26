@extends('layouts.app')

@section('title', 'GST Returns')

@php
    use App\Support\Gst;

    $money = fn ($n) => '₹' . number_format((float) $n, 2);
    $t = $return['totals'];
@endphp

@section('content')
    <x-page-header
        title="GST Returns"
        subtitle="The month's outward supplies, split the way GSTR-1 splits them."
        :crumbs="['Home' => url('/'), 'Compliance', 'GST Returns']"
    />

    @if (! $state)
        <div class="nv-mt">
            <x-alert tone="danger" title="This branch has no GST state">
                Every table below needs to know which state the hotel supplies from. Set the GSTIN — or the
                state code directly — under <a href="{{ url('branches') }}">Administration → Branches</a>.
                Until then the place of supply is blank and the return cannot be filed.
            </x-alert>
        </div>
    @endif

    <div class="nv-mt">
        <x-card>
            <form method="GET" class="nv-toolbar">
                <x-field label="Month" name="month" help="Defaults to last month — the one you file for.">
                    <input type="month" name="month" value="{{ $month }}" class="nv-input" />
                </x-field>

                <button type="submit" class="nv-btn nv-btn-primary"><x-icon name="search" /> Show</button>

                <span class="nv-toolbar-spacer"></span>

                <span class="nv-cm-state">
                    <x-icon name="shield" />
                    <span>
                        <strong>{{ $branch?->gst_no ?: 'No GSTIN set' }}</strong>
                        <small>{{ $stateName ? $state . ' — ' . $stateName : 'State not set' }}</small>
                    </span>
                </span>
            </form>
        </x-card>
    </div>

    <div class="nv-grid nv-grid-4 nv-mt">
        <x-stat label="Invoices" :value="$t['invoices']" icon="file" />
        <x-stat label="Taxable value" :value="$money($t['taxable'])" icon="wallet" tone="info" />
        <x-stat label="Tax" :value="$money($t['tax'])" icon="chart" tone="warning"
                caption="Half CGST, half SGST" />
        <x-stat label="Invoice value" :value="$money($t['net'])" icon="trending-up" tone="success" />
    </div>

    {{-- ── B2B ───────────────────────────────────────────────────────────── --}}
    <div class="nv-mt">
        <x-card title="B2B — {{ $t['b2b'] }} {{ \Illuminate\Support\Str::plural('invoice', $t['b2b']) }}"
                subtitle="Sold to somebody with a GSTIN. Listed one by one, because the buyer has to find their own invoice in it."
                :flush="true">
            <x-slot:actions>
                <a href="{{ route('compliance.gst.export', ['table' => 'b2b', 'month' => $month]) }}"
                   class="nv-btn nv-btn-sm nv-btn-outline"><x-icon name="download" /> CSV</a>
            </x-slot:actions>

            @if ($return['b2b']->isEmpty())
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="inbox" /></span>
                    <strong>No B2B invoices this month</strong>
                    <p>
                        A bill lands here when it carries the buyer's GSTIN. Enter it on the checkout screen
                        before the bill is made — it cannot be added afterwards without re-issuing.
                    </p>
                </div>
            @else
                <div class="nv-table-wrap">
                    <table class="nv-table nv-table-compact">
                        <thead>
                            <tr>
                                <th>GSTIN</th>
                                <th>Buyer</th>
                                <th>Invoice</th>
                                <th>Date</th>
                                <th>Place of supply</th>
                                <th class="is-end">Rate</th>
                                <th class="is-end">Taxable</th>
                                <th class="is-end">Tax</th>
                                <th class="is-end">Invoice value</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($return['b2b'] as $bill)
                                @foreach ($bill->rates as $i => $rate)
                                    <tr>
                                        @if ($i === 0)
                                            <td rowspan="{{ $bill->rates->count() }}">
                                                <strong>{{ strtoupper($bill->buyer_gstin) }}</strong>
                                            </td>
                                            <td rowspan="{{ $bill->rates->count() }}">
                                                {{ $bill->buyer_name ?: $bill->guest_name }}
                                            </td>
                                            <td rowspan="{{ $bill->rates->count() }}">{{ $bill->bill_no }}</td>
                                            <td rowspan="{{ $bill->rates->count() }}">
                                                {{ \Carbon\CarbonImmutable::parse($bill->bill_date)->format('d/m/Y') }}
                                            </td>
                                            <td rowspan="{{ $bill->rates->count() }}">
                                                {{ Gst::stateLabel($bill->place_of_supply) ?: '—' }}
                                            </td>
                                        @endif

                                        <td class="is-end">{{ rtrim(rtrim(number_format($rate['rate'], 2), '0'), '.') }}%</td>
                                        <td class="is-end">{{ $money($rate['taxable']) }}</td>
                                        <td class="is-end">{{ $money($rate['tax']) }}</td>

                                        @if ($i === 0)
                                            <td class="is-end" rowspan="{{ $bill->rates->count() }}">
                                                <strong>{{ $money($bill->net_amount) }}</strong>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>
    </div>

    {{-- ── B2CS ──────────────────────────────────────────────────────────── --}}
    <div class="nv-grid nv-grid-2 nv-mt">
        <x-card title="B2CS — summarised"
                subtitle="Everybody without a GSTIN. One row per place of supply per rate."
                :flush="true">
            <x-slot:actions>
                <a href="{{ route('compliance.gst.export', ['table' => 'b2cs', 'month' => $month]) }}"
                   class="nv-btn nv-btn-sm nv-btn-outline"><x-icon name="download" /> CSV</a>
            </x-slot:actions>

            @if ($return['b2cs']->isEmpty())
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="inbox" /></span>
                    <strong>Nothing to summarise</strong>
                    <p>No walk-in bills this month.</p>
                </div>
            @else
                <div class="nv-table-wrap">
                    <table class="nv-table nv-table-compact">
                        <thead>
                            <tr>
                                <th>Place of supply</th>
                                <th class="is-end">Rate</th>
                                <th class="is-end">Taxable</th>
                                <th class="is-end">Tax</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($return['b2cs'] as $row)
                                <tr>
                                    <td>{{ Gst::stateLabel($row['place']) ?: '—' }}</td>
                                    <td class="is-end">{{ rtrim(rtrim(number_format($row['rate'], 2), '0'), '.') }}%</td>
                                    <td class="is-end">{{ $money($row['taxable']) }}</td>
                                    <td class="is-end">{{ $money($row['tax']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        {{-- ── HSN ───────────────────────────────────────────────────────── --}}
        <x-card title="HSN / SAC summary"
                subtitle="What was sold, by code and rate."
                :flush="true">
            <x-slot:actions>
                <a href="{{ route('compliance.gst.export', ['table' => 'hsn', 'month' => $month]) }}"
                   class="nv-btn nv-btn-sm nv-btn-outline"><x-icon name="download" /> CSV</a>
            </x-slot:actions>

            @if ($return['hsn']->isEmpty())
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="inbox" /></span>
                    <strong>Nothing sold this month</strong>
                    <p>No bills were raised in this period.</p>
                </div>
            @else
                <div class="nv-table-wrap">
                    <table class="nv-table nv-table-compact">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Description</th>
                                <th class="is-end">Rate</th>
                                <th class="is-end">Taxable</th>
                                <th class="is-end">Tax</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($return['hsn'] as $row)
                                <tr>
                                    <td>{{ $row['code'] }}</td>
                                    <td>{{ $row['description'] }}</td>
                                    <td class="is-end">{{ rtrim(rtrim(number_format($row['rate'], 2), '0'), '.') }}%</td>
                                    <td class="is-end">{{ $money($row['taxable']) }}</td>
                                    <td class="is-end">{{ $money($row['tax']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>
    </div>

    {{-- ── B2CL, when there is any ───────────────────────────────────────── --}}
    @if ($return['b2cl']->isNotEmpty())
        <div class="nv-mt">
            <x-card title="B2CL — {{ $t['b2cl'] }} {{ \Illuminate\Support\Str::plural('invoice', $t['b2cl']) }}"
                    subtitle="Unregistered buyers in another state, above the invoice threshold."
                    :flush="true">
                <x-slot:actions>
                    <a href="{{ route('compliance.gst.export', ['table' => 'b2cl', 'month' => $month]) }}"
                       class="nv-btn nv-btn-sm nv-btn-outline"><x-icon name="download" /> CSV</a>
                </x-slot:actions>

                <div class="nv-table-wrap">
                    <table class="nv-table nv-table-compact">
                        <thead>
                            <tr>
                                <th>Invoice</th><th>Date</th><th>Buyer</th><th>Place of supply</th>
                                <th class="is-end">Invoice value</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($return['b2cl'] as $bill)
                                <tr>
                                    <td>{{ $bill->bill_no }}</td>
                                    <td>{{ \Carbon\CarbonImmutable::parse($bill->bill_date)->format('d/m/Y') }}</td>
                                    <td>{{ $bill->buyer_name ?: $bill->guest_name }}</td>
                                    <td>{{ Gst::stateLabel($bill->place_of_supply) ?: '—' }}</td>
                                    <td class="is-end">{{ $money($bill->net_amount) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>
    @endif

    <div class="nv-mt">
        <x-alert tone="info" title="Place of supply for a hotel">
            Accommodation is supplied where the hotel is, whoever the guest is and wherever they came from.
            So a room sold to a Bengaluru company by a hotel in {{ $stateName ?: 'this state' }} is CGST + SGST,
            not IGST — which is the opposite of how every other B2B sale works, and the thing most often got
            wrong. These figures are read from bills exactly as they were issued; nothing on this screen
            recalculates tax. Have your accountant check the treatment of anything beyond room rent before
            you file.
        </x-alert>
    </div>
@endsection
