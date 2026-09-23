@extends('layouts.app')

@section('title', 'Store Categories')

@php
    $mayAdd = can_here('add');
    $mayEdit = can_here('edit');
    $mayDelete = can_here('delete');
@endphp

@section('content')
    <x-page-header
        title="Store Categories"
        subtitle="Vegetables, Beverages, Linen, Cleaning — what kind of thing it is."
        :crumbs="['Home' => url('/'), 'Store', 'Categories']"
    >
        <x-slot:actions>
            <a href="{{ route('store.items') }}" class="nv-btn nv-btn-outline">
                <x-icon name="chevron-left" /> Items
            </a>
        </x-slot:actions>
    </x-page-header>

    @if ($errors->any())
        <div class="nv-mt"><x-alert tone="danger" title="Not saved">{{ $errors->first() }}</x-alert></div>
    @endif

    @if ($mayAdd && ! $editing)
        <form id="cat-new" method="POST" action="{{ route('store.categories.store') }}">
            @csrf
            <input type="hidden" name="status" value="1" />
        </form>
    @endif

    @if ($mayEdit && $editing)
        <form id="cat-edit" method="POST" action="{{ route('store.categories.update', $editing) }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="status" value="0" />
        </form>
    @endif

    <div class="nv-mt">
        <x-card flush>
            <div class="nv-table-wrap">
                <table class="nv-table nv-setup-grid">
                    <thead>
                        <tr>
                            <th style="min-width:200px">Category</th>
                            <th style="width:100px">Code</th>
                            <th style="min-width:170px">Usually drawn by</th>
                            <th style="width:86px">Sort</th>
                            <th class="is-end" style="width:80px">Items</th>
                            <th style="width:170px">&nbsp;</th>
                        </tr>
                    </thead>

                    <tbody>
                        @if ($mayAdd && ! $editing)
                            <tr class="is-new">
                                <td>
                                    <input form="cat-new" name="name" class="nv-input" value="{{ old('name') }}"
                                           placeholder="Vegetables" required />
                                </td>
                                <td><input form="cat-new" name="code" class="nv-input" value="{{ old('code') }}" /></td>
                                <td>
                                    <select form="cat-new" name="department" class="nv-select">
                                        <option value="">Anybody</option>
                                        @foreach ($departments as $key => $label)
                                            <option value="{{ $key }}" @selected(old('department') === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input form="cat-new" type="number" name="sort" class="nv-input"
                                           value="{{ old('sort', 0) }}" min="0" max="255" />
                                </td>
                                <td class="is-end">—</td>
                                <td>
                                    <button form="cat-new" type="submit" class="nv-btn nv-btn-sm nv-btn-primary">
                                        <x-icon name="plus" /> Add
                                    </button>
                                </td>
                            </tr>
                        @endif

                        @forelse ($rows as $row)
                            @if ($editing === $row->id)
                                <tr class="is-editing">
                                    <td>
                                        <input form="cat-edit" name="name" class="nv-input"
                                               value="{{ old('name', $row->name) }}" required />
                                    </td>
                                    <td>
                                        <input form="cat-edit" name="code" class="nv-input"
                                               value="{{ old('code', $row->code) }}" />
                                    </td>
                                    <td>
                                        <select form="cat-edit" name="department" class="nv-select">
                                            <option value="">Anybody</option>
                                            @foreach ($departments as $key => $label)
                                                <option value="{{ $key }}" @selected(old('department', $row->department) === $key)>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input form="cat-edit" type="number" name="sort" class="nv-input"
                                               value="{{ old('sort', $row->sort) }}" min="0" max="255" />
                                    </td>
                                    <td class="is-end">
                                        <label class="nv-check-inline">
                                            <input form="cat-edit" type="checkbox" name="status" value="1"
                                                   @checked(old('status', $row->status)) />
                                            On
                                        </label>
                                    </td>
                                    <td>
                                        <div class="nv-row-actions">
                                            <a href="{{ route('store.categories') }}" class="nv-btn nv-btn-sm nv-btn-ghost">Cancel</a>
                                            <button form="cat-edit" type="submit" class="nv-btn nv-btn-sm nv-btn-primary">
                                                <x-icon name="check" /> Save
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @else
                                <tr @class(['is-off' => ! $row->isActive()])>
                                    <td><strong>{{ $row->name }}</strong></td>
                                    <td>{{ $row->code ?: '—' }}</td>
                                    <td>{{ $departments[$row->department] ?? 'Anybody' }}</td>
                                    <td>{{ $row->sort }}</td>
                                    <td class="is-end">{{ $row->items_count }}</td>
                                    <td>
                                        <div class="nv-row-actions">
                                            @if ($mayEdit)
                                                <a href="{{ route('store.categories', ['edit' => $row->id]) }}"
                                                   class="nv-btn nv-btn-sm nv-btn-ghost">
                                                    <x-icon name="pencil" /> Edit
                                                </a>
                                            @endif

                                            @if ($mayDelete)
                                                <form method="POST" action="{{ route('store.categories.destroy', $row) }}"
                                                      data-confirm="Delete {{ $row->name }}?"
                                                      data-confirm-title="Delete category">
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
                                <td colspan="6">
                                    <div class="nv-empty">
                                        <span class="nv-empty-icon"><x-icon name="grid" /></span>
                                        <strong>No categories yet</strong>
                                        <p>They are optional, but they are what makes a stock sheet readable.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>
@endsection
