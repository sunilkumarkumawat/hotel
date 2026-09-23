@extends('layouts.app')

@section('title', $shape['plural'])

@php
    $money = fn ($n) => '₹' . number_format((float) $n, 2);
    $qty = fn ($n) => rtrim(rtrim(number_format((float) $n, 3), '0'), '.');
@endphp

@section('content')
    <x-page-header
        :title="$shape['plural']"
        :subtitle="$shape['blurb']"
        :crumbs="['Home' => url('/'), 'Store', $shape['plural']]"
    >
        <x-slot:actions>
            @canAdd($shape['permission'])
                <a href="{{ route('store.docs.create', $kind) }}" class="nv-btn nv-btn-primary">
                    <x-icon name="plus" /> New {{ strtolower($shape['label']) }}
                </a>
            @endCanAdd
        </x-slot:actions>
    </x-page-header>

    <div class="nv-mt">
        <x-card flush>
            <form method="GET" class="nv-toolbar">
                <div class="nv-field-search">
                    <x-icon name="search" />
                    <input type="search" name="q" value="{{ $filters['q'] }}" class="nv-input"
                           placeholder="Document or invoice number…" />
                </div>

                <x-field label="Status" name="status">
                    <select name="status" class="nv-select">
                        <option value="">Any status</option>
                        @foreach (['draft' => 'Draft', 'posted' => 'Posted', 'partial' => 'Part received',
                                   'closed' => 'Closed', 'cancelled' => 'Cancelled'] as $key => $label)
                            <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </x-field>

                <button type="submit" class="nv-btn nv-btn-primary"><x-icon name="search" /> Search</button>

                @if ($filters['q'] || $filters['status'])
                    <a href="{{ route('store.docs', $kind) }}" class="nv-btn nv-btn-ghost">Reset</a>
                @endif
            </form>

            @if ($docs->isEmpty())
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon :name="$shape['icon']" /></span>
                    <strong>Nothing here yet</strong>
                    <p>{{ $shape['blurb'] }}</p>
                </div>
            @else
                <div class="nv-table-wrap">
                    <table class="nv-table">
                        <thead>
                            <tr>
                                <th>Number</th>
                                <th>Date</th>
                                <th>
                                    {{ match ($shape['party']) {
                                        'vendor' => 'Supplier',
                                        'outlet' => 'Outlet',
                                        default => 'Department',
                                    } }}
                                </th>
                                <th class="is-end">Lines</th>
                                @if (in_array($kind, ['po', 'transfer_out'], true))
                                    <th class="is-end">Received</th>
                                @endif
                                <th class="is-end">Value</th>
                                <th>Status</th>
                                <th class="is-end">&nbsp;</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($docs as $doc)
                                <tr @class(['is-off' => $doc->isCancelled()])>
                                    <td>
                                        <strong>{{ $doc->doc_no }}</strong>
                                        @if ($doc->invoice_no)
                                            <span class="nv-sub">Invoice {{ $doc->invoice_no }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $doc->doc_date->format('d M Y') }}</td>
                                    <td>{{ $doc->party }}</td>
                                    <td class="is-end">{{ $doc->items->count() }}</td>
                                    @if (in_array($kind, ['po', 'transfer_out'], true))
                                        <td class="is-end">
                                            @php
                                                $ordered = $doc->items->sum(fn ($l) => (float) $l->qty);
                                                $got = $doc->items->sum(fn ($l) => (float) $l->received_qty);
                                            @endphp
                                            {{ $qty($got) }} / {{ $qty($ordered) }}
                                        </td>
                                    @endif
                                    <td class="is-end">{{ $money($doc->net_amount) }}</td>
                                    <td><x-badge :tone="$doc->status_tone">{{ ucfirst($doc->status) }}</x-badge></td>
                                    <td class="is-end">
                                        <div class="nv-row-actions">
                                            @if ($kind === 'po' && in_array($doc->status, ['posted', 'partial'], true)
                                                && can_do('store/grn', 'add'))
                                                <a href="{{ route('store.docs.create', ['kind' => 'grn', 'from' => $doc->id]) }}"
                                                   class="nv-btn nv-btn-sm nv-btn-outline">
                                                    <x-icon name="package" /> Receive
                                                </a>
                                            @endif

                                            <a href="{{ route('store.docs.show', ['kind' => $kind, 'doc' => $doc]) }}"
                                               class="nv-btn nv-btn-sm nv-btn-ghost">
                                                <x-icon name="external" /> Open
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="nv-card-foot">{{ $docs->links() }}</div>
            @endif
        </x-card>
    </div>
@endsection
