@extends('layouts.app')

@section('title', ($row->exists ? 'Edit ' : 'Add ') . $config['label'])

@section('content')
    @php
        $label = $config['label'];
        $action = $row->exists
            ? route('masters.update', [$master, $row->id])
            : route('masters.store', $master);
    @endphp

    <x-page-header
        :title="($row->exists ? 'Edit ' : 'Add ') . strtolower($label)"
        :subtitle="$config['intro'] ?? null"
        :crumbs="[
            'Home' => url('/'),
            'Masters' => route('masters.home'),
            ($config['plural'] ?? $label) => route('masters.index', $master),
            $row->exists ? 'Edit' : 'Add',
        ]"
    />

    <form method="POST" action="{{ $action }}">
        @csrf
        @if ($row->exists) @method('PUT') @endif

        @if ($errors->any())
            <div style="margin-bottom:18px">
                <x-alert tone="danger" title="Please fix {{ $errors->count() }} field(s)">
                    {{ $errors->first() }}
                </x-alert>
            </div>
        @endif

        <div class="nv-grid nv-grid-form">
            <div class="nv-form-aside">
                <h3>{{ $label }}</h3>
                <p>{{ $config['intro'] ?? '' }}</p>
            </div>

            <x-card>
                <div class="nv-form-grid">
                    @foreach ($config['fields'] as $column => $field)
                        @php
                            $type = $field['type'] ?? 'text';
                            $value = old($column, $row->{$column});
                        @endphp

                        @if ($type === 'switch')
                            <div style="grid-column:1/-1">
                                <label class="nv-check">
                                    <input type="checkbox" name="{{ $column }}" value="1" @checked($value) />
                                    <span>{{ $field['label'] }}</span>
                                </label>
                            </div>
                        @else
                            <x-field
                                :label="$field['label']"
                                :name="$column"
                                :help="$field['help'] ?? null"
                                :required="str_contains($field['rules'] ?? '', 'required')"
                                :wide="$field['wide'] ?? false"
                            >
                                @if ($type === 'select')
                                    <x-select :name="$column" :options="$options[$column] ?? []"
                                              :selected="$value" placeholder="Choose…" />
                                @elseif ($type === 'textarea')
                                    <x-textarea :name="$column" :value="$value" rows="3" />
                                @elseif ($type === 'money')
                                    <x-input :name="$column" type="number" step="0.01" min="0"
                                             :value="$value" placeholder="0.00" />
                                @elseif ($type === 'percent')
                                    <x-input :name="$column" type="number" step="0.01" min="0" max="100"
                                             :value="$value" placeholder="0" />
                                @elseif ($type === 'number')
                                    <x-input :name="$column" type="number" :value="$value" />
                                @else
                                    <x-input :name="$column" :value="$value"
                                             :placeholder="$field['placeholder'] ?? ''" />
                                @endif
                            </x-field>
                        @endif
                    @endforeach
                </div>

                <hr class="nv-hr" />

                <label class="nv-check">
                    <input type="checkbox" name="status" value="1"
                           @checked(old('status', $row->exists ? $row->status : 1)) />
                    <span>Active — inactive rows stop appearing in dropdowns</span>
                </label>
            </x-card>
        </div>

        <div class="nv-actions" style="justify-content:flex-end;margin-top:22px">
            <a href="{{ route('masters.index', $master) }}" class="nv-btn nv-btn-outline">Cancel</a>
            <button type="submit" class="nv-btn nv-btn-primary">
                <x-icon name="check" /> {{ $row->exists ? 'Save changes' : 'Add ' . strtolower($label) }}
            </button>
        </div>
    </form>
@endsection
