@extends('layouts.app')

@section('title', 'Account Groups')

@section('content')
    <x-page-header
        title="Account Groups"
        subtitle="The shelves ledgers stand on. A group's nature decides which column its ledgers land in."
        :crumbs="['Home' => url('/'), 'Accounting' => route('accounting.day-book'), 'Group']"
    />

    <div class="nv-grid nv-grid-2 nv-mt">
        @canAdd('accounting/group')
            <x-card :title="$editing ? 'Edit ' . $editing->name : 'Add a group'">
                <form method="POST" action="{{ route('accounting.group.save') }}">
                    @csrf

                    @if ($editing)
                        <input type="hidden" name="id" value="{{ $editing->id }}" />
                    @endif

                    <div class="nv-grid nv-grid-2">
                        <x-field label="Name" name="name" required wide>
                            <x-input name="name" :value="$editing?->name" placeholder="Sundry Creditors" />
                        </x-field>

                        <x-field label="Nature" name="nature" required
                                 help="Assets and expenses grow on the debit side; liabilities and income on the credit side.">
                            <x-select name="nature" :options="$natures" :selected="$editing?->nature ?? 'asset'" />
                        </x-field>

                        <x-field label="Sits under" name="parent_id" help="Optional — for a group inside a group.">
                            <x-select name="parent_id" :options="$parents" :selected="$editing?->parent_id"
                                      placeholder="Top level" />
                        </x-field>

                        <x-field label="On" name="status" wide>
                            <label class="nv-check">
                                <input type="hidden" name="status" value="0" />
                                <input type="checkbox" name="status" value="1" @checked($editing ? $editing->isActive() : true) />
                                <span>Ledgers can be filed under it</span>
                            </label>
                        </x-field>
                    </div>

                    @if ($editing?->isSystem())
                        <p class="nv-callout is-warning">
                            This is one of the standard groups. Its nature cannot be changed — every report in
                            the module reads it as {{ $editing->nature_label }}, and moving it would change last
                            year's profit without a single voucher being touched.
                        </p>
                    @endif

                    <div class="nv-actions" style="justify-content:flex-end;margin-top:14px">
                        @if ($editing)
                            <a href="{{ route('accounting.group') }}" class="nv-btn nv-btn-ghost">Cancel</a>
                        @endif

                        <button type="submit" class="nv-btn nv-btn-primary">
                            <x-icon name="check" /> {{ $editing ? 'Save changes' : 'Add group' }}
                        </button>
                    </div>
                </form>
            </x-card>
        @endCanAdd

        <x-card flush>
            <x-slot:title>{{ $groups->count() }} group(s)</x-slot:title>

            <x-slot:actions>
                <form method="GET" class="nv-actions">
                    <select name="nature" class="nv-select nv-input-sm" onchange="this.form.submit()" aria-label="Nature">
                        <option value="">Every nature</option>
                        @foreach ($natures as $key => $label)
                            <option value="{{ $key }}" @selected($filters['nature'] === $key)>{{ $label }}</option>
                        @endforeach
                    </select>

                    <div class="nv-field-search">
                        <x-icon name="search" />
                        <input type="search" name="q" value="{{ $filters['q'] }}" class="nv-input nv-input-sm" placeholder="Search…" />
                    </div>
                </form>
            </x-slot:actions>

            @if ($groups->isEmpty())
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="layers" /></span>
                    <strong>No groups yet</strong>
                    <p>Run the accounting seeder for a standard Indian chart, or add them here.</p>
                </div>
            @else
                <div class="nv-table-wrap">
                    <table class="nv-table">
                        <thead>
                            <tr>
                                <th>Group</th>
                                <th>Nature</th>
                                <th class="is-num">Ledgers</th>
                                <th style="width:90px"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($groups as $group)
                                <tr @class(['nv-fac-off' => ! $group->isActive()])>
                                    <td>
                                        <strong>{{ $group->name }}</strong>
                                        @if ($group->parent)
                                            <span class="nv-sub">under {{ $group->parent->name }}</span>
                                        @elseif ($group->isSystem())
                                            <span class="nv-sub">standard group</span>
                                        @endif
                                    </td>

                                    <td><x-badge plain>{{ $group->nature_label }}</x-badge></td>

                                    <td class="is-num">{{ $group->ledgers_count }}</td>

                                    <td class="nv-fac-row-actions">
                                        @canEdit('accounting/group')
                                            <a href="{{ route('accounting.group', ['edit' => $group->id]) }}"
                                               class="nv-icon-btn" title="Edit"><x-icon name="pencil" /></a>
                                        @endCanEdit

                                        @canDelete('accounting/group')
                                            @unless ($group->isSystem())
                                                <form method="POST" action="{{ route('accounting.group.toggle', $group->id) }}">
                                                    @csrf
                                                    <button type="submit" class="nv-icon-btn"
                                                            title="{{ $group->isActive() ? 'Switch off' : 'Switch back on' }}">
                                                        <x-icon :name="$group->isActive() ? 'x-circle' : 'refresh'" />
                                                    </button>
                                                </form>
                                            @endunless
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
