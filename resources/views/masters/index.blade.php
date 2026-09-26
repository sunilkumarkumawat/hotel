@extends('layouts.app')

@section('title', $config['plural'] ?? $config['label'])

@php
    $titleColumn = $config['search'] ?? 'name';
@endphp

@section('content')
    <x-page-header
        :title="$config['plural'] ?? $config['label']"
        :subtitle="$config['intro'] ?? null"
        :crumbs="['Home' => url('/'), 'Masters' => route('masters.home'), $config['plural'] ?? $config['label']]"
    >
        <x-slot:actions>
            @canAdd('masters')
                @if ($config['bulk'] ?? true)
                    <a href="{{ route('masters.create-many', $master) }}" class="nv-btn nv-btn-outline">
                        <x-icon name="layers" /> Add several
                    </a>
                @endif

                <a href="{{ route('masters.create', $master) }}" class="nv-btn nv-btn-primary">
                    <x-icon name="plus" /> Add {{ strtolower($config['label']) }}
                </a>
            @endCanAdd
        </x-slot:actions>
    </x-page-header>

    <div class="nv-grid nv-grid-3">
        <x-stat :label="'All ' . strtolower($config['plural'] ?? $config['label'])" :value="$counts['all']" :icon="$config['icon'] ?? 'grid'" />
        <x-stat label="Active" :value="$counts['active']" icon="check-circle" tone="success" />
        <x-stat label="Inactive" :value="$counts['all'] - $counts['active']" icon="x-circle" tone="danger" />
    </div>

    <div class="nv-mt">
        <x-card flush>
            <form method="GET" class="nv-toolbar">
                <div class="nv-field-search">
                    <x-icon name="search" />
                    <input type="search" name="q" value="{{ $term }}" class="nv-input"
                           placeholder="Search {{ strtolower($config['plural'] ?? $config['label']) }}…" />
                </div>
                <button type="submit" class="nv-btn nv-btn-outline"><x-icon name="filter" /> Filter</button>
                @if ($term)
                    <a href="{{ route('masters.index', $master) }}" class="nv-btn nv-btn-ghost">Reset</a>
                @endif
            </form>

            @if ($rows->count())
                <div class="nv-table-wrap">
                    <table class="nv-table">
                        <thead>
                            <tr>
                                @foreach ($config['columns'] as $column)
                                    @php $field = $config['fields'][$column] ?? ['label' => ucfirst($column), 'type' => 'text']; @endphp
                                    <th @class(['is-num' => in_array($field['type'], ['number', 'money', 'percent'])])>
                                        {{ $field['label'] }}
                                    </th>
                                @endforeach
                                <th>Status</th>
                                <th class="is-end">Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    @foreach ($config['columns'] as $i => $column)
                                        @php
                                            $field = $config['fields'][$column] ?? ['type' => 'text'];
                                            $value = $row->{$column};
                                        @endphp

                                        <td @class([
                                            'is-num' => in_array($field['type'], ['number', 'money', 'percent']),
                                            'nv-nowrap' => $i === 0,
                                        ])>
                                            @if ($i === 0)
                                                <strong>{{ $value ?: '—' }}</strong>
                                            @elseif ($field['type'] === 'money')
                                                ₹{{ number_format((float) $value, 2) }}
                                            @elseif ($field['type'] === 'percent')
                                                {{ rtrim(rtrim(number_format((float) $value, 2), '0'), '.') }}%
                                            @elseif ($field['type'] === 'switch')
                                                @if ($value)<x-badge tone="primary">Default</x-badge>@else <span class="nv-muted">—</span> @endif
                                            @elseif ($field['type'] === 'select')
                                                {{ $options[$column][$value] ?? ($field['options'][$value] ?? '—') }}
                                            @else
                                                {{ $value === null || $value === '' ? '—' : $value }}
                                            @endif
                                        </td>
                                    @endforeach

                                    <td>
                                        @canEdit('masters')
                                            <form method="POST" action="{{ route('masters.toggle', [$master, $row->id]) }}">
                                                @csrf
                                                <button type="submit" style="background:none;border:0;padding:0;cursor:pointer"
                                                        title="Click to toggle">
                                                    <x-badge :tone="$row->isActive() ? 'success' : 'danger'">
                                                        {{ $row->isActive() ? 'Active' : 'Inactive' }}
                                                    </x-badge>
                                                </button>
                                            </form>
                                        @else
                                            <x-badge :tone="$row->isActive() ? 'success' : 'danger'">
                                                {{ $row->isActive() ? 'Active' : 'Inactive' }}
                                            </x-badge>
                                        @endCanEdit
                                    </td>

                                    <td class="is-end">
                                        <div class="nv-row-actions">
                                            @canEdit('masters')
                                                <a href="{{ route('masters.edit', [$master, $row->id]) }}"
                                                   class="nv-btn nv-btn-ghost nv-btn-sm"
                                                   aria-label="Edit {{ $row->{$titleColumn} }}">
                                                    <x-icon name="pencil" />
                                                </a>
                                            @endCanEdit

                                            @canDelete('masters')
                                                <form method="POST" action="{{ route('masters.destroy', [$master, $row->id]) }}"
                                                      data-confirm="{{ $row->{$titleColumn} }} will be removed from this list."
                                                      data-confirm-title="Delete {{ strtolower($config['label']) }}?"
                                                      data-confirm-action="Delete">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="nv-btn nv-btn-ghost nv-btn-sm"
                                                            aria-label="Delete {{ $row->{$titleColumn} }}">
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
            @else
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon :name="$config['icon'] ?? 'grid'" /></span>
                    <strong>Nothing here yet</strong>
                    <p>{{ $term ? 'Nothing matches that search.' : $config['intro'] }}</p>
                    @canAdd('masters')
                        <a href="{{ route('masters.create', $master) }}" class="nv-btn nv-btn-primary">
                            <x-icon name="plus" /> Add {{ strtolower($config['label']) }}
                        </a>

                        @if ($config['bulk'] ?? true)
                            <a href="{{ route('masters.create-many', $master) }}" class="nv-btn nv-btn-ghost">
                                or add several at once
                            </a>
                        @endif
                    @endCanAdd
                </div>
            @endif

            <x-slot:footer>
                {{ $rows->links() }}
            </x-slot:footer>
        </x-card>
    </div>
@endsection
