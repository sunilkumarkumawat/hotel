<?php

/*
|--------------------------------------------------------------------------
| The PDF that goes with the guest's message
|--------------------------------------------------------------------------
| A WhatsApp message is read once on a phone. The PDF is the thing the guest
| keeps, forwards to whoever is paying, and shows at the desk — so it carries
| the same facts laid out properly, with the hotel's name at the top.
|
| One entry per event, keyed exactly like config/guest-messages.php. An event
| with no entry here still sends its message; it simply sends it without an
| attachment.
|
| Anything in {curly braces} is filled in from the same data the message uses,
| so adding a line here needs nothing changed in any controller. A line whose
| placeholder comes out empty is dropped, and a section left with no lines is
| dropped with it — a booking with no advance paid does not print a blank
| "Advance received" row.
|
| Every event below draws with the same teal-and-gold branded template
| (see app/Support/GuestDocument.php) — 'sections' becomes one boxed card per
| heading, and an optional 'highlight' becomes the gold total callout, the
| same shape the booking voucher has always had. 'badge' is the small pill in
| the header's top-right corner; it switches itself to a red "BALANCE DUE" or
| green "SETTLED" automatically on any event whose data carries a balance
| (checkout, a payment, an advance) — see GuestDocument::header() — so it only
| needs setting here for events that never carry one.
|
| ATTACHMENTS ONLY WORK ON A PUBLIC ADDRESS. The gateway fetches the file from
| APP_URL (or PMS_PUBLIC_URL), so while that is http://127.0.0.1:8000 it can
| reach nothing and the message is sent on its own. Administration ->
| Notification Settings says so on screen rather than leaving anybody
| guessing.
*/

