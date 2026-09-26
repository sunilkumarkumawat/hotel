@extends('layouts.app')

@section('title', 'Add several ' . strtolower($config['plural'] ?? $config['label']))

@section('content')
    <x-page-header
        :title="'Add several ' . strtolower($config['plural'] ?? $config['label'])"
        :subtitle="$config['intro'] ?? null"
        :crumbs="[
            'Home' => url('/'),
            'Masters' => route('masters.home'),
            ($config['plural'] ?? $config['label']) => route('masters.index', $master),
            'Add several',
        ]"
    >
        <x-slot:actions>
            <a href="{{ route('masters.create', $master) }}" class="nv-btn nv-btn-outline">
                <x-icon name="plus" /> Add one instead
            </a>
        </x-slot:actions>
    </x-page-header>

    @if (session('error'))
        <div class="nv-mt"><x-alert tone="danger" title="Not saved">{{ session('error') }}</x-alert></div>
    @endif

    @if ($errors->any())
        <div class="nv-mt">
            <x-alert tone="danger" title="Nothing was saved — please fix {{ $errors->count() }} thing(s)">
                {{ $errors->first() }}
            </x-alert>
        </div>
    @endif

    {{--
        The form sits outside the table on purpose: a <form> may not wrap table
        rows, so every control in the grid points back at it with the HTML
        `form` attribute instead.
    --}}
    <form id="rows-new" method="POST" action="{{ route('masters.store-many', $master) }}">
        @csrf
    </form>

    <div class="nv-mt">
        <x-card flush>
            <div class="nv-table-wrap">
                <table class="nv-table nv-setup-grid">
                    <thead>
                        <tr>
                            @foreach ($config['fields'] as $column => $field)
                                <th>
                                    {{ $field['label'] }}
                                    @if (str_contains($field['rules'] ?? '', 'required'))
                                        <span class="nv-req">*</span>
                                    @endif
                                </th>
                            @endforeach
                            <th style="width:110px">Active</th>
                            <th class="is-end" style="width:150px">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($rowKeys as $i)
                            @include('masters.partials.bulk-row', ['i' => $i])
                        @endforeach

                        <tr class="is-new" data-bulk-actions>
                            <td colspan="{{ count($config['fields']) + 2 }}">
                                <div class="nv-bulk-actions">
                                    <button type="submit" form="rows-new" class="nv-btn nv-btn-primary nv-btn-sm">
                                        <x-icon name="check" /> Save all rows
                                    </button>

                                    {{-- Hidden until the script that makes it work
                                         has run, so it is never a button that does
                                         nothing. --}}
                                    <button type="button" class="nv-btn nv-btn-outline nv-btn-sm"
                                            data-add-row hidden>
                                        <x-icon name="plus" /> Add another row
                                    </button>

                                    <a href="{{ route('masters.index', $master) }}" class="nv-btn nv-btn-ghost nv-btn-sm">
                                        Cancel
                                    </a>

                                    <span class="nv-muted">
                                        Rows you leave blank are ignored. Press Enter to move down a row.
                                    </span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>

    {{--
        The row the Add button clones. A <template> is inert — its inputs are
        not part of any form and are never posted — which is the whole point:
        the same markup the server renders, sitting ready, with __i__ where the
        row number goes.
    --}}
    <template id="bulk-row-template">
        <table><tbody>
            @include('masters.partials.bulk-row', ['i' => '__i__'])
        </tbody></table>
    </template>
@endsection

@push('scripts')
    <script src="{{ asset('js/setup-rows.js') }}?v={{ file_exists(public_path('js/setup-rows.js')) ? filemtime(public_path('js/setup-rows.js')) : time() }}" defer></script>
@endpush
