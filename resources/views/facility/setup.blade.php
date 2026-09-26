@extends('layouts.app')

@section('title', $config['title'])

@php
    /*
        One screen, four lists. What each field looks like comes from the
        controller's description of it, so adding a facility means adding an
        entry to that array and nothing here.
    */
    $value = fn (string $name, $default = null) => old($name, $editing?->{$name} ?? $default);
@endphp

@section('content')
    <x-page-header
        :title="$config['title']"
        :subtitle="$config['subtitle']"
        :crumbs="['Home' => url('/'), $config['crumb'], $config['title']]"
    />

    <div class="nv-grid nv-grid-2 nv-mt nv-fac-setup">
        {{-- ── The form ──────────────────────────────────────────────────── --}}
        @canAdd($screen)
            <x-card :title="$editing ? 'Edit ' . $config['singular'] : 'Add a ' . $config['singular']">
                <form method="POST" action="{{ route($routes['save']) }}">
                    @csrf

                    @if ($editing)
                        <input type="hidden" name="id" value="{{ $editing->id }}" />
                    @endif

                    <div class="nv-grid nv-grid-2">
                        @foreach ($config['fields'] as $name => $field)
                            <x-field
                                :label="$field['label']"
                                :name="$name"
                                :help="$field['help'] ?? null"
                                :wide="$field['wide'] ?? false"
                            >
                                @switch($field['type'])
                                    @case('select')
                                        <x-select :name="$name" :options="$field['options']" :selected="$value($name)" />
                                        @break

                                    @case('textarea')
                                        <textarea name="{{ $name }}" id="{{ $name }}" rows="3" class="nv-input">{{ $value($name) }}</textarea>
                                        @break

                                    @case('money')
                                        <x-input :name="$name" type="number" step="0.01" min="0" :value="$value($name, 0)" />
                                        @break

                                    @case('number')
                                        <x-input :name="$name" type="number" step="1" min="0" :value="$value($name, 0)" />
                                        @break

                                    @case('time')
                                        <x-input :name="$name" type="time" :value="substr((string) $value($name), 0, 5)" />
                                        @break

                                    @default
                                        <x-input :name="$name" :value="$value($name)" />
                                @endswitch
                            </x-field>
                        @endforeach
                    </div>

                    <div class="nv-actions" style="justify-content:flex-end;margin-top:14px">
                        @if ($editing)
                            <a href="{{ route($routes['index']) }}" class="nv-btn nv-btn-ghost">Cancel</a>
                        @endif

                        <button type="submit" class="nv-btn nv-btn-primary">
                            <x-icon name="check" /> {{ $editing ? 'Save changes' : 'Add' }}
                        </button>
                    </div>
                </form>
            </x-card>
        @endCanAdd

        {{-- ── The list ──────────────────────────────────────────────────── --}}
        <x-card flush>
            <x-slot:title>{{ $rows->count() }} on the list</x-slot:title>

            <x-slot:actions>
                <form method="GET" class="nv-field-search">
                    <x-icon name="search" />
                    <input type="search" name="q" value="{{ $q }}" class="nv-input nv-input-sm" placeholder="Search…" />
                </form>
            </x-slot:actions>

            @if ($rows->isEmpty())
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="inbox" /></span>
                    <strong>Nothing set up yet</strong>
                    <p>Add the first {{ $config['singular'] }} on the left.</p>
                </div>
            @else
                <div class="nv-table-wrap">
                    <table class="nv-table">
                        <thead>
                            <tr>
                                @foreach ($config['columns'] as $heading)
                                    <th>{{ $heading }}</th>
                                @endforeach
                                <th style="width:110px"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr @class(['nv-fac-off' => ! $row->isActive()])>
                                    @foreach ($config['columns'] as $column => $heading)
                                        <td>
                                            @php
                                                $cell = $row->{$column};
                                                // A select column stores a key and shows its label.
                                                $options = $config['fields'][$column]['options'] ?? null;
                                            @endphp

                                            {{ $options[$cell] ?? ($cell === null || $cell === '' ? '—' : $cell) }}

                                            @if ($loop->first && ! $row->isActive())
                                                <span class="nv-sub">switched off</span>
                                            @endif
                                        </td>
                                    @endforeach

                                    <td class="nv-fac-row-actions">
                                        @canEdit($screen)
                                            <a href="{{ route($routes['index'], ['edit' => $row->id]) }}"
                                               class="nv-icon-btn" title="Edit"><x-icon name="pencil" /></a>
                                        @endCanEdit

                                        @canDelete($screen)
                                            <form method="POST" action="{{ route($routes['toggle'], ['id' => $row->id]) }}">
                                                @csrf
                                                <button type="submit" class="nv-icon-btn"
                                                        title="{{ $row->isActive() ? 'Switch off' : 'Switch back on' }}">
                                                    <x-icon :name="$row->isActive() ? 'x-circle' : 'refresh'" />
                                                </button>
                                            </form>
                                        @endCanDelete
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>
    </div>
@endsection
