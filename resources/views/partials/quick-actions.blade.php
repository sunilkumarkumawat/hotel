{{--
    Topbar Quick Actions.

    One box, two jobs: jump straight to any screen the signed-in user can see
    (the exact list the sidebar shows — @include('partials.menu-list') below
    is the same partial the sidebar and the mobile "More" sheet both use, so
    there is nothing new to keep in sync), and find a guest already in the
    guest book. Picking a guest does not navigate straight away — it swaps in
    a short list of what to do with them, because "found the guest" and
    "start a reservation" are two different decisions and the second one
    should never happen by accident.

    $menu is passed in explicitly from layouts/app.blade.php (already
    computed once there for the sidebar); menu-list.blade.php falls back to
    sidebar_menu() itself if it is ever included without it.
--}}
@php
    $canGuestSearch = can_do('crm/guests', 'view');
    $canReservationAdd = can_do('reservation/new-reservation', 'add');

    // The five guest-first actions below, each gated on the same permission
    // its own screen already checks — a tile never offers what the signed-in
    // user could not do by walking to that screen directly.
    $canRoomService = can_do('point-of-sale/pos', 'add');
    $canCarParking = can_do('car/parking', 'add');
    $canHallBooking = can_do('hall/bookings', 'add');
    $canPoolBooking = can_do('pool/bookings', 'add');
    $canPoliceRegister = can_do('compliance/police-register', 'view');

    // Built as a real variable, not inlined into @json() below — Blade's
    // @json compiles by splitting its raw expression text on every comma
    // (to pull out optional encoding-options/depth arguments), so an
    // array literal with its own commas gets shredded mid-array and
    // compiles to broken PHP. A bare variable has no top-level comma to
    // trip on, which is exactly how reservation/form.blade.php's own
    // @json($boot) already does this safely.
    $quickActionsBoot = [
        'canGuestSearch' => $canGuestSearch,
        'urls' => $canGuestSearch ? [
            'guestSearch' => route('crm.guests.search'),
            'reservationCreate' => route('reservation.create'),
            'guestProfile' => url('crm/guests'),
            // A route existing here is not itself permission — every one of
            // these is still only reachable through a tile gated below, and
            // the route is enforced server-side again regardless.
            'posRoom' => url('point-of-sale/pos/room'),
            'carParking' => route('car.parking'),
            'hallBooking' => route('hall.bookings.create'),
            'poolBooking' => route('pool.bookings.create'),
            'policeRegister' => route('compliance.police-register'),
        ] : [],
    ];
@endphp

<div class="nv-modal-backdrop" data-quick-actions>
    <div class="nv-modal nv-quick-modal" role="dialog" aria-modal="true" aria-label="Quick actions">
        <div class="nv-modal-head">
            <strong>Quick Actions</strong>
            <button type="button" class="nv-icon-btn" data-close-quick-actions aria-label="Close">
                <x-icon name="x" />
            </button>
        </div>

        <div class="nv-quick-body">
            <div class="nv-field-search">
                <x-icon name="search" />
                <input type="search" class="nv-input" data-quick-query autocomplete="off"
                       placeholder="{{ $canGuestSearch ? 'Jump to a screen, or find a guest…' : 'Jump to a screen…' }}" />
            </div>

            @if ($canGuestSearch)
                {{-- Populated by quick-actions.js; empty and hidden until a
                     search of two or more characters turns up a match. --}}
                <div data-quick-guests hidden>
                    <p class="nv-quick-label">Guests</p>
                    <div class="nv-guest-results" data-quick-guest-results></div>
                </div>

                {{-- Swapped in over the two sections above once a guest is
                     picked; Back returns to them exactly as they were. --}}
                <div data-quick-guest-actions hidden>
                    <button type="button" class="nv-quick-back" data-quick-back>
                        <x-icon name="chevron-left" /> Back to search
                    </button>
                    <div class="nv-quick-guest-head" data-quick-guest-head></div>
                    <div class="nv-quick-action-grid" data-quick-action-list></div>
                </div>

                {{-- Real tiles, built once here with the permission checks
                     Blade already has, then cloned and pointed at whichever
                     guest was picked — quick-actions.js never has to decide
                     on its own whether an action is allowed.

                     Everything else staff can open for a guest — Advance
                     Deposit, Compliance's Form C, Group Bill, Pre-Reg Card —
                     turns out to need a booking or a check-in to attach to
                     already, not just a guest, so there is nothing for a
                     tile to sensibly create before one exists. The six below
                     are the ones that do work from a bare guest: New
                     Reservation is the first booking; View Guest Profile
                     already carries their notes and history; Room Service,
                     Car Parking, Hall Booking and Pool Booking all take a
                     free-text name and mobile with no stay required (Room
                     Service is the one exception — it opens a bill against a
                     room, so it only appears once the guest picked actually
                     has one, via quick-actions.js's own active_check_in_id
                     check); Police Register does not create anything at all
                     — it jumps to today's register already filtered to this
                     name, because the register itself is a report, not a
                     form. A tile is a `@if`-gated <template>, so one more
                     guest-first action later is one more block here. --}}
                @if ($canReservationAdd)
                    <template data-quick-action-template="reservation">
                        <a href="#" class="nv-quick-tile" data-quick-action-link>
                            <span class="nv-stat-icon is-info"><x-icon name="calendar" /></span>
                            New Reservation
                        </a>
                    </template>
                @endif

                <template data-quick-action-template="profile">
                    <a href="#" class="nv-quick-tile" data-quick-action-link>
                        <span class="nv-stat-icon is-success"><x-icon name="user" /></span>
                        View Guest Profile
                    </a>
                </template>

                @if ($canRoomService)
                    <template data-quick-action-template="room-service">
                        <a href="#" class="nv-quick-tile" data-quick-action-link>
                            <span class="nv-stat-icon is-warning"><x-icon name="bag" /></span>
                            Room Service
                        </a>
                    </template>
                @endif

                @if ($canCarParking)
                    <template data-quick-action-template="car-parking">
                        <a href="#" class="nv-quick-tile" data-quick-action-link>
                            <span class="nv-stat-icon is-info"><x-icon name="package" /></span>
                            Car Parking
                        </a>
                    </template>
                @endif

                @if ($canHallBooking)
                    <template data-quick-action-template="hall-booking">
                        <a href="#" class="nv-quick-tile" data-quick-action-link>
                            <span class="nv-stat-icon is-success"><x-icon name="layers" /></span>
                            Hall Booking
                        </a>
                    </template>
                @endif

                @if ($canPoolBooking)
                    <template data-quick-action-template="pool-booking">
                        <a href="#" class="nv-quick-tile" data-quick-action-link>
                            <span class="nv-stat-icon is-warning"><x-icon name="sun" /></span>
                            Pool Booking
                        </a>
                    </template>
                @endif

                @if ($canPoliceRegister)
                    <template data-quick-action-template="police-register">
                        <a href="#" class="nv-quick-tile" data-quick-action-link>
                            <span class="nv-stat-icon is-danger"><x-icon name="shield" /></span>
                            Police Register
                        </a>
                    </template>
                @endif
            @endif

            <div data-quick-modules>
                <p class="nv-quick-label">Screens</p>
                @include('partials.menu-list', ['menu' => $menu])
            </div>
        </div>
    </div>
</div>

<script>
    window.QUICK_ACTIONS_BOOT = @json($quickActionsBoot);
</script>
