{{--
    The phone-sized front door to the same menu partials/sidebar.blade.php
    draws for desktop — same $menu (sidebar_menu(), computed once in
    layouts/app.blade.php and handed to both partials so it is only queried
    a single time per request), same permissions, same "Soon" pills. Hidden
    above 1024px by CSS (public/css/mobile-theme.css) — nothing here is
    rendered twice or fought over with the sidebar, it simply is not shown
    on a desktop-width screen.

    The first four modules a user can see become direct one-tap shortcuts;
    "More" opens a sheet with the complete list, in the same order the
    sidebar itself uses — so nothing on mobile is ever more than the bar or
    one tap away, no matter how many modules a role can see.
--}}
@php
    $menu ??= sidebar_menu();
    $primaryModules = $menu->take(4);
@endphp

@if ($menu->isNotEmpty())
    <nav class="nv-mobilebar" aria-label="Primary">
        @foreach ($primaryModules as $module)
            @php $target = $module->submodules->first(); @endphp

            @if ($target)
                <a href="{{ menu_url($target) }}"
                   @class(['nv-mobilebar-item', 'is-active' => is_module_active($module)])>
                    <x-icon :name="$module->icon ?: 'circle'" />
                    <span>{{ $module->name }}</span>
                </a>
            @endif
        @endforeach

        <button type="button" class="nv-mobilebar-item" data-toggle="more-sheet"
                aria-haspopup="true" aria-expanded="false" aria-controls="nv-more-sheet">
            <x-icon name="grid" />
            <span>More</span>
        </button>
    </nav>

    <div class="nv-more-overlay" data-close="more-sheet"></div>

    <div class="nv-more-sheet" id="nv-more-sheet" data-more-sheet role="dialog" aria-modal="true" aria-label="All modules">
        <div class="nv-more-sheet-handle" aria-hidden="true"></div>

        <div class="nv-more-sheet-head">
            <strong>Menu</strong>
            <button type="button" class="nv-icon-btn" data-close="more-sheet" aria-label="Close menu">
                <x-icon name="x" />
            </button>
        </div>

        <div class="nv-more-sheet-body nv-scroll">
            @include('partials.menu-list', ['menu' => $menu])
        </div>
    </div>
@endif
