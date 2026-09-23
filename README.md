# Hotel Admin

A Laravel 12 hotel PMS built on your `hotel.rspayroll.in` project, with the
whole Tosskey menu rebuilt in the Nova design.

| | |
|---|---|
| **Menu** | 9 modules, 90 sub-modules — your live Tosskey sidebar, plus a Point Of Sale module |
| **Database** | 78 tables covering reservation, front office, house keeping, point of sale, petty cash and accounting |
| **Working now** | A front-desk dashboard (room categories that open to their rooms, a colour month calendar), Masters (15 lists), New Reservation end to end, booking list, status view, booking calendar, room calendar, cancel list, Advance Deposit Details, Check in Guest + Check in Details, Reservation Calendar Monthly, Pre Reg Card, Room Calendar, Check Out Guest + billing, House Keeping Status, Issue + Received (laundry), Room Blocked, Work Order, POS Dashboard, **POS Setup** (Outlets, Tables, Item Category, Rate Plan, Department, Slots, Stewards, NC Types), plus Users / Roles / Branches / Modules |
| **Not built yet** | The other 40 screens are in the menu with a **Soon** pill — write a route and the pill disappears on its own |

---

## 1. Install

Open **Command Prompt** (not PowerShell) in the project folder and run these
one at a time:

```
composer install
copy .env.example .env
php artisan key:generate
```

> PowerShell users: `&&` does not work there. Run one line per press of Enter,
> or use `;` between commands.

## 2. Database

Create the database first — phpMyAdmin → **New** → name it `hotel_admin`.

Then open `.env` and set:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hotel_admin
DB_USERNAME=root
DB_PASSWORD=
```

Now pick **one**:

**A — let Laravel build it (recommended)**

```
php artisan migrate --seed
```

**B — import the SQL file**

phpMyAdmin → `hotel_admin` → **Import** → choose `database/hotel_admin.sql`.

Same 109 tables, same starting data. Use A from then on, so new migrations
apply without wiping what you have.

> **Already running an older copy?** Do not re-import — you would lose your
> bookings. Just run `php artisan migrate` once. Recent versions add the plan
> charge to booking rows and check-ins, the link from a folio line back to the
> service it came from, and the laundry tables (`vendors`, `hk_issue_items`,
> `hk_receipt_items`, and rates on `hk_items`) and the work order columns
> (`order_no`, `category`, start/end dates, and the block a job can hold its
> room with), the eight Point Of Sale tables, and the POS Setup tables — a much
> wider `outlets` (address, tax numbers, bill series, print settings, logo),
> plus `outlet_user`, `pos_table_groups`, `pos_tables`, `pos_rate_plans`,
> `pos_departments`, `pos_stewards`, `pos_nc_types` and
> `pos_reservation_slots`. The plan charge is filled in for bookings you have
> already taken, and old work orders are given numbers.
> The newest migrations add `business_days` (the hotel's own calendar, behind
> the Night Audit screen) and `rate_seasons`, `rate_plans` and `rate_rules`
> with a `rate_plan_id` on reservations, reservation rooms and check-ins —
> the Rate Management module. The one after that adds `form_c_entries`, an
> ID and nationality on `check_ins`, GST buyer details on `bills`, and the
> GST state and FRRO code on `branches` — the Compliance module. The newest
> adds `guest_notes`, `guest_feedback` and `loyalty_entries`, plus the CRM
> figures on `guests` — Guest CRM. The newest of all adds the seven store
> tables — `store_categories`, `store_items`, `store_docs`, `store_doc_items`,
> `stock_ledger`, `recipes` and `recipe_items` — the Store module. The newest
> of all adds `cashier_shifts` and `activity_logs` — shift closing and the
> audit trail.
> After migrating, run `php artisan db:seed --class=MenuSeeder` once to add the
> Point Of Sale module, its Setup screens, Night Audit and the whole Rate
> Management, Compliance, Guest CRM, Store and Shift & Audit modules to your
> sidebar — they are
> appended, so nothing you already have moves or loses its permissions. Then
> tick the new rows for your user under **Administration → Users**.
>
> Outlet logos are stored on the public disk, so run `php artisan storage:link`
> once as well if you want to upload one. Everything else works without it.

## 3. Run

```
php artisan serve
```

Open <http://127.0.0.1:8000> and sign in:

```
username: admin
password: password
```

Change that password from **My profile → Change password** before anyone else
touches it.

Two demo cleaners are seeded as well — **meena** and **ramesh**, same password
— so House Keeping Status has somebody to assign rooms to. They can see only
House Keeping Status and the Room Calendar, which makes either of them the
account to log in as when you want to see what a limited user sees. Delete
them from **Administration → User** when you put this live.

---

## 4. Putting it on a live server

Laravel writes two things while it runs: the compiled Blade views and the
framework caches. Upload the files by FTP and they land owned by *your* FTP
user, while PHP runs as somebody else — usually `www-data` or `apache` — so it
cannot write to them. That is one command to fix, and skipping it is what
almost every "it worked locally" report turns out to be.

**With SSH:**

```
cd /var/www/your-site
mkdir -p storage/framework/views storage/framework/cache/data storage/framework/sessions bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
php artisan view:clear && php artisan cache:clear && php artisan config:clear
```

On cPanel the web user is usually your own account name rather than
`www-data` — `ps aux | grep php-fpm` says which.

**Without SSH (cPanel File Manager):** create any of those folders that are
missing, then select `storage` and `bootstrap/cache`, choose **Permissions**,
set **775**, and tick *recurse into subdirectories*.

### The two errors this prevents

| What you see | What it means |
|---|---|
| `Please provide a valid cache path` | `storage/framework/views` does not exist |
| `tempnam(): file created in the system's temporary directory` | it exists but PHP may not write to it |

### "No such file or directory" on `storage/framework/sessions`

```
file_put_contents(.../storage/framework/sessions/xxxx): Failed to open stream:
No such file or directory
```

An **empty folder that went missing in transit**. `storage/framework/sessions`,
`storage/framework/views`, `storage/framework/cache/data` and `storage/logs`
are empty in every copy of a Laravel project, and empty folders are exactly what
a zip tool drops, FTP skips and a Git checkout never had.

`App\Providers\AppServiceProvider::register()` now creates all four before
anything writes to them, so on a current copy this cannot happen. On an older
one, make them by hand:

```
mkdir storage\framework\sessions
mkdir storage\framework\cache\data
php artisan optimize:clear
```

On Linux, `mkdir -p` and then give them to the web user — see the permissions
section above.

### A fix that "did not work"

`public/build/` is made by `npm run build` and is **not in the zip**. Laravel
prefers it over `public/css/theme.css`, so an old build keeps being served after
you unzip a new version — new HTML, yesterday's CSS. The symptom is a screen
that looks subtly wrong in a way the code does not explain.

`partials/assets.blade.php` now refuses a build that is older than
`public/css/theme.css` and falls back to the compiled copy, so this should not
bite again. If it ever does:

```
rm -rf public/build          # or just delete the folder
php artisan view:clear
```

then hard-refresh the browser (Ctrl+F5). To keep using Vite instead, run
`npm run build` after each update so the build stays the newest file.

### Turn the debug screen off

A live site must not show the red Symfony stack trace — it prints your file
paths, and on some pages your database settings. In `.env`:

```
APP_ENV=production
APP_DEBUG=false
```

then `php artisan config:clear`. Errors go to `storage/logs/laravel.log`
instead, where only you can read them.

---

## What is actually working

### Masters — fill these in first

`Masters` in the sidebar. Fifteen lists, all with full add / edit / delete /
activate:

Room Category · Room Type · Plan Type · Room · Tax · Service · Company ·
Booked By · Business Market · Visit Purpose · Pick and Drop ·
Billing Instruction · Pay Mode · Expense Head · Receive Head

The seeder puts realistic demo data in — 16 rooms, 6 room types, 4 plans, GST
slabs, 6 services. Delete it once your own data is in.

A master that something is pointing at cannot be deleted. Trying to remove a
room type that reservations use tells you why instead of breaking those
bookings.

### New Reservation

Matches your screen: **Personal Details** and **Guest Details** tabs,
the rooms allotment grid, the service grid, live totals, and an advance
deposit box.

What it does for you:

- **Customer Search** finds a past guest by name, mobile or email and fills the
  whole form in, country → state → city included. Every booking saves the guest,
  so the second visit is one click.
- **Room Category (Avl : n)** is a live count, not a guess — it asks the server
  which rooms are free for exactly those dates.
- Picking a room type fills the rent in from the master; picking a plan adds its
  per-night charge.
- The room dropdown only offers rooms that are actually free, and it will not
  let you put the same room on the booking twice for overlapping nights.
- Totals move as you type. The server then recalculates all of it on save, so a
  tampered form cannot change what is stored.

**No. of Days is not typed — it is counted.** Nights come from Arrival Date and
Checked Out Date (09 Sep → 12 Sep is 3 nights), so the field is read-only and
changes when you change a date. This is what stops the bill and the calendar
from disagreeing.

**No. of Room vs Room No.** — they answer different questions:

| Room No. | No. of Room | What the row means |
|---|---|---|
| `Not allotted` | 3 | *Three rooms of this type* — numbers picked later, from Front Office or the tape chart |
| `102` | locked to 1 | *This exact room* |

So the moment you pick a room number the count locks to 1, because one row
cannot be both "room 102" and "five rooms". Need three rooms with numbers?
Add three rows. The server refuses the combination too, so a stale browser
tab cannot save five rooms while holding one.

### Reservation Status View

The availability board: room category down the side, one column per night.

Each cell in the top block shows two numbers — **sold** (green) and
**tentative** (amber). Under them come the roll-ups: Total, Blocked room and
Total available. The bottom block is what is still **free to sell**, and a
category that is sold out turns red.

- **Click a sold or tentative number** to see exactly which bookings make it up.
- **Click a free number** to open New Reservation with that night and that
  category already filled in.
- Previous / Next / a date box, and 7, 15 or 30 days.
- **Export** gives you the same grid as a CSV that opens in Excel.

Cancelled and no-show bookings are not counted. A night belongs to the arrival
date, not the checkout date — a guest leaving on the 9th frees that room for
the 9th, which is the same rule the booking form uses, so the two can never
disagree.

The whole board is three queries no matter how many categories or days are on
screen (`app/Support/Availability.php`).

### Reservation Calendar

Room category down the side, one column per night, and two numbers in every
cell:

- **green — current booking**: the guest is already checked in
- **amber — advance booking**: confirmed or tentative, not arrived yet

Under them: Total, Blocked room, Total available, and a Free-to-sell block per
category.

**Click any number** and *Reservation Booking Details* opens for that cell —
the same three blocks as the old system:

| | |
|---|---|
| **Current Booking** | Party name, booking no., room no., from, to, pax |
| **Advance Booking** | Party name, adv booking no., rooms, arrival, departure, coming from, pax, action |
| **Tally** | Current + Advance = Total room booked |

A booking with no room yet shows an **Allot** button that drops you on the Room
Calendar for that date. **Export** gives the whole grid as a CSV.

