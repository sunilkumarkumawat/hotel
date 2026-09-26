/*
|------------------------------------------------------------------------------
| New Reservation
|------------------------------------------------------------------------------
| Runs only on the reservation form. No framework, no build step.
|
| The maths here mirrors app/Support/Money.php exactly so the totals move while
| you type — but the server redoes all of it on save, so a tampered form can
| never change what gets stored.
*/
(function () {
    'use strict';

    const form = document.querySelector('[data-reservation]');
    if (!form) return;

    const boot = window.RESERVATION_BOOT || {};
    const $ = (sel, root) => (root || document).querySelector(sel);
    const $$ = (sel, root) => Array.from((root || document).querySelectorAll(sel));

    const money = (n) => '₹' + (Math.round((Number(n) || 0) * 100) / 100).toLocaleString('en-IN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

    const round2 = (n) => Math.round((Number(n) || 0) * 100) / 100;

    /* ── The same money rules as App\Support\Money ───────────────────────── */

    /**
     * The percent a Tax dropdown works out to — the browser half of App\Support\Tax.
     *
     * Nothing here invents a tax. 'none' is zero, an unknown choice is zero,
     * and the GST slab is only reached by picking it.
     */
    function taxPercentFor(choice, nightlyRent) {
        choice = String(choice === null || choice === undefined ? 'none' : choice);

        if (choice === '' || choice === '0' || choice === 'none') return 0;
        if (choice === 'slab') return roomTaxPercent(nightlyRent || 0);

        const map = boot.taxPercents || {};

        return Object.prototype.hasOwnProperty.call(map, choice) ? Number(map[choice]) || 0 : 0;
    }

    /** GST percent from the nightly rent, using the slabs from config/pms.php. */
    function roomTaxPercent(nightlyRent) {
        const slabs = boot.taxSlabs || {};
        const keys = Object.keys(slabs).filter((k) => k !== 'above');

        if (!keys.length) return 0;

        for (const key of keys) {
            if (nightlyRent <= Number(key)) return Number(slabs[key]);
        }

        return Number(slabs.above || 0);
    }

    /** Split a gross figure into taxable value + tax. */
    function split(gross, percent, taxType) {
        gross = round2(gross);
        if (percent <= 0) return { amount: gross, tax: 0, net: gross };

        if (taxType === 'inclusive') {
            const amount = round2(gross / (1 + percent / 100));
            return { amount: amount, tax: round2(gross - amount), net: gross };
        }

        const tax = round2((gross * percent) / 100);
        return { amount: gross, tax: tax, net: round2(gross + tax) };
    }

    function nightsBetween(from, to) {
        if (!from || !to) return 1;
        const days = Math.round((new Date(to) - new Date(from)) / 86400000);
        return Math.max(1, days);
    }

    function planCharge(id) {
        const plan = (boot.planTypes || []).find((p) => String(p.id) === String(id));
        return plan ? Number(plan.charge) : 0;
    }

    /** Work out one room row. Returns the row plus its computed figures. */
    function computeRoom(row) {
        // Always derive the nights from the dates, exactly as the server does.
        // A stored row can arrive without no_of_days; trusting it would price a
        // three-night stay as one.
        const days = nightsBetween(row.arrival_date, row.checkout_date);
        const count = Math.max(1, Number(row.no_of_rooms) || 1);

        const nightly = Math.max(
            0,
            round2(
                Math.max(0, Number(row.room_rent) || 0) +
                    Math.max(0, planCharge(row.plan_type_id)) -
                    Math.max(0, Number(row.discount) || 0)
            )
        );

        const gross = round2(nightly * days * count);
        const percent = taxPercentFor(row.tax_choice, nightly);
        const s = split(gross, percent, row.tax_type);

        return Object.assign({}, row, {
            nightly: nightly,
            no_of_days: days,
            amount: s.amount,
            tax_percent: percent,
            tax_amount: s.tax,
            net_amount: s.net,
            discount_total: round2((Number(row.discount) || 0) * days * count),
        });
    }

    function computeService(row) {
        const gross = round2((Number(row.qty) || 0) * (Number(row.price) || 0));
        const percent = taxPercentFor(row.tax_choice, 0);
        const s = split(gross, percent, row.tax_type);

        return Object.assign({}, row, {
            amount: s.amount,
            tax_percent: percent,
            tax_amount: s.tax,
            total_amount: s.net,
        });
    }

    /* ── State ──────────────────────────────────────────────────────────── */

    let rooms = (boot.rows || []).map(computeRoom);
    let services = (boot.serviceRows || []).map(computeService);

    const nameOf = (list, id) => {
        const hit = (list || []).find((x) => String(x.id) === String(id));
        return hit ? hit.name : '—';
    };

    /* ── Rendering ──────────────────────────────────────────────────────── */

    const roomBody = $('[data-room-rows]');
    const roomEmpty = $('[data-room-empty]');
    const serviceBody = $('[data-service-rows]');
    const inputHost = $('[data-row-inputs]');

    function renderRooms() {
        roomBody.innerHTML = '';

        rooms.forEach((row, index) => {
            const tr = document.createElement('tr');
            tr.innerHTML =
                '<td class="nv-nowrap">' + row.arrival_date + '<span class="nv-sub">' + (row.arrival_time || '') + '</span></td>' +
                '<td class="nv-nowrap">' + row.checkout_date + '<span class="nv-sub">' + (row.checkout_time || '') + '</span></td>' +
                '<td class="is-num">' + row.no_of_days + '</td>' +
                '<td>' + nameOf(boot.categories, row.room_category_id) + '</td>' +
                '<td><strong>' + nameOf(boot.roomTypes, row.room_type_id) + '</strong></td>' +
                '<td>' + nameOf(boot.planTypes, row.plan_type_id) + '</td>' +
                '<td>' + (row.tax_type === 'inclusive' ? 'Inclusive' : 'Exclusive') + '</td>' +
                '<td class="is-num">' + money(row.room_rent) + '</td>' +
                '<td class="is-num">' + money(row.discount) + '</td>' +
                '<td class="is-num">' + row.no_of_rooms + '</td>' +
                '<td class="is-num nv-nowrap">' + row.male + '/' + row.female + '/' + row.child + '</td>' +
                '<td class="is-num">' + money(row.amount) + '</td>' +
                '<td class="is-num">' + money(row.tax_amount) + '<span class="nv-sub">' + row.tax_percent + '%</span></td>' +
                '<td class="is-num"><strong>' + money(row.net_amount) + '</strong></td>' +
                '<td class="is-end"></td>';

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'nv-btn nv-btn-ghost nv-btn-sm';
            remove.setAttribute('aria-label', 'Remove this room row');
            remove.innerHTML = '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg>';
            remove.addEventListener('click', () => {
                rooms.splice(index, 1);
                renderRooms();
            });

            tr.lastElementChild.appendChild(remove);
            roomBody.appendChild(tr);
        });

        roomEmpty.hidden = rooms.length > 0;
        roomBody.closest('.nv-table-wrap').hidden = rooms.length === 0;

        syncInputs();
        renderTotals();
    }

    function renderServices() {
        serviceBody.innerHTML = '';

        if (!services.length) {
            const tr = document.createElement('tr');
            tr.innerHTML = '<td colspan="8" class="nv-muted">No services added.</td>';
            serviceBody.appendChild(tr);
        }

        services.forEach((row, index) => {
            const tr = document.createElement('tr');
            tr.innerHTML =
                '<td><strong>' + row.service_name + '</strong></td>' +
                '<td>' + (row.tax_type === 'inclusive' ? 'Inclusive' : 'Exclusive') + '</td>' +
                '<td class="is-num">' + row.qty + '</td>' +
                '<td class="is-num">' + money(row.price) + '</td>' +
                '<td class="is-num">' + money(row.tax_amount) + '</td>' +
                '<td class="is-num"><strong>' + money(row.total_amount) + '</strong></td>' +
                '<td>' + (row.remark || '<span class="nv-muted">—</span>') + '</td>' +
                '<td class="is-end"></td>';

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'nv-btn nv-btn-ghost nv-btn-sm';
            remove.setAttribute('aria-label', 'Remove ' + row.service_name);
            remove.textContent = 'Remove';
            remove.addEventListener('click', () => {
                services.splice(index, 1);
                renderServices();
            });

            tr.lastElementChild.appendChild(remove);
            serviceBody.appendChild(tr);
        });

        syncInputs();
        renderTotals();
    }

    function renderTotals() {
        const roomTotal = rooms.reduce((sum, r) => sum + r.amount, 0);
        const serviceTotal = services.reduce((sum, s) => sum + s.amount, 0);
        const discount = rooms.reduce((sum, r) => sum + r.discount_total, 0);
        const tax = rooms.reduce((sum, r) => sum + r.tax_amount, 0) +
            services.reduce((sum, s) => sum + s.tax_amount, 0);
        const net = rooms.reduce((sum, r) => sum + r.net_amount, 0) +
            services.reduce((sum, s) => sum + s.total_amount, 0);

        const set = (key, value) => {
            const el = $('[data-total="' + key + '"]');
            if (el) el.textContent = money(value);
        };

        set('room', roomTotal);
        set('service', serviceTotal);
        set('discount', discount);
        set('tax', tax);
        set('net', net);
    }

    /** Rebuild the hidden inputs the form actually posts. */
    function syncInputs() {
        inputHost.innerHTML = '';

        const add = (name, value) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value === null || value === undefined ? '' : value;
            inputHost.appendChild(input);
        };

        const roomFields = [
            'arrival_date', 'arrival_time', 'checkout_date', 'checkout_time', 'guest_type',
            'room_category_id', 'room_type_id', 'plan_type_id',
            'no_of_rooms', 'tax_type', 'tax_choice', 'room_rent', 'discount', 'male', 'female', 'child',
        ];

        rooms.forEach((row, i) => {
            roomFields.forEach((field) => add('rooms[' + i + '][' + field + ']', row[field]));
        });

        const serviceFields = [
            'service_id', 'service_name', 'tax_type', 'tax_choice', 'qty', 'price', 'remark',
        ];

        services.forEach((row, i) => {
            serviceFields.forEach((field) => add('services[' + i + '][' + field + ']', row[field]));
        });
    }

    /* ── The "add a room" row ───────────────────────────────────────────── */

    const entry = $('[data-room-entry]');
    const get = (key) => $('[data-r="' + key + '"]', entry);
    const val = (key) => {
        const el = get(key);
        return el ? el.value : '';
    };

    /*
     * How many rooms of each type are free for the nights currently in the
     * form. Filled by the availability call; empty until the first one lands,
     * which is why every reader treats "unknown" and "none" differently.
     */
    let freeByType = {};

    /** The free count for a type, or null when the server has not said yet. */
    function freeFor(typeId) {
        if (!typeId) return null;

        const value = freeByType[String(typeId)];

        return value === undefined ? null : Number(value);
    }

    /** Write "— n free" onto every room type option. */
    function labelRoomTypes() {
        const select = get('room_type_id');

        Array.prototype.forEach.call(select.options, function (option) {
            if (!option.value) return;

            const base = option.dataset.label || option.textContent;
            option.dataset.label = base;

            const free = freeFor(option.value);
            option.textContent = free === null ? base : base + ' — ' + free + ' free';
        });
    }

    /**
     * Say under the Room Type field how many are free, and whether the number
     * of rooms being asked for will fit.
     *
     * The warning is a warning, not a block: a hotel does overbook on purpose,
     * and the server checks the real availability on save anyway. What this
     * prevents is finding out at the end of a long form.
     */
    function showTypeFree() {
        const note = $('[data-type-free]');
        if (!note) return;

        const free = freeFor(val('room_type_id'));

        if (free === null) {
            note.textContent = '';
            note.classList.remove('is-short');

            return;
        }

        const wanted = Number(val('no_of_rooms')) || 1;

        if (wanted > free) {
            note.textContent = free === 0
                ? 'Nothing of this type is free for these nights.'
                : 'Only ' + free + ' free for these nights — you are asking for ' + wanted + '.';
            note.classList.add('is-short');

            return;
        }

        note.textContent = free + ' free for these nights.';
        note.classList.remove('is-short');
    }

    /** Room types belonging to the chosen category (or all, if none chosen). */
    function fillRoomTypes() {
        const categoryId = val('room_category_id');
        const select = get('room_type_id');
        const previous = select.value;

        select.innerHTML = '<option value="">Select RoomType</option>';

        (boot.roomTypes || [])
            .filter((t) => !categoryId || String(t.category_id) === String(categoryId))
            .forEach((t) => {
                const option = document.createElement('option');
                option.value = t.id;
                option.textContent = t.name;
                option.dataset.rent = t.rent;
                select.appendChild(option);
            });

        if (previous && select.querySelector('option[value="' + previous + '"]')) {
            select.value = previous;
        }

        labelRoomTypes();
        showTypeFree();
    }

    /** Ask the server which rooms are free for these dates. */
    let availabilityTimer = null;
    function refreshAvailability() {
        clearTimeout(availabilityTimer);

        availabilityTimer = setTimeout(function () {
            const params = new URLSearchParams({
                arrival_date: val('arrival_date'),
                checkout_date: val('checkout_date'),
                room_category_id: val('room_category_id') || '',
                room_type_id: val('room_type_id') || '',
            });

            if (!val('arrival_date') || !val('checkout_date')) return;

            fetch(boot.urls.availability + '?' + params.toString(), {
                headers: { Accept: 'application/json' },
            })
                .then((r) => (r.ok ? r.json() : null))
                .then(function (data) {
                    if (!data) return;

                    const badge = $('[data-avail-badge]');
                    if (badge) badge.textContent = 'Avl : ' + data.available;

                    // Remember the per-type counts and put them on the dropdown,
                    // so every line reads "Deluxe Double — 4 free" and the clerk
                    // can see which type can take the family before choosing.
                    freeByType = data.by_type || {};
                    labelRoomTypes();
                    showTypeFree();
                })
                .catch(function () {
                    /* offline — the badge just stops updating */
                });
        }, 250);
    }

    function refreshDays() {
        const days = nightsBetween(val('arrival_date'), val('checkout_date'));
        get('no_of_days').value = days;
        return days;
    }

    function currentRow() {
        return {
            arrival_date: val('arrival_date'),
            arrival_time: val('arrival_time') || boot.defaults.arrival_time,
            checkout_date: val('checkout_date'),
            checkout_time: val('checkout_time') || boot.defaults.checkout_time,
            guest_type: val('guest_type'),
            room_category_id: val('room_category_id') || null,
            room_type_id: val('room_type_id') || null,
            plan_type_id: val('plan_type_id') || null,
            no_of_days: refreshDays(),
            no_of_rooms: Number(val('no_of_rooms')) || 1,
            tax_type: val('tax_type'),
            tax_choice: val('tax_choice') || 'none',
            room_rent: Number(val('room_rent')) || 0,
            discount: Number(val('discount')) || 0,
            male: Number(val('male')) || 0,
            female: Number(val('female')) || 0,
            child: Number(val('child')) || 0,
        };
    }

    function previewRoom() {
        const computed = computeRoom(currentRow());
        const preview = $('[data-room-preview]');
        if (preview) preview.value = money(computed.net_amount);
    }

    entry.addEventListener('input', previewRoom);
    entry.addEventListener('change', previewRoom);

    ['arrival_date', 'checkout_date'].forEach(function (key) {
        get(key).addEventListener('change', function () {
            // Keep checkout at least one night after arrival. Same-day is not
            // a stay: a row that starts and ends on one date holds no room, so
            // the same room could be sold twice over that day.
            if (new Date(val('checkout_date')) <= new Date(val('arrival_date'))) {
                const next = new Date(val('arrival_date'));
                next.setDate(next.getDate() + 1);
                get('checkout_date').value = next.toISOString().slice(0, 10);
            }

            refreshDays();
            refreshAvailability();
            refreshRate();
            previewRoom();
        });
    });

    get('room_category_id').addEventListener('change', function () {
        fillRoomTypes();
        refreshAvailability();
        previewRoom();
    });

    get('room_type_id').addEventListener('change', function () {
        // Start from the room type's own rent, then let the rate plan correct
        // it. The base rent is filled in first on purpose: if the lookup is
        // slow or fails, the field is already holding a sane number rather
        // than the last room type's price.
        const option = get('room_type_id').selectedOptions[0];
        if (option && option.dataset.rent) get('room_rent').value = option.dataset.rent;

        refreshAvailability();
        refreshRate();
        showTypeFree();
        previewRoom();
    });

    /*
     * What the rate plan says this stay should cost.
     *
     * The rent box is the clerk's to overrule — a walk-in talked down to four
     * thousand is a real thing that happens — so this fills it in and then
     * gets out of the way. It only overwrites a figure the clerk has not
     * touched: `rateFilled` remembers what was last put there automatically,
     * and anything else in the box is somebody's decision.
     */
    let rateTimer = null;
    let rateFilled = null;

    function refreshRate() {
        if (!boot.urls.rate) return;

        clearTimeout(rateTimer);

        rateTimer = setTimeout(function () {
            const typeId = val('room_type_id');
            const from = val('arrival_date');
            const to = val('checkout_date');

            if (!typeId || !from || !to) return;

            /*
             * The company and the market are guest-level fields, not part of
             * the room panel, so they are read from the page rather than
             * through get() — which only ever looks inside that panel. They
             * are what makes a negotiated rate come back instead of the rack
             * rate, so reading them from the wrong place would quietly sell
             * every corporate booking at full price.
             */
            const field = (name) => {
                const el = document.querySelector('[name="' + name + '"]');

                return el ? el.value : '';
            };

            const params = new URLSearchParams({
                room_type_id: typeId,
                from: from,
                to: to,
                company_id: field('company_id'),
                market_id: field('business_market_id'),
            });

            fetch(boot.urls.rate + '?' + params.toString(), {
                headers: { Accept: 'application/json' },
            })
                .then((r) => (r.ok ? r.json() : null))
                .then(function (data) {
                    if (!data) return;

                    const box = get('room_rent');
                    const typed = Number(box.value) || 0;
                    const mine = rateFilled !== null && Math.abs(typed - rateFilled) < 0.005;
                    const untouched = typed === 0 || mine || Number(option_rent()) === typed;

                    if (untouched) {
                        box.value = data.amount.toFixed(2);
                        rateFilled = data.amount;
                        previewRoom();
                    }

                    showRateNote(data, untouched);
                })
                .catch(function () {
                    /* offline — the box keeps the base rent */
                });
        }, 300);
    }

    /** The room type's own base rent, as the dropdown carries it. */
    function option_rent() {
        const option = get('room_type_id').selectedOptions[0];

        return option && option.dataset.rent ? Number(option.dataset.rent) : NaN;
    }

    function showRateNote(data, applied) {
        const note = $('[data-rate-note]');
        if (!note) return;

        const parts = [];

        if (data.source === 'rule') {
            parts.push((data.plan || 'Rate plan') + ' — ' + money(data.amount) + ' a night');
        } else if (data.source === 'mixed') {
            parts.push((data.plan || 'Rate plan') + ' — ' + money(data.amount) + ' a night on average');
        } else {
            parts.push('No rate loaded — using the base rent');
        }

        if (data.nights > 1) parts.push(data.nights + ' nights, ' + money(data.total) + ' in total');
        if (!applied) parts.push('your figure kept');

        note.textContent = parts.join(' · ');
        note.classList.remove('nv-hidden');
        note.classList.toggle('is-warn', !data.sellable || data.warnings.length > 0);

        if (data.warnings.length) note.textContent += ' · ' + data.warnings[0];
    }

    /*
     * A row books a NUMBER of rooms of a type. Which rooms they are is decided
     * when the guests arrive, so nothing here is ever locked to one — a family
     * of five is one row saying five.
     */
    get('no_of_rooms').addEventListener('input', showTypeFree);

    $('[data-add-room]').addEventListener('click', function () {
        const row = currentRow();

        if (!row.room_type_id) {
            alertInline('Pick a room type first.');
            return;
        }

        if (!row.arrival_date || !row.checkout_date) {
            alertInline('Both dates are needed.');
            return;
        }

        rooms.push(computeRoom(row));
        renderRooms();
        refreshAvailability();

        /*
         * The next line starts fresh. Without this, a clerk who overrode the
         * rate on one row would have that override treated as "the number the
         * rate plan put there" on the next, and the plan's own price would
         * stop filling in.
         */
        rateFilled = null;
    });

    /*
     * A negotiated rate depends on who the booking is for, so naming the
     * company after picking the room has to re-price it.
     */
    ['company_id', 'business_market_id'].forEach(function (name) {
        const el = document.querySelector('[name="' + name + '"]');

        if (el) el.addEventListener('change', refreshRate);
    });

    /* ── Services ───────────────────────────────────────────────────────── */

    const sEntry = $('[data-service-entry]');
    const sGet = (key) => $('[data-s="' + key + '"]', sEntry);
    const sVal = (key) => (sGet(key) ? sGet(key).value : '');

    function currentService() {
        const select = sGet('service_id');
        const option = select.selectedOptions[0];
        const isCustom = select.value === 'custom' || !select.value;

        return {
            service_id: isCustom ? null : select.value,
            service_name: isCustom ? sVal('service_name') : (option ? option.textContent.trim() : ''),
            tax_type: sVal('tax_type'),
            tax_choice: sVal('tax_choice') || 'none',
            qty: Number(sVal('qty')) || 0,
            price: Number(sVal('price')) || 0,
            remark: sVal('remark'),
        };
    }

    function previewService() {
        const computed = computeService(currentService());
        const preview = $('[data-service-preview]');
        if (preview) preview.value = money(computed.total_amount);
    }

    sEntry.addEventListener('input', previewService);
    sEntry.addEventListener('change', previewService);

    sGet('service_id').addEventListener('change', function () {
        const option = sGet('service_id').selectedOptions[0];

        if (option && option.dataset.price !== undefined) {
            sGet('price').value = option.dataset.price;

            // Offered, not imposed — the clerk can put it straight back to No Tax.
            const choice = sGet('tax_choice');
            const wanted = option.dataset.taxChoice || 'none';

            if (choice) {
                const exists = Array.prototype.some.call(choice.options, function (o) {
                    return o.value === wanted;
                });

                choice.value = exists ? wanted : 'none';
            }
        }

        sGet('service_name').disabled = sGet('service_id').value !== 'custom';
        previewService();
    });

    $('[data-add-service]').addEventListener('click', function () {
        const row = currentService();

        if (!row.service_name) {
            alertInline('Pick a service, or choose “Other” and type a name.');
            return;
        }

        services.push(computeService(row));
        renderServices();

        sGet('qty').value = 1;
        sGet('price').value = 0;
        sGet('remark').value = '';
        previewService();
    });

    /* ── Tabs ───────────────────────────────────────────────────────────── */

    $$('[data-tab]').forEach(function (tab) {
        tab.addEventListener('click', function () {
            $$('[data-tab]').forEach((t) => t.classList.toggle('is-active', t === tab));
            $$('[data-tab-panel]').forEach(function (panel) {
                panel.hidden = panel.dataset.tabPanel !== tab.dataset.tab;
            });
        });
    });

    /* ── Customer search ────────────────────────────────────────────────── */

    const searchBox = $('[data-guest-search]');
    const results = $('[data-guest-results]');
    const query = $('[data-guest-query]');
    let searchTimer = null;

    function openSearch() {
        searchBox.classList.add('is-open');
        query.value = '';
        query.focus();
    }

    function closeSearch() {
        searchBox.classList.remove('is-open');
    }

    $$('[data-open-guest-search]').forEach((b) => b.addEventListener('click', openSearch));
    $$('[data-close-guest-search]').forEach((b) => b.addEventListener('click', closeSearch));

    searchBox.addEventListener('click', function (event) {
        if (event.target === searchBox) closeSearch();
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && searchBox.classList.contains('is-open')) closeSearch();
    });

    query.addEventListener('input', function () {
        clearTimeout(searchTimer);

        const term = query.value.trim();

        if (term.length < 2) {
            results.innerHTML = '<p class="nv-muted" style="font-size:13px;padding:14px">Type at least two characters.</p>';
            return;
        }

        searchTimer = setTimeout(function () {
            fetch(boot.urls.guests + '?q=' + encodeURIComponent(term), {
                headers: { Accept: 'application/json' },
            })
                .then((r) => r.json())
                .then(function (guests) {
                    if (!guests.length) {
                        results.innerHTML = '<p class="nv-muted" style="font-size:13px;padding:14px">No guest found. Just type the details in — they get saved for next time.</p>';
                        return;
                    }

                    results.innerHTML = '';

                    guests.forEach(function (guest) {
                        const item = document.createElement('button');
                        item.type = 'button';
                        item.className = 'nv-guest-result';
                        item.innerHTML =
                            '<span class="nv-guest-name">' + guest.name + '</span>' +
                            '<span class="nv-guest-meta">' + (guest.mobile || '—') +
                            (guest.email ? ' · ' + guest.email : '') + '</span>';

                        item.addEventListener('click', function () {
                            applyGuest(guest);
                            closeSearch();
                        });

                        results.appendChild(item);
                    });
                })
                .catch(function () {
                    results.innerHTML = '<p class="nv-muted" style="font-size:13px;padding:14px">Could not reach the server.</p>';
                });
        }, 250);
    });

    /** Drop a chosen guest into the Personal Details fields. */
    function applyGuest(guest) {
        const set = (name, value) => {
            const el = form.querySelector('[name="' + name + '"]');
            if (el && value !== null && value !== undefined) el.value = value;
        };

        $('[data-guest-id]').value = guest.id;

        ['title', 'first_name', 'last_name', 'email', 'email2', 'mobile', 'mobile2',
            'address', 'dob', 'zip_code', 'company_id'].forEach((f) => set(f, guest[f]));

        if (guest.gender) {
            const radio = form.querySelector('[name="gender"][value="' + guest.gender + '"]');
            if (radio) radio.checked = true;
        }

        // Country → state → city have to load in order.
        const country = form.querySelector('[name="country_id"]');

        if (guest.country_id && country) {
            country.value = guest.country_id;
            country.dispatchEvent(new Event('change', { bubbles: true }));

            setTimeout(function () {
                const state = form.querySelector('[name="state_id"]');

                if (guest.state_id && state) {
                    state.value = guest.state_id;
                    state.dispatchEvent(new Event('change', { bubbles: true }));

                    setTimeout(function () {
                        set('city_id', guest.city_id);
                    }, 400);
                }
            }, 400);
        }
    }

    /* ── Returning-guest suggestions while typing ─────────────────────────
     *
     * Customer Search above is a lookup somebody has to open; this is the
     * same lookup offered without being asked for it — type into First Name
     * or Mobile No and, if it matches somebody already in the guest book, a
     * short list drops below that field. Picking one runs through the same
     * applyGuest() as Customer Search, so a returning guest is filled in and
     * booked exactly the same way whichever route found them.
     */

    $$('[data-typeahead]').forEach(function (wrap) {
        const input = wrap.querySelector('input');
        const box = wrap.querySelector('[data-typeahead-results]');

        if (!input || !box) return;

        let matches = [];
        let active = -1;
        let timer = null;

        function hide() {
            box.hidden = true;
            box.innerHTML = '';
            matches = [];
            active = -1;
        }

        function highlight() {
            $$('.nv-guest-result', box).forEach((el, i) => el.classList.toggle('is-active', i === active));
        }

        function choose(guest) {
            applyGuest(guest);
            hide();
        }

        function render(guests) {
            matches = Array.isArray(guests) ? guests : [];
            active = -1;

            if (!matches.length) {
                hide();
                return;
            }

            box.innerHTML = '';

            matches.forEach(function (guest) {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'nv-guest-result';
                item.innerHTML =
                    '<span class="nv-guest-name">' + guest.name + '</span>' +
                    '<span class="nv-guest-meta">Returning guest · ' + (guest.mobile || '—') +
                    (guest.email ? ' · ' + guest.email : '') + '</span>';

                // mousedown with preventDefault, not click — a click would
                // lose the race to the field's own blur, which closes this
                // list (and erases it) before the click ever lands.
                item.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                    choose(guest);
                });

                box.appendChild(item);
            });

            box.hidden = false;
        }

        input.addEventListener('input', function () {
            clearTimeout(timer);

            const term = input.value.trim();

            if (term.length < 2) {
                hide();
                return;
            }

            timer = setTimeout(function () {
                fetch(boot.urls.guests + '?q=' + encodeURIComponent(term), {
                    headers: { Accept: 'application/json' },
                })
                    .then((r) => r.json())
                    .then(render)
                    .catch(hide);
            }, 250);
        });

        input.addEventListener('keydown', function (event) {
            if (box.hidden || !matches.length) return;

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                active = (active + 1) % matches.length;
                highlight();
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                active = (active - 1 + matches.length) % matches.length;
                highlight();
            } else if (event.key === 'Enter' && active > -1) {
                event.preventDefault();
                choose(matches[active]);
            } else if (event.key === 'Escape') {
                hide();
            }
        });

        input.addEventListener('blur', hide);
    });

    /* ── Small helpers ──────────────────────────────────────────────────── */

    function alertInline(message) {
        let box = $('[data-inline-alert]');

        if (!box) {
            box = document.createElement('div');
            box.className = 'nv-alert nv-alert-warning';
            box.setAttribute('data-inline-alert', '');
            box.style.margin = '0 0 14px';
            entry.parentElement.insertBefore(box, entry);
        }

        box.textContent = message;
        box.scrollIntoView({ block: 'nearest', behavior: 'smooth' });

        clearTimeout(box._timer);
        box._timer = setTimeout(() => box.remove(), 4000);
    }

    /** Stop a save with an empty grid before it reaches the server. */
    form.addEventListener('submit', function (event) {
        if (!rooms.length) {
            event.preventDefault();

            const stayTab = $('[data-tab="stay"]');
            if (stayTab) stayTab.click();

            alertInline('Add at least one room before saving.');
        }
    });

    /* ── Go ─────────────────────────────────────────────────────────────── */

    fillRoomTypes();

    // A room type sent by the tape chart, chosen before availability loads.
    if (boot.prefill && boot.prefill.room_type_id) {
        const wanted = get('room_type_id');

        if (wanted.querySelector('option[value="' + boot.prefill.room_type_id + '"]')) {
            wanted.value = boot.prefill.room_type_id;
            const option = wanted.selectedOptions[0];
            if (option && option.dataset.rent) get('room_rent').value = option.dataset.rent;
        }
    }

    refreshDays();
    refreshAvailability();
    previewRoom();
    previewService();
    sGet('service_name').disabled = true;
    renderRooms();
    renderServices();
})();
