/*
 * The guest's own two pages — the menu they reach by scanning a table's QR,
 * and the status page they land on after sending an order.
 *
 * No login, no session, so no framework and no build-time data: everything
 * this file needs is either already sitting in the page's markup (the menu's
 * items, each carrying its own name/category/price as data attributes) or
 * handed over explicitly as a global (window.guestOrderPoll, the label map
 * for a kitchen status). Nothing here is load-bearing on the server — the
 * form posts and the page still works with this file missing, only without
 * the search box, the running total, or the live status badge.
 *
 * Both halves guard on the element they need and simply do nothing on the
 * other page, so one file can be shared by both without an if/else on the
 * route.
 */
(function () {
    'use strict';

    const $ = (sel, root) => (root || document).querySelector(sel);
    const $$ = (sel, root) => Array.from((root || document).querySelectorAll(sel));

    const money = (n) =>
        '₹ ' +
        (Math.round((Number(n) || 0) * 100) / 100).toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });

    const clampQty = (n) => Math.max(0, Math.min(99, Number(n) || 0));

    /* ── The menu ─────────────────────────────────────────────────────────── */

    (function menuPage() {
        const list = $('#om-list');
        if (!list) return;

        const search = $('#om-search');
        const tabs = $('#om-tabs');
        const noMatch = $('#om-no-match');
        const rows = $$('.nv-om-item', list);

        const cartCount = $('#om-cart-count');
        const cartTotal = $('#om-cart-total');
        const cartEmpty = $('#om-cart-empty');
        const cartFilled = $('#om-cart-filled');
        const sendBtn = $('#om-send');

        let activeCategory = '';

        /* A row shows only when it matches BOTH the open tab and whatever has
         * been typed so far — an item with no category of its own only ever
         * shows up under "All", the same as a menu that genuinely groups it
         * nowhere. */
        function applyFilter() {
            const term = ((search && search.value) || '').trim().toLowerCase();
            let visible = false;

            rows.forEach(function (row) {
                const show =
                    (!term || row.dataset.name.indexOf(term) !== -1) &&
                    (!activeCategory || row.dataset.cat === activeCategory);

                row.hidden = !show;
                if (show) visible = true;
            });

            if (noMatch) noMatch.hidden = visible;
        }

        function updateCart() {
            let count = 0;
            let total = 0;

            rows.forEach(function (row) {
                const input = $('.nv-om-qty-input', row);
                const qty = input ? clampQty(input.value) : 0;

                row.classList.toggle('is-in-cart', qty > 0);
                count += qty;
                total += qty * (Number(row.dataset.price) || 0);
            });

            if (cartCount) cartCount.textContent = String(count);
            if (cartTotal) cartTotal.textContent = money(total);
            if (cartEmpty) cartEmpty.hidden = count > 0;
            if (cartFilled) cartFilled.hidden = count === 0;
            if (sendBtn) sendBtn.disabled = count === 0;
        }

        if (search) search.addEventListener('input', applyFilter);

        if (tabs) {
            tabs.addEventListener('click', function (event) {
                const btn = event.target.closest('.nv-om-tab');
                if (!btn) return;

                $$('.nv-om-tab', tabs).forEach((t) => t.classList.remove('is-active'));
                btn.classList.add('is-active');
                activeCategory = btn.dataset.cat || '';
                applyFilter();
            });
        }

        // The + / − buttons and typing a number both land here, so a stepper
        // click and a pasted digit are clamped and totalled the same way.
        list.addEventListener('click', function (event) {
            const step = event.target.closest('.nv-om-step');
            if (!step) return;

            const input = $('.nv-om-qty-input', step.closest('.nv-om-item'));
            if (!input) return;

            input.value = clampQty(Number(input.value || 0) + Number(step.dataset.step || 0));
            updateCart();
        });

        list.addEventListener('input', function (event) {
            if (!event.target.matches('.nv-om-qty-input')) return;

            const clamped = clampQty(event.target.value);
            if (String(clamped) !== event.target.value) event.target.value = clamped;

            updateCart();
        });

        applyFilter();
        updateCart();
    })();

    /* ── The status page ─────────────────────────────────────────────────── */

    (function statusPage() {
        const banner = $('#os-banner');
        if (!banner) return;

        const label = $('#os-label');
        const rows = $$('#os-items [data-item]');
        const labels = window.kitchenStatusLabels || {};

        function applyState(state) {
            banner.dataset.status = state.status;
            if (label) label.textContent = state.label;

            (state.items || []).forEach(function (item, index) {
                const row = rows[index];
                const badge = row && $('[data-kstatus]', row);
                if (!badge) return;

                badge.textContent = item.kitchen_status ? labels[item.kitchen_status] || item.kitchen_status : '—';
            });
        }

        const pollUrl = window.guestOrderPoll;
        if (!pollUrl) return;

        // A guest may well leave this tab open on the table the whole meal —
        // one request in flight at a time, and a network hiccup just waits
        // for the next tick rather than piling retries up.
        let busy = false;

        const timer = setInterval(function () {
            if (busy) return;
            busy = true;

            fetch(pollUrl, { headers: { Accept: 'application/json' } })
                .then((res) => (res.ok ? res.json() : null))
                .then(function (state) {
                    busy = false;
                    if (!state) return;

                    applyState(state);

                    const items = state.items || [];
                    const allServed = state.status === 'approved' && items.length > 0 &&
                        items.every((i) => i.kitchen_status === 'served');

                    // Nothing further can happen to a declined order, and once
                    // every line has been served the kitchen has no more
                    // status left to give — no point knocking every four
                    // seconds after that.
                    if (state.status === 'rejected' || allServed) clearInterval(timer);
                })
                .catch(function () {
                    busy = false;
                });
        }, 4000);
    })();
})();
