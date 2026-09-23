{{--
    The whole menu comes from the `module` / `submodule` tables through the
    sidebar_menu() helper (app/Helpers/helpers.php), filtered by what the
    signed-in user may view. Nothing here is hard-coded.
--}}
@php $photo = sidebar_photo(); @endphp

{{--
    `data-photo` is what the stylesheet keys off, and the picture itself rides
    in on a custom property rather than in the stylesheet — the file is chosen
    by whoever drops it into public/images, and CSS cannot go looking.
--}}
<aside class="nv-sidebar" @if ($photo) data-photo style="--nv-sidebar-photo:url('{{ $photo }}')" @endif>
    <a href="{{ url('/') }}" class="nv-brand" data-tooltip="{{ config('app.name') }}">
        <span class="nv-brand-mark">
            <x-icon name="sparkles" :size="19" />
        </span>
        <span class="nv-brand-name">{{ config('app.name') }}</span>
    </a>

    <nav class="nv-nav nv-scroll">
        @forelse (sidebar_menu() as $module)
            @php $open = is_module_active($module); @endphp

            @if (($module->total_submodules ?? $module->submodules->count()) === 1)
                @php $only = $module->submodules->first(); @endphp

                <a href="{{ menu_url($only) }}" @class(['nv-nav-link', 'is-active' => is_menu_active($only)])
                   data-tooltip="{{ $module->name }}">
                    <x-icon :name="$module->icon ?: 'circle'" />
                    <span class="nv-nav-text">{{ $module->name }}</span>

                    @unless ($only->isLinked())
                        <span class="nv-nav-pill is-soon">Soon</span>
                    @endunless
                </a>
            @else
                <div @class(['nv-nav-group', 'is-open' => $open]) data-nav-group>
                    <button type="button" @class(['nv-nav-link', 'nv-nav-toggle', 'is-active' => $open])
                            data-nav-toggle aria-expanded="{{ $open ? 'true' : 'false' }}"
                            data-tooltip="{{ $module->name }}">
                        <x-icon :name="$module->icon ?: 'circle'" />
                        <span class="nv-nav-text">{{ $module->name }}</span>
                        <x-icon name="chevron-down" class="nv-nav-caret" />
                    </button>

                    <div class="nv-nav-children">
                        @foreach ($module->submodules as $submodule)
                            <a href="{{ menu_url($submodule) }}"
                               @class(['nv-nav-child', 'is-active' => is_menu_active($submodule)])
                               data-tooltip="{{ $submodule->name }}">
                                <span class="nv-nav-dot"></span>
                                <span class="nv-nav-text">{{ $submodule->name }}</span>

                                @unless ($submodule->isLinked())
                                    <span class="nv-nav-pill is-soon">Soon</span>
                                @endunless
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        @empty
            <p class="nv-nav-section">No menu</p>
            <p style="padding:0 12px;font-size:12.5px;color:#7d8ea7;line-height:1.6">
                Your account has no modules yet. Ask an administrator to grant access,
                or run <span class="nv-kbd-inline">php artisan migrate --seed</span>.
            </p>
        @endforelse
    </nav>

    <div class="nv-sidebar-foot">
        <div class="nv-role-chip">
            <x-icon name="shield" />

            <span class="nv-role-chip-text">
                <strong>{{ auth()->user()->role_name }}</strong>
                <span>{{ is_admin() ? 'Full access' : 'Limited access' }}</span>
            </span>

            @canView('role')
                <a href="{{ route('role.index') }}">Manage</a>
            @endCanView
        </div>
    </div>
</aside>
