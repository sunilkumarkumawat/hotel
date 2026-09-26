@extends('layouts.app')

@section('title', 'Issue House Keeping')

@php
    $money = fn ($n) => '₹' . number_format((float) $n, 2);
    $qty = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
@endphp

@section('content')
    <x-page-header
        title="Issue House Keeping"
        subtitle="Linen going out to the laundry. One note per vendor, per day."
        :crumbs="['Home' => url('/'), 'House Keeping', 'Issue']"
    >
        <x-slot:actions>
            @canView('house-keeping/received')
                <a href="{{ route('house-keeping.received') }}" class="nv-btn nv-btn-outline">
                    <x-icon name="download" /> Received
                </a>
            @endCanView

            @canAdd('house-keeping/issue')
                <a href="{{ route('house-keeping.issue.create') }}" class="nv-btn nv-btn-primary">
                    <x-icon name="plus" /> Add Issue
                </a>
            @endCanAdd
        </x-slot:actions>
    </x-page-header>

    @if (session('error'))
        <div class="nv-mt"><x-alert tone="danger" title="Not saved">{{ session('error') }}</x-alert></div>
    @endif

    <div class="nv-grid nv-grid-4">
        <x-stat label="Notes" :value="$counts['notes']" icon="file" />
        <x-stat label="Out with vendors" :value="$qty($counts['out']) . ' pcs'" icon="upload" tone="warning" />
        <x-stat label="This month" :value="$money($counts['month'])" icon="wallet" tone="info" />
        <x-stat label="Items set up" :value="$counts['items']" icon="layers" tone="success" />
    </div>

    <div class="nv-mt">
        <x-card>
            <form method="GET" class="nv-toolbar">
                <div class="nv-field-search">
                    <x-icon name="search" />
                    <input type="search" name="q" value="{{ $filters['q'] }}" class="nv-input"
                           placeholder="Issue no. or vendor…" />
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
                <a href="{{ route('house-keeping.issue') }}" class="nv-btn nv-btn-ghost">Reset</a>
            </form>
        </x-card>
    </div>

    <div class="nv-mt">
        <x-card flush>
            @if ($issues->count())
                <div class="nv-table-wrap">
                    <table class="nv-table">
                        <thead>
                            <tr>
                                <th>Issue No</th>
                                <th>Vendor</th>
                                <th>Issue Date</th>
                                <th>Items</th>
                                <th class="is-num">Pieces</th>
                                <th class="is-num">Amount</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($issues as $issue)
                                <tr>
                                    <td><strong class="nv-mono">{{ $issue->issue_no }}</strong></td>

                                    <td>
                                        {{ $issue->vendor?->name ?? '—' }}
                                        @if ($issue->remark)
                                            <span class="nv-sub">{{ $issue->remark }}</span>
                                        @endif
                                    </td>

                                    <td>{{ $issue->issue_date->format('d M Y') }}</td>

                                    <td>{{ $issue->lines->count() }} line(s)</td>

                                    <td class="is-num">{{ $qty($issue->total_qty) }}</td>

                                    <td class="is-num"><strong>{{ $money($issue->total_amount) }}</strong></td>

                                    <td>
                                        <div class="nv-row-actions">
                                            @canView('house-keeping/issue')
                                                <a href="{{ route('house-keeping.issue.show', $issue) }}"
                                                   class="nv-btn nv-btn-ghost nv-btn-sm"
                                                   aria-label="Open {{ $issue->issue_no }}">
                                                    <x-icon name="external" />
                                                </a>
                                            @endCanView

                                            @canDelete('house-keeping/issue')
                                                <form method="POST"
                                                      action="{{ route('house-keeping.issue.destroy', $issue) }}"
                                                      data-confirm="Those pieces go back onto {{ $issue->vendor?->name ?? 'the vendor' }}'s outstanding list."
                                                      data-confirm-title="Delete {{ $issue->issue_no }}?"
                                                      data-confirm-action="Delete">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="nv-btn nv-btn-ghost nv-btn-sm"
                                                            aria-label="Delete {{ $issue->issue_no }}">
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

                <x-slot:footer>{{ $issues->links() }}</x-slot:footer>
            @else
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="file" /></span>
                    <strong>No issues found.</strong>
                    <p>
                        Press <b>Add Issue</b> to write the first note. The items and the laundry
                        are set up on that screen — there is nothing to fill in first.
                    </p>
                </div>
            @endif
        </x-card>
    </div>
@endsection