Both this screen and the Status View come off one `Availability` grid — three
queries, whatever the number of categories or days. They just cut the same
rooms differently: this one by *in house vs coming*, the Status View by
*sold vs tentative*.

### Reservation Calendar New

The tape chart: one row per room, one column per night, bookings drawn as bars.

- **Drag across free nights** on a room and a panel appears with the arrival and
  departure dates. From there: **Reservation** opens the booking form with that
  room and those dates already filled in, or **Block Room** takes the room out of
  service with a reason.
- A drag **stops at the first occupied night**, so you can never select across
  someone else's stay.
- **Click a bar** for the guest, the dates and a link to the booking. Click a
  blocked bar to release the room.
- **Waiting for a room** at the top lists bookings with no room yet, each with a
  dropdown of rooms actually free for those exact dates. Pick one and it lands
  on the chart.
- **Drag a booking bar to change its dates.** "Meri booking agli date pe kar
  do" is one drag: pick the bar up, drop it where the guest now wants it. Drop
  it on another room's row and the room changes too. A confirm box shows the
  old dates against the new ones before anything is saved.
- Categories collapse. Room colours: blue = reservation, red = checked in,
  amber = tentative, slate = blocked. The dot beside each room is its
  housekeeping state.
- The bottom two rows are **Room availability** (free / occupied) and
  **Occupancy (%)**, counted from the bars actually drawn.

A stay that starts before or ends after the visible window is drawn flush to
that edge with its corner squared off, so it is obvious it continues.

**About dragging a booking:**

- The stay keeps its length, so the nights, the rent and the tax are untouched
  — only the dates move. That is why moving a booking never re-prices it.
- Only a booking **nobody has arrived for** can be dragged, and the same goes
  for allotting a room from the "no room yet" list. Once a guest is in a room
  the bar is fixed: that is a room transfer, not a date change, and moving the
  booking under them would leave the chart naming one room while the bill
  charged another. The check is per booking row, not per booking — a booking
  with a second room still to come reads as "confirmed" while somebody is
  already asleep in the first one.
- A stay running off either edge of the window is not draggable either, since
  you would be moving it by a distance you cannot see.
- The night under the pointer is read off the date headers, not the cells, so
  a stay can be nudged **one night** even though that night is hidden under the
  bar you are holding.

Every action is re-checked on the server: blocking, allotting or moving into a
room that has since been taken is refused with the reason, because the chart in
the browser may be seconds out of date. If the same room somehow ends up with
two overlapping bookings, the screen says so at the top rather than hiding one.

### Reservation Calendar Monthly

The position of the house over 30, 45 or 60 days — one column per date, one
row per number the front office actually reads:

| Row | What it counts |
|---|---|
| Available Rooms | Every active room in the branch |
| Expected Checkin | Rooms whose stay **starts** that day |
| Stay On (InHouse) | Rooms occupied that night by somebody who arrived earlier |
| Occupancy | Rooms with somebody in them that night = arrivals + stay-on |
| Expected Checkout | Stays that **end** that day |
| Management Block / Maintanance Block | Rooms out of the pool, by the kind of block |
| Position | Available − Occupancy − blocks: what is still sellable |
| Wait List | Waiting-list bookings — they hold no room, so they never come off Position |
| Occupancy % | Occupancy ÷ Available Rooms |

Underneath, **Room Typewise Position** gives the same free count per room type.
**Export** writes the whole thing to CSV.

Two things worth knowing:

- Departures are **not** counted in Occupancy. A night belongs to the arrival
  date, so a guest leaving on the 9th frees that room for the 9th — the same
  rule the booking form and the other calendars use.
- **A red negative means the house is oversold** for that night. It is
  deliberately not clamped to zero: hiding an oversell behind a tidy `0` is
  the one thing this report must never do.

The Block Room form on the tape chart asks which kind of block it is, so these
two rows can never be a guess about what somebody typed in the reason box.

### Reservation Booking Details / Cancel Reservation List

Search by reservation number, guest or mobile; filter by status and date.
Open a booking to see the rooms, the services, the money and the deposits —
and to record another deposit or a refund.

Cancelling puts the rooms straight back in the available pool.

### Advance Deposit Details

Money taken before or during a stay, and money handed back. One screen for
both — **Payment** at the top left says which.

- **Guest** picks which list the **Guest Name** dropdown offers: advance
  bookings that have not arrived, guests currently in house, or all open
  bookings. Choosing a booking prints its net, what it has paid and what is
  left underneath.
- **Pay Mode** is the branch's own list (Masters → Pay Mode). **Pay Details**
  is how the money actually moved — cash, card, UPI, NEFT, cheque. The **Card
  Type / Name of Card / Card no.** fields only appear for a card payment and
  are cleared if you switch away from one.
- Every save re-adds that booking's deposits into `reservations.advance_paid`,
  so the balance on the reservation screen is never stale. Moving an entry to
  a different booking fixes both of them.
- A refund can never exceed what the guest has actually given you; the screen
  says how much is held and refuses the rest.
- The date cannot be in the future, and a PAN has to look like `ABCDE1234F`.

**Card numbers are never stored.** The column is four characters wide and
holds only the last four digits, which is all a receipt or a chargeback
dispute needs — keeping full card numbers in an ordinary application database
is a PCI-DSS breach and puts the hotel on the hook for it. The table shows
them as `•••• 4242`.

---

## Check in Guest

A booking is a promise. A check-in is the guest actually in the building.
**Nothing is checked in until somebody presses Save on this screen** — that is
the whole point of keeping them apart.

The flow, exactly as the desk works:

1. **Reservation Booking Details** → tick the booking's checkbox → **Check-in**
   in the toolbar. Bookings that are cancelled, or whose rooms have all
   arrived, have no checkbox; hovering the dash says why.
2. **Check in Guest** opens with the guest's details prefilled. Correct them
   off the ID card — saving writes the corrections back to the booking too.
3. **Allot Room** on a line opens the **Allotment Room No** popup: the rooms
   actually free for those nights. Tick one per guest, **Save** the popup.
4. **Save (F10)** on the page. Now they are checked in, and the screen goes to
   **Check in Details**.

Things worth knowing:

- **Rooms can arrive in batches.** A booking for 3 rooms can check 2 in today
  and 1 tomorrow. The booking list shows "3 / 2 in", and the booking only
  becomes **Checked in** when the last room arrives — undo one and it goes
  back to Confirmed, because "Checked in" has to mean every room.
- **The popup will not let you over-tick.** Three rooms owed means three ticks.
- **The popup shows the whole category, not just the booked type**, and marks
  anything that is not the booked type. Putting a Deluxe Triple guest in a
  Deluxe Double is a real decision the desk makes — the screen offers it and
  says so rather than hiding it.
- **A booking that already named a room offers that room first.** Its own hold
  is not counted as a clash against itself, and whichever room the guest is
  actually given is written back onto the booking — so the calendars, the
  registration card and the bill all name the same room. Change the room at
  arrival and the booking follows the guest, not the other way round.
- **Every room is checked again on the server.** The popup's list can be
  minutes old; a room taken in the meantime is refused by name, so two clerks
  cannot put two guests in one room.
- A check-in **holds its room** the same way a booking does. Room availability
  everywhere — the booking form, the calendars — now counts bookings, blocks
  **and** check-ins, which matters because a "3 Deluxe" booking names no room
  until its guests arrive.
- **The folio opens at arrival**, not at checkout: the nights and the services
  booked with the reservation are on it from the moment the guest walks in.
- **Undo** on Check in Details takes the guest back out and frees the room. The
  folio goes with them — charges, payments and pax records — and the booking's
  services become chargeable again for whoever arrives instead. It only works
  while they are still in house.
- **A booking whose guests have arrived cannot be edited or cancelled.** Both
  rewrite every room row, which would cut the check-in loose from its booking
  and let the same room be checked in twice. Once anybody is in, the booking is
  changed from Front Office.

Arrival numbers are `FO-<branch>-<0001>`; all the rooms checked in together
share one, the way the desk reads it out.

### Pre Reg Card

The Guest Registration Card the guest signs at the desk — the one the police
verification is copied from.

- **Filled from the booking.** The list shows who is arriving in the next week;
  press **Print** and the card comes out with the guest, the stay and the room
  already on it. That is the point of a *pre*-registration card: it is ready
  before the guest walks in, so they only check it and sign.
- **Blank card** prints an empty one for the pad under the counter, for a
  walk-in who has no booking yet.
- Both come off **one template**, so a field added to the card can never appear
  on one and not the other.

**Set the letterhead first.** The hotel's name, address, GSTIN and SAC code
come from **Administration → Branches**, along with the **Registration card
terms** (one per line) that print under Terms & Condition. Until the GSTIN is
filled in, the Pre Reg Card screen says so at the top — a tax document without
a GSTIN is the hotel's problem, not the guest's. `Legal name` is separate from
`Branch name` because the name on the GST certificate is often not the name
over the door.

Four fields print as blank rules on purpose — **Date of Arrival in India**,
**Employed In India**, **C Form No.**, and the guest's signature. The system
does not hold them, and a guessed value on a document that goes to the police
is worse than an empty line the desk fills in from the passport.

The card prints from its own stylesheet (`resources/css/print.css`), not the
app theme: A4, black on white, no dark mode. The toolbar at the top is on
screen only.

### Room Calendar

Every room in the house as a tile, coloured by what it is doing on one day.
The tape chart answers *which nights*; this answers *right now, what is room
203 doing and who is in it*.

- **All / Occupy / Available**, plus filters for category, floor, room number
  and date.
- **Click a tile** and the panel on the right fills in: guest, mobile,
  category, plan, guest type, GSTIN, company, arrival, departure, booked by.
  From there, **Check In** or **Check Out** for that room.
- **HouseKeeping Status** changes what the picked room is marked as.

A tile's colour comes from three things at once — the guest, the booking and
the housekeeping state — because a room can be **reserved and dirty**, or
**checked in and dirty**, and the desk needs to see both. That is what the
striped tile means.

### Check Out Guest

The bill, and the guest leaving. Everything on this screen comes off one
`Folio` (`app/Support/Folio.php`), so the screen, the proforma and the saved
bill can never disagree about what is owed.

| Button | What it does |
|---|---|
| **Add Folio** | Restaurant, laundry, anything else the guest owes. Pick a service and the rate and tax fill in. |
| **Extend Checkout** | Push the departure out. The extra nights are added to the folio at the same rate, and the booking moves with the guest so the calendars stop offering their room from the old date. |
| **Pax Checkout** | Some of the people in the room leaving before the rest — a log, so two out today and two on Thursday both show. |
| **Multiple Pay Mode** | Part of the bill on one pay mode. ₹2,000 on a card and ₹100 in cash is two payments. |
| **Proforma Invoice** | The bill before the guest has left, stamped **PROVISIONAL**. |
| **Checkout (F10)** | Writes the bill and the guest leaves. |

**The bill adds up to the booking.** A night on the folio is priced exactly the
way the booking priced it — room rent **plus the plan charge**, minus the
discount — from the figures stored on the stay, not looked up again. So a room
booked at ₹3,600 on American Plan (₹1,400) is billed at ₹5,000 a night, and the
plan is named on the line. The services taken with the booking are charged too,
once per booking however many rooms it covers. The Bill card names the booked
value of the reservation underneath, so the desk can see the two agree.

