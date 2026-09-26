@extends('layouts.app')

@section('title', 'Rate Plans')

@php
    $mayAdd = can_here('add');
    $mayEdit = can_here('edit');
    $mayDelete = can_here('delete');
@endphp

@section('content')
    <x-page-header
        title="Rate Plans"
        subtitle="A price list with a name, and who it is for."
        :crumbs="['Home' => url('/'), 'Rate Management', 'Rate Plans']"
    >
        <x-slot:actions>
            @canView('rates/calendar')
                <a href="{{ route('rates.calendar') }}" class="nv-btn nv-btn-outline">
                    <x-icon name="calendar" /> Rate calendar
                </a>
            @endCanView
            @canView('rates/rules')
                <a href="{{ route('rates.rules') }}" class="nv-btn nv-btn-primary">
                    <x-icon name="grid" /> The rate grid
                </a>
            @endCanView
        </x-slot:actions>
    </x-page-header>

    @if ($errors->any())
        <div class="nv-mt">
            <x-alert tone="danger" title="Not saved">{{ $errors->first() }}</x-alert>
        </div>
    @endif

    {{--
        The forms live outside the table because a <form> may not wrap table
        rows. The inputs point back at them with the HTML `form` attribute.
        One form is on screen at a time — opening an edit closes the add row —
        so a validation error can only ever belong to one of them.
    --}}
    @if ($mayAdd && ! $editing)
        <form id="plan-new" method="POST" action="{{ route('rates.plans.store') }}">
            @csrf
            {{-- Always present, so an unticked box reads as 0 rather than as
                 "the user said nothing". --}}
            <input type="hidden" name="is_default" value="0" />
            <input type="hidden" name="status" value="1" />
        </form>
    @endif

    @if ($mayEdit && $editing)
        <form id="plan-edit" method="POST" action="{{ route('rates.plans.update', $editing) }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="is_default" value="0" />
            <input type="hidden" name="status" value="0" />
        </form>
    @endif

    <div class="nv-mt">
        <x-card flush>
            <div class="nv-table-wrap">
                <table class="nv-table nv-setup-grid">
                    <thead>
                        <tr>
                            <th style="min-width:170px">Plan</th>
                            <th style="width:96px">Code</th>
                            <th style="min-width:190px">Who it is for</th>
                            <th style="min-width:200px">In force</th>
                            <th style="width:86px">Priority</th>
                            <th style="width:104px">Flags</th>
                            <th style="width:72px">Rates</th>
                            <th style="width:170px">&nbsp;</th>
                        </tr>
                    </thead>

                    <tbody>
                        @if ($mayAdd && ! $editing)
                            <tr class="is-new">
                                <td>
                                    <input form="plan-new" name="name" class="nv-input"
                                           value="{{ old('name') }}" placeholder="Rack Rate" required />
                                </td>
                                <td>
                                    <input form="plan-new" name="code" class="nv-input"
                                           value="{{ old('code') }}" placeholder="RACK" />
                                </td>
                                <td>
                                    <div class="nv-rt-pair">
                                        <select form="plan-new" name="business_market_id" class="nv-select">
                                            <option value="">Any market</option>
                                            @foreach ($markets as $id => $name)
                                                <option value="{{ $id }}" @selected(old('business_market_id') == $id)>{{ $name }}</option>
                                            @endforeach
                                        </select>
                                        <select form="plan-new" name="company_id" class="nv-select">
                                            <option value="">Any company</option>
                                            @foreach ($companies as $id => $name)
                                                <option value="{{ $id }}" @selected(old('company_id') == $id)>{{ $name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </td>
                                <td>
                                    <div class="nv-rt-pair">
                                        <input form="plan-new" type="date" name="valid_from" class="nv-input"
                                               value="{{ old('valid_from') }}" />
                                        <input form="plan-new" type="date" name="valid_to" class="nv-input"
                                               value="{{ old('valid_to') }}" />
                                    </div>
                                </td>
                                <td>
                                    <input form="plan-new" type="number" name="priority" class="nv-input"
                                           value="{{ old('priority', 0) }}" min="0" max="255" />
                                </td>
                                <td>
                                    <label class="nv-check-inline" title="The plan used when a booking names nobody">
                                        <input form="plan-new" type="checkbox" name="is_default" value="1"
                                               @checked(old('is_default')) />
                                        Default
                                    </label>
                                </td>
                                <td>—</td>
                                <td>
                                    <button form="plan-new" type="submit" class="nv-btn nv-btn-sm nv-btn-primary">
                                        <x-icon name="plus" /> Add plan
                                    </button>
                                </td>
                            </tr>
                        @endif

                        @forelse ($rows as $row)
                            @if ($editing === $row->id)
                                <tr class="is-editing">
                                    <td>
                                        <input form="plan-edit" name="name" class="nv-input"
                                               value="{{ old('name', $row->name) }}" required />
                                    </td>
                                    <td>
                                        <input form="plan-edit" name="code" class="nv-input"
                                               value="{{ old('code', $row->code) }}" />
                                    </td>
                                    <td>
                                        <div class="nv-rt-pair">
                                            <select form="plan-edit" name="business_market_id" class="nv-select">
                                                <option value="">Any market</option>
                                                @foreach ($markets as $id => $name)
                                                    <option value="{{ $id }}" @selected(old('business_market_id', $row->business_market_id) == $id)>{{ $name }}</option>
                                                @endforeach
                                            </select>
                                            <select form="plan-edit" name="company_id" class="nv-select">
                                                <option value="">Any company</option>
                                                @foreach ($companies as $id => $name)
                                                    <option value="{{ $id }}" @selected(old('company_id', $row->company_id) == $id)>{{ $name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="nv-rt-pair">
                                            <input form="plan-edit" type="date" name="valid_from" class="nv-input"
                                                   value="{{ old('valid_from', $row->valid_from?->toDateString()) }}" />
                                            <input form="plan-edit" type="date" name="valid_to" class="nv-input"
                                                   value="{{ old('valid_to', $row->valid_to?->toDateString()) }}" />
                                        </div>
                                    </td>
                                    <td>
                                        <input form="plan-edit" type="number" name="priority" class="nv-input"
                                               value="{{ old('priority', $row->priority) }}" min="0" max="255" />
                                    </td>
                                    <td>
                                        <label class="nv-check-inline">
                                            <input form="plan-edit" type="checkbox" name="is_default" value="1"
                                                   @checked(old('is_default', $row->is_default)) />
                                            Default
                                        </label>
                                        <label class="nv-check-inline">
                                            <input form="plan-edit" type="checkbox" name="status" value="1"
                                                   @checked(old('status', $row->status)) />
                                            Active
                                        </label>
                                    </td>
                                    <td>{{ $row->rules_count }}</td>
                                    <td>
                                        <div class="nv-row-actions">
                                            <a href="{{ route('rates.plans') }}" class="nv-btn nv-btn-sm nv-btn-ghost">Cancel</a>
                                            <button form="plan-edit" type="submit" class="nv-btn nv-btn-sm nv-btn-primary">
                                                <x-icon name="check" /> Update
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @else
                                <tr @class(['is-off' => ! $row->isActive()])>
                                    <td>
                                        <strong>{{ $row->name }}</strong>
                                        @if ($row->is_default)
                                            <span class="nv-sub">Used when a booking names nobody</span>
                                        @elseif ($row->remark)
                                            <span class="nv-sub">{{ $row->remark }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $row->code ?: '—' }}</td>
                                    <td>
                                        {{ $row->audience }}
                                        @unless ($row->isActive())
                                            <span class="nv-sub">Switched off — not offered</span>
                                        @endunless
                                    </td>
                                    <td>
                                        @if ($row->valid_from || $row->valid_to)
                                            {{ $row->valid_from?->format('d M Y') ?? 'any date' }}
                                            – {{ $row->valid_to?->format('d M Y') ?? 'onwards' }}
                                        @else
                                            <span class="nv-muted">Always</span>
                                        @endif
                                    </td>
                                    <td>{{ $row->priority }}</td>
                                    <td>
                                        @if ($row->is_default)
                                            <x-badge tone="success">Default</x-badge>
                                        @else
                                            <span class="nv-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @canView('rates/rules')
                                            <a href="{{ route('rates.rules', ['plan' => $row->id]) }}">{{ $row->rules_count }}</a>
                                        @else
                                            {{ $row->rules_count }}
                                        @endCanView
                                    </td>
                                    <td>
                                        <div class="nv-row-actions">
                                            @if ($mayEdit)
                                                <a href="{{ route('rates.plans', ['edit' => $row->id]) }}"
                                                   class="nv-btn nv-btn-sm nv-btn-ghost">
                                                    <x-icon name="pencil" /> Edit
                                                </a>
                                            @endif

                                            @if ($mayDelete)
                                                <form method="POST" action="{{ route('rates.plans.destroy', $row) }}"
                                                      data-confirm="Delete the rate plan &quot;{{ $row->name }}&quot;?"
                                                      data-confirm-title="Delete rate plan">
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
                        @empty
                            <tr>
                                <td colspan="8">
                                    <div class="nv-empty">
                                        <span class="nv-empty-icon"><x-icon name="wallet" /></span>
                                        <strong>No rate plan yet</strong>
                                        <p>
                                            Start with one called Rack Rate and tick Default. Until a plan exists,
                                            every booking falls back to the room type's base rent.
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
        <x-alert tone="info" title="How a plan gets picked">
            A booking is priced on the plan that fits it most closely: the guest's company's own plan first,
            then their market's, and the default for everybody else. A plan for one company is that company's
            rate and nobody else's — which is the whole reason for negotiating one.
        </x-alert>
    </div>
@endsection
