@extends('layouts.app')

@section('title', 'Feedback')

@php
    $tab = fn (string $name) => request()->fullUrlWithQuery(['view' => $name, 'page' => null]);

    $score = fn (?float $n) => $n === null ? '—' : number_format($n, 1);

    /* Four is not "good". It is the score of a guest who found something wrong
       and did not say what, so only five is green. */
    $tone = fn (?float $n) => match (true) {
        $n === null => 'muted',
        $n >= 4.5 => 'success',
        $n >= 4 => 'info',
        $n >= 3 => 'warning',
        default => 'danger',
    };
@endphp

@section('content')
    <x-page-header
        title="Feedback"
        subtitle="What guests said after they left — and what was done about it."
        :crumbs="['Home' => url('/'), 'Guest CRM', 'Feedback']"
    >
        <x-slot:actions>
            <div class="nv-tabs">
                <a href="{{ $tab('attention') }}" @class(['nv-tab', 'is-active' => $view === 'attention'])>
                    <x-icon name="alert" /> Needs attention
                    @if ($summary['unhandled'] > 0)
                        <span class="nv-crm-count">{{ $summary['unhandled'] }}</span>
                    @endif
                </a>
                <a href="{{ $tab('all') }}" @class(['nv-tab', 'is-active' => $view === 'all'])>
                    <x-icon name="inbox" /> Everything
                </a>
                <a href="{{ $tab('unanswered') }}" @class(['nv-tab', 'is-active' => $view === 'unanswered'])>
                    <x-icon name="clock" /> Asked, no reply
                </a>
            </div>
        </x-slot:actions>
    </x-page-header>

    <div class="nv-grid nv-grid-5">
        <x-stat label="Total feedback" :value="number_format($summary['answered'])" icon="inbox"
                :caption="$summary['sent'] . ' asked · ' . $summary['rate'] . '% replied'" />
        <x-stat label="Average rating" :value="$score($summary['overall'])" icon="star" :tone="$tone($summary['overall'])"
                caption="Out of 5, this period" />
        <x-stat label="Positive" :value="number_format($summary['positive'])" icon="check-circle" tone="success"
                :caption="$summary['answered'] ? round($summary['positive'] / $summary['answered'] * 100) . '% of replies' : null" />
        <x-stat label="Negative" :value="number_format($summary['detractors'])" icon="alert"
                :tone="$summary['detractors'] ? 'warning' : 'success'"
                caption="3 or below" />
        <x-stat label="Not dealt with" :value="number_format($summary['unhandled'])" icon="x-circle"
                :tone="$summary['unhandled'] ? 'danger' : 'success'"
                caption="Somebody has to ring these" />
    </div>

    {{-- ── The areas, side by side ───────────────────────────────────────── --}}
    <div class="nv-mt">
        <x-card title="By area" subtitle="Where the score is coming from.">
            <div class="nv-crm-scores">
                @foreach ($summary['scores'] as $label => $value)
                    <div class="nv-crm-score">
                        <span class="nv-crm-score-label">{{ $label }}</span>
                        <span @class(['nv-crm-score-value', 'is-' . $tone($value)])>{{ $score($value) }}</span>
                        <span class="nv-crm-score-bar">
                            <i style="width: {{ $value ? round($value / 5 * 100) : 0 }}%"
                               class="is-{{ $tone($value) }}"></i>
                        </span>
                    </div>
                @endforeach
            </div>
        </x-card>
    </div>

    <div class="nv-mt">
        <x-card flush>
            <form method="GET" class="nv-toolbar">
                <input type="hidden" name="view" value="{{ $view }}" />

                <x-field label="From" name="from"><x-input type="date" name="from" :value="$from" /></x-field>
                <x-field label="To" name="to"><x-input type="date" name="to" :value="$to" /></x-field>

                <button type="submit" class="nv-btn nv-btn-primary"><x-icon name="search" /> Show</button>
            </form>

            @if ($rows->isEmpty())
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="check-circle" /></span>
                    <strong>
                        {{ $view === 'attention' ? 'Nothing waiting' : 'Nothing here' }}
                    </strong>
                    <p>
                        @if ($view === 'attention')
                            Every low score in this period has been dealt with and written up.
                        @elseif ($view === 'unanswered')
                            Every guest who was asked has replied.
                        @else
                            No feedback came back in this period. Links go out with the checkout message.
                        @endif
                    </p>
                </div>
            @else
                <div class="nv-crm-inbox">
                    @foreach ($rows as $row)
                        <div @class(['nv-crm-item', 'is-bad' => $row->needsAttention()])>
                            <div class="nv-crm-item-head">
                                <div>
                                    <strong>{{ $row->guest?->name ?: ($row->checkIn?->guest_name ?: 'A guest') }}</strong>
                                    <span class="nv-sub">
                                        {{ $row->checkIn?->room?->room_no ? 'Room ' . $row->checkIn->room->room_no . ' · ' : '' }}
                                        {{ $row->checkIn?->folio_no }}
                                        @if ($row->answered_at)
                                            · answered {{ $row->answered_at->format('d M Y') }}
                                        @elseif ($row->sent_at)
                                            · asked {{ $row->sent_at->format('d M Y') }}, no reply yet
                                        @endif
                                    </span>
                                </div>

                                @if ($row->overall)
                                    <span class="nv-fb-shown is-inline">
                                        @for ($i = 1; $i <= 5; $i++)
                                            <i @class(['is-on' => $i <= $row->overall])>★</i>
                                        @endfor
                                    </span>
                                @endif
                            </div>

                            @if ($row->isAnswered())
                                <div class="nv-crm-item-areas">
                                    @foreach ($areas as $key => $label)
                                        @if ($row->{$key})
                                            <span>{{ $label }} <b>{{ $row->{$key} }}</b></span>
                                        @endif
                                    @endforeach

                                    @if ($row->would_return !== null)
                                        <span @class(['is-flag', 'is-bad' => ! $row->would_return])>
                                            {{ $row->would_return ? 'Would come back' : 'Would not come back' }}
                                        </span>
                                    @endif
                                </div>

                                @if ($row->liked)
                                    <p class="nv-crm-item-said"><strong>Liked</strong> {{ $row->liked }}</p>
                                @endif

                                @if ($row->improve)
                                    <p class="nv-crm-item-said is-improve"><strong>Could be better</strong> {{ $row->improve }}</p>
                                @endif
                            @endif

                            @if ($row->handled_at)
                                <p class="nv-crm-item-handled">
                                    <x-icon name="check-circle" />
                                    <span>
                                        <strong>Dealt with by {{ $row->handler?->name ?? 'somebody' }}
                                            on {{ $row->handled_at->format('d M Y') }}</strong>
                                        {{ $row->handled_note }}
                                    </span>
                                </p>
                            @elseif ($row->isDetractor() && can_here('edit'))
                                <form method="POST" action="{{ route('crm.feedback.handled', $row) }}"
                                      class="nv-crm-handle">
                                    @csrf
                                    <input type="text" name="handled_note" class="nv-input" required maxlength="2000"
                                           placeholder="What did you do? Rang them, offered…" />
                                    <button type="submit" class="nv-btn nv-btn-sm nv-btn-primary">
                                        <x-icon name="check" /> Dealt with
                                    </button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="nv-card-foot">{{ $rows->links() }}</div>
            @endif
        </x-card>
    </div>

    <div class="nv-mt">
        <x-alert tone="info" title="How the link reaches the guest">
            A feedback link is made when a guest checks out and goes with the checkout message on WhatsApp or
            email. There is no account and no login — the forty characters in the link are what identifies
            the stay. A guest who opens it twice sees what they already said rather than a blank form.
        </x-alert>
    </div>
@endsection
