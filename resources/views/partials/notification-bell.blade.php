{{--
    The bell.

    Renders whatever the server already knows, then hands over to notify.js,
    which polls and replaces the list. That order matters: a desk on a flaky
    connection still sees the last state of the world rather than an empty
    dropdown, and the screen is never blank waiting for a fetch.

    The browser pop-up — the WhatsApp-style one — is asked for by the button in
    the footer rather than on page load. Browsers refuse a permission prompt
    that was not triggered by a click, and the ones that do not refuse it
    remember the "block" forever.
--}}

@php
    use App\Models\Notification\AppNotification;

    $branchIdForBell = (int) \App\Helpers\Helper::getActiveBranchId();
    $meForBell = (int) auth()->user()->user_id;

    /*
        Wrapped, and deliberately so. This partial is in the top bar of every
        screen in the app, which means a missing `app_notifications` table — a
        database restored from before this version, a migration not yet run —
        would take down the whole system rather than one feature. An empty bell
        is a far better failure than a white page on the login screen.
    */
    $bellRows = $branchIdForBell
        ? rescue(
            fn () => AppNotification::query()
                ->visibleTo($branchIdForBell, $meForBell)
                ->latest('id')
                ->limit(8)
                ->get(),
            collect(),
            false
        )
        : collect();

    $bellUnread = $bellRows->whereNull('read_at')->count();

    /*
     * Every icon a notification can wear, rendered once into a hidden bank.
     * notify.js clones out of it rather than carrying its own copy of the icon
     * set — there is one place icons are drawn in this project and this keeps
     * it that way, at the cost of a few hundred bytes of markup.
     */
    $bellIcons = collect(config('notifications.events'))
        ->pluck('icon')
        ->push('bell')
        ->filter()
        ->unique()
        ->values();
@endphp

<div class="nv-dropdown nv-bell" data-dropdown data-notify
     data-feed="{{ route('notifications.feed') }}"
     data-read="{{ route('notifications.read') }}"
     data-all="{{ route('notifications.index') }}"
     data-poll="{{ (int) config('pms.notify_poll_seconds', 25) }}"
     {{-- Taken from asset() rather than hard-coded: a hotel running this from
          htdocs/pms/public serves the worker from a sub-path, and a service
          worker cannot control pages above its own URL. --}}
     data-worker="{{ asset('sw-notify.js') }}">
    <button type="button" class="nv-icon-btn nv-bell-trigger" data-dropdown-trigger aria-label="Notifications">
        <x-icon name="bell" />
        <span class="nv-bell-dot" data-notify-count @if ($bellUnread === 0) hidden @endif>
            {{ $bellUnread > 99 ? '99+' : $bellUnread }}
        </span>
    </button>

    <div class="nv-menu nv-bell-menu">
        <div class="nv-menu-head nv-bell-head">
            <strong>Notifications</strong>
            <button type="button" class="nv-btn nv-btn-ghost nv-btn-sm" data-notify-read-all>Mark all read</button>
        </div>

        <div class="nv-bell-list" data-notify-list>
            @forelse ($bellRows as $row)
                <a href="{{ $row->url ?: route('notifications.index') }}"
                   @class(['nv-bell-item', 'is-unread' => $row->read_at === null, 'is-' . $row->level])>
                    <span class="nv-bell-icon"><x-icon :name="$row->icon ?: 'bell'" /></span>
                    <span class="nv-bell-text">
                        <strong>{{ $row->title }}</strong>
                        @if ($row->body)
                            <small>{{ \Illuminate\Support\Str::limit($row->body, 90) }}</small>
                        @endif
                        <em>{{ $row->created_at?->diffForHumans() }}</em>
                    </span>
                </a>
            @empty
                <p class="nv-bell-empty">Nothing yet.</p>
            @endforelse
        </div>

        <div class="nv-icon-bank" data-notify-icons hidden aria-hidden="true">
            @foreach ($bellIcons as $iconName)
                <span data-icon="{{ $iconName }}"><x-icon :name="$iconName" /></span>
            @endforeach
        </div>

        <div class="nv-menu-sep"></div>

        <div class="nv-bell-foot">
            <a href="{{ route('notifications.index') }}" class="nv-menu-item">
                <x-icon name="inbox" /> See all
            </a>

            {{-- Hidden until the browser says pop-ups are possible and not yet
                 allowed; notify.js is what decides that. --}}
            <button type="button" class="nv-menu-item" data-notify-permission hidden>
                <x-icon name="bell" /> Turn on pop-up alerts
            </button>
        </div>
    </div>
</div>

@push('scripts')
    {{-- filemtime busts the browser cache whenever build-fallback.php reruns --}}
    <script src="{{ asset('js/notify.js') }}?v={{ file_exists(public_path('js/notify.js')) ? filemtime(public_path('js/notify.js')) : time() }}" defer></script>
@endpush
