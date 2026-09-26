{{--
    The whole menu comes from the `module` / `submodule` tables through the
    sidebar_menu() helper (app/Helpers/helpers.php), filtered by what the
    signed-in user may view. Nothing here is hard-coded.

    $menu is computed once in layouts/app.blade.php and handed to this
    partial and to partials/mobile-nav.blade.php alike, so a page render
    queries it a single time no matter how many places draw it out. Falls
    back to sidebar_menu() so this still works if ever @included on its own.
--}}
@php
    $photo = sidebar_photo();
    $menu ??= sidebar_menu();
@endphp

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

    @include('partials.menu-list', ['menu' => $menu])

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
