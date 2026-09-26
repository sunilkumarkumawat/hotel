@extends('layouts.app')

@section('title', 'Received House Keeping')

@php $qty = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.'); @endphp

@section('content')
    <x-page-header
        title="Received House Keeping"
        subtitle="Linen coming back from the laundry. This is what closes an issue."
        :crumbs="['Home' => url('/'), 'House Keeping', 'Received']"
    >
        <x-slot:actions>
            @canView('house-keeping/issue')
                <a href="{{ route('house-keeping.issue') }}" class="nv-btn nv-btn-outline">
                    <x-icon name="upload" /> Issue
                </a>
            @endCanView

            @canAdd('house-keeping/received')
                <a href="{{ route('house-keeping.received.create') }}" class="nv-btn nv-btn-primary">
                    <x-icon name="plus" /> Add Received
                </a>
            @endCanAdd
        </x-slot:actions>
    </x-page-header>

    @if (session('error'))
        <div class="nv-mt"><x-alert tone="danger" title="Not saved">{{ session('error') }}</x-alert></div>
    @endif

    <div class="nv-grid nv-grid-4">
        <x-stat label="Notes" :value="$counts['notes']" icon="file" />
        <x-stat label="Still out" :value="$qty($counts['out']) . ' pcs'" icon="upload" tone="warning" />
        <x-stat label="Kinds out" :value="$counts['kinds']" icon="layers" tone="info" />
        <x-stat label="Back this month" :value="$qty($counts['month']) . ' pcs'" icon="download" tone="success" />
    </div>

    {{-- What is still with the laundries, which is the reason to open this
         screen at all. --}}
    @if ($outstanding->isNotEmpty())
        <div class="nv-mt">
            <x-card title="Out with vendors right now"
                    subtitle="Across every laundry. Pick a vendor on the Add Received screen to see whose.">
                <div class="nv-chips">
                    @foreach ($outstanding as $row)
                        <span class="nv-chip">
                            <strong>{{ $row['name'] }}</strong>
                            <span>{{ $qty($row['qty']) }} {{ $row['unit'] }}</span>
                        </span>
                    @endforeach
                </div>
            </x-card>
        </div>
    @endif

    <div class="nv-mt">
        <x-card>
            <form method="GET" class="nv-toolbar">
                <div class="nv-field-search">
                    <x-icon name="search" />
                    <input type="search" name="q" value="{{ $filters['q'] }}" class="nv-input"
                           placeholder="Receipt no. or vendor…" />
                </div>

                <select name="vendor" class="nv-select" style="width:200px">
                    <option value="">All vendors</option>
                    @foreach ($vendors as $vendor)
                        <option value="{{ $vendor->id }}" @selected($filters['vendor'] === $vendor->id)>
                            {{ $vendor->name }}
                        </option>
                    @endforeach
                </select>

                <input type="date" name="from" value="{{ $filters['from'] }}" class="nv-input" style="width:160px" />
                <input type="date" name="to" value="{{ $filters['to'] }}" class="nv-input" style="width:160px" />

                <button type="submit" class="nv-btn nv-btn-outline"><x-icon name="filter" /> Search</button>
                <a href="{{ route('house-keeping.received') }}" class="nv-btn nv-btn-ghost">Reset</a>
            </form>
        </x-card>
    </div>

    <div class="nv-mt">
        <x-card flush>
            @if ($receipts->count())
                <div class="nv-table-wrap">
                    <table class="nv-table">
                        <thead>
                            <tr>
                                <th>Receipt No</th>
                                <th>Vendor</th>
                                <th>Receive Date</th>
                                <th>Items</th>
                                <th class="is-num">Received</th>
                                <th class="is-num">Written off</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($receipts as $receipt)
                                @php
                                    $lost = $receipt->lines->sum('damaged_qty') + $receipt->lines->sum('missing_qty');
                                @endphp

                                <tr>
                                    <td><strong class="nv-mono">{{ $receipt->receipt_no }}</strong></td>

                                    <td>
                                        {{ $receipt->vendor?->name ?? '—' }}
                                        @if ($receipt->remark)
                                            <span class="nv-sub">{{ $receipt->remark }}</span>
                                        @endif
                                    </td>

                                    <td>{{ $receipt->receive_date->format('d M Y') }}</td>

                                    <td>{{ $receipt->lines->count() }} line(s)</td>

                                    <td class="is-num"><strong>{{ $qty($receipt->total_qty) }}</strong></td>

                                    <td class="is-num">
                                        @if ($lost > 0)
                                            <x-badge tone="danger">{{ $qty($lost) }}</x-badge>
                                        @else
                                            <span class="nv-muted">—</span>
                                        @endif
                                    </td>

                                    <td>
                                        <div class="nv-row-actions">
                                            @canView('house-keeping/received')
                                                <a href="{{ route('house-keeping.received.show', $receipt) }}"
                                                   class="nv-btn nv-btn-ghost nv-btn-sm"
                                                   aria-label="Open {{ $receipt->receipt_no }}">
                                                    <x-icon name="external" />
                                                </a>
                                            @endCanView

                                            @canDelete('house-keeping/received')
                                                <form method="POST"
                                                      action="{{ route('house-keeping.received.destroy', $receipt) }}"
                                                      data-confirm="Those pieces go back onto {{ $receipt->vendor?->name ?? 'the vendor' }}'s outstanding list."
                                                      data-confirm-title="Delete {{ $receipt->receipt_no }}?"
                                                      data-confirm-action="Delete">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="nv-btn nv-btn-ghost nv-btn-sm"
                                                            aria-label="Delete {{ $receipt->receipt_no }}">
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

                <x-slot:footer>{{ $receipts->links() }}</x-slot:footer>
            @else
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="download" /></span>
                    <strong>Nothing received yet.</strong>
                    <p>
                        @if ($counts['out'] > 0)
                            {{ $qty($counts['out']) }} piece(s) are out with the laundry — press
                            <b>Add Received</b> when the van comes back.
                        @else
                            Write an issue note first; there is nothing out to come back.
                        @endif
                    </p>
                </div>
            @endif
        </x-card>
    </div>
@endsection
