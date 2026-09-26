{{--
    The module/submodule list itself — split out of partials/sidebar.blade.php
    so the exact same markup, permissions and "Soon" pills can be reused
    inside the mobile "More" sheet (partials/mobile-nav.blade.php) without
    keeping two menus in sync by hand. Pass $menu explicitly; it falls back
    to sidebar_menu() so an @include that forgets it still works.
--}}
@php $menu ??= sidebar_menu(); @endphp

<nav class="nv-nav nv-scroll">
    @forelse ($menu as $module)
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
