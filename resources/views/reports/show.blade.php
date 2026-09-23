@extends('layouts.app')

@section('title', $meta['label'])

@php
    // Money is right-aligned and formatted; everything else is printed as it
    // comes. The column definition decides, so one view renders all eighteen.
    $cell = function (array $column, $value) {
        if ($value === null || $value === '') {
            return '—';
        }

        return ($column['money'] ?? false)
            ? '₹ ' . number_format((float) $value, 2)
            : (is_float($value) ? rtrim(rtrim(number_format($value, 2), '0'), '.') : $value);
    };
@endphp

@section('content')
    <x-page-header
        :title="$meta['label']"
        :subtitle="$meta['about']"
        :crumbs="['Home' => url('/'), 'Reports' => route('reports.index'), $meta['label']]"
    >
        <x-slot:actions>
            <a href="{{ route('reports.export', ['report' => $slug] + array_filter($filters)) }}"
               class="nv-btn nv-btn-outline"><x-icon name="download" /> Export</a>
        </x-slot:actions>
    </x-page-header>

    @if ($summary)
        <div @class(['nv-grid', 'nv-grid-' . min(4, max(2, count($summary)))])>
            @foreach ($summary as $tile)
                <x-stat
                    :label="$tile['label']"
                    :value="$tile['value']"
                    :icon="$tile['icon'] ?? 'chart'"
                    :tone="$tile['tone'] ?? 'primary'" />
            @endforeach
        </div>
    @endif

    <div class="nv-mt">
        <x-card>
            <form method="GET" class="nv-toolbar">
                @if (in_array('range', $meta['filters'], true))
                    <input type="date" name="from" value="{{ $filters['from'] }}" class="nv-input"
                           style="width:160px" aria-label="From" />
                    <input type="date" name="to" value="{{ $filters['to'] }}" class="nv-input"
                           style="width:160px" aria-label="To" />
                @endif

                @foreach (['room_type' => 'Every room type', 'pay_mode' => 'Every pay mode', 'outlet' => 'Every outlet', 'room' => 'Every room'] as $filter => $blank)
                    @if (isset($options[$filter]))
                        <select name="{{ $filter }}" class="nv-select" style="width:180px" aria-label="{{ $blank }}">
                            <option value="">{{ $blank }}</option>
                            @foreach ($options[$filter] as $id => $name)
                                <option value="{{ $id }}" @selected($filters[$filter] === $id)>{{ $name }}</option>
                            @endforeach
                        </select>
                    @endif
                @endforeach

                <button type="submit" class="nv-btn nv-btn-primary"><x-icon name="filter" /> Show</button>
                <a href="{{ route('reports.show', $slug) }}" class="nv-btn nv-btn-ghost">Reset</a>

                <span class="nv-muted" style="margin-left:auto">{{ $rows->count() }} row(s)</span>
            </form>

            @if ($note)
                <p class="nv-help">{{ $note }}</p>
            @endif
        </x-card>
    </div>

    <div class="nv-mt">
        <x-card flush>
            @if ($rows->isEmpty())
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon :name="$meta['icon']" /></span>
                    <strong>Nothing to show</strong>
                    <p>Widen the dates, or clear the filters.</p>
                </div>
            @else
                <div class="nv-table-wrap">
                    <table class="nv-table">
                        <thead>
                            <tr>
                                @foreach ($columns as $column)
                                    <th @class(['is-num' => $column['num'] ?? false])>{{ $column['label'] }}</th>
                                @endforeach
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    @foreach ($columns as $key => $column)
                                        <td @class(['is-num' => $column['num'] ?? false])>
                                            {{ $cell($column, $row->{$key} ?? null) }}
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>

                        @if ($totals)
                            <tfoot>
                                <tr>
                                    @foreach ($columns as $key => $column)
                                        @if ($loop->first)
                                            <th>Total</th>
                                        @else
                                            <th @class(['is-num' => $column['num'] ?? false])>
                                                @isset($totals[$key])
                                                    {{ $cell($column, $totals[$key]) }}
                                                @endisset
                                            </th>
                                        @endif
                                    @endforeach
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            @endif
        </x-card>
    </div>
@endsection
