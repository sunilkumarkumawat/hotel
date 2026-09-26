@extends('layouts.app')

@section('title', 'Pre Reg Card')

@php
    $tones = [
        'confirmed' => 'success', 'tentative' => 'warning',
        'checked_in' => 'primary', 'checked_out' => 'info',
    ];

    // Nothing prints usefully on a letterhead with no hotel details on it.
    $ready = $branch && $branch->gst_no && $branch->address;
@endphp

@section('content')
    <x-page-header
        title="Pre Reg Card"
        subtitle="The Guest Registration Card the guest signs at the desk — printed before they arrive."
        :crumbs="['Home' => url('/'), 'Front Office', 'Pre Reg Card']"
    >
        <x-slot:actions>
            <a href="{{ route('front-office.pre-reg-card.blank') }}" target="_blank" class="nv-btn nv-btn-outline">
                <x-icon name="file" /> Blank card
            </a>
        </x-slot:actions>
    </x-page-header>

    @unless ($ready)
        <div class="nv-mt">
            <x-alert tone="warning" title="The letterhead is incomplete">
                This branch has no {{ $branch?->gst_no ? 'address' : 'GSTIN' }} saved, so the card will print
                without it. Fill it in under
                @canEdit('viewBranch')
                    <a href="{{ route('viewBranch.edit', $branch) }}">Administration → Branches → {{ $branch?->branch_name }}</a>.
                @else
                    Administration → Branches.
                @endCanEdit
            </x-alert>
        </div>
    @endunless

    <div class="nv-mt">
        <x-card flush>
            <form method="GET" class="nv-toolbar">
                <div class="nv-field-search">
                    <x-icon name="search" />
                    <input type="search" name="q" value="{{ $filters['q'] }}" class="nv-input"
                           placeholder="Reservation no., guest or mobile…" />
                </div>

                <input type="date" name="from" value="{{ $filters['from'] }}" class="nv-input" style="width:160px" />
                <input type="date" name="to" value="{{ $filters['to'] }}" class="nv-input" style="width:160px" />

                <select name="per_page" class="nv-select" style="width:130px" onchange="this.form.submit()">
                    @foreach ([15, 25, 50, 100] as $size)
                        <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }} per page</option>
                    @endforeach
                </select>

                <button type="submit" class="nv-btn nv-btn-outline"><x-icon name="filter" /> Search</button>

                <a href="{{ route('front-office.pre-reg-card') }}" class="nv-btn nv-btn-ghost">Reset</a>
            </form>

            @if ($arrivals->count())
                <div class="nv-table-wrap">
                    <table class="nv-table nv-table-compact">
                        <thead>
                            <tr>
                                <th>Reservation</th>
                                <th>Guest</th>
                                <th>Arrival</th>
                                <th>Rooms</th>
                                <th class="is-num">Pax</th>
                                <th>Status</th>
                                <th class="is-end">Card</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($arrivals as $reservation)
                                @php
                                    $first = $reservation->rooms->first();
                                    $pax = $reservation->rooms->sum(fn ($r) => $r->male + $r->female + $r->child);
                                @endphp

                                <tr>
                                    <td class="nv-nowrap">
                                        <strong class="nv-mono">{{ $reservation->reservation_no }}</strong>
                                        <span class="nv-sub">{{ $reservation->reservation_date->format('d M Y') }}</span>
                                    </td>

                                    <td>
                                        <strong>{{ $reservation->guest_name }}</strong>
                                        <span class="nv-sub">{{ $reservation->mobile }}</span>
                                    </td>

                                    <td class="nv-nowrap nv-muted">
                                        @if ($first)
                                            {{ $first->arrival_date->format('d M Y') }}
                                            <span class="nv-sub">{{ substr((string) $first->arrival_time, 0, 5) }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>

                                    <td>
                                        {{ $reservation->rooms->sum('no_of_rooms') }}
                                        <span class="nv-sub">{{ $reservation->rooms->first()?->category?->name }}</span>
                                    </td>

                                    <td class="is-num">{{ $pax }}</td>

                                    <td>
                                        <x-badge :tone="$tones[$reservation->status] ?? null">
                                            {{ \App\Models\Reservation\Reservation::STATUSES[$reservation->status] }}
                                        </x-badge>
                                    </td>

                                    <td class="is-end">
                                        <div class="nv-row-actions">
                                            <a href="{{ route('front-office.pre-reg-card.show', $reservation) }}"
                                               target="_blank" class="nv-btn nv-btn-ghost nv-btn-sm"
                                               aria-label="Open card for {{ $reservation->reservation_no }}">
                                                <x-icon name="eye" />
                                            </a>

                                            <a href="{{ route('front-office.pre-reg-card.show', [$reservation, 'auto' => 1]) }}"
                                               target="_blank" class="nv-btn nv-btn-outline nv-btn-sm">
                                                Print
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="file" /></span>
                    <strong>No arrivals in these dates</strong>
                    <p>Widen the dates, or print a blank card for a walk-in.</p>
                    <a href="{{ route('front-office.pre-reg-card.blank') }}" target="_blank" class="nv-btn nv-btn-primary">
                        <x-icon name="file" /> Blank card
                    </a>
                </div>
            @endif

            <x-slot:footer>
                {{ $arrivals->links() }}
            </x-slot:footer>
        </x-card>
    </div>
@endsection