Two things follow from that. The plan's charge is **resolved when the booking is
taken and then stored**, so re-pricing a plan next month cannot change what a
guest was already quoted. And while the guest is in house, nights already on the
folio are re-priced if the rate on the stay changes — after checkout the bill is
a frozen copy and nothing moves it.

**Room rent is posted a night at a time**, not as one lump for the stay. That
is what makes extending simply add nights, and what lets the bill name the
date of every night it charges for. Those lines are posted by the system and
cannot be deleted by hand — change the checkout date instead.

The maths, in order: `Sub Total + Tax − Discount = Net`, then
`Net − Advance − Paid = Due`. A discount can be **₹ or %**; a percentage is of
the taxed total, which is what a guest means by "10% off" while looking at the
bill. The advance sits on the *booking*, so a booking covering three rooms
gives each room a third of it — charging the whole advance to the first room
to leave would make the other two look unpaid.

**On checkout:** the bill is written as a copy of the folio at that moment (a
bill the guest has signed must not move if a rate is edited later), the room is
marked **dirty**, and the stay closes. Reopening a settled stay takes you to
its bill rather than letting a second payment be taken against it.

**A guest leaving early gives their nights back.** The booking goes on holding
its room until the date it was sold to, so without this the desk could not sell
a room that is standing empty and the tape chart would keep drawing a bar over
it. The booking's dates and its own figures are pulled back to the stay that
actually happened; the money the guest owes is settled on the bill and does not
move. When the last guest on a booking has gone, the booking itself closes.

The proforma is stamped **PROVISIONAL — not a tax invoice**, because one that
could be mistaken for a tax invoice is a real problem. The final bill carries
the GSTIN, the SAC code and a **tax summary split by rate**, with GST halved
into CGST and SGST.

### House Keeping Status

The supervisor's morning list. Tick rooms, pick what to do with them, press
**Update** — all three jobs work on many rooms at once, because a floor is
allotted in one go, not a room at a time.

| Set Housekeeping Status | What it does |
|---|---|
| **Set Status** | Marks the ticked rooms **Cleaned / Dirty / Touch Up / Inspect**, with an optional remark. |
| **Assign Housekeeper** | Gives the ticked rooms to one person. Their name shows in the last column. |
| **UnAssign Housekeeper** | Takes them back. |

Picking an action reveals only the field that action needs, so the form never
asks for a housekeeper while you are setting a status.

Each row carries two different facts side by side, and the supervisor reads
them together:

- **Status** — what housekeeping has the room marked as.
- **Available** — what the room is *doing* today: Occupied, Reserved, DNR,
  Blocked or Available, with the guest's name. This is worked out exactly the
  way the Room Calendar works it out, so the two screens can never disagree
  about whether 203 is free.

**Repair is not on the settable list.** Taking a room out of service has a
reason and dates and belongs to *House Keeping → Room Blocked*, so the
calendars know about it too — marking such a room clean here is refused with a
message saying where to release it.

**Only rooms to clean** is the filter the supervisor actually works from: it
narrows the list to Dirty and Touch Up. The four counters at the top —
rooms, to clean, occupied, nobody assigned — follow the filters.

Checkout marks a room **dirty** on its own, so the list fills itself as the
morning goes.

### Issue and Received — the laundry

Two screens, one cycle: linen out to the laundry, linen back. They are only
useful together, which is why they were built together.

**Where the items come from.** There is no separate master screen to fill in
first — linen and laundries are only ever set up while writing a note, so
**Add Issue** has **+ New item** and **+ New vendor** on it. A bedsheet takes a
name, a unit and the two contract rates; a laundry takes a name and a mobile.
Ten common items and two demo laundries are seeded, so the screen works the
moment the app is installed.

**Writing an issue note.** Pick the vendor and the date, then one line per kind
of linen:

| Column | What it is |
|---|---|
| **Prev Qty** | What this vendor is **already** holding of that item, from earlier notes. Filled in for you the moment you pick the vendor — never typed. |
| **Std Qty** | Pieces going for a normal wash. |
| **Exp Qty** | Pieces going express, because a guest is waiting. |
| **ReWash** | Pieces going back because the wash was not good enough. Counted, never charged — it is the vendor putting their own work right. |
| **Std Rate / Exp Rate** | Per piece, from the item's contract rate. Type over it and the note keeps what you typed. |
| **Amount** | Std Qty × Std Rate + Exp Qty × Exp Rate. |

An item may appear only once on a note. Two lines for the same bedsheet would
each show the same Prev Qty and read as a double count, so the note is refused
and the item is named.

**Receiving.** Pick the vendor and the grid fills with exactly what they are
holding — you cannot receive something that never went out. Per item:
**Received**, **Damaged** and **Missing**. All three come off the vendor's list,
because the hotel is not getting the damaged or missing pieces back either and
leaving them on would keep a finished job open for ever; they are recorded
separately so the loss stays visible. Nothing can add up to more than Pending,
and that limit is checked again on the server.

A receipt is written against a **vendor, not against one issue note**. A laundry
with four notes open sends back one van with a mix of all four, and making the
clerk split that van across four documents is exactly how counts stop matching.

**The one sum both screens run on** is in `app/Support/Laundry.php`:

```
outstanding = everything issued − (received + damaged + missing)
```

Issue calls it *Prev Qty*, Received calls it *Pending*, and because both read it
from the same place the two screens can never quote different numbers. Deleting
a note puts its pieces straight back on the vendor's list.

Note numbers are `ISS-<branch>-0001` and `REC-<branch>-0001`, unique per branch
in the database — two clerks saving at the same instant cannot take the same
number.

### Room Blocked

Taking rooms off sale, and putting them back. Pick a date and press **Search**;
the rooms come up with what each one is doing that day.

**Tick a room and the Block button appears**; untick the last one and it goes
again. It only ever shows when it has something to do — tick a room that is
already blocked and you get **Release** instead, because that is the only thing
left to do to it.

**Block** asks for four things: From Date, To Date, the kind of block, and a
remark. The dates follow the same rule as everything else here — *To Date is the
day the room comes back*, so 10 Sep → 11 Sep is one night.

| Kind | What it means |
|---|---|
| **Maintenance** | The room cannot be slept in. It is also marked **Repair** in house keeping, so nobody makes it up and puts it back on the board — House Keeping Status refuses to change its status and says to release it from here. Releasing sets it to **Dirty**, because nobody has been in to look at it yet. |
| **Management** | The room is fine, it is simply not for sale — held for the owner, kept back for a group. House keeping goes on cleaning it as normal, so its status is left alone. |

**Bulk Block** does the same thing for a run of room numbers without ticking
anything: *201 to 210, the 15th to the 30th*. That is what a floor going under
renovation actually looks like. Room numbers are compared as **numbers** when
both ends are numbers, so a hotel with rooms 102 and 1016 gets the range it
meant rather than the one the alphabet would give.

**A room with a guest or a booking in it cannot be blocked.** It has no tick box
at all, and the row says who has it. Bulk Block skips those rooms and names them
in the message afterwards — a supervisor who thinks the whole floor is off sale
and finds a guest walking into 204 has been lied to by the screen.

**Nothing else has to be switched off.** A block is the third thing that can hold
a room, alongside a booking and a check-in, and every availability test in the
app already reads it: the tape chart, the status view, the monthly position, the
room dropdown on the booking form, the Allot Room popup. Block a room here and
it disappears from all of them at once.

Releasing posts the **block's** id, not the room's, so a room blocked for two
separate weeks never loses the wrong one — and a second maintenance block still
running keeps the room marked Repair after the first is released.

### Work Order

The maintenance job card: what is broken, where, who is on it, by when.

**Add Work Order** asks for Start Date and Time, End Date, Unit/Room, Due Date,
Category, Priority, Status, Assign To and Job Notes. The order number is given
out on save — `WO-<branch>-0001`, unique per branch in the database, so two
clerks saving at the same instant cannot take the same one.

- **Category** comes from `config('pms.work_order_categories')` — Electrical,
  Plumbing, Carpentry, AC/HVAC, Painting, Furniture, Housekeeping, IT/Network,
  Lift, Other. Edit that one list and the dropdown follows; no migration, no
  database change, no permission needed.
- **Unit/Room** may be left on *Common area* — a lift or a corridor is a job
  with no room number.
- **Assign To** may be left blank, and the list then shows the job as nobody's.
- There is no Title field. A job is known by its trade and its room, so the
  title writes itself as *AC / HVAC · Room 204*.

**The list leads with what is late.** Overdue jobs sort to the top and the row
turns red with the number of days; closed ones drop to the bottom and go grey.
Filters: date range, status, priority and employee. The tick on a row's
**✓** closes the job in one press, so a supervisor closing six finished jobs
does not open six forms.

**A job can take its room off sale.** Tick *Block this room while the job runs*
and the order writes a **maintenance block** for its own Start and End dates —
exactly the same `room_blocks` row the Room Blocked screen uses. The room goes
**Repair** in house keeping and drops out of the tape chart, the status view,
the monthly position and the room dropdown on the booking form. Change the
dates and the block follows; close or delete the job and the room goes back on
sale. Untick it and the block is released on the spot.

Leave it unticked for a dripping tap — a guest can stay in the room while it is
fixed, and blocking the room would cost a night's rent for nothing.

**A room somebody has already booked cannot be held.** The job still saves; the
screen says the room could not be blocked and why, rather than pretending it is
off sale. Two jobs on one bathroom do not un-block each other either — the room
stays Repair until the last of them is closed.

### POS Dashboard

One screen for what the outlets sold — restaurant, room service, the bar.

Pick a date range (or one of the four presets) and it answers six questions at
the top: **Total Sales**, **Collections**, **Orders**, **Complimentary**,
**Turn Around Time** and **Discounts**. Sales are counted on the **invoice**,
not the order — an order can be opened, changed and cancelled without a rupee
moving, so what the hotel sold is what it billed. Cancelled invoices are left
out of every figure on the page.

**Revenue Control** is its own strip: invoices deleted, order items removed,
items modified, invoices re-printed. None of those is wrong on its own — a guest
does send a dish back, a printer does jam — but they are the four things a till
gets worked through, so they are counted where somebody sees them rather than
buried in a report nobody runs.

Three charts, and a fourth pair of lists:

| | |
|---|---|
| **Outlet Sales — daily** | One column per day, stacked by outlet. Every day in the range gets a column whether it sold anything or not: a blank Tuesday is information. |
| **Sales by order type** | Dine-in, room service, delivery, take away — *how* it was sold, which is a different question from which outlet rang it. |
| **Collections by pay mode** | One measure, so one colour: this is magnitude, not identity. |
| **Top / Low selling items** | What to push, and what to take off the menu. |

**The charts are drawn by hand in SVG — there is no chart library.** Nothing to
`npm install`, nothing fetched from a CDN, and it renders on a machine with no
internet, which is the same promise the rest of the app makes.

