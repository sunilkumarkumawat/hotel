<?php

/*
|--------------------------------------------------------------------------
| What the guest is actually told
|--------------------------------------------------------------------------
| The WhatsApp messages that go to the GUEST — not the staff notifications,
| which live in config/notifications.php and appear in the bell.
|
| Edit the text here and it changes everywhere, immediately; no migration and
| no database. Anything in {curly braces} is filled in when the message is
| sent, and a placeholder with nothing behind it is removed along with its line
| rather than printed empty — so a booking with no advance paid does not send a
| message with a dangling "Advance received:".
|
| WHATSAPP'S OWN FORMATTING WORKS HERE and is what makes these read like a
| hotel rather than like a database:
|
|     *bold*      _italic_      ~strikethrough~      ```monospace```
|
| The stars and underscores are not printed — WhatsApp turns them into the
| formatting. A label written *Check-in:* comes out bold, and if its value is
| empty the whole line still disappears cleanly, markers and all.
|
| {attachment} is special: it becomes a line about the PDF when one is going
| out, and nothing at all when the system cannot send attachments — so no
| message ever promises a document that is not there. See
| config/guest-documents.php for what those PDFs say.
|
| Emoji are left out on purpose. They travel as four bytes through a gateway
| that is only documented for plain text, and a booking confirmation that
| arrives as question marks is a worse trade than one without a smiley. Paste
| one in here if you want it — nothing stops you, and you will see it in a test
| message straight away.
|
| Switch a message on or off from Administration → Notification Settings: each
| one is an event there (guest.booking, guest.checkin, …) with WhatsApp as its
| channel. Turning the event off stops the message without touching this file.
|
| Keep them short. This arrives on a phone.
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Reservation
    |--------------------------------------------------------------------------
    */

    'guest.booking' => "*{hotel}*\n"
        . "_BOOKING CONFIRMATION_\n\n"
        . "Dear {guest},\n\n"
        . "Your reservation has been successfully confirmed! 🎉\n"
        . "We look forward to welcoming you at *{hotel}*.\n\n"
        . "*📅 BOOKING DETAILS*\n"
        . "*Booking ID:* {reservation_no}\n"
        . "*Guest Name:* {guest}\n"
        . "*Room Type:* {room_type}\n"
        . "*Room No.:* {room_no}\n"
        . "*Check-in:* {arrival} {arrival_time}\n"
        . "*Check-out:* {departure} {departure_time}\n"
        . "*Total Nights:* {nights}\n"
        . "*Guests:* {guests}\n"
        . "*Total Amount:* {amount}\n"
        . "*Payment Mode:* {payment_mode}\n"
        . "*Payment Status:* {payment_status}\n\n"
        . "{attachment}\n\n"
        . "If you need any assistance, feel free to reply to this message. 😊\n\n"
        . "Warm Regards,\n"
        . "*{hotel}*\n"
        . "{phone}",

    'guest.booking-cancelled' => "*{hotel}*\n"
        . "_Booking cancelled_\n\n"
        . "Namaste {guest},\n\n"
        . "Your booking *{reservation_no}* for {arrival} has been cancelled as requested.\n\n"
        . "*Refund:* {refund}\n\n"
        . "We hope to host you another time. Call {phone} if this was not you.",

    'guest.advance' => "*{hotel}*\n"
        . "_Advance received_\n\n"
        . "Namaste {guest},\n\n"
        . "Thank you — we have received your advance.\n\n"
        . "*Booking no:* {reservation_no}\n"
        . "*Amount received:* {amount}\n"
        . "*Balance:* {balance}\n"
        . "*Check-in:* {arrival}\n\n"
        . "{attachment}",

    /*
    |--------------------------------------------------------------------------
    | Front office
    |--------------------------------------------------------------------------
    */

    'guest.checkin' => "*{hotel}*\n"
        . "_Welcome_\n\n"
        . "Welcome, {guest}. You are checked in.\n\n"
        . "*Room:* {room}\n"
        . "*Folio no:* {folio_no}\n"
        . "*Check-out:* {departure}\n\n"
        . "{attachment}\n"
        . "For anything at all, dial reception or call {phone}.\n"
        . "_Have a comfortable stay._",

    'guest.payment' => "*{hotel}*\n"
        . "_Payment received_\n\n"
        . "Thank you, {guest}.\n\n"
        . "*Amount received:* {amount}\n"
        . "*Mode:* {mode}\n"
        . "*Folio no:* {folio_no}\n"
        . "*Balance now:* {balance}\n\n"
        . "{attachment}",

    'guest.checkout' => "*{hotel}*\n"
        . "_Checked out_\n\n"
        . "Thank you for staying with us, {guest}.\n\n"
        . "*Folio no:* {folio_no}\n"
        . "*Bill total:* {amount}\n"
        . "*Paid:* {paid}\n"
        . "*Balance:* {balance}\n\n"
        . "{attachment}\n"
        . "It was a pleasure having you. _We hope to see you again._\n\n"
        . "How did we do? {feedback_url}",

    /*
    |--------------------------------------------------------------------------
    | Pool and hall
    |--------------------------------------------------------------------------
    */

    'guest.pool' => "*{hotel}*\n"
        . "_Pool booking confirmed_\n\n"
        . "Namaste {guest},\n\n"
        . "*Booking no:* {booking_no}\n"
        . "*Pool:* {pool}\n"
        . "*Date:* {date}\n"
        . "*Time:* {time}\n"
        . "*People:* {people}\n"
        . "*Amount:* {amount}\n\n"
        . "{attachment}\n"
        . "_Please carry your own swimwear and a towel._",

    'guest.hall' => "*{hotel}*\n"
        . "_Hall booking confirmed_\n\n"
        . "Namaste {guest},\n\n"
        . "*Booking no:* {booking_no}\n"
        . "*Hall:* {hall}\n"
        . "*Date:* {date}\n"
        . "*Time:* {time}\n"
        . "*Guests:* {people}\n"
        . "*Amount:* {amount}\n"
        . "*Advance received:* {advance}\n\n"
        . "{attachment}\n"
        . "Our banquet team will call you to run through the arrangements.",

    /*
    |--------------------------------------------------------------------------
    | Car
    |--------------------------------------------------------------------------
    */

    'guest.trip' => "*{hotel}*\n"
        . "_Transfer booked_\n\n"
        . "Namaste {guest},\n\n"
        . "*From:* {from}\n"
        . "*To:* {to}\n"
        . "*Date:* {date}\n"
        . "*Time:* {time}\n"
        . "*Vehicle:* {vehicle}\n"
        . "*Driver:* {driver} {driver_mobile}\n\n"
        . "{attachment}\n"
        . "The driver will call you shortly before pick-up.",

    'guest.trip-started' => "*{hotel}*\n"
        . "_Your car is on the way_\n\n"
        . "{guest}, your car has left for the pick-up point.\n\n"
        . "*Vehicle:* {vehicle}\n"
        . "*Driver:* {driver} {driver_mobile}\n\n"
        . "Please be ready. Call {phone} if you cannot find the car.",

    'guest.parking' => "*{hotel}*\n"
        . "_Vehicle parked_\n\n"
        . "Namaste {guest},\n\n"
        . "*Ticket no:* {ticket_no}\n"
        . "*Vehicle:* {vehicle}\n"
        . "*Slot:* {slot}\n"
        . "*Parked at:* {time}\n"
        . "*Charge:* {amount}\n\n"
        . "{attachment}\n"
        . "_Please show this when you collect the vehicle._",

    /*
    |--------------------------------------------------------------------------
    | Restaurant
    |--------------------------------------------------------------------------
    */

    'guest.pos-bill' => "*{hotel}*\n"
        . "_{outlet}_\n\n"
        . "Thank you, {guest}.\n\n"
        . "*Bill no:* {bill_no}\n"
        . "*Total:* {amount}\n\n"
        . "{attachment}\n"
        . "_We hope you enjoyed your meal._",

    'guest.pos-order' => "*{hotel}*\n"
        . "_Order received_\n\n"
        . "Namaste {guest},\n\n"
        . "Your order has been sent to the kitchen.\n\n"
        . "*Where:* {where}\n"
        . "*Items:* {items}\n\n"
        . "_We'll have it ready shortly._",

];
