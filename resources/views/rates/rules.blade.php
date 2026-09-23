@extends('layouts.app')

@section('title', 'Rate Grid')

@php
    $mayAdd = can_here('add');
    $mayEdit = can_here('edit');
    $mayDelete = can_here('delete');

    $weekdays = \App\Models\Rate\RateRule::WEEKDAYS;

    // Rates read best grouped by the room type they price — that is how a rate
    // sheet is laid out on paper, and how somebody checks one is missing.
    $byType = $rows->groupBy(fn ($row) => $row->roomType?->name ?: 'Unknown room type');
@endphp

@section('content')
    <x-page-header
        title="Rate Grid"
        subtitle="One row is a price: this plan, this room type, these nights, this much."
        :crumbs="['Home' => url('/'), 'Rate Management', 'Rate Grid']"
    >
        <x-slot:actions>
            @canView('rates/seasons')
                <a href="{{ route('rates.seasons') }}" class="nv-btn nv-btn-outline">
                    <x-icon name="calendar" /> Seasons
                </a>
            @endCanView
            @canView('rates/calendar')
                <a href="{{ route('rates.calendar', ['plan' => $planId]) }}" class="nv-btn nv-btn-primary">
                    <x-icon name="grid" /> See it on the calendar
                </a>
            @endCanView
        </x-slot:actions>
    </x-page-header>

    @if ($errors->any())
        <div class="nv-mt">
            <x-alert tone="danger" title="Not saved">{{ $errors->first() }}</x-alert>
        </div>
    @endif

    @if ($plans->isEmpty())
        <div class="nv-mt">
            <x-alert tone="warning" title="No rate plan yet">
                A rate belongs to a plan, so make one first — call it Rack Rate and tick Default.
                @canView('rates/plans')
                    <a href="{{ route('rates.plans') }}">Set up rate plans</a>.
                @endCanView
            </x-alert>
        </div>
    @else
        {{-- ── Which plan am I editing? ──────────────────────────────────── --}}
        <div class="nv-mt">
            <div class="nv-tabs nv-rt-plans">
                @foreach ($plans as $p)
                    <a href="{{ route('rates.rules', ['plan' => $p->id]) }}"
                       @class(['nv-tab', 'is-active' => (int) $p->id === (int) $planId])>
                        {{ $p->name }}
                        @if ($p->is_default)
                            <span class="nv-rt-flag">default</span>
                        @endif
                    </a>
                @endforeach
            </div>

            @if ($plan)
                <p class="nv-rt-audience">
                    <x-icon name="info" />
                    <span><strong>{{ $plan->name }}</strong> — sold to {{ strtolower($plan->audience) }}.
                    {{ $rows->count() }} {{ \Illuminate\Support\Str::plural('rate', $rows->count()) }} on it.</span>
                </p>
            @endif
        </div>

        {{-- ── Price several room types in one go ────────────────────────── --}}
        @if ($mayAdd && ! $editing && $types->isNotEmpty())
            <div class="nv-mt">
                <x-card title="Add rates"
                        subtitle="Say when it applies once, then a price against each room type it applies to.">
                    <form method="POST" action="{{ route('rates.rules.store-many') }}">
                        @csrf
                        <input type="hidden" name="rate_plan_id" value="{{ $planId }}" />

                        <div class="nv-rt-when">
                            <x-field label="Season" name="rate_season_id"
                                     help="Pick a season OR type dates below — not both.">
                                <select name="rate_season_id" class="nv-select">
                                    <option value="">No season</option>
                                    @foreach ($seasons as $season)
                                        <option value="{{ $season->id }}" @selected(old('rate_season_id') == $season->id)>
                                            {{ $season->label }}
                                        </option>
                                    @endforeach
                                </select>
                            </x-field>

                            <x-field label="From" name="from_date">
                                <x-input type="date" name="from_date" :value="old('from_date')" />
                            </x-field>

                            <x-field label="To" name="to_date">
                                <x-input type="date" name="to_date" :value="old('to_date')" />
                            </x-field>

                            <x-field label="Minimum stay" name="min_stay" help="0 means no minimum.">
                                <x-input type="number" name="min_stay" :value="old('min_stay', 0)" min="0" max="365" />
                            </x-field>
                        </div>

                        <div class="nv-rt-days">
                            <span class="nv-label">Nights of the week</span>

                            <div class="nv-rt-day-list">
                                @foreach ($weekdays as $key => $label)
                                    <label class="nv-check-inline">
                                        <input type="checkbox" name="weekdays[]" value="{{ $key }}"
                                               @checked(in_array($key, (array) old('weekdays', []), true)) />
                                        {{ $label }}
                                    </label>
                                @endforeach

                                <span class="nv-help">Leave them all clear for every night.</span>
                            </div>

                            <label class="nv-check-inline nv-rt-stop">
                                <input type="checkbox" name="stop_sell" value="1" @checked(old('stop_sell')) />
                                Stop sell — close these nights rather than price them
                            </label>
                        </div>

                        <hr class="nv-hr" />

                        <div class="nv-rt-prices">
                            @foreach ($types as $type)
                                <div class="nv-rt-price">
                                    <label for="amount-{{ $type->id }}">
                                        {{ $type->name }}
                                        <small>base ₹{{ number_format((float) $type->base_rent, 2) }}</small>
                                    </label>
                                    <input id="amount-{{ $type->id }}" type="number" step="0.01" min="0"
                                           class="nv-input" name="amounts[{{ $type->id }}]"
                                           value="{{ old('amounts.' . $type->id) }}" placeholder="—" />
                                </div>
                            @endforeach
                        </div>

                        <p class="nv-help nv-mt">
                            A blank price is a room type you are not pricing on this plan — it is skipped,
                            not saved as free.
                        </p>

                        <div class="nv-actions" style="justify-content:flex-end;margin-top:14px">
                            <button type="submit" class="nv-btn nv-btn-primary">
                                <x-icon name="plus" /> Add these rates
                            </button>
                        </div>
                    </form>
                </x-card>
            </div>
        @endif

        {{-- ── The rates already on this plan ────────────────────────────── --}}
        @if ($mayEdit && $editing)
            <form id="rule-edit" method="POST" action="{{ route('rates.rules.update', $editing) }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="rate_plan_id" value="{{ $planId }}" />
                <input type="hidden" name="stop_sell" value="0" />
                <input type="hidden" name="status" value="1" />
            </form>
        @endif

        <div class="nv-mt">
            <x-card flush>
                <div class="nv-table-wrap">
                    <table class="nv-table nv-setup-grid">
                        <thead>
                            <tr>
                                <th style="min-width:210px">When it applies</th>
                                <th style="width:120px">Rate</th>
                                <th style="width:120px">Extra adult</th>
                                <th style="width:120px">Extra child</th>
                                <th style="width:96px">Min stay</th>
                                <th style="width:110px">Closed</th>
                                <th style="width:86px">Priority</th>
                                <th style="width:160px">&nbsp;</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse ($byType as $typeName => $group)
                                <tr class="nv-rt-group">
                                    <td colspan="8">
                                        <strong>{{ $typeName }}</strong>
                                        <span class="nv-sub">{{ $group->count() }}
                                            {{ \Illuminate\Support\Str::plural('rate', $group->count()) }}</span>
                                    </td>
                                </tr>

                                @foreach ($group as $row)
                                    @if ($editing === $row->id)
                                        <tr class="is-editing">
                                            <td>
                                                <input form="rule-edit" type="hidden" name="room_type_id"
                                                       value="{{ $row->room_type_id }}" />

                                                <div class="nv-rt-pair">
                                                    <select form="rule-edit" name="rate_season_id" class="nv-select">
                                                        <option value="">No season</option>
                                                        @foreach ($seasons as $season)
                                                            <option value="{{ $season->id }}" @selected(old('rate_season_id', $row->rate_season_id) == $season->id)>
                                                                {{ $season->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    <input form="rule-edit" type="date" name="from_date" class="nv-input"
                                                           value="{{ old('from_date', $row->from_date?->toDateString()) }}" />
                                                    <input form="rule-edit" type="date" name="to_date" class="nv-input"
                                                           value="{{ old('to_date', $row->to_date?->toDateString()) }}" />
                                                </div>

                                                <div class="nv-rt-day-list is-tight">
                                                    @foreach ($weekdays as $key => $label)
                                                        <label class="nv-check-inline">
                                                            <input form="rule-edit" type="checkbox" name="weekdays[]"
                                                                   value="{{ $key }}"
                                                                   @checked(in_array($key, old('weekdays', $row->weekdayList()), true)) />
                                                            {{ $label }}
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </td>
                                            <td>
                                                <input form="rule-edit" type="number" step="0.01" min="0" class="nv-input"
                                                       name="amount" value="{{ old('amount', $row->amount) }}" required />
                                            </td>
                                            <td>
                                                <input form="rule-edit" type="number" step="0.01" min="0" class="nv-input"
                                                       name="extra_adult" value="{{ old('extra_adult', $row->extra_adult) }}" />
                                            </td>
                                            <td>
                                                <input form="rule-edit" type="number" step="0.01" min="0" class="nv-input"
                                                       name="extra_child" value="{{ old('extra_child', $row->extra_child) }}" />
                                            </td>
                                            <td>
                                                <input form="rule-edit" type="number" min="0" max="365" class="nv-input"
                                                       name="min_stay" value="{{ old('min_stay', $row->min_stay) }}" />
                                            </td>
                                            <td>
                                                <label class="nv-check-inline">
                                                    <input form="rule-edit" type="checkbox" name="stop_sell" value="1"
                                                           @checked(old('stop_sell', $row->stop_sell)) />
                                                    Stop sell
                                                </label>
                                            </td>
                                            <td>
                                                <input form="rule-edit" type="number" min="0" max="255" class="nv-input"
                                                       name="priority" value="{{ old('priority', $row->priority) }}" />
                                            </td>
                                            <td>
                                                <div class="nv-row-actions">
                                                    <a href="{{ route('rates.rules', ['plan' => $planId]) }}"
                                                       class="nv-btn nv-btn-sm nv-btn-ghost">Cancel</a>
                                                    <button form="rule-edit" type="submit"
                                                            class="nv-btn nv-btn-sm nv-btn-primary">
                                                        <x-icon name="check" /> Update
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @else
                                        <tr @class(['is-off' => ! $row->isActive() || $row->stop_sell])>
                                            <td>
                                                {{ $row->when }}
                                                @if ($row->remark)
                                                    <span class="nv-sub">{{ $row->remark }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($row->stop_sell)
                                                    <x-badge tone="danger">Closed</x-badge>
                                                @else
                                                    <strong>₹{{ number_format((float) $row->amount, 2) }}</strong>
                                                @endif
                                            </td>
                                            <td>{{ (float) $row->extra_adult > 0 ? '₹' . number_format((float) $row->extra_adult, 2) : '—' }}</td>
                                            <td>{{ (float) $row->extra_child > 0 ? '₹' . number_format((float) $row->extra_child, 2) : '—' }}</td>
                                            <td>{{ $row->min_stay > 0 ? $row->min_stay . ' nights' : '—' }}</td>
                                            <td>
                                                @if ($row->closed_to_arrival || $row->closed_to_departure)
                                                    <span class="nv-sub">
                                                        {{ $row->closed_to_arrival ? 'No arrivals' : '' }}
                                                        {{ $row->closed_to_departure ? 'No departures' : '' }}
                                                    </span>
                                                @else
                                                    <span class="nv-muted">—</span>
                                                @endif
                                            </td>
                                            <td>{{ $row->priority }}</td>
                                            <td>
                                                <div class="nv-row-actions">
                                                    @if ($mayEdit)
                                                        <a href="{{ route('rates.rules', ['plan' => $planId, 'edit' => $row->id]) }}"
                                                           class="nv-btn nv-btn-sm nv-btn-ghost">
                                                            <x-icon name="pencil" /> Edit
                                                        </a>
                                                    @endif

                                                    @if ($mayDelete)
                                                        <form method="POST" action="{{ route('rates.rules.destroy', $row) }}"
                                                              data-confirm="Delete this rate?"
                                                              data-confirm-title="Delete rate">
                                                            @csrf @method('DELETE')
                                                            <button type="submit" class="nv-btn nv-btn-sm nv-btn-ghost">
                                                                <x-icon name="trash" />
                                                            </button>
                                                        </form>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                            @empty
                                <tr>
                                    <td colspan="8">
                                        <div class="nv-empty">
                                            <span class="nv-empty-icon"><x-icon name="grid" /></span>
                                            <strong>No rates on this plan</strong>
                                            <p>
                                                Until one exists, bookings on this plan fall back to each room
                                                type's base rent. Start with an all-year row per room type,
                                                then lay seasons and weekends over it.
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>

        <div class="nv-mt">
            <x-alert tone="info" title="Which row wins a night">
                The most specific one. Own dates beat a season, a season beats an all-year row, and naming
                nights of the week narrows any of them further. Priority is your own thumb on the scale and
                is added last, so it can always win.
            </x-alert>
        </div>
    @endif
@endsection