Four colours are used, in fixed order, and a fifth outlet folds into the fourth
rather than inventing a hue. Both the light and the dark sets were checked
against this app's own surfaces for lightness, chroma, colour-blind separation
and contrast. Two of the light hues sit under 3:1 against white, which is
exactly why every chart here also carries a legend with the value written out
and — on the daily chart — a **table view** underneath: colour is never the only
thing carrying the meaning.

The screen reads what the till writes, so the moment you take a real order on
POS → Dine In, the figures here start filling themselves in.

---

### POS — the till

**Point Of Sale → POS.** Two screens and everything they lead to.

**Dine In** is the floor. One tile per table, coloured by the same room-status
palette the front desk uses, so green means "you may sell this", plum means
"somebody is in it" and amber means "this needs a person" here exactly as it
does on the Room Calendar. An occupied tile carries the running total, how many
items and KOT rounds are on it, and **a clock that counts up** — the tile stops
looking calm after ninety minutes and says so in red after two and a half hours.

Each tile has a **quick-action rail**: *Code*, *Shift*, *Order*, *Invoice*. It
opens on hover, on keyboard focus, and on a tap of the ⋮ handle — because the
machine this screen is actually used on is a touchscreen, where hover does not
exist. Every action carries a word as well as an icon.

The picker at the top left holds the outlet **and** what it is showing:
"Coffee Shop — Restaurant" draws the floor plan, "Coffee Shop — Room" draws the
rooms with a guest in them, so a room-service order is taken from the same till
and can be signed straight to the guest's folio. Outlets that have not been
switched on for room service simply have no Room line.

**The bill screen** is the other half. Categories open one at a time with an
**All** to get back out, sub-categories appear underneath the heading you
opened, and there is a search box for the times somebody knows the name. The
running bill is on the right: quantity steppers, a note per line, no-charge
per line, steward, covers, price list, discount, and the totals underneath.

Four things about it are worth knowing:

- **A line that has gone to the kitchen is marked, and changing it is recorded.**
  `kot_no = 0` means still being typed; anything above it has been cooked, and
  cutting it or removing it writes to the Revenue Control log with the KOT
  number.
- **KOT rounds are numbered, and round two prints only what round one did not.**
  That is what lets a waiter add a dessert without the kitchen cooking the
  starters again. Tickets print one per department — the bar does not need to
  read the curry.
- **Prices are read from the menu, never from the form.** The browser posts an
  item id and a quantity; what it costs is the server's business.
- **Settling is three genuinely different acts**, so it is three panels rather
  than one dropdown: payment (up to three pay modes on one bill, because part
  cash and part card is ordinary), sign to the room (it becomes one line on the
  guest's folio carrying the bill number, and what gets signed is what is still
  *owed*), or no charge against a named reason.

Beside Dine In sit **Live Orders**, **Unsettled Invoices**, **Cash Balance**,
**Invoices**, **Outlet Orders** and **Collections** — all real screens reading
the same data. Unsettled Invoices is the one to clear before going home: every
line on it is food that left the kitchen and money that did not arrive.

### Kitchen Display System

**Point Of Sale → Kitchen Display System.** Tickets in three columns — New,
Preparing, Ready — one ticket per KOT round, biggest type in the app, and a
**live clock on every ticket**.

The split between what runs locally and what comes from the server is the whole
design. The clocks tick every second in the browser from an epoch the server
stamped on the ticket: they need no network, and they do not need the kitchen
screen's own clock to be right. The list of tickets is re-fetched every twenty
seconds and replaced wholesale. **So a screen that has lost its connection goes
on telling the chef that table 7 has been waiting nineteen minutes** — it just
says "Not updating" where it usually says "Live".

A ticket turns amber after ten minutes and red after twenty
(`config/pms.php` → `kds_warn_minutes`, `kds_late_minutes`, `kds_refresh_seconds`).
Those are the only amber and red on the screen, which is what makes a ticket
turning mean something.

### Digital ordering — the table card

The *Code* action on a tile prints a card for the table: the outlet's name, the
table number, a **QR code** and the same code in type big enough to key in by
hand.

**The QR is generated in PHP, in `app/Support/Qr.php`, with nothing installed.**
No package, no image service, no call to anybody's server — a hotel's table cards
must not stop working because the property's internet is down. It is written
from ISO/IEC 18004: byte mode, error correction level M, versions 1 to 10, with
Reed–Solomon over GF(256) and the standard's own mask-penalty scoring.

The code in the URL is an HMAC of the table id against the app key, so a card
photographed at one hotel will not open a table at another, and a guessed table
number does not open somebody else's bill.

---

### One family, five rooms

**A booking holds a number of rooms, never a room number.** The New Reservation
row no longer asks for Room No. at all — a family that needs five rooms is one
row saying five, and which five rooms they get is decided when they arrive. A
booking that named 102 three weeks out was only ever a wish anyway.

Beside Room Type the form now says how many of that type are free for the nights
in the form, and every line of the dropdown carries its own count — "Deluxe
Double — 4 free" — so a clerk booking a family can see which type can take them
before choosing rather than finding out on save. Asking for more than are free
is a warning, not a block: hotels do overbook on purpose, and the server checks
again when the booking is saved.

Five rooms count as five everywhere, because Reservation Status View, both
Reservation Calendars and Reservation Calendar Monthly all read the row's
`no_of_rooms` rather than counting rows.

On the tape chart, dropping a five-room booking onto a room **allots one of the
five**: the row splits — its count drops to four and a new one-room row is
created in that room — so five drags produce five allotted rooms and the queue
counts down as the clerk works. The money splits with it, priced off the same
per-room figures the booking was taken at, so the reservation's total is
identical before and after. (There is a test for exactly that; see below.)

### One bill, or five

Rooms checked in together already share a folio number — that is the family. The
Check Out screen now offers **All n rooms on one bill** whenever there is more
than one still in house, which opens the group screen: every room side by side,
what each owes, and one grand total.

Checking the family out from there **still writes one numbered bill per room** —
room revenue is reported per room and a tax invoice is per room, so collapsing
five into one would lose something the hotel needs. What it adds is a shared
`group_no`, and that is what prints them as **one document with one grand
total**: each room's nights and services under its own bill number, the family's
total at the bottom, on one sheet.

So the family gets one bill, the accountant gets five, and neither is a
reconstruction of the other. Any single room can still be settled and printed on
its own from its own row — the group is an option, not a cage.

A discount entered once for the family is spread across the rooms in proportion
to what each owes, and a payment is spread room by room in the order they are
listed, each taking what it owes until the money runs out. Both matter for the
same reason: a room's own bill has to show what was actually paid against it, or
every room's revenue figure in the group is wrong.

### The two room-wise reports

Two questions that sound alike and are not, so they are two screens:

**Front Office → Room Wise Services.** Everything posted to a room's folio that
is not a room night — laundry, an airport pickup, an extra bed, a sundry. Room
by room, busiest first, with each room's subtotal above its lines rather than
under them: the answer comes first and the detail is what you drop into when the
number surprises you.

**Point Of Sale → Room Service Orders.** What the kitchen actually sent up,
taken on the POS as a room-service order. Same rooms, different till.

Neither is derived from the other. A guest can order a club sandwich and pay
cash at the door so it never reaches the folio, and a room can carry a laundry
charge that never went near the POS. Both take a date range and a room, and both
export to CSV.

### The Housekeeping Board

**House Keeping → Housekeeping Board** is the same rooms as the Status list,
seen as work rather than as a list, and it has two views of one board.

**Room View** groups every room under its type, which is how a supervisor
allots: *do the four suites first, they check in at two.* Each tile carries a
coloured rail — that is the only thing anybody reads from across the room — plus
who is in it, who is looking after it, and an **Out today** or **Arrival** flag,
which is what turns a list of dirty rooms into a priority order.

**Pipeline View** is the four columns work actually moves through:

```
Occupied  →  Dirty  →  Cleaning  →  Ready
```

*Cleaning* is new, and it is the whole reason the board exists: the old list
went straight from Dirty to Cleaned, so a room being made up right now looked
exactly like one nobody had started.

What can be dragged is deliberately narrow, because a drag is a poor way to
record a time:

| From | Can be dragged to |
|---|---|
| Occupied | Dirty |
| Ready | Dirty |
| Cleaning | **nowhere** |

Everything else is a button — **Start** begins a clean and starts its clock,
**Done** finishes it. The rules are enforced on the server as well as in the
browser: a rule that lives only in JavaScript is a rule a stale tab does not
have to obey, and "cleaning cannot be dragged" is exactly the one a sleeve on a
touchscreen would break.

Every move is written to `housekeeping_logs` with the person who made it. That
is the answer to *"the guest says the room was never cleaned"*, and it is what
the Housekeeping report reads.

---

### The Reports section

**Reports** is eighteen reports behind one permission key, listed in
`config/reports.php` and built in `app/Support/Reports.php`. They all come back
in the same shape, so there is one screen, one CSV export and one set of filters
rather than eighteen of each — adding the nineteenth is a method and a config
line.

| Front Desk | Money | Operations | Pool, Hall & Car |
|---|---|---|---|
| Arrivals | Occupancy | Housekeeping | Pool Bookings |
| Departures | Revenue | Work Orders | Hall Bookings |
| In House | Tax Summary | | Parking |
| Guest List | Collections | | Pickup & Drop |
| Cancellations | Outstanding | | |
| No Show | POS Item Sales | | |

Nothing in there stores a total. Every figure is read from the rows that
produced it, which is why a report can never drift out of step with the folio
it came from — the classic hotel-software bug where the night audit says one
thing and the bill says another.

Two worth knowing about. **Occupancy** counts rooms sold from the room nights
actually posted to folios, so it agrees with the bills rather than with the
calendar, and it keeps ADR (per room *sold*) and RevPAR (per room *available*)
apart, which is the most commonly confused pair of numbers in the trade.
**All Receipt**, over in Accounts, shows its three sources separately on
purpose: a settlement at the desk and the receipt voucher an accountant later
posts for it are the same money seen twice, and one grand total would count it
twice.

---

### Accounts

A plain double-entry set. Two tables — `vouchers` and `voucher_entries` — and
every screen in the module is a view over them, which is why none of them can
disagree with another.

`app/Support/Vouchers.php` is the **only** way a voucher gets into the books.
There is deliberately no other door, because four rules have to hold for every
single voucher and a screen that wrote its own INSERT would sooner or later
forget one:

1. **It balances.** Debit equals credit to the paisa, or nothing is stored and
   the clerk is told what the difference is.
2. **Its ledgers are this branch's.** A dropdown is not a permission check.
3. **Its number is its own.** Taken inside the transaction with the last row
   locked, so two clerks pressing Save in the same second cannot both be given
   `PAY-1-000042`.
4. **It is written whole.** Header and entries in one transaction.

**A voucher is never deleted, only cancelled.** Voucher numbers are a continuous
series and a missing number is the first thing an auditor asks about, so a
mistake keeps its number, gets `is_cancelled = 1`, and drops out of every report
through one scope.

The sign convention, which is the single most confusing thing in the module and
is written at the top of `app/Support/Ledgers.php`: every balance is a signed
figure where **positive is Dr and negative is Cr**. Assets and expenses are
Dr-positive; liabilities and income are Cr-positive and therefore come back
negative, and the screens flip the sign to show them.

