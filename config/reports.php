<?php

/*
|--------------------------------------------------------------------------
| The Reports section
|--------------------------------------------------------------------------
| One entry per report. The key is the url segment and the permission is the
| section's, not the report's — a hotel that trusts somebody with reports
| trusts them with all of them, and eighteen permission rows for eighteen
| reports is a matrix nobody would ever tick correctly.
|
|   label    what the tile and the page are called
|   group    which heading it sits under on the index
|   icon     from resources/views/components/icon.blade.php
|   about    one line under the title, saying what question it answers
|   filters  which controls the screen draws: range, room_type, pay_mode,
|            outlet, room, status
|
| Adding a report means adding an entry here and a builder in
| App\Support\Reports — nothing else, and no migration.
*/

return [

    'groups' => [
        'front-desk' => 'Front Desk',
        'revenue' => 'Money',
        'operations' => 'Operations',
        'facilities' => 'Pool, Hall & Car',
    ],

    'reports' => [

        /* ── Front desk ─────────────────────────────────────────────────── */

        'arrivals' => [
            'label' => 'Arrivals',
            'group' => 'front-desk',
            'icon' => 'arrow-down',
            'about' => 'Who is expected, when, and in which kind of room.',
            'filters' => ['range', 'room_type'],
        ],
        'departures' => [
            'label' => 'Departures',
            'group' => 'front-desk',
            'icon' => 'arrow-up',
            'about' => 'Who is leaving, and what they still owe.',
            'filters' => ['range'],
        ],
        'in-house' => [
            'label' => 'In House',
            'group' => 'front-desk',
            'icon' => 'users',
            'about' => 'Everybody in the hotel right now, room by room.',
            'filters' => ['room_type'],
        ],
        'guest-list' => [
            'label' => 'Guest List',
            'group' => 'front-desk',
            'icon' => 'user',
            'about' => 'Everyone who stayed between two dates — the list a marketing letter is written from.',
            'filters' => ['range'],
        ],
        'cancellations' => [
            'label' => 'Cancellations',
            'group' => 'front-desk',
            'icon' => 'x-circle',
            'about' => 'Bookings that were cancelled, and the reasons given.',
            'filters' => ['range'],
        ],
        'no-show' => [
            'label' => 'No Show',
            'group' => 'front-desk',
            'icon' => 'alert',
            'about' => 'Bookings nobody arrived for, and what had been taken in advance.',
            'filters' => ['range'],
        ],

        /* ── Money ──────────────────────────────────────────────────────── */

        'occupancy' => [
            'label' => 'Occupancy',
            'group' => 'revenue',
            'icon' => 'trending-up',
            'about' => 'Rooms sold, occupancy, average rate and RevPAR, night by night.',
            'filters' => ['range'],
        ],
        'revenue' => [
            'label' => 'Revenue',
            'group' => 'revenue',
            'icon' => 'chart',
            'about' => 'What was earned each day, split by where it came from.',
            'filters' => ['range'],
        ],
        'tax-summary' => [
            'label' => 'Tax Summary',
            'group' => 'revenue',
            'icon' => 'file',
            'about' => 'Taxable value and tax, grouped by rate — the figures a GST return is filled in from.',
            'filters' => ['range'],
        ],
        'collections' => [
            'label' => 'Collections',
            'group' => 'revenue',
            'icon' => 'wallet',
            'about' => 'Money taken, by pay mode, at the desk and at the till.',
            'filters' => ['range', 'pay_mode'],
        ],
        'outstanding' => [
            'label' => 'Outstanding',
            'group' => 'revenue',
            'icon' => 'credit-card',
            'about' => 'Bills that are not fully paid, oldest first.',
            'filters' => ['range'],
        ],
        'pos-sales' => [
            'label' => 'POS Item Sales',
            'group' => 'revenue',
            'icon' => 'bag',
            'about' => 'What the kitchen and the bar actually sold, item by item.',
            'filters' => ['range', 'outlet'],
        ],

        /* ── Operations ─────────────────────────────────────────────────── */

        'housekeeping' => [
            'label' => 'Housekeeping',
            'group' => 'operations',
            'icon' => 'layers',
            'about' => 'Every status change, who made it and when.',
            'filters' => ['range'],
        ],
        'work-orders' => [
            'label' => 'Work Orders',
            'group' => 'operations',
            'icon' => 'cog',
            'about' => 'Maintenance jobs raised and closed.',
            'filters' => ['range'],
        ],

        /* ── Pool, Hall & Car ───────────────────────────────────────────── */

        'pool' => [
            'label' => 'Pool Bookings',
            'group' => 'facilities',
            'icon' => 'globe',
            'about' => 'Who had the pool, for how long, and what it came to.',
            'filters' => ['range'],
        ],
        'hall' => [
            'label' => 'Hall Bookings',
            'group' => 'facilities',
            'icon' => 'grid',
            'about' => 'The banquet diary as a list, with what is still owed.',
            'filters' => ['range'],
        ],
        'parking' => [
            'label' => 'Parking',
            'group' => 'facilities',
            'icon' => 'package',
            'about' => 'Vehicles in and out — and the few that were charged for.',
            'filters' => ['range'],
        ],
        'trips' => [
            'label' => 'Pickup & Drop',
            'group' => 'facilities',
            'icon' => 'arrow-right',
            'about' => 'Every airport run, with the car and the driver.',
            'filters' => ['range'],
        ],
    ],
];
