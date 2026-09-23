@extends('layouts.app')

@section('title', $def['plural'])

@php
    $fields = $def['fields'];
    $mayAdd = can_do($def['permission'], 'add');
    $mayEdit = can_do($def['permission'], 'edit');
    $mayDelete = can_do($def['permission'], 'delete');

    // The filters the user is looking at, carried through every save so a
    // search does not evaporate the moment a row is added.
    $keep = array_filter(['q' => $term ?: null, 'deleted' => $showDeleted ? 1 : null]);

    $photos = $def['photos'] ?? false;
    $columns = count($fields) + 2 + ($photos ? 1 : 0);

    // Lists that take a whole batch at once say so in their definition. The
    // rest keep the one-row-at-a-time screen, which is the right shape for a
    // list of six departments.
    $bulk = ($def['bulk'] ?? false) && $mayAdd && ! $editing;

    /*
     * How many blank rows to put up. After a failed save it is exactly the
     * rows that came back, so every error lands on the line it belongs to;
     * otherwise it is three, which is enough to feel like a list and few
     * enough not to look like a form.
     */
    $oldRows = (array) old('rows', []);
    $rowKeys = $oldRows ? array_keys($oldRows) : range(0, 2);
@endphp

@section('content')
    <x-page-header
        :title="$def['plural']"
        :subtitle="$def['intro']"
        :crumbs="['Home' => url('/'), 'Point Of Sale' => route('point-of-sale.setup'), 'Setup' => route('point-of-sale.setup'), $def['plural']]"
    >
        <x-slot:actions>
            <a href="{{ route('point-of-sale.setup') }}" class="nv-btn nv-btn-outline">
                <x-icon name="chevron-left" /> Back to Setup
            </a>
        </x-slot:actions>
    </x-page-header>

    @if (session('error'))
        <div class="nv-mt"><x-alert tone="danger" title="Not saved">{{ session('error') }}</x-alert></div>
    @endif

    @if ($errors->any())
        <div class="nv-mt">
            <x-alert tone="danger" title="Please fix {{ $errors->count() }} thing(s)">
                {{ $errors->first() }}
            </x-alert>
        </div>
    @endif

    <div class="nv-grid nv-grid-3">
        <x-stat :label="'All ' . strtolower($def['plural'])" :value="$counts['all']" :icon="$def['icon']" />
        <x-stat label="Active" :value="$counts['active']" icon="check-circle" tone="success" />
        <x-stat label="Deleted" :value="$counts['deleted']" icon="trash" tone="warning" />
    </div>

    {{--
        The forms live outside the table on purpose: a <form> may not wrap table
        rows, so the inputs in the add row and the open edit row point back here
        with the HTML `form` attribute instead. One form is on screen at a time —
        opening an edit closes the add row — so a validation error can only ever
        belong to one of them.
    --}}
    @if ($bulk)
        <form id="rows-new" method="POST" action="{{ route($def['route'] . '.store-many') }}">
            @csrf
            @foreach ($keep as $key => $value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}" />
            @endforeach
        </form>
    @elseif ($mayAdd && ! $editing)
        <form id="row-new" method="POST" action="{{ route($def['route'] . '.store') }}">
            @csrf
            {{-- Always present, so an unticked Active box reads as 0 rather
                 than as "the user said nothing". --}}
            <input type="hidden" name="status" value="0" />
            @foreach ($keep as $key => $value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}" />
            @endforeach
        </form>
    @endif

    @if ($mayEdit && $editing)
        <form id="row-edit" method="POST" action="{{ route($def['route'] . '.update', $editing) }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="status" value="0" />
            @foreach ($keep as $key => $value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}" />
            @endforeach
        </form>
    @endif

    <div class="nv-mt">
        <x-card flush>
            <form method="GET" class="nv-toolbar">
                <div class="nv-field-search">
                    <x-icon name="search" />
                    <input type="search" name="q" value="{{ $term }}" class="nv-input"
                           placeholder="Search {{ strtolower($def['plural']) }}…" />
                </div>

                <label class="nv-check-inline">
                    <input type="checkbox" name="deleted" value="1" @checked($showDeleted) />
                    Show deleted
                </label>

                <button type="submit" class="nv-btn nv-btn-primary"><x-icon name="search" /> Search</button>

                @if ($term || $showDeleted)
                    <a href="{{ route($def['route']) }}" class="nv-btn nv-btn-ghost">Reset</a>
                @endif
            </form>

            <div class="nv-table-wrap">
                <table class="nv-table nv-setup-grid">
                    <thead>
                        <tr>
                            @if ($photos)
                                <th style="width:56px">Photo</th>
                            @endif
                            @foreach ($fields as $name => $field)
                                <th @if (! empty($field['width'])) style="width:{{ $field['width'] }}" @endif>
                                    {{ $field['label'] }}
                                </th>
                            @endforeach
                            <th style="width:110px">Active</th>
                            <th class="is-end" style="width:190px">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        {{-- ── The rows you type new ones into ────────────────

                             One row per thing being added, all posted together.
                             A row nobody touched is dropped on the server, so
                             three blank rows and one filled in adds one thing —
                             there is nothing to tidy up first.
                        --}}
                        @if ($bulk)
                            @foreach ($rowKeys as $i)
                                @include('pos.setup.partials.bulk-row', ['i' => $i, 'fields' => $fields, 'photos' => $photos])
                            @endforeach

                            <tr class="is-new" data-bulk-actions>
                                <td colspan="{{ $columns }}">
                                    <div class="nv-bulk-actions">
                                        <button type="submit" form="rows-new" class="nv-btn nv-btn-primary nv-btn-sm">
                                            <x-icon name="check" /> Save all rows
                                        </button>

                                        {{-- Hidden until the script that makes it
                                             work has run, so it is never a button
                                             that does nothing. --}}
                                        <button type="button" class="nv-btn nv-btn-outline nv-btn-sm"
                                                data-add-row hidden>
                                            <x-icon name="plus" /> Add another row
                                        </button>

                                        @if ($keep)
                                            <a href="{{ route($def['route'], $keep) }}" class="nv-btn nv-btn-ghost nv-btn-sm">Clear</a>
                                        @endif

                                        <span class="nv-muted">Rows you leave blank are ignored.</span>
                                    </div>
                                </td>
                            </tr>
                        @elseif ($mayAdd && ! $editing)
                            <tr class="is-new">
                                @foreach ($fields as $name => $field)
                                    <td>
                                        @include('pos.setup.partials.cell', [
                                            'field' => $field,
                                            'name' => $name,
                                            'value' => ($field['type'] ?? 'text') === 'checkboxes' ? [] : null,
                                            'form' => 'row-new',
                                        ])
                                    </td>
                                @endforeach

                                <td>
                                    <label class="nv-check" for="row-new_status">
                                        <input type="checkbox" name="status" id="row-new_status" form="row-new"
                                               value="1" @checked(old('status', true)) />
                                        <span>Active</span>
                                    </label>
                                </td>

                                <td class="is-end">
                                    <div class="nv-row-actions">
                                        <button type="submit" form="row-new" class="nv-btn nv-btn-primary nv-btn-sm">
                                            <x-icon name="plus" /> Add
                                        </button>
                                        @if ($keep)
                                            <a href="{{ route($def['route'], $keep) }}" class="nv-btn nv-btn-ghost nv-btn-sm">Clear</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endif

                        {{-- ── What is already there ─────────────────────────── --}}
                        @forelse ($rows as $row)
                            @php
                                $isEditing = $editing === (int) $row->id && $mayEdit && ! $row->trashed();
                            @endphp

                            <tr @class(['is-editing' => $isEditing, 'is-trashed' => $row->trashed()])>
                                @if ($photos)
                                    <td>
                                        <form method="POST" action="{{ route($def['route'] . '.photo', $row->id) }}"
                                              enctype="multipart/form-data" class="nv-item-photo-form">
                                            @csrf
                                            <label for="item-photo-{{ $row->id }}" class="nv-item-photo"
                                                   title="Click to {{ $row->photo ? 'change' : 'add' }} a photo">
                                                @if ($row->photo)
                                                    <img src="{{ asset('storage/' . $row->photo) }}" alt="" />
                                                @else
                                                    <x-icon name="upload" />
                                                @endif
                                            </label>
                                            <input type="file" name="photo" id="item-photo-{{ $row->id }}"
                                                   accept="image/png,image/jpeg,image/webp"
                                                   onchange="this.form.requestSubmit()" />
                                        </form>
                                    </td>
                                @endif
                                @foreach ($fields as $name => $field)
                                    @php $type = $field['type'] ?? 'text'; @endphp

                                    <td>
                                        @if ($isEditing)
                                            @include('pos.setup.partials.cell', [
                                                'field' => $field,
                                                'name' => $name,
                                                'value' => isset($field['current'])
                                                    ? ($field['current'])($row)
                                                    : $row->{$name},
                                                'form' => 'row-edit',
                                            ])
                                        @elseif (isset($field['display']))
                                            {{ ($field['display'])($row) }}
                                        @elseif ($type === 'select')
                                            {{ $field['options'][$row->{$name}] ?? '—' }}
                                        @elseif ($type === 'checkbox')
                                            {{ $row->{$name} ? 'Yes' : 'No' }}
                                        @elseif ($loop->first)
                                            <strong>{{ $row->{$name} ?: '—' }}</strong>
                                        @else
                                            {{ $row->{$name} === null || $row->{$name} === '' ? '—' : $row->{$name} }}
                                        @endif
                                    </td>
                                @endforeach

                                <td>
                                    @if ($isEditing)
                                        <label class="nv-check" for="row-edit_status">
                                            <input type="checkbox" name="status" id="row-edit_status" form="row-edit"
                                                   value="1" @checked(old('status', $row->status)) />
                                            <span>Active</span>
                                        </label>
                                    @elseif ($row->trashed())
                                        <x-badge tone="warning">Deleted</x-badge>
                                    @else
                                        <x-badge :tone="$row->isActive() ? 'success' : 'danger'">
                                            {{ $row->isActive() ? 'Active' : 'Inactive' }}
                                        </x-badge>
                                    @endif
                                </td>

                                <td class="is-end">
                                    <div class="nv-row-actions">
                                        @if ($isEditing)
                                            <button type="submit" form="row-edit" class="nv-btn nv-btn-primary nv-btn-sm">
                                                <x-icon name="check" /> Update
                                            </button>
                                            <a href="{{ route($def['route'], $keep) }}" class="nv-btn nv-btn-ghost nv-btn-sm">
                                                Cancel
                                            </a>
                                        @elseif ($row->trashed())
                                            @if ($mayDelete)
                                                <form method="POST" action="{{ route($def['route'] . '.restore', $row->id) }}">
                                                    @csrf
                                                    <button type="submit" class="nv-btn nv-btn-outline nv-btn-sm">
                                                        <x-icon name="refresh" /> Restore
                                                    </button>
                                                </form>
                                            @else
                                                <span class="nv-muted">—</span>
                                            @endif
                                        @else
                                            @if ($mayEdit)
                                                <a href="{{ route($def['route'], $keep + ['edit' => $row->id]) }}"
                                                   class="nv-btn nv-btn-ghost nv-btn-sm">
                                                    <x-icon name="pencil" /> Edit
                                                </a>
                                            @endif

                                            @if ($mayDelete)
                                                <form method="POST" action="{{ route($def['route'] . '.destroy', $row->id) }}"
                                                      data-confirm="{{ $row->name }} will be hidden from the POS screens. Show deleted brings it back."
                                                      data-confirm-title="Delete {{ strtolower($def['label']) }}?"
                                                      data-confirm-action="Delete">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="nv-btn nv-btn-ghost nv-btn-sm">
                                                        <x-icon name="trash" /> Delete
                                                    </button>
                                                </form>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $columns }}">
                                    <div class="nv-empty">
                                        <span class="nv-empty-icon"><x-icon :name="$def['icon']" /></span>
                                        <strong>Nothing here yet</strong>
                                        <p>
                                            {{ $term
                                                ? 'Nothing matches that search.'
                                                : ($mayAdd ? 'Type in the row above and press Add.' : $def['intro']) }}
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-slot:footer>
                {{ $rows->links() }}
            </x-slot:footer>
        </x-card>
    </div>
    @if ($bulk)
        {{--
            The row the Add button clones. A <template> is inert — its inputs
            are not part of any form and are never posted — which is the whole
            point: the same markup the server renders, sitting ready, with
            __i__ where the row number goes.
        --}}
        <template id="bulk-row-template">
            <table><tbody>
                @include('pos.setup.partials.bulk-row', ['i' => '__i__', 'fields' => $fields, 'photos' => $photos])
            </tbody></table>
        </template>
    @endif
@endsection

@if ($bulk)
    @push('scripts')
        <script src="{{ asset('js/setup-rows.js') }}?v={{ file_exists(public_path('js/setup-rows.js')) ? filemtime(public_path('js/setup-rows.js')) : time() }}" defer></script>
    @endpush
@endif