return [

    'guest.booking' => [
        'title' => 'Booking Confirmation Voucher',
        'reference' => '{reservation_no}',
        'greeting' => 'Your reservation has been successfully confirmed!',
        'lead' => 'Thank you for choosing {hotel}. We look forward to welcoming you.',
        'sections' => [
            'Guest Details' => [
                'Guest Name' => '{guest}',
                'Phone' => '{guest_phone}',
                'Email' => '{guest_email}',
                'Address' => '{guest_address}',
            ],
            'Room Details' => [
                'Room Type' => '{room_type}',
                'Room No.' => '{room_no}',
                'Meal Plan' => '{meal_plan}',
            ],
            'Booking Details' => [
                'Booking ID' => '{reservation_no}',
                'Booking Date' => '{booking_date}',
                'Check-in' => '{arrival} {arrival_time}',
                'Check-out' => '{departure} {departure_time}',
                'Total Nights' => '{nights}',
                'Guests' => '{guests}',
            ],
            'Payment Details' => [
                'Payment Mode' => '{payment_mode}',
                'Payment Status' => '{payment_status}',
                'Total Amount' => '{amount}',
                'Advance Paid' => '{advance}',
                'Balance' => '{balance}',
            ],
        ],
        'note' => 'Check-in time is 02:00 PM and check-out time is 11:00 AM unless otherwise agreed. Please carry a valid photo ID for every adult guest. Early check-in and late check-out are subject to availability.',
    ],

    'guest.checkin' => [
        'title' => 'Registration Slip',
        'badge' => 'CHECKED IN',
        'reference' => '{folio_no}',
        'greeting' => 'Welcome, {guest}.',
        'lead' => 'You are checked in. This slip has your room and folio number on it — the desk '
            . 'will ask for the folio number when anything is charged to the room.',
        'sections' => [
            'Guest Details' => [
                'Guest Name' => '{guest}',
                'Phone' => '{guest_phone}',
                'Email' => '{guest_email}',
            ],
            'Stay Details' => [
                'Reservation No.' => '{reservation_no}',
                'Room' => '{room}',
                'Room Type' => '{room_type}',
                'Meal Plan' => '{meal_plan}',
                'Guests' => '{guests}',
                'Folio No.' => '{folio_no}',
                'Check-in' => '{arrival}',
                'Expected Check-out' => '{departure}',
            ],
        ],
        'note' => 'Anything at all — dial the reception from your room.',
    ],

    'guest.checkout' => [
        'title' => 'Final Bill',
        'badge' => 'SETTLED',
        'reference' => '{bill_no}',
        'greeting' => 'Thank you for staying with us, {guest}.',
        'lead' => 'Here is your bill. We hope to see you again.',
        'sections' => [
            'Guest Details' => [
                'Guest Name' => '{guest}',
                'Phone' => '{guest_phone}',
            ],
            'Stay Details' => [
                'Room' => '{room}',
                'Room Type' => '{room_type}',
                'Meal Plan' => '{meal_plan}',
                'Folio No.' => '{folio_no}',
                'Check-in' => '{checkin_date}',
                'Checked out' => '{departure}',
                'Nights' => '{nights}',
            ],
            'Bill Breakdown' => [
                'Room Charges' => '{room_total}',
                'Service Charges' => '{service_total}',
                'Discount' => '{discount_total}',
                'Tax' => '{tax_total}',
            ],
        ],
        'highlight' => [
            'label' => 'Total Amount',
            'value' => '{amount}',
            'sub' => [
                'Paid' => '{paid}',
                'Balance' => '{balance}',
            ],
        ],
        'note' => 'This is a computer-generated bill and needs no signature.',
    ],

    'guest.payment' => [
        'title' => 'Payment Receipt',
        'badge' => 'RECEIVED',
        'reference' => '{folio_no}',
        'greeting' => 'Thank you, {guest}.',
        'lead' => 'We have received your payment. This is your receipt.',
        'sections' => [
            'Guest Details' => [
                'Guest Name' => '{guest}',
                'Phone' => '{guest_phone}',
            ],
            'Stay Details' => [
                'Room' => '{room}',
                'Folio No.' => '{folio_no}',
                'Mode' => '{mode}',
            ],
        ],
        'highlight' => [
            'label' => 'Amount Received',
            'value' => '{amount}',
            'sub' => [
                'Balance now' => '{balance}',
            ],
        ],
    ],

    'guest.advance' => [
        'title' => 'Advance Receipt',
        'badge' => 'RECEIVED',
        'reference' => '{reservation_no}',
        'greeting' => 'Thank you, {guest}.',
        'lead' => 'Your advance has been received against the booking below.',
        'sections' => [
            'Guest Details' => [
                'Guest Name' => '{guest}',
                'Phone' => '{guest_phone}',
            ],
            'Booking Details' => [
                'Booking No.' => '{reservation_no}',
                'Check-in' => '{arrival}',
            ],
        ],
        'highlight' => [
            'label' => 'Advance Received',
            'value' => '{amount}',
            'sub' => [
                'Balance' => '{balance}',
            ],
        ],
    ],

    'guest.pool' => [
        'title' => 'Pool Booking',
        'reference' => '{booking_no}',
        'greeting' => 'Namaste {guest},',
        'lead' => 'Your pool session is booked. Please show this at the pool gate.',
        'sections' => [
            'Session' => [
                'Pool' => '{pool}',
                'Date' => '{date}',
                'Time' => '{time}',
                'People' => '{people}',
            ],
            'Payment' => [
                'Amount' => '{amount}',
            ],
        ],
        'note' => 'Please carry your own swimwear and a towel. Children must be with an adult.',
    ],

    'guest.hall' => [
        'title' => 'Hall Booking',
        'reference' => '{booking_no}',
        'greeting' => 'Namaste {guest},',
        'lead' => 'Your hall booking is confirmed. Our banquet team will call you to run '
            . 'through the arrangements.',
        'sections' => [
            'Event' => [
                'Hall' => '{hall}',
                'Date' => '{date}',
                'Time' => '{time}',
                'Guests' => '{people}',
            ],
            'Payment' => [
                'Amount' => '{amount}',
                'Advance received' => '{advance}',
            ],
        ],
    ],

    'guest.trip' => [
        'title' => 'Transfer Booked',
        'reference' => '{trip_no}',
        'greeting' => 'Namaste {guest},',
        'lead' => 'Your car is booked. The driver will call you shortly before pick-up.',
        'sections' => [
            'Journey' => [
                'From' => '{from}',
                'To' => '{to}',
                'Date' => '{date}',
                'Time' => '{time}',
                'Vehicle' => '{vehicle}',
                'Driver' => '{driver}',
            ],
            'Payment' => [
                'Amount' => '{amount}',
            ],
        ],
    ],

    'guest.parking' => [
        'title' => 'Parking Ticket',
        'reference' => '{ticket_no}',
        'greeting' => 'Namaste {guest},',
        'lead' => 'Your vehicle is parked with us. Please show this ticket when you collect it.',
        'sections' => [
            'Vehicle' => [
                'Vehicle' => '{vehicle}',
                'Slot' => '{slot}',
                'Parked at' => '{time}',
            ],
            'Payment' => [
                'Charge' => '{amount}',
            ],
        ],
    ],

    'guest.pos-bill' => [
        'title' => 'Restaurant Bill',
        'badge' => 'PAID',
        'reference' => '{bill_no}',
        'greeting' => 'Thank you, {guest}.',
        'lead' => 'Here is your bill from {outlet}.',
        'sections' => [
            'Bill Details' => [
                'Guest Name' => '{guest}',
                'Room' => '{room}',
                'Outlet' => '{outlet}',
                'Bill No.' => '{bill_no}',
                'Items' => '{items}',
            ],
        ],
        'highlight' => [
            'label' => 'Total Amount',
            'value' => '{amount}',
        ],
        'note' => 'This is a computer-generated bill and needs no signature.',
    ],

    'guest.pos-order' => [
        'title' => 'Order Confirmation',
        'badge' => 'IN KITCHEN',
        'reference' => '{where}',
        'greeting' => 'Thank you, {guest}.',
        'lead' => 'Your order has been sent to the kitchen.',
        'sections' => [
            'Order Details' => [
                'Guest Name' => '{guest}',
                'Where' => '{where}',
                'Items' => '{items}',
            ],
        ],
        'note' => "We'll have it ready shortly.",
    ],

];
