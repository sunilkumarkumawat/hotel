@extends('layouts.app')

@section('title', 'Guests')

@php
    use App\Support\GuestCrm;

    $money = fn ($n) => '₹' . number_format((float) $n, 0);
    $sortUrl = fn (string $by) => request()->fullUrlWithQuery(['sort' => $by]);
@endphp

@section('content')
    <x-page-header
        title="Guests"
        subtitle="The person, rather than the booking — what they spend, what they ask for, and when they last came."
        :crumbs="['Home' => url('/'), 'Guest CRM', 'Guests']"
    >
        <x-slot:actions>
            @canView('crm/occasions')
                <a href="{{ route('crm.occasions') }}" class="nv-btn nv-btn-outline">
                    <x-icon name="calendar" /> Birthdays
                </a>
            @endCanView
            @canView('crm/feedback')
                <a href="{{ route('crm.feedback') }}" class="nv-btn nv-btn-primary">
                    <x-icon name="star" /> Feedback
                </a>
            @endCanView
        </x-slot:actions>
    </x-page-header>

    <div class="nv-grid nv-grid-6">
        <x-stat label="Guests on file" :value="number_format($counts['all'])" icon="users"
                caption="All registered guests" />
        <x-stat label="Have been back" :value="number_format($counts['returning'])" icon="refresh" tone="success"
                :caption="$counts['all'] ? round($counts['returning'] / max(1, $counts['all']) * 100) . '% of the book' : null" />
        <x-stat label="New this year" :value="number_format($counts['new'])" icon="sparkles" tone="info"
                :caption="'First stay in ' . now()->year" />
        <x-stat label="VIP guests" :value="number_format($counts['vip'])" icon="star" tone="warning"
                caption="Gold and Platinum" />
        <x-stat label="Lifetime value" :value="$money($counts['spend'])" icon="wallet"
                caption="Everything ever billed" />
        <x-stat label="Blacklisted" :value="number_format($counts['blacklisted'])" icon="shield"
                :tone="$counts['blacklisted'] ? 'danger' : 'muted'" />
    </div>

    <div class="nv-mt">
        <x-card flush>
            <form method="GET" class="nv-toolbar">
                <div class="nv-field-search">
                    <x-icon name="search" />
                    <input type="search" name="q" value="{{ $filters['q'] }}" class="nv-input"
                           placeholder="Name, mobile or email…" />
                </div>

                <x-field label="Tier" name="tier">
                    <select name="tier" class="nv-select">
                        <option value="">Any tier</option>
                        @foreach ($tiers as $key => $tier)
                            <option value="{{ $key }}" @selected($filters['tier'] === $key)>{{ $tier['label'] }}</option>
                        @endforeach
                    </select>
                </x-field>

                <label class="nv-check-inline">
                    <input type="checkbox" name="returning" value="1" @checked($filters['returning']) />
                    Been back
                </label>

                <label class="nv-check-inline">
                    <input type="checkbox" name="blacklisted" value="1" @checked($filters['blacklisted']) />
                    Blacklisted
                </label>

                <button type="submit" class="nv-btn nv-btn-primary"><x-icon name="search" /> Search</button>

                @if (array_filter([$filters['q'], $filters['tier'], $filters['returning'], $filters['blacklisted']]))
                    <a href="{{ route('crm.guests') }}" class="nv-btn nv-btn-ghost">Reset</a>
                @endif
            </form>

            @if ($guests->isEmpty())
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="users" /></span>
                    <strong>No guests match</strong>
                    <p>Guests appear here as bookings are taken — every booking makes or finds one.</p>
                </div>
            @else
                <div class="nv-table-wrap">
                    <table class="nv-table">
                        <thead>
                            <tr>
                                <th><a href="{{ $sortUrl('name') }}">Guest</a></th>
                                <th>Contact</th>
                                <th>Tier</th>
                                <th class="is-end"><a href="{{ $sortUrl('stays') }}">Stays</a></th>
                                <th class="is-end"><a href="{{ $sortUrl('total_spend') }}">Spend</a></th>
                                <th class="is-end">Points</th>
                                <th><a href="{{ $sortUrl('last_stay_at') }}">Last here</a></th>
                                <th class="is-end">&nbsp;</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($guests as $guest)
                                <tr @class(['is-off' => $guest->is_blacklisted])>
                                    <td>
                                        <div class="nv-crm-who">
                                            <x-avatar :name="$guest->name" size="sm" />
                                            <span>
                                                <strong>{{ $guest->name }}</strong>
                                                @if ($guest->is_blacklisted)
                                                    <span class="nv-sub nv-crm-bad">Blacklisted{{ $guest->blacklist_reason ? ' — ' . $guest->blacklist_reason : '' }}</span>
                                                @elseif ($guest->company)
                                                    <span class="nv-sub">{{ $guest->company->name }}</span>
                                                @endif
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        {{ $guest->mobile ?: '—' }}
                                        @if ($guest->email)
                                            <span class="nv-sub">{{ $guest->email }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="nv-crm-tier is-{{ $guest->tier ?: 'guest' }}">{{ $guest->tier_label }}</span>
                                    </td>
                                    <td class="is-end">{{ number_format((int) $guest->stays) }}</td>
                                    <td class="is-end">{{ $money($guest->total_spend) }}</td>
                                    <td class="is-end">{{ number_format((int) $guest->loyalty_points) }}</td>
                                    <td>
                                        @if ($guest->last_stay_at)
                                            {{ $guest->last_stay_at->format('d M Y') }}
                                            <span class="nv-sub">{{ $guest->last_stay_at->diffForHumans() }}</span>
                                        @else
                                            <span class="nv-muted">Never</span>
                                        @endif
                                    </td>
                                    <td class="is-end">
                                        <a href="{{ route('crm.guests.show', $guest) }}"
                                           class="nv-btn nv-btn-sm nv-btn-ghost" title="View profile">
                                            <x-icon name="eye" />
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="nv-card-foot">{{ $guests->links() }}</div>
            @endif
        </x-card>
    </div>
@endsection
