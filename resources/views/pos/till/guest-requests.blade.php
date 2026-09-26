@extends('layouts.app')

@section('title', 'Guest Requests')

@section('content')
    <x-page-header
        title="Guest Requests"
        subtitle="What guests have asked for from their own phones, by scanning a table's QR"
        :crumbs="['Home' => url('/'), 'POS' => route('point-of-sale.pos'), 'Guest Requests']"
    />

    @include('pos.till.partials.nav', ['current' => 'guest-requests'])

    @if (session('error'))
        <div class="nv-mt"><x-alert tone="danger" title="Not sent">{{ session('error') }}</x-alert></div>
    @endif

    <div class="nv-grid nv-grid-3 nv-mt">
        <x-stat label="Waiting on you" :value="$pending->count()" icon="bell" :tone="$pending->isNotEmpty() ? 'warning' : 'success'" />
        <x-stat label="Items waiting" :value="(int) $pending->sum(fn ($r) => $r->itemCount())" icon="bag" />
        <x-stat label="Decided recently" :value="$decided->count()" icon="check-circle" />
    </div>

    <div class="nv-mt">
        <x-card flush>
            <x-slot:title>{{ $pending->count() }} waiting</x-slot:title>

            @forelse ($pending as $row)
                <div class="nv-gr-row">
                    <div class="nv-gr-main">
                        <div class="nv-gr-where">
                            <strong>{{ $row->table?->name ? 'Table ' . $row->table->name : 'Table' }}</strong>
                            <span class="nv-muted">· {{ $row->created_at->diffForHumans() }}</span>
                        </div>

                        <ul class="nv-gr-items">
                            @foreach ($row->items as $i)
                                <li>{{ rtrim(rtrim(number_format((float) $i['qty'], 2), '0'), '.') }} × {{ $i['name'] }}</li>
                            @endforeach
                        </ul>

                        @if ($row->guest_note)
                            <p class="nv-gr-note"><x-icon name="info" /> “{{ $row->guest_note }}”</p>
                        @endif
                    </div>

                    <div class="nv-gr-actions">
                        <form method="POST" action="{{ route('point-of-sale.pos.guest-requests.approve', $row->id) }}">
                            @csrf
                            <button type="submit" class="nv-btn nv-btn-primary nv-btn-sm">
                                <x-icon name="check" /> Accept &amp; send to kitchen
                            </button>
                        </form>

                        <form method="POST" action="{{ route('point-of-sale.pos.guest-requests.reject', $row->id) }}"
                              class="nv-gr-decline">
                            @csrf
                            <input type="text" name="reason" maxlength="255" placeholder="Reason (optional)" class="nv-input nv-input-sm" />
                            <button type="submit" class="nv-btn nv-btn-ghost nv-btn-sm">
                                <x-icon name="x" /> Decline
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="check-circle" /></span>
                    <strong>Nothing waiting</strong>
                    <p>Every guest request has been accepted or declined.</p>
                </div>
            @endforelse
        </x-card>
    </div>

    @if ($decided->isNotEmpty())
        <div class="nv-mt">
            <x-card flush>
                <x-slot:title>Recently decided</x-slot:title>

                <div class="nv-table-wrap">
                    <table class="nv-table">
                        <thead>
                            <tr>
                                <th>Where</th>
                                <th>Items</th>
                                <th>Sent</th>
                                <th>Decided</th>
                                <th>State</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($decided as $row)
                                <tr>
                                    <td>{{ $row->table?->name ? 'Table ' . $row->table->name : '—' }}</td>
                                    <td>{{ collect($row->items)->pluck('name')->implode(', ') }}</td>
                                    <td>{{ $row->created_at->format('h:i A') }}</td>
                                    <td>{{ $row->decided_at?->format('h:i A') ?: '—' }}</td>
                                    <td>
                                        <x-badge :tone="$row->status === 'approved' ? 'success' : 'danger'">
                                            {{ \App\Models\Pos\PosGuestRequest::STATUSES[$row->status] ?? $row->status }}
                                        </x-badge>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>
    @endif
@endsection