The screens: **Group** and **Ledger** (the masters), **Payment**, **Receipt**,
**Contra** and **Journal** vouchers, **Vendor Payment** and **Customer Receipt**
(the same posting code with the party list narrowed to Sundry Creditors and
Sundry Debtors, and each party's balance shown beside its name), then **Day
Book**, **Cash Book**, **Bank Book**, **Ledger Statement**, **Trial Balance**,
**Profit & Loss** and **All Receipt**.

`php artisan db:seed --class=AccountingSeeder` fills in a standard Indian chart
of accounts — the groups a Tally-trained accountant expects to find, named the
way they expect to find them.

**Vouchers do not compute tax, and there is no Tax dropdown on these screens.**
That is not an omission: GST on a supplier's bill is a *line* of the voucher —
Dr Expenses, Dr Duties & Taxes, Cr the vendor — typed by whoever is reading the
bill.

---

### Pool, Banquet Hall, and the car

Four things the hotel sells that are not rooms and not food. They share one
shape: a master, a booking against it, money that is optional at every level,
and a `Put it on the room bill` tick that posts one line to the guest's folio.

**Pool** — a pool, a day, two times, so many adults and so many children. Two
different things can refuse a booking: the pool is already booked over part of
that window, or it would hold more people than it is allowed to. The second is a
safety limit, so capacity is checked on the server; set a pool's capacity to 0
and sessions may share the water freely. There is a day calendar, hour by hour.

**Banquet Hall** — held from one moment to another, with extras (décor, DJ,
buffet) each priced and taxed on their own, an advance, and what is still owed.
**A tentative booking holds the hall exactly as a confirmed one does** — that is
the entire point of writing a hold down. The week calendar shows a wedding that
runs Friday to Sunday in all three columns.

**Car parking is free.** The hotel asked for it and the code says it: a parking
record's *charge for it* tick starts **off**, and a ticket with it off costs the
guest nothing no matter what is in the rate. The charge, if there is one, is
worked out **on the way out**, because that is the first moment anybody knows
how long the car was there. Hours are rounded up, the way every car park in the
country counts.

**Pickup & drop** works the same way: the trip is recorded either way, and
`is_chargeable` starts off — a hotel whose tariff includes the airport pickup
never turns it on. A pickup is normally booked against a *reservation* (the
guest has not arrived, so there is no stay to hang it on) and a drop against a
stay.

Posting to a folio is idempotent — every one of these screens goes through
`Facility::syncFolio()`, which remembers the charge it wrote, so saving the same
booking five times leaves one line on the bill rather than five. A charge that
has already been settled is history: it is left alone and the clerk is told.

---

### Notifications — the bell, email and WhatsApp

Everything that happens in the hotel can raise a notification. The list lives in
`config/notifications.php`; adding an event there makes it appear on the
settings screen with no other change.

Three channels:

**The bell**, top right of every screen. Always on, costs one insert, and it is
what the desk actually reads. It polls once every 25 seconds *while the tab is
visible* — a hotel leaves this open on six machines all night, and polling a
hidden tab is thousands of requests nobody will ever read.

**The browser pop-up** — the WhatsApp-looking one that slides in over whatever
else is on screen. Turned on by the *Turn on pop-up alerts* button in the bell's
footer, never on page load: browsers refuse a permission prompt that was not
triggered by a click, and a refused prompt counts as "denied" for ever.

**Email and WhatsApp**, per event, per branch, from **Administration →
Notification Settings** — and WhatsApp goes to the *guest* as well as to the
desk; see *What the guest is actually told* below. That screen also says exactly where the keys go, and
the **Send a test** button on it answers "did I put the password in the right
place" without waiting for a guest to check in.

Every send is written to `notification_deliveries` with its result — including
the provider's own error message when it refuses. Without that table, *"the
guest never got the mail"* has no answer.

**Nothing about a notification is allowed to break the thing that caused it.**
`Notify::send()` never throws; a booking does not fail because a WhatsApp token
expired.

#### Where the mail password goes

Open `.env` and set these lines. **`MAIL_PASSWORD` is the only one you have to
fill in** — the rest are already right for a Gmail account:

```
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=biltykumawat341@gmail.com
MAIL_PASSWORD=              ← paste the app password here
MAIL_FROM_ADDRESS=biltykumawat341@gmail.com
MAIL_FROM_NAME="Hotel Admin"
```

Gmail will not accept the account's normal password. In the Google account turn
on 2-Step Verification, then **Security → App passwords → Mail**, and paste the
16-character password it gives you on the `MAIL_PASSWORD` line — no spaces, no
quotes. Then run `php artisan config:clear` once.

#### Where the WhatsApp API key goes

The gateway is **int.chatway.in** and the username is already set. Open `.env`
and **paste the token on one line**:

```
WHATSAPP_DRIVER=chatway
WHATSAPP_USERNAME=sales@rukmanisoftware.com
WHATSAPP_TOKEN=             ← paste the Chatway token here
WHATSAPP_CHATWAY_URL=https://int.chatway.in/api/send-msg
```

Then `php artisan config:clear` once. Until the token is filled in nothing
breaks — messages go to `storage/logs/laravel.log` instead, so you can read
exactly what would have been sent.

The token is deliberately **not** committed to any file in this project. A
token written into the source is a token in every backup and in every copy of
the project anybody is ever sent, and changing it then means changing code.

Numbers can be typed however the desk types them — `98765 43210`,
`+91 98765 43210` and `09876543210` all reach the same phone. The sender adds
the country code from `WHATSAPP_COUNTRY_CODE` to a bare ten-digit number, so a
foreign guest's number reaches them too — which the raw `'number=91' . $mobile`
version of this did not.

Moving to Meta, Twilio or any other gateway later is a different
`WHATSAPP_DRIVER` and nothing else; the four options are documented at the top
of `config/services.php`.

#### Sending one from anywhere in the code

```php
Helper::sendWhatsappMessage($mobile, $finalMessage);
Helper::sendWhatsappMessage($mobile, $text, $pdfUrl, 'bill.pdf');
```

It **never throws** — a message that cannot be sent must not take a booking down
with it — so the failure comes back as a value:

```php
['status' => 'success', 'response' => '…']
['status' => 'error',   'message' => 'why it did not send']
```

Either way a row is written to `notification_deliveries`, and the delivery log
at the bottom of Notification Settings shows it with the gateway's own error on
it. That table is the answer to *"the guest says they never got anything"*.

#### What the guest is actually told

These are the messages that reach the **guest's** phone, as opposed to the
staff bell:

| When | Message |
|---|---|
| Booking taken | confirmation with dates, rooms, amount, advance |
| Booking cancelled | which booking, and the reason |
| Advance received | receipt |
| Check-in | welcome, room number, folio, checkout date |
| Payment taken | receipt with the balance left |
| Check-out | bill number, total, paid, balance |
| Pool booked | pool, date, time, people |
| Hall booked | hall, event, from/to, advance, balance |
| Pickup or drop booked | route, time, car, **driver's name and mobile** |
| Car on its way | driver and car, so the guest knows who to look for |
| Vehicle parked | ticket number and bay |
| Restaurant bill signed to a room | outlet, invoice, amount |

The **wording** lives in `config/guest-messages.php` — plain text, editable,
with `{curly braces}` for the bits that get filled in. A placeholder with
nothing behind it takes its whole line with it, so a booking with no arrival
time does not send a message with a dangling "Time:" on a line of its own.

Each one is switched on and off from **Administration → Notification Settings**
under *Messages to the guest*. Untick WhatsApp on a row and that message stops,
without anybody editing a file.

---

### Night Audit — the hotel's day, closed

**Front Office → Night Audit.**

A hotel's day does not end at midnight. The desk is still checking people in at
one in the morning, the restaurant is still closing its last table, and all of
it belongs to yesterday's trading. So the hotel keeps a date of its own — the
**business date** — and the night auditor moves it forward once a day, after
the day's figures have been taken.

The screen is one job done top to bottom, in the order a night auditor does it.

**1. The walk round.** Four questions, each with a count and the screen that
fixes it:

| Question | What happens if you leave it |
|---|---|
| Arrivals not checked in | They get marked **No Show** and their rooms released |
| Departures still in house | Their checkout date has passed, so tonight's rent is not charged — extend the stay or check them out |
| Restaurant orders still open | An open KOT is not in the day's sale yet |
| Rooms not yet ready | Not the audit's business, but tomorrow's arrivals need somewhere to sleep |

None of them stop the audit. A hotel that cannot close its day because one
guest is late paying has a worse problem than an untidy report.

**2. The figures.** Rooms sold, occupancy, room revenue, restaurant revenue,
what was actually collected, and the three numbers a hotel is judged on:

- **ADR** — room revenue ÷ rooms **sold**. What a sold room fetched.
- **RevPAR** — room revenue ÷ rooms **available**. The one that tells an owner
  whether the discounting was worth it.
- The **gap** between earned and collected, spelled out in a sentence rather
  than left for somebody to subtract.

Rooms sold is counted off the folio, not off the check-ins, so it can never
disagree with the room revenue beside it.

**3. Close it.** One button. It posts any unposted room rent through the same
`App\Support\Folio` the check-in screen uses, marks the no-shows, freezes the
figures into `business_days.figures`, and moves the business date on a day.

**It is safe to run twice.** `Folio::postRoomCharges()` refuses a night it has
already charged, the no-show sweep only touches bookings still waiting, and the
day row is claimed under a row lock — two clerks pressing Run at the same
second get one night's rent, not two. The database carries a unique key on
`(branch_id, business_date)` so it is not left to the code to remember.

**It never runs ahead of itself.** The business date is the day after the last
close and it stops at today. A hotel that has not audited since Friday closes
each of those nights one at a time, oldest first, each with its own figures —
rather than one enormous close that says nothing about any of them.

Every audited night keeps its report: **Front Office → Night Audit → Open**,
with a printable manager's report behind *Print the report*. The most recent
close can be reopened (a `delete` permission) if something has to be fixed —
the rent it posted stays on the folios, because that is money guests owe.

#### Running it by itself

```bash
php artisan hotel:night-audit             # every active branch, one night
php artisan hotel:night-audit --branch=1  # just this one
php artisan hotel:night-audit --catch-up  # keep closing until it reaches today
php artisan hotel:night-audit --dry-run   # say what it would do, change nothing
```

Put it in cron at three in the morning and the day closes itself. A cron that
fires twice is not a problem, for the reasons above.

When the night closes, `audit.closed` goes out through **Notification
Settings** — the bell and, by default, email, because the person who most wants
last night's figures is asleep at four in the morning and is not going to open
the app to find out how it went.

---

### Rate Management — what a room costs tonight

**Rate Management → Rate Calendar / Rate Plans / Rate Grid / Seasons.**

Until now a room type had one `base_rent` and that was the answer all year. Real
hotels do not work that way: a Deluxe is ₹3,500 in July, ₹9,000 between
Christmas and the 2nd, ₹4,200 on a Saturday, and ₹2,800 for the company that
books forty room nights a month.

Three tables, and each answers one question:

| | Answers | Screen |
|---|---|---|
| `rate_seasons` | **When** — a named stretch of the calendar: Peak, Diwali, Monsoon Offer | Seasons |
| `rate_plans` | **Who for** — Rack, Corporate, OTA; optionally tied to a market or one company | Rate Plans |
| `rate_rules` | **How much** — this plan, this room type, these nights, this many rupees | Rate Grid |

#### How a night gets priced

All of it lives in `app/Support/Rates.php`, and the booking screen, the rate
calendar and anything plugged in later all ask that one class.

1. **Pick the plan.** The guest's company's own plan, else their market's, else
   the default. A plan for one company is that company's rate and nobody
   else's — which is the whole reason for negotiating one.
2. **Take every live rule** on that plan for that room type.
3. **Throw away the ones that do not speak for this night** — wrong dates,
   wrong season, wrong day of the week.
4. **Keep the most specific.** Own dates beat a season, a season beats an
   all-year row, and naming nights of the week narrows any of them. Two rules
   that each name a season are separated by the season's own priority, so a
   short *Diwali* laid over a long *Peak* wins the nights they share. `priority`
   on the rule itself is your thumb on the scale and is added last.
5. **Nothing left?** Fall back to the room type's base rent — **and say so.**

Step 5 matters more than the rest put together. A rate engine that silently
returns zero when nobody has loaded rates is a hotel giving rooms away, so every
answer carries where it came from, and the calendar draws the fallbacks pale.

#### Priced night by night, never averaged

A three-night stay over a season boundary is two nights at one price and one at
another. `Rates::quote()` returns the breakdown, the total and the average, and
the booking screen shows the average with the total beside it. The departure
night is never charged — a guest leaving on the 9th does not pay for the night
of the 9th.

It also returns **warnings**, in sentences rather than codes, because they are
read by a clerk about to take a booking:

- *Not for sale on Fri, 25 Dec. The rate plan has these nights closed.*
- *This rate needs a minimum of 3 nights; the stay is 2.*
- *2 of 4 nights have no rate loaded and fall back to the base rent.*

#### On the booking screen

Pick a room type and two dates and the Room Rent box fills itself in, with a
line underneath saying which plan it came from and what the stay totals. Name
the company afterwards and it re-prices.

**The box stays the clerk's to overrule.** A walk-in talked down to four
thousand is a real thing that happens, so the lookup only ever overwrites a
figure nobody has touched — once you type your own number it is left alone, and
the line says *your figure kept*.

Rates are read-only to the front desk unless they are given the Rate Management
permissions; without `rates/calendar` view, the booking screen behaves exactly
as it did before and starts from the base rent.

#### The rate calendar

Every room type against every night, for a fortnight, a month or two months,
with the room-type column pinned while the nights scroll. Season bands under the
dates, stop-sell nights crossed out, minimum stays marked, and the unpriced
cells pale — that last one is as much the point of the screen as the prices
are, because an unmarked wall of numbers would hide the fortnight nobody has
priced.

#### Where the rate is recorded

`rate_plan_id` is stored on the reservation, on each reservation room and on the
check-in — beside the rate it was sold at, never instead of it. The amount on
the booking row is the contract. Editing or deleting a rate afterwards changes
what the *next* booking is quoted, and nothing about one already taken.

---

### Compliance — the paperwork owed to somebody other than the guest

**Compliance → Police Register / Form C / GST Returns / Tally Export.**

Four screens, four different authorities, and no shared workflow between them.

#### Police Register

Everybody who slept in the building on a given night — **one row per person,
not per room**, because that is what a station asks for and a family of four is
four lines. The main guest first, then the people on the pax list.

The night is the test, not the date range: a stay counts if it had arrived by
that date and had not departed before it. A guest who checked out on the
morning of the 9th did not sleep there on the night of the 9th.

Four figures at the top, and the one that matters is **No ID recorded** — it is
what an officer will pick on and the only one the desk can still fix.

**ID numbers are masked by default.** Only the last four characters show, which
is enough to confirm a guest at the desk. The whole number — on screen, on
paper and in the CSV export — needs the **Delete** permission on this screen.
There is no "reveal" flag in the permission matrix and Delete is the one nobody
gets by accident, so that is the line; every screen that shows an ID says which
side of it you are on. Masking fails closed: a new screen that forgets to ask
gets the masked form.

#### Form C

Every foreign national staying at a hotel has to be reported to the FRRO, and
it wants passport and visa details the front desk does not otherwise collect.

The screen opens on **who still needs one** and puts what has been done
underneath — a compliance screen that opens on a history table is one nobody
acts on. Writing a form pre-fills everything the hotel already knows (name,
nationality, address in India, dates), so a clerk with a passport in their hand
starts at the passport.

**One form per person.** A room holding four people gets four forms, picked
from a row of tabs at the top. A visa expiring before the guest leaves is
called out in red on the list — it is the one thing on the form that needs
somebody to do something today.

*This screen records; it does not file.* Submitting is still done on the FRRO's
site. A form marked "Filed" means somebody sent it and typed the reference in,
and `filed_at` is stamped at that moment rather than being a checkbox.

#### GST Returns

The month's outward supplies, split the way GSTR-1 splits them — **B2B**
(anyone with a GSTIN, invoice by invoice), **B2CL**, **B2CS** (summarised by
place of supply and rate) and the **HSN/SAC** table. Each downloads as CSV. The
month defaults to *last* month, because that is the one you file for.

**The rule most often got wrong:** for hotel accommodation the place of supply
is the location of the hotel, whoever the guest is and wherever they came from.
A room sold to a Bengaluru company by a Udaipur hotel is CGST + SGST of
Rajasthan, **not IGST** — the opposite of how every other B2B sale works.

Everything is read from bills exactly as they were issued. Nothing recalculates
tax: a return that disagreed with the invoice a guest is holding would be the
worst possible outcome. CGST and SGST are split so they always add back to the
tax on the invoice, to the paisa — an odd number of paise puts the extra on
CGST rather than losing it.

The buyer's GSTIN is **copied onto the bill** when it is issued, not read from
the company later. A registration can change; last year's invoice has to keep
saying what it said.

> This software records and arranges facts. It is not tax advice. Have your
> accountant confirm the treatment of anything beyond room rent, and check the
> current B2CL threshold and state codes before you file.

#### Tally Export

The month's sales as vouchers, in a file Tally imports through *Gateway of
Tally → Import Data → Vouchers*. One voucher per bill: the party debited for
the invoice value, revenue credited net of tax, CGST and SGST credited
separately.

Two things about the format that are easy to get wrong, and are handled:

- **Tally's sign convention is inverted.** A debit carries
  `ISDEEMEDPOSITIVE=Yes` and a *negative* amount. Backwards, and every voucher
  imports as a purchase.
- **Ledger names must already exist**, spelled exactly, or Tally invents its
  own and the accountant finds out in March. The screen lists the names the
  file will use so they can be created first.

Every voucher balances before it is written. A bill that cannot be made to
balance has the difference posted to the party ledger with a note in the
narration, so the file still imports and the one bad bill is findable.

Take a backup of the Tally company first — an import cannot be undone from
inside Tally, and a month imported twice is a month of doubled sales.

---

### Guest CRM — the person, rather than the booking

**Guest CRM → Guests / Feedback / Birthdays.**

A hotel already holds everything it needs to remember a guest — who came, what
they paid, what they asked for — and throws it away every time they leave,
because it is scattered across bookings and nobody ever puts it back together.
This module is the putting back together.

#### The profile

Opens with what a receptionist can act on, in that order:

1. **Blacklisted?** said first, before anything else on the page.
2. **Pinned notes** — "asks for a high floor, away from the lift". These are
   what whoever checks them in needs to know. Only a few notes should ever be
   pinned: history that shouts is history nobody reads.
3. Then the figures, the stays, what they said last time, and the levers.

Notes come in four kinds — preference, complaint, compliment, note — and the
one that earns its keep is **preference**, because it is the only one that can
be acted on before the guest has to ask twice.

#### Tiers are always derived

Guest → Silver → Gold → Platinum, from **stays or spend — either qualifies**.
A company booking one long conference is worth as much as a family on their
eighth weekend, and a rule that only counted visits would miss the first.

Nothing ever writes a tier the figures do not support, so a guest cannot be
Platinum because somebody clicked it once in 2023. The column exists so a list
can sort on it, and it is rebuilt from the numbers.

The profile also shows **how far to the next tier**, by whichever route they
are further along — "two more stays to Gold" is something a receptionist can
say out loud.

#### Totals are a cache, and say when they were taken

`stays`, `nights`, `total_spend` and `last_stay_at` sit on the guest so a list
of thirty does not run a hundred and twenty aggregate queries. They are rebuilt
by `App\Support\GuestCrm::recount()` when a stay closes and whenever a profile
is opened — and if they are behind, the profile says so in a banner rather than
showing a stale figure quietly.

Spend is what was **billed**, not what is on a folio: an open folio is a stay in
progress and its figures are still moving. A guest's lifetime value should not
go up and down while they are asleep upstairs.

#### Loyalty is a ledger, never a number

Points are earned on **room revenue** at one point per ₹100 — not on the
restaurant bill they signed for somebody else. Every entry has a date and a
reason, so a balance somebody argues with can be explained line by line. The
number on the guest is a cache of the sum.

Awarding is idempotent per stay: a checkout screen opened twice does not pay
twice.

#### Feedback

A link goes out with the checkout message on WhatsApp or email. The guest opens
it on their phone — **no account, no login**; the forty characters in the link
identify the stay, exactly like the document links.

The form asks for an overall score, five areas, two questions in words, and
whether they would come back. **Every question is optional.** Somebody who
rates the room and skips the food has told the hotel something useful, and a
form that insists on all five gets answered by nobody. It is plain HTML and CSS
with no JavaScript at all — the stars are radio buttons.

A guest who opens the link twice sees what they already said, not a blank form
that would quietly replace it.

Inside the app, **Feedback is an inbox rather than a report**. Averages are at
the top because somebody asks for them, but it is built round the list of low
scores nobody has dealt with — the only part of it that is a to-do list.
Marking one handled takes a note, because "handled" with nothing written down
is a tick box somebody presses to clear a list.

Two deliberate choices in the scoring: **four is not "good"** — it is the score
of a guest who found something wrong and did not say what, so only five shows
green. And an average **ignores what a guest skipped** rather than counting it
as a zero.

The row is created when the link is **sent**, so "we asked and they did not
reply" is a fact the hotel holds rather than a silence it has to interpret.

#### Birthdays and anniversaries

Matched on the **day and month, never the year** — that is the whole trick, as
a plain date comparison would match once and never again. Grouped by day, for a
week, a fortnight or a month ahead.

### Store and Inventory — what the hotel bought, and where it went

**Store → Stock / Items / Purchase Orders / Goods Receipts / Issues / Wastage /
Stock Adjustments / Recipes / Categories.**

A restaurant's food cost is the difference between what the kitchen bought and
what it sold, and most hotels cannot state it because the buying lives in a
register and the selling lives in the till. This module is the register, kept
in a way that can be subtracted.

#### The ledger is the truth

`stock_ledger` has one row per movement, ever. Nothing in the module updates a
ledger row and nothing deletes one — a correction is another row, and a
cancelled document posts its reverse rather than vanishing. The quantity and
average rate sitting on `store_items` are a **cache** of running that ledger,
and `App\Support\Store::rebuild()` can produce them again from nothing.

If the cache and the ledger ever disagree, the ledger is right. That is the
whole reason the ledger exists — a store balance nobody can explain is a store
balance nobody believes, and "why does it say forty kilos?" has to be
answerable by a list of rows, not by a number.

#### One document, five kinds

A purchase order, a goods receipt, an issue, a wastage note and a stock
adjustment are the same shape: a header, a party, some lines, a total. They are
one `store_docs` table with a `kind`, one controller and one form, because five
near-identical controllers means the fifth one is the one with the bug in it.

What differs is what posting **does**, and that is a single table:

| Kind | On posting |
|---|---|
| Purchase Order | nothing moves — it is a promise, and gets marked placed |
| Goods Receipt | stock in, **and the average cost is recalculated** |
| Issue | stock out, at the average rate |
| Wastage | stock out, at the average rate, against a reason |
| Stock Adjustment | moves the balance by the amount typed, either sign |

A receipt can be raised **against** an order: the lines come across pre-filled
with what is still due, the order goes to *part received* or *closed* on its
own, and the two stay linked in both directions.

#### Valuation moves only on the way in

Moving weighted average:

```
new average = (held × old average + received × paid) ÷ (held + received)
```

A kitchen buys the same onions at four prices in a month, and the average is
what survives that — and what an auditor expects to see.

It moves **only on a receipt**. Stock going out leaves at the average and does
not change it, because issuing cannot alter what the stock still on the shelf
cost. An implementation that recalculated on the way out drifts a little every
time the kitchen draws anything, and by March nobody can say what happened.

#### Negative stock is allowed, and drawn in red

A kitchen that used forty kilos before anybody entered the delivery note is a
hotel, not a bug. Refusing that issue would mean the books say the stock is
still on the shelf — the one thing that is definitely false. So the issue goes
through, the balance goes below zero, and the stock sheet lists it under
**"issued more than we had"** until a receipt is entered.

#### Recipes, and therefore food cost

A recipe is a dish and the items it eats. `yield_qty` is how many portions it
makes, so a biryani written for four is costed per one, and the cost is worked
out from the **current average rate** of each ingredient — the cost card
re-prices itself as the market moves, rather than showing what the dish cost in
January. Linked to a POS item, it is the other half of the food-cost sum.

#### Permissions are split fine on purpose

Ordering, receiving, issuing and writing stock off are four different levels of
trust. A storekeeper who may receive a delivery should not be able to write
ten thousand rupees of meat off as wastage, so each kind is its own permission
key (`store/purchase-orders`, `store/grn`, `store/issues`, `store/wastage`,
`store/adjustments`) rather than one "Store" tick. Posting needs **add**;
cancelling a posted document needs **delete**.

### Shift closing and the audit trail — the drawer, and who changed what

**Shift & Audit → My Shift / Shift Reports / Audit Trail / Sign-in History.**

Two questions an owner asks that nothing else in this system could answer: is
the cash in the drawer the cash the shift took, and who changed that.

#### A shift holds no money of its own

`cashier_shifts` does not keep a copy of the payments. A shift's figures are a
**question asked of the payment rows** — `settlements`, `advance_deposits`,
`pos_payments` and the two petty cash tables — that the hotel already writes
when it takes money. Nothing is copied anywhere.

That has three consequences worth having: a shift report can never disagree
with the day book; a hotel that turns this screen on today can still close
yesterday; and a payment entered late lands in the shift it was **entered** in
rather than in no shift at all.

A payment belongs to a shift when the same person took it while that shift was
open. Not the date on the receipt — the moment it was entered, which is when
the money was actually in somebody's hand.

#### Except the two things nothing else knows

**What the cashier counted**, which exists in no other table, and a frozen copy
of the expected figures taken at the instant of closing. A bill corrected next
week moves the day book; it does not move a variance somebody has already
signed for. The screen says as much on a closed shift.

The count is asked for **before** the expected figure is shown. A close where
the clerk can read the answer off the screen first is not a count, it is
transcription — so the boxes are blank and the comparison appears afterwards.

#### Only cash can be short

Card and UPI are counted too, because a machine total that does not match is
worth knowing about tonight rather than at the month end. But the headline
variance is cash, because cash is the only one somebody can walk out of the
building with. Short and over are shown as different things: over usually means
a payment was never entered, which is a different problem — and the guest's
bill is the one that is wrong.

Anything more than a rupee either way is flagged. One open drawer per person,
ever: two would each claim the same payments and both reports would be right
about half the money.

#### Money taken while no drawer was open

It still goes on the guest's bill and into the day book — it simply belongs to
no shift, so nobody counted it. **Shift Reports** lists it by person at the top
of the screen. That control report is what makes the rest of it worth having: a
hotel where half the payments belong to no shift has a screen nobody uses.

#### The audit trail

One row per thing done, in `activity_logs`, never updated and never deleted.
There is no edit and no delete route behind these screens for anybody,
including an administrator — a log a manager can tidy up is not evidence of
anything.

Three rules hold it together:

1. **It never breaks the thing it is watching.** Every write goes through
   `rescue()`. A hotel that cannot take a payment because the audit table is
   full has been made worse by its audit trail, not better.
2. **It writes names, not ids.** The user's name and the row's label are copied
   into the log. Deleting a user must not blank a year of history, and "Bill
   INV-1-0042" is what somebody is actually looking for.
3. **It records only what changed** — the columns that moved, with their before
   and after. A log that stores a copy of every record is one nobody can read,
   and it is how audit tables end up bigger than the database they watch.

A column that only *looks* changed does not count: `12.00` and `12` are the
same amount, and a trail full of rows saying a price went from 12.00 to 12 is a
trail people learn to ignore. Passwords, tokens and timestamps are never in it.
Neither are the columns the system keeps up to date by itself — a room's
housekeeping status, an item's stock balance, a guest's lifetime totals —
because that drift would bury the changes a person actually made.

Sign-ins, sign-outs and **refused** sign-ins are in the same table, with their
own screen. A refusal records the username that was tried and where it came
from; it does not record what was typed into the password box, whether or not
it was the right one.

---

## How the money works

All of it lives in `app/Support/Money.php`, and `resources/js/reservation.js`
mirrors it so the screen and the server never disagree.

**Room, per night** = room rent + plan charge − discount
**Room gross** = that × nights × number of rooms

### Tax is a choice, never a side effect

**Nothing in this system adds tax to a figure on its own.** Every row that
carries money carries a **Tax** dropdown beside it, that dropdown opens on
*No Tax*, and a row nobody touched is taxed at nothing. This is deliberate and
it is the rule the whole app is built on — a room row, a folio line, a POS bill,
a pool or hall booking, a car trip.

The dropdown offers four kinds of answer, and `app/Support/Tax.php` is the one
place that turns any of them into a percent:

| Choice | What it means |
|---|---|
| **No Tax** | nothing. The default everywhere. |
| **GST slab** | the Indian hotel slab, worked out from the rent for one room for one night. Room screens only. |
| **As billed** | the percent already stored on that row. What a booking taken before this version resolves to, so re-pricing it never changes its tax. |
| a tax from **Masters → Tax** | "GST 12%", "VAT 5%", whatever the hotel set up. |

The slabs themselves live in `config/pms.php` and are what the *GST slab* choice
means — they are not what happens by itself:

| Rent per room per night | GST |
|---|---|
| up to ₹1,000 | 0% |
| ₹1,001 – ₹7,500 | 12% |
| above ₹7,500 | 18% |

If you would rather every dropdown came up already pointing at something, set
`PMS_TAX_DEFAULT` in `.env` to `slab` or to the id of a row in Masters → Tax.
It only changes what a *new* row opens on; nothing already saved moves, and the
clerk can always put it back to No Tax.

**Exclusive** adds tax on top. **Inclusive** works it backwards out of the
figure you typed.

Worked example — Deluxe Double at ₹3,600 on a Continental Plan (+₹400),
three nights, exclusive, with **GST slab** picked on the row:

```
nightly  3600 + 400 = 4000        →  ≤ 7500, so 12%
gross    4000 × 3 × 1 = 12,000
tax      12,000 × 12%  = 1,440
net                    = 13,440
```

The same row with the dropdown left alone comes to **₹12,000** and no tax at
all, which is the point.

---

## How permissions work

Three tables and one rule.

```
module            a sidebar group          Reservation
  └─ submodule    a screen inside it       New Reservation
       └─ user_permission                  what one user may do, per branch
```

`user_permission` holds **one row per (user, branch)** — exactly the shape your
original database used:

| column | example | meaning |
|---|---|---|
| `module_id` | `1,2` | which modules they can see |
| `submodule_id` | `1,2,3` | which screens they can see |
| `permissions` | `{"2":{"view":1,"add":1,"edit":0,"delete":0}}` | keyed by sub-module id |

**The rule:** a sub-module's `url` is both the link *and* the permission key.
`submodule.url = 'reservation/new-reservation'` means that route is guarded by
`permission:reservation/new-reservation,add`. Because the sidebar and the
routes read the same value, a link can never appear that the route then
refuses.

**Role 1 (Administrator) bypasses every check.** Everyone else gets exactly
what is ticked on their Users screen.

Ticking Add, Edit or Delete switches **View** on automatically — the sidebar
uses View to decide what to show. Switching View off clears the whole row.

### Guarding a route

```php
Route::get('house-keeping/issue', [IssueController::class, 'index'])
    ->middleware('permission:house-keeping/issue,view');
```

### Guarding a button

```blade
@canAdd('house-keeping/issue')
    <a href="{{ route('hk.issue.create') }}" class="nv-btn nv-btn-primary">Add issue</a>
@endCanAdd
```

`@canView` `@canAdd` `@canEdit` `@canDelete` `@admin` are all available, each
with a matching `@endCan…`.

### In PHP

```php
can_do('house-keeping/issue', 'edit')   // this user, that screen, that action
can_here('delete')                      // same, for the screen currently open
is_admin()                              // role 1
sidebar_menu()                          // the filtered menu the sidebar renders
active_branch()                         // the branch they are working in
```

---

## Building the next screen

Every remaining menu item already exists as a sub-module, so there are only
two steps.

1. **Write the route** with the sub-module's url as its path and permission key:

   ```php
   Route::get('house-keeping/issue', [IssueController::class, 'index'])
       ->name('hk.issue')
       ->middleware('permission:house-keeping/issue,view');
   ```

2. **Build the controller and view.** The tables are already there — see
   `database/migrations`.

That is all. The **Soon** pill disappears the moment the route exists, because
`SubModule::isLinked()` asks the router whether anything answers that path.

### Adding a whole new master

Add an entry to `config/masters.php` and it appears at `/masters/<key>` with a
working list, form, validation, search, activate and delete. No controller, no
blade file:

```php
'floor' => [
    'model'  => \App\Models\Master\Floor::class,
    'label'  => 'Floor',
    'plural' => 'Floors',
    'icon'   => 'layers',
    'intro'  => 'Floors in the building.',
    'columns' => ['name', 'code'],
    'fields' => [
        'name' => ['label' => 'Floor', 'type' => 'text', 'rules' => 'required|string|max:255'],
        'code' => ['label' => 'Code', 'type' => 'text', 'rules' => 'nullable|string|max:20'],
    ],
],
```

Field types: `text` `number` `money` `percent` `select` `textarea` `switch`.
A `select` takes `options` (an array) or `source` (another master's model).

---

## Where things are

```
app/Support/Money.php             every money rule, in one file
app/Support/Availability.php      the availability grid behind the status view
app/Support/RoomTimeline.php      the room-by-night bars behind the calendar
app/Support/MonthlyPosition.php   the day-by-day position behind the monthly
app/Support/Folio.php             every checkout figure, in one place
app/Support/Laundry.php           what each vendor is still holding
app/Support/PosDashboard.php      every POS figure, in one place
app/Support/PosTill.php           the till: one order, opened to paid for
app/Support/PosFloor.php          what every table and room is doing right now
app/Support/Qr.php                QR codes in PHP, with nothing installed
app/Support/Tax.php               the only thing that turns a tax choice into a %
app/Support/Notify.php            raising a notification, on every channel
app/Support/WhatsApp.php          five ways to send a WhatsApp message
app/Support/GuestMessage.php      the messages that go to the guest's phone
app/Support/Reports.php           all eighteen reports, in one file
app/Support/Ledgers.php           every balance in the accounts module
app/Support/Vouchers.php          the only door into the books
app/Support/Facility.php          shared bits of pool, hall, parking and trips
app/Support/HousekeepingBoard.php the house as work rather than as a list
app/Support/NightAudit.php        the hotel's own calendar, and the close
app/Support/Rates.php             what a room costs tonight, and why
app/Support/Gst.php               GSTINs, place of supply, GSTR-1
app/Support/Compliance.php        Form C, the police register, ID masking
app/Support/Tally.php             the month's sales as Tally vouchers
app/Support/GuestCrm.php          tiers, points, birthdays, feedback scores
app/Support/Store.php             the stock ledger, and what it all cost
app/Support/Shifts.php            the drawer, and whether it is right
app/Support/Audit.php             who changed what, and when
app/Helpers/Helper.php            menu + permission logic, cached per request
app/Helpers/helpers.php           the global can_do() / sidebar_menu() wrappers
app/Http/Middleware/
    CheckUserPermission.php       'permission:<url>,<action>'
    EnsureIsAdmin.php             'admin'
app/Http/Controllers/
    MasterController.php          all 15 master screens
    ReservationController.php     new / list / show / edit / cancel / deposit
    ReservationStatusController   the status board and its CSV
    ReservationCalendarController the tape chart, blocking, allotting, moving
    HousekeepingBoardController   the board, its drag rules and its log
    NightAuditController          the night audit, its reports and reopening
    Rate/RateController           rate plans, seasons, the grid, and the quote
    Rate/RateCalendarController   every room type's price for every night
    ComplianceController          Form C, police register, GST, Tally
    Crm/GuestController           the guest profile and everything on it
    Crm/FeedbackController        the feedback inbox
    Store/DocController           orders, receipts, issues, wastage, adjustments
    Store/ItemController          the items, and their opening stock
    Store/StockController         the stock sheet, one item's ledger, consumption
    Store/RecipeController        cost cards, priced at today's average
    Audit/ShiftController         opening, counting and closing a drawer
    Audit/TrailController         the trail, the sign-ins, and the CSV
    GuestFeedbackFormController   the form the guest fills in, no login
    ReportsController             the Reports section — index, one report, CSV
    NotificationController        the bell, its feed, and the settings screen
    Accounting/VoucherController  all six screens that write a voucher
    Accounting/BookController     day book, cash book, bank book, statements
    Accounting/ReportController   trial balance and profit & loss
    Facility/PoolController       pool bookings and the day calendar
    Facility/HallController       the banquet diary and the week calendar
    Facility/ParkingController    cars in, cars out (free unless ticked)
    Facility/TripController       pickups and drops
    Facility/FacilitySetupController  pools, halls, bays and cars — one screen ×4
    MonthlyCalendarController     the monthly position report and its CSV
    PreRegCardController          the registration card, filled and blank
    RoomCalendarController        the tile board and its guest panel
    CheckOutController            folio, extend, pax, payments, the bill
    BookingCalendarController     the current/advance grid and its modal
    AdvanceDepositController      receipts, refunds and the booking picker
    CheckInController             booking -> guest in the room, and the list
    HouseKeepingController        the status list and its three bulk actions
    HkIssueController             laundry notes out, and the two quick-add lists
    HkReceivedController          laundry back, and what is still outstanding
    RoomBlockedController         rooms off sale, and back on
    WorkOrderController           maintenance jobs, and the block one can hold
    PosDashboardController        the outlets' sales, in one screen
    GroupBillController           one family, several rooms, one bill
    RoomServiceReportController   the two room-wise reports
    Pos/PosController             the floor, the bill, and every act on an order
    Pos/PosListController         the six screens beside Dine In
    Pos/KitchenController         the kitchen board and its feed
    Pos/SetupListController       the engine behind the six small setup lists
app/Models/Master/                the master models
app/Models/Reservation/           reservation, rooms, services, deposits
app/Models/FrontOffice/           check-ins and the pax register
app/Models/HouseKeeping/          linen, laundry notes, blocks and work orders
app/Models/Pos/                   outlets, orders, invoices and the audit log
app/Models/Store/                 items, documents, the ledger and recipes
app/Models/Audit/                 cashier shifts and the activity log
config/masters.php                what each master screen looks like
config/pms.php                    tax slabs, times, prefixes, work order categories
resources/js/reservation.js       the reservation grid — mirrors Money.php
resources/js/room-calendar.js     drag-to-book, blocking, category collapse
resources/js/check-in.js          the Allot Room popup
resources/js/room-grid.js         the room tiles and the guest panel
resources/js/check-out.js         the four checkout modals
resources/js/laundry.js           the issue grid and its quick-add popups
resources/js/laundry-receive.js   the receive grid and its pending limits
resources/js/room-blocked.js      the tick-to-reveal Block and Release buttons
resources/js/pos-till.js          the floor clocks, the action rail, the dialogs
resources/js/pos-kds.js           the kitchen clocks and its quiet refresh
resources/js/store-lines.js       the line grid on every store document
resources/views/partials/sidebar  reads sidebar_menu() — nothing hard-coded
resources/views/masters/          three views serve all 15 masters
resources/views/layouts/print     A4 layout — no theme, no dark mode
resources/css/print.css           how printed documents look
routes/web.php                    every route mapped to a permission key
database/migrations/              109 tables, in 32 migrations
database/seeders/MenuSeeder.php   the whole sidebar
database/seeders/MasterSeeder.php the demo rooms, rates and services
database/hotel_admin.sql          the same thing as a phpMyAdmin import
scripts/dump-mysql.php            regenerates that .sql from the migrations
scripts/build-fallback.php        regenerates public/css/theme.css + theme.js
```

## The look

**The theme is deliberately dense.** Controls are 27px tall and the base text is
11.5px, which is about 80% of a conventional admin theme — so a fifteen-day
calendar, a thirty-day monthly position and a floor of forty tables each fit on
one screen without the user reaching for the browser's zoom.

That was done property by property rather than with a blanket shrink: space
(padding, gap, height) took the biggest cut because space is what actually makes
a screen dense, type took a smaller one on a curve so the 10px labels stayed
readable, and borders, shadows and the responsive breakpoints were not scaled at
all — a hairline that becomes 0.78px renders inconsistently, and moving the
breakpoints would change which layout a given width gets.


The theme has a name and a reason: **plum and sea green**. Plum is the product's
own colour — it is not the blue every other back office reaches for. Sea green
is the second voice, and it means the same thing everywhere it appears:
available, confirmed, money in.

**Type.** Headings are set in *Fraunces*, a soft old-style serif, because a
hotel sells hospitality and nothing else in this category looks like it. The
interface itself is *Plus Jakarta Sans*, which stays legible at 12px in a dense
table — where this software actually lives. Both load from Google Fonts in
`layouts/app.blade.php`; with no internet they fall back to Segoe UI and
Palatino, chosen for matching metrics so the layout does not shift.

**Room colours live in one place.** `--rs-clean`, `--rs-checkin`,
`--rs-reservation`, `--rs-dirty`, `--rs-checkout`, `--rs-repair`,
`--rs-inspect`, `--rs-blocked` — each with a `-soft` tint and an `-ink` that
clears 4.5:1 on it. The dashboard, the Room Calendar and the month calendar all
read from these, so a colour cannot come to mean two things.

**Occupancy is a magnitude, so it gets a ramp,** not a rainbow: `--cal-1`
through `--cal-5`, one hue, light to dark, used by the dashboard calendar and by
the occupancy row on Reservation Calendar Monthly. Both ramps were checked for
monotonic lightness, and the text on the two darkest steps clears 4.5:1 in light
and dark mode alike.

**Colour is never alone.** Every room tile prints its status word, every
calendar cell prints `sold / total`, and every chart has a legend — so the
screens work in greyscale, on a photocopy, and for a colour-blind user. The
categorical chart palette was validated for colour-vision separation before it
was used.

**Class names are global, so they are prefixed by screen.** `nv-mcal-*` is the
dashboard month calendar, `nv-cal-*` is the tape chart on Reservation Status
View and Booking Calendar, `nv-rd-*` is the dashboard donut and `nv-donut-*` the
POS one, `nv-av-bar-*` is the availability chart and `nv-bar` is the bar row on
Reservation Calendar. Reusing a name is how one screen silently rewrites
another — a `display: grid` landing on a `<table>` pulls its header out of line
with its body, and nothing warns you. Before adding a component class, grep
`app.css` for it.

---

## Styling

`public/css/theme.css` and `public/js/theme.js` are pre-built, so the app looks
right the moment you clone it — no `npm install` needed.

To edit the design with Tailwind:

```
npm install
npm run dev
```

The layout switches to the Vite build automatically once one exists. Sources
live in `resources/css/app.css` and `resources/js/app.js`. After editing them
without Vite, run `php scripts/build-fallback.php`.

Dark mode is a toggle in the top bar; the choice is remembered per browser.

---

## Things worth knowing

- `users` uses **`user_id`** as its primary key, not `id` — kept from your
  original table.
- Every master and every reservation is scoped by `branch_id`. Switch branch
  from the top bar and the whole app follows.
- Deleting a user removes its `user_permission` rows in the same transaction.
- Deleting a module or sub-module strips its ids out of every
  `user_permission` row, so no stale permissions are left behind.
- You cannot delete or deactivate the account you are signed in with, and the
  last administrator cannot be removed.
- Role 1 is a system role: it cannot be renamed or deleted.
- `database/hotel_admin.sql` is generated from the migrations, never written by
  hand — change a migration, run `php scripts/dump-mysql.php`, and the dump
  follows.
