<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tax
    |--------------------------------------------------------------------------
    | Nothing in this system adds tax to a figure on its own. Every row that
    | carries money carries a Tax dropdown beside it, and that dropdown decides
    | — `No Tax` (which is what it opens on), the GST slab, or one of the taxes
    | set up in Masters → Tax.
    |
    | This is the one place that can change what a *new* row opens on. Leave it
    | as 'none' and every screen starts with no tax, which is what the hotel
    | asked for. Set it to 'slab' — or to the id of a row in Masters → Tax — if
    | you would rather the dropdown came up already pointing at something. It
    | never changes a row that is already saved, and the clerk can always put it
    | back to No Tax on the screen.
    */

    /*
    |--------------------------------------------------------------------------
    | The PDF that goes with a guest's WhatsApp message
    |--------------------------------------------------------------------------
    | What it says lives in config/guest-documents.php. It is only ever
    | attached when APP_URL is an address the outside world can reach, because
    | WhatsApp fetches the file itself — see App\Support\GuestDocument.
    |
    | The documents are kept for a few weeks and then cleared out. A guest opens
    | the link within minutes of the message; a folder holding every bill the
    | hotel has ever sent is a year of PDFs nobody reads.
    */
    'guest_pdf' => env('PMS_GUEST_PDF', true),

    /*
     * The address a guest's PDF link is built on, when it has to differ from
     * APP_URL. A hotel running this on a laptop can point a tunnel at it and
     * put the tunnel's address here: WhatsApp can then fetch the file while
     * the application itself carries on living on 127.0.0.1. Left empty,
     * APP_URL is used, which is right for a real server.
     *
     * Email never needs this. It carries the file itself.
     */
    'public_url' => env('PMS_PUBLIC_URL'),
    'guest_doc_days' => (int) env('PMS_GUEST_DOC_DAYS', 30),

    /*
     * Where `php artisan pms:tunnel-watch` (routes/console.php) finds the
     * cloudflared program, only if it is not where the official Windows
     * installer always puts it. Leave this empty unless a portable copy of
     * cloudflared.exe lives somewhere unusual on this machine — the command
     * already checks the normal install path (and, failing that, PATH) on
     * its own.
     */
    'cloudflared_path' => env('CLOUDFLARED_PATH'),

    'tax_default' => env('PMS_TAX_DEFAULT', 'none'),

    /*
    |--------------------------------------------------------------------------
    | Room GST slabs
    |--------------------------------------------------------------------------
    | Indian hotel GST is charged on the *rent per room per night*, not on the
    | bill total: up to ₹7,500 it is 12%, above that 18%. Each entry is
    | "up to this rent" => "this percent"; the last one is the top slab.
    |
    | Set this to an empty array to switch the whole system to a single flat
    | rate — it then uses whichever tax you marked as default in Masters → Tax.
    |
    | None of this happens unless somebody picks "GST slab" from a Tax dropdown.
    | These numbers are what that choice means, not what the app does by itself.
    */

    'room_tax_slabs' => [
        1000 => 0,
        7500 => 12,
        'above' => 18,
    ],

    /*
    |--------------------------------------------------------------------------
    | Check-in / check-out defaults
    |--------------------------------------------------------------------------
    */

    'default_arrival_time' => '12:00',
    'default_checkout_time' => '11:00',

    /*
     * The longest stay the folio will post room charges for in one go.
     *
     * A booking's expected checkout date is typed by hand, and a slipped
     * year (2126 instead of 2026) turns "one night" into tens of thousands —
     * Folio::postRoomCharges() would insert one row per night and the
     * checkout screen would then try to render all of them, which is heavy
     * enough to crash the browser tab rather than show a bill. This is a
     * sanity limit, not a business rule: a real stay this long has never
     * happened at a hotel, so past it the folio refuses to post and says so,
     * instead of quietly building a folio nobody could open.
     */
    'max_stay_nights' => (int) env('PMS_MAX_STAY_NIGHTS', 366),

    /*
    |--------------------------------------------------------------------------
    | Numbering
    |--------------------------------------------------------------------------
    */

    'reservation_prefix' => 'RSV',
    'folio_prefix' => 'FO',
    'bill_prefix' => 'BILL',
    // Five rooms billed together share one of these; each still has its own bill_no.
    'bill_group_prefix' => 'GRP',
    'work_order_prefix' => 'WO',
    'pos_order_prefix' => 'ORD',
    'pool_booking_prefix' => 'POOL',
    'hall_booking_prefix' => 'HALL',
    'parking_prefix' => 'PRK',
    'trip_prefix' => 'TRIP',

    /*
    |--------------------------------------------------------------------------
    | Kitchen Display System
    |--------------------------------------------------------------------------
    | How long a ticket may sit before the screen starts saying so. The first
    | number turns it amber, the second turns it red — in minutes.
    |
    | These are service-level targets, not alarms: nothing is blocked when a
    | ticket goes over, the kitchen simply sees which one to pick up next.
    */

    'kds_warn_minutes' => 10,
    'kds_late_minutes' => 20,

    /*
    | How often the Kitchen Display System asks the server for new tickets, in
    | seconds. The clocks on screen tick every second regardless — only the
    | list of tickets waits for this.
    */

    'kds_refresh_seconds' => 20,

    /*
    |--------------------------------------------------------------------------
    | Work order categories
    |--------------------------------------------------------------------------
    | What a maintenance job can be about. Edit this list and the Category
    | dropdown on Add Work Order follows — no migration, no database change.
    | The key is what gets stored, so add new ones at the end rather than
    | renaming a key that jobs already point at.
    */

    'work_order_categories' => [
        'electrical' => 'Electrical',
        'plumbing' => 'Plumbing',
        'carpentry' => 'Carpentry',
        'hvac' => 'AC / HVAC',
        'painting' => 'Painting',
        'furniture' => 'Furniture',
        'housekeeping' => 'Housekeeping',
        'it' => 'IT / Network',
        'lift' => 'Lift',
        'other' => 'Other',
    ],


    /*
    |--------------------------------------------------------------------------
    | Car parking and the car itself
    |--------------------------------------------------------------------------
    | Parking is free. That is the default and it is deliberate: the tick that
    | turns a parking record into a charge starts off, and a stay that parks a
    | car owes nothing until somebody turns it on. Put a number in
    | `parking_default_rate` only if you want the *suggested* figure to be
    | something other than zero when the tick is turned on — it still charges
    | nothing while the tick is off.
    */

    'parking_charge_default' => false,
    'parking_default_rate' => 0,

    // Same idea for a pickup or a drop: the trip is recorded either way, and
    // whether it costs the guest anything is a tick on the trip.
    'trip_charge_default' => false,

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    | How often the bell asks the server whether anything has happened, in
    | seconds, and how many it keeps on screen. The browser pop-up (the
    | WhatsApp-style one) fires from the same feed, so this is its speed too.
    */

    'notify_poll_seconds' => env('PMS_NOTIFY_POLL', 25),
    'notify_keep_days' => env('PMS_NOTIFY_KEEP_DAYS', 30),
    'notify_feed_limit' => 30,

    /*
     * Kill switches for outbound WhatsApp and mail — staff and guest — and
     * the guest PDF that rides with them, so each can be taken out of the
     * picture on its own while these are being tested a piece at a time,
     * without unticking Notification Settings event by event. The in-app
     * bell is never affected by any of these — it is always written.
     *
     * Guest WhatsApp, the guest PDF link that rides with it, and now guest
     * and staff mail too are all on at the desk's own request. Only staff
     * WhatsApp still stays paused until asked for. Flip one back to true —
     * or set the matching PMS_..._PAUSED=true in .env — to pause just that
     * piece again.
     */
    'guest_whatsapp_paused' => (bool) env('PMS_GUEST_WHATSAPP_PAUSED', false),
    'guest_mail_paused' => (bool) env('PMS_GUEST_MAIL_PAUSED', false),
    'guest_pdf_paused' => (bool) env('PMS_GUEST_PDF_PAUSED', false),
    'staff_mail_paused' => (bool) env('PMS_STAFF_MAIL_PAUSED', false),
    'staff_whatsapp_paused' => (bool) env('PMS_STAFF_WHATSAPP_PAUSED', true),

];
