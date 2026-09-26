@php
    $me = auth()->user();
    $branches = \App\Helpers\Helper::availableBranches();
    $active = active_branch();
@endphp

<header class="nv-topbar">
    <button type="button" class="nv-icon-btn" data-toggle="sidebar" aria-label="Toggle sidebar">
        <x-icon name="menu" />
    </button>

    @if ($branches->count() > 1)
        <form method="POST" action="{{ route('changeBranch') }}" class="nv-branch-switch">
            @csrf
            <x-icon name="globe" />
            <select name="branch_id" class="nv-select" onchange="this.form.submit()" aria-label="Active branch">
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected($active?->id === $branch->id)>
                        {{ $branch->branch_name }}
                    </option>
                @endforeach
            </select>
        </form>
    @elseif ($active)
        <span class="nv-branch-label">
            <x-icon name="globe" /> {{ $active->branch_name }}
        </span>
    @endif

    <button type="button" class="nv-icon-btn" data-open-quick-actions
            aria-label="Quick actions" title="Quick actions (Ctrl K)">
        <x-icon name="search" />
    </button>

    <div class="nv-topbar-spacer"></div>

    @include('partials.notification-bell')

    <button type="button" class="nv-icon-btn nv-theme-toggle" data-toggle="theme" aria-label="Toggle dark mode">
        <x-icon name="sun" class="nv-icon-sun" />
        <x-icon name="moon" class="nv-icon-moon" />
    </button>

    <div class="nv-dropdown" data-dropdown>
        <button type="button" class="nv-dropdown-trigger" data-dropdown-trigger>
            <x-avatar :name="$me->name ?: $me->username" />
            <span class="nv-user-meta">
                <strong>{{ $me->name ?: $me->username }}</strong>
                <small>{{ $me->role_name }}</small>
            </span>
            <x-icon name="chevron-down" />
        </button>

        <div class="nv-menu">
            <div class="nv-menu-head">
                <strong style="display:block;font-size:13.5px">{{ $me->name ?: $me->username }}</strong>
                <span class="nv-muted" style="font-size:12.5px">{{ '@' . $me->username }}</span>
            </div>

            <a href="{{ route('user-profile') }}" class="nv-menu-item">
                <x-icon name="user" /> My profile
            </a>
            <a href="{{ route('password.change') }}" class="nv-menu-item">
                <x-icon name="lock" /> Change password
            </a>

            @canView('modules')
                <a href="{{ route('modules.index') }}" class="nv-menu-item">
                    <x-icon name="grid" /> Modules
                </a>
            @endCanView

            <div class="nv-menu-sep"></div>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="nv-menu-item is-danger">
                    <x-icon name="logout" /> Sign out
                </button>
            </form>
        </div>
    </div>
</header>
