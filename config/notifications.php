<?php

/*
|--------------------------------------------------------------------------
| Everything the hotel can be told about
|--------------------------------------------------------------------------
| One entry per event. The key is what the code fires and what the settings
| screen saves against; the rest is what a manager sees and what happens when
| nobody has said otherwise.
|
|   label     what the settings screen calls it
|   group     which heading it sits under on that screen
|   level     info | success | warning | danger — the colour of the toast
|   icon      from resources/views/components/icon.blade.php
|   channels  what it does out of the box. 'app' is the bell in the top bar and
|             the browser pop-up; 'mail' and 'whatsapp' are off by default
|             because they cost money and need keys.
|
| Adding an event here makes it appear on Administration → Notification
| Settings with no other change. Firing one that is not here still works — it
| just shows up in the bell with no settings row, which is the right failure:
| a missing config line should never swallow a notification.
*/

return [

    'groups' => [
        'reservation' => 'Reservation',
        'front-office' => 'Front Office',
        'housekeeping' => 'House Keeping',
        'pos' => 'Point Of Sale',
        'facilities' => 'Pool, Hall & Car',
        'accounts' => 'Accounts',
        // Not the bell — these go to the GUEST's own phone. The text lives in
        // config/guest-messages.php; this is only the on/off switch.
        'guest' => 'Messages to the guest (WhatsApp)',
        'system' => 'System',
    ],

    'events' => [

        /* ── Reservation ────────────────────────────────────────────────── */

        'reservation.created' => [
            'label' => 'New booking taken',
            'group' => 'reservation', 'level' => 'success', 'icon' => 'calendar',
            'channels' => 'app',
        ],
        'reservation.updated' => [
            'label' => 'Booking changed',
            'group' => 'reservation', 'level' => 'info', 'icon' => 'pencil',
            'channels' => 'app',
        ],
        'reservation.cancelled' => [
            'label' => 'Booking cancelled',
            'group' => 'reservation', 'level' => 'danger', 'icon' => 'x-circle',
            'channels' => 'app',
        ],
        'reservation.deposit' => [
            'label' => 'Advance deposit taken',
            'group' => 'reservation', 'level' => 'success', 'icon' => 'wallet',
            'channels' => 'app',
        ],
        'reservation.no-show' => [
            'label' => 'Guest did not arrive',
            'group' => 'reservation', 'level' => 'warning', 'icon' => 'alert',
            'channels' => 'app',
        ],

        /* ── Front Office ───────────────────────────────────────────────── */

        'checkin.done' => [
            'label' => 'Guest checked in',
            'group' => 'front-office', 'level' => 'success', 'icon' => 'check-circle',
            'channels' => 'app',
        ],
        'checkin.undone' => [
            'label' => 'Check-in undone',
            'group' => 'front-office', 'level' => 'warning', 'icon' => 'refresh',
            'channels' => 'app',
        ],
        'checkout.done' => [
            'label' => 'Guest checked out',
            'group' => 'front-office', 'level' => 'info', 'icon' => 'logout',
            'channels' => 'app',
        ],
        'checkout.group' => [
            'label' => 'Family checked out on one bill',
            'group' => 'front-office', 'level' => 'info', 'icon' => 'users',
            'channels' => 'app',
        ],
        'folio.charge' => [
            'label' => 'Charge added to a folio',
            'group' => 'front-office', 'level' => 'info', 'icon' => 'file',
            'channels' => 'app',
        ],
        'payment.received' => [
            'label' => 'Payment taken',
            'group' => 'front-office', 'level' => 'success', 'icon' => 'credit-card',
            'channels' => 'app',
        ],
        'stay.extended' => [
            'label' => 'Checkout date extended',
            'group' => 'front-office', 'level' => 'info', 'icon' => 'clock',
            'channels' => 'app',
        ],

        /* ── House Keeping ──────────────────────────────────────────────── */

        'housekeeping.status' => [
            'label' => 'Room status changed',
            'group' => 'housekeeping', 'level' => 'info', 'icon' => 'layers',
            'channels' => 'app',
        ],
        'housekeeping.ready' => [
            'label' => 'Room ready to sell',
            'group' => 'housekeeping', 'level' => 'success', 'icon' => 'check-circle',
            'channels' => 'app',
        ],
        'housekeeping.assigned' => [
            'label' => 'Rooms given to a housekeeper',
            'group' => 'housekeeping', 'level' => 'info', 'icon' => 'user',
            'channels' => 'app',
        ],
        'workorder.created' => [
            'label' => 'Maintenance job raised',
            'group' => 'housekeeping', 'level' => 'warning', 'icon' => 'cog',
            'channels' => 'app',
        ],
        'workorder.closed' => [
            'label' => 'Maintenance job closed',
            'group' => 'housekeeping', 'level' => 'success', 'icon' => 'check',
            'channels' => 'app',
        ],
        'room.blocked' => [
            'label' => 'Room taken off sale',
            'group' => 'housekeeping', 'level' => 'danger', 'icon' => 'lock',
            'channels' => 'app',
        ],
        'room.released' => [
            'label' => 'Room put back on sale',
            'group' => 'housekeeping', 'level' => 'success', 'icon' => 'check-circle',
            'channels' => 'app',
        ],
        'laundry.issued' => [
            'label' => 'Linen sent to the laundry',
            'group' => 'housekeeping', 'level' => 'info', 'icon' => 'upload',
            'channels' => 'app',
        ],
        'laundry.received' => [
            'label' => 'Linen back from the laundry',
            'group' => 'housekeeping', 'level' => 'info', 'icon' => 'download',
            'channels' => 'app',
        ],

        /* ── Point Of Sale ──────────────────────────────────────────────── */

        'pos.order.opened' => [
            'label' => 'Order opened',
            'group' => 'pos', 'level' => 'info', 'icon' => 'bag',
            'channels' => 'app',
        ],
        'pos.kot' => [
            'label' => 'KOT sent to the kitchen',
            'group' => 'pos', 'level' => 'info', 'icon' => 'inbox',
            'channels' => 'app',
        ],
        'pos.bill' => [
            'label' => 'Bill printed',
            'group' => 'pos', 'level' => 'info', 'icon' => 'file',
            'channels' => 'app',
        ],
        'pos.settled' => [
            'label' => 'Order settled',
            'group' => 'pos', 'level' => 'success', 'icon' => 'wallet',
            'channels' => 'app',
        ],
        'pos.cancelled' => [
            'label' => 'Order cancelled',
            'group' => 'pos', 'level' => 'danger', 'icon' => 'x-circle',
            'channels' => 'app',
        ],
        'pos.room-service' => [
            'label' => 'Room service ordered',
            'group' => 'pos', 'level' => 'info', 'icon' => 'home',
            'channels' => 'app',
        ],
        'pos.guest_request' => [
            'label' => 'Guest asked to order (QR menu)',
            'group' => 'pos', 'level' => 'warning', 'icon' => 'bell',
            'channels' => 'app',
        ],

        /* ── Pool, Hall & Car ───────────────────────────────────────────── */

        'pool.booked' => [
            'label' => 'Pool booked',
            'group' => 'facilities', 'level' => 'success', 'icon' => 'globe',
            'channels' => 'app',
        ],
        'pool.cancelled' => [
            'label' => 'Pool booking cancelled',
            'group' => 'facilities', 'level' => 'danger', 'icon' => 'x-circle',
            'channels' => 'app',
        ],
        'hall.booked' => [
            'label' => 'Hall booked',
            'group' => 'facilities', 'level' => 'success', 'icon' => 'grid',
            'channels' => 'app',
        ],
        'hall.cancelled' => [
            'label' => 'Hall booking cancelled',
            'group' => 'facilities', 'level' => 'danger', 'icon' => 'x-circle',
            'channels' => 'app',
        ],
        'parking.in' => [
            'label' => 'Vehicle parked',
            'group' => 'facilities', 'level' => 'info', 'icon' => 'package',
            'channels' => 'app',
        ],
        'parking.out' => [
            'label' => 'Vehicle taken out',
            'group' => 'facilities', 'level' => 'info', 'icon' => 'arrow-right',
            'channels' => 'app',
        ],
        'trip.scheduled' => [
            'label' => 'Pickup or drop booked',
            'group' => 'facilities', 'level' => 'success', 'icon' => 'arrow-right',
            'channels' => 'app',
        ],
        'trip.started' => [
            'label' => 'Car left for the guest',
            'group' => 'facilities', 'level' => 'info', 'icon' => 'clock',
            'channels' => 'app',
        ],
        'trip.completed' => [
            'label' => 'Trip finished',
            'group' => 'facilities', 'level' => 'success', 'icon' => 'check-circle',
            'channels' => 'app',
        ],

        /* ── Accounts ───────────────────────────────────────────────────── */

        'accounts.voucher' => [
            'label' => 'Voucher posted',
            'group' => 'accounts', 'level' => 'info', 'icon' => 'file',
            'channels' => 'app',
        ],
        'accounts.payment' => [
            'label' => 'Vendor paid',
            'group' => 'accounts', 'level' => 'warning', 'icon' => 'arrow-up',
            'channels' => 'app',
        ],
        'accounts.receipt' => [
            'label' => 'Customer receipt',
            'group' => 'accounts', 'level' => 'success', 'icon' => 'arrow-down',
            'channels' => 'app',
        ],
        'pettycash.approval' => [
            'label' => 'Petty cash waiting for approval',
            'group' => 'accounts', 'level' => 'warning', 'icon' => 'alert',
            'channels' => 'app',
        ],

        /* ── System ─────────────────────────────────────────────────────── */

        'user.created' => [
            'label' => 'New user added',
            'group' => 'system', 'level' => 'info', 'icon' => 'user',
            'channels' => 'app',
        ],

        /*
         * The night's figures, the moment they are frozen. This one defaults
         * to mail as well as the bell because the person who most wants it —
         * the owner — is asleep when it fires and is not going to open the
         * app at four in the morning to find out how last night went.
         */
        'audit.closed' => [
            'label' => 'Night audit closed',
            'group' => 'system', 'level' => 'success', 'icon' => 'check-circle',
            'channels' => 'app,mail',
        ],

        /*
         * A drawer counted. The cash figure is in the body, so a manager who
         * is not in the building reads the answer in the notification rather
         * than having to open the shift report to find out whether it
         * balanced. It stays a 'success' event: a short drawer is a fact to
         * look into, not an alarm to wake somebody with.
         */
        'shift.closed' => [
            'label' => 'Cashier shift closed',
            'group' => 'system', 'level' => 'success', 'icon' => 'wallet',
            'channels' => 'app',
        ],
        /* ── Messages to the guest ──────────────────────────────────────
         *
         * These are the ones that reach the guest's own phone, and they are
         * the reason the WhatsApp gateway is set up at all — so they default
         * to `whatsapp` rather than to `app`. Nothing appears in the bell for
         * them; the delivery log on the settings screen is where they show up.
         *
         * Switch one off here and it stops, without anybody editing the text.
         */

        'guest.booking' => [
            'label' => 'Booking confirmed → guest',
            'group' => 'guest', 'level' => 'success', 'icon' => 'calendar',
            'channels' => 'whatsapp,mail',
        ],
        'guest.booking-cancelled' => [
            'label' => 'Booking cancelled → guest',
            'group' => 'guest', 'level' => 'danger', 'icon' => 'x-circle',
            'channels' => 'whatsapp,mail',
        ],
        'guest.advance' => [
            'label' => 'Advance received → guest',
            'group' => 'guest', 'level' => 'success', 'icon' => 'wallet',
            'channels' => 'whatsapp,mail',
        ],
        'guest.checkin' => [
            'label' => 'Welcome on check-in → guest',
            'group' => 'guest', 'level' => 'success', 'icon' => 'home',
            'channels' => 'whatsapp,mail',
        ],
        'guest.payment' => [
            'label' => 'Payment receipt → guest',
            'group' => 'guest', 'level' => 'success', 'icon' => 'credit-card',
            'channels' => 'whatsapp,mail',
        ],
        'guest.checkout' => [
            'label' => 'Bill on check-out → guest',
            'group' => 'guest', 'level' => 'info', 'icon' => 'file',
            'channels' => 'whatsapp,mail',
        ],
        'guest.pool' => [
            'label' => 'Pool booking → guest',
            'group' => 'guest', 'level' => 'success', 'icon' => 'globe',
            'channels' => 'whatsapp,mail',
        ],
        'guest.hall' => [
            'label' => 'Hall booking → guest',
            'group' => 'guest', 'level' => 'success', 'icon' => 'grid',
            'channels' => 'whatsapp,mail',
        ],
        'guest.trip' => [
            'label' => 'Pickup or drop booked → guest',
            'group' => 'guest', 'level' => 'success', 'icon' => 'arrow-right',
            'channels' => 'whatsapp,mail',
        ],
        'guest.trip-started' => [
            'label' => 'Car on its way → guest',
            'group' => 'guest', 'level' => 'info', 'icon' => 'clock',
            'channels' => 'whatsapp,mail',
        ],
        'guest.parking' => [
            'label' => 'Parking ticket → guest',
            'group' => 'guest', 'level' => 'info', 'icon' => 'package',
            'channels' => 'whatsapp,mail',
        ],
        'guest.pos-bill' => [
            'label' => 'Restaurant bill → guest',
            'group' => 'guest', 'level' => 'info', 'icon' => 'bag',
            'channels' => 'whatsapp,mail',
        ],
        'guest.pos-order' => [
            'label' => 'Order placed → guest',
            'group' => 'guest', 'level' => 'info', 'icon' => 'bag',
            'channels' => 'whatsapp,mail',
        ],

        'system.test' => [
            'label' => 'Test message',
            'group' => 'system', 'level' => 'info', 'icon' => 'bell',
            'channels' => 'app,mail,whatsapp',
        ],
    ],
];
