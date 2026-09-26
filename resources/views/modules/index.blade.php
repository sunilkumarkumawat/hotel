@extends('layouts.app')

@section('title', 'Modules')

@php
    $iconNames = ['home','calendar','layers','grid','users','user','refresh','bag','wallet','credit-card','file','inbox','trending-up','chart','activity','cog','shield','package','mail','lock','globe','star','sparkles','palette','clock','check-circle','alert','info'];
    $iconOptions = array_combine($iconNames, $iconNames);
@endphp

@section('content')
    <x-page-header
        title="Modules"
        subtitle="The sidebar and the user permission matrix are both built from these two tables."
        :crumbs="['Home' => url('/'), 'Modules']"
    />

    <div class="nv-grid nv-grid-3">
        <x-stat label="Modules" :value="$counts['modules']" icon="grid" />
        <x-stat label="Sub-modules" :value="$counts['submodules']" icon="layers" tone="info" />
        <x-stat label="Linked screens" :value="$counts['linked']" icon="external" tone="success"
                caption="the rest show as “Soon”" />
    </div>

    <div class="nv-grid nv-grid-main nv-mt">
        @canAdd('modules')
        <x-card title="Add a module" subtitle="A module is a sidebar group; screens go inside it as sub-modules.">
            <form method="POST" action="{{ route('modules.store') }}">
                @csrf

                <div class="nv-form-grid">
                    <x-field label="Name" name="name" required>
                        <x-input name="name" placeholder="Room Reservation" />
                    </x-field>

                    <x-field label="Icon" name="icon">
                        <x-select name="icon" :options="$iconOptions" placeholder="Choose an icon…" />
                    </x-field>

                    <x-field label="URL" name="url" help="Optional — a module is usually just a group.">
                        <x-input name="url" placeholder="booking-list" />
                    </x-field>

                    <x-field label="Sort" name="sort" help="Leave empty to put it last.">
                        <x-input name="sort" type="number" min="0" max="127" />
                    </x-field>
                </div>

                <div class="nv-actions" style="justify-content:flex-end">
                    <button type="submit" class="nv-btn nv-btn-primary"><x-icon name="plus" /> Add module</button>
                </div>
            </form>
        </x-card>
        @endCanAdd

        <x-card title="How it works">
            <p style="font-size:13.5px;line-height:1.7;color:var(--nv-text-2)">
                A <strong>module</strong> is a sidebar group. A <strong>sub-module</strong> is a screen inside it,
                and it is what you tick on and off per user.
            </p>

            <hr class="nv-hr" />

            <div class="nv-feed">
                <div class="nv-feed-item">
                    <span class="nv-matrix-icon"><x-icon name="external" /></span>
                    <div class="nv-feed-body">
                        <p><b>URL</b> is both the link and the permission key.</p>
                        <span class="nv-feed-time">A route name (<span class="nv-mono">users.index</span>) or a path (<span class="nv-mono">room-list</span>).</span>
                    </div>
                </div>
                <div class="nv-feed-item">
                    <span class="nv-matrix-icon"><x-icon name="lock" /></span>
                    <div class="nv-feed-body">
                        <p><b>Permissions</b> are per user, per branch.</p>
                        <span class="nv-feed-time">Set them on the Users screen — View, Add, Edit, Delete.</span>
                    </div>
                </div>
                <div class="nv-feed-item">
                    <span class="nv-matrix-icon"><x-icon name="shield" /></span>
                    <div class="nv-feed-body">
                        <p><b>Administrators</b> bypass every check.</p>
                        <span class="nv-feed-time">Role 1 always sees the whole menu.</span>
                    </div>
                </div>
            </div>
        </x-card>
    </div>

    @forelse ($modules as $module)
        <div class="nv-mt">
            <x-card>
                <x-slot:actions>
                    <x-badge plain>{{ $module->submodules->count() }} sub-modules</x-badge>

                    @canDelete('modules')
                    <form method="POST" action="{{ route('modules.destroy', $module) }}"
                          data-confirm="{{ $module->name }}, its {{ $module->submodules->count() }} sub-module(s) and every permission pointing at them will be removed."
                          data-confirm-title="Delete module?"
                          data-confirm-action="Delete module">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="nv-btn nv-btn-ghost nv-btn-sm" aria-label="Delete {{ $module->name }}">
                            <x-icon name="trash" />
                        </button>
                    </form>
                    @endCanDelete
                </x-slot:actions>

                <form method="POST" action="{{ route('modules.update', $module) }}">
                    @csrf
                    @method('PUT')

                    <div class="nv-module-row">
                        <span class="nv-matrix-icon"><x-icon :name="$module->icon ?: 'grid'" /></span>

                        <x-field label="Module">
                            <x-input name="name" :value="$module->name" />
                        </x-field>

                        <x-field label="Icon">
                            <x-select name="icon" :options="$iconOptions" :selected="$module->icon" placeholder="—" />
                        </x-field>

                        <x-field label="URL">
                            <x-input name="url" :value="$module->url" placeholder="—" />
                        </x-field>

                        <x-field label="Sort">
                            <x-input name="sort" type="number" :value="$module->sort" />
                        </x-field>

                        <div class="nv-module-row-actions">
                            @canEdit('modules')
                                <button type="submit" class="nv-btn nv-btn-outline nv-btn-sm">
                                    <x-icon name="check" /> Save
                                </button>
                            @endCanEdit
                        </div>
                    </div>
                </form>

                <div class="nv-table-wrap" style="margin-top:4px">
                    <table class="nv-table">
                        <thead>
                            <tr>
                                <th>Sub-module</th>
                                <th>URL</th>
                                <th>Link</th>
                                <th class="is-num">Sort</th>
                                <th class="is-end"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($module->submodules as $submodule)
                                <tr>
                                    <td class="nv-nowrap"><strong>{{ $submodule->name }}</strong></td>
                                    <td class="nv-mono">{{ $submodule->url ?: '—' }}</td>
                                    <td>
                                        @if ($submodule->isLinked())
                                            <x-badge tone="success">Linked</x-badge>
                                        @else
                                            <x-badge tone="warning">Soon</x-badge>
                                        @endif
                                    </td>
                                    <td class="is-num">{{ $submodule->sort }}</td>
                                    <td class="is-end">
                                        <div class="nv-row-actions">
                                            @canEdit('modules')
                                                <button type="button" class="nv-btn nv-btn-ghost nv-btn-sm"
                                                        data-toggle-row="sub-{{ $submodule->id }}"
                                                        aria-label="Edit {{ $submodule->name }}">
                                                    <x-icon name="pencil" />
                                                </button>
                                            @endCanEdit

                                            @canDelete('modules')
                                            <form method="POST" action="{{ route('submodules.destroy', $submodule) }}"
                                                  data-confirm="{{ $submodule->name }} will be removed from the menu and from every user's permissions."
                                                  data-confirm-title="Delete sub-module?"
                                                  data-confirm-action="Delete sub-module">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="nv-btn nv-btn-ghost nv-btn-sm"
                                                        aria-label="Delete {{ $submodule->name }}">
                                                    <x-icon name="trash" />
                                                </button>
                                            </form>
                                            @endCanDelete
                                        </div>
                                    </td>
                                </tr>

                                <tr id="sub-{{ $submodule->id }}" hidden>
                                    <td colspan="5" style="background:var(--nv-surface-2)">
                                        <form method="POST" action="{{ route('submodules.update', $submodule) }}">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="module_id" value="{{ $module->id }}" />

                                            @include('modules._submodule-fields', ['submodule' => $submodule])

                                            <div class="nv-actions" style="justify-content:flex-end">
                                                <button type="button" class="nv-btn nv-btn-ghost nv-btn-sm"
                                                        data-toggle-row="sub-{{ $submodule->id }}">Cancel</button>
                                                <button type="submit" class="nv-btn nv-btn-primary nv-btn-sm">
                                                    <x-icon name="check" /> Save sub-module
                                                </button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="nv-muted">No sub-modules yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <x-slot:footer>
                    @canAdd('modules')
                    <button type="button" class="nv-btn nv-btn-soft nv-btn-sm" data-toggle-row="add-{{ $module->id }}">
                        <x-icon name="plus" /> Add a sub-module to {{ $module->name }}
                    </button>

                    <div id="add-{{ $module->id }}" hidden style="margin-top:16px">
                        <form method="POST" action="{{ route('submodules.store') }}">
                            @csrf
                            <input type="hidden" name="module_id" value="{{ $module->id }}" />

                            @include('modules._submodule-fields', ['submodule' => null])

                            <div class="nv-actions" style="justify-content:flex-end">
                                <button type="submit" class="nv-btn nv-btn-primary nv-btn-sm">
                                    <x-icon name="plus" /> Add sub-module
                                </button>
                            </div>
                        </form>
                    </div>
                    @endCanAdd
                </x-slot:footer>
            </x-card>
        </div>
    @empty
        <div class="nv-mt">
            <x-card>
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="grid" /></span>
                    <strong>No modules yet</strong>
                    <p>Add one above, or run <span class="nv-kbd-inline">php artisan migrate --seed</span> for the starting menu.</p>
                </div>
            </x-card>
        </div>
    @endforelse
@endsection
