/*
|------------------------------------------------------------------------------
| Kitchen Display System
|------------------------------------------------------------------------------
| Two jobs, and the split between them is the whole design:
|
|   The clocks run locally, every second, from a server-stamped epoch on each
|   ticket. They do not need the network and they do not need the till's own
|   clock to be right — only the difference between two of its own readings.
|   A kitchen screen that has lost its connection goes on telling the chef that
|   table 7 has been waiting nineteen minutes, which is the one thing it must
|   never stop doing.
|
|   The list of tickets is re-fetched on a timer and replaced wholesale. A diff
|   would be faster and would eventually disagree with the database; a handful
|   of tickets is small enough that replacing them costs nothing. A running
|   clock survives the replace because the new card is stamped with the same
|   fired-at epoch, so it simply carries on counting.
|
| Without this file the board still renders, still shows every ticket, and the
| Start / Ready buttons still work — it just stops refreshing itself, and the
| clocks show the time each ticket was fired instead of counting up.
*/

(function () {
    'use strict';

    var board = document.querySelector('[data-kds]');

    if (!board) return;

    var feed = board.getAttribute('data-kds-feed');
    var every = parseInt(board.getAttribute('data-kds-refresh'), 10) || 20;
    var warnAt = parseInt(board.getAttribute('data-kds-warn'), 10) || 600;
    var lateAt = parseInt(board.getAttribute('data-kds-late'), 10) || 1200;
    var advance = board.getAttribute('data-kds-advance');
    var token = board.getAttribute('data-kds-token');
    var canEdit = board.getAttribute('data-kds-can-edit') === '1';

    var ORDER = ['pending', 'preparing', 'ready'];
    var NEXT = { pending: 'preparing', preparing: 'ready', ready: 'served' };
    var WORDS = { pending: 'Start cooking', preparing: 'Mark ready', ready: 'Served' };

    var all = function (sel, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(sel));
    };

    /* ────────────────────────────────────────────────────── the clocks ── */

    /** "00:42" / "19:05" / "1:04:20" — minutes and seconds, because both matter here. */
    function spell(seconds) {
        var pad = function (n) { return (n < 10 ? '0' : '') + n; };

        if (seconds >= 3600) {
            return Math.floor(seconds / 3600) + ':' + pad(Math.floor((seconds % 3600) / 60)) + ':' + pad(seconds % 60);
        }

        return pad(Math.floor(seconds / 60)) + ':' + pad(seconds % 60);
    }

    function tick() {
        var now = Math.floor(Date.now() / 1000);

        all('[data-kot]', board).forEach(function (card) {
            var fired = parseInt(card.getAttribute('data-fired'), 10);
            var value = card.querySelector('[data-kot-clock-value]');

            if (!fired || !value) return;

            // Never negative: a till whose clock is ahead of the server's would
            // otherwise show a ticket fired in the future.
            var waited = Math.max(0, now - fired);

            value.textContent = spell(waited);

            card.classList.toggle('is-warn', waited >= warnAt && waited < lateAt);
            card.classList.toggle('is-late', waited >= lateAt);
        });
    }

    tick();
    setInterval(tick, 1000);

    /* ──────────────────────────────────────────────── drawing a ticket ── */

    function el(tag, className, text) {
        var node = document.createElement(tag);

        if (className) node.className = className;
        if (text !== undefined && text !== null) node.textContent = text;

        return node;
    }

    /**
     * Build one ticket card.
     *
     * This is the same shape as resources/views/pos/kitchen/partials/ticket.blade.php.
     * Change one, change the other.
     */
    function render(ticket) {
        var card = el('article', 'nv-kot');
        card.setAttribute('data-kot', '');
        card.setAttribute('data-fired', ticket.fired_at || '');
        card.setAttribute('data-key', ticket.key);

        var head = el('header', 'nv-kot-head');
        var who = el('div');
        who.appendChild(el('strong', 'nv-kot-where', ticket.where));
        who.appendChild(el('span', 'nv-kot-no', 'KOT ' + ticket.kot_no + ' · ' + ticket.order_no));
        head.appendChild(who);

        var clock = el('span', 'nv-kot-clock');
        clock.setAttribute('data-kot-clock', '');
        var value = el('b', null, '—');
        value.setAttribute('data-kot-clock-value', '');
        clock.appendChild(value);
        head.appendChild(clock);
        card.appendChild(head);

        var meta = el('div', 'nv-kot-meta');
        [ticket.type, ticket.outlet, ticket.steward, ticket.pax + ' pax'].forEach(function (bit) {
            if (bit) meta.appendChild(el('span', null, bit));
        });
        card.appendChild(meta);

        var list = el('ul', 'nv-kot-lines');

        (ticket.lines || []).forEach(function (line) {
            var item = el('li');
            item.appendChild(el('b', null, line.qty));

            var name = el('span', null, line.name);
            if (line.remark) name.appendChild(el('i', null, line.remark));
            if (line.nc) name.appendChild(el('i', null, 'No charge'));

            item.appendChild(name);
            list.appendChild(item);
        });

        card.appendChild(list);

        if (canEdit && advance) {
            var form = el('form', 'nv-kot-act');
            form.method = 'POST';
            form.action = advance;

            [['_token', token], ['order_id', ticket.order_id], ['kot_no', ticket.kot_no],
                ['to', NEXT[ticket.status] || 'served']].forEach(function (pair) {
                var input = el('input');
                input.type = 'hidden';
                input.name = pair[0];
                input.value = pair[1];
                form.appendChild(input);
            });

            var button = el('button', 'nv-btn nv-btn-primary nv-btn-sm', WORDS[ticket.status] || 'Served');
            button.type = 'submit';
            form.appendChild(button);

            card.appendChild(form);
        }

        return card;
    }

    /* ─────────────────────────────────────────────────── the refresh ── */

    var pulse = document.querySelector('[data-kds-pulse]');
    var pulseText = document.querySelector('[data-kds-pulse-text]');

    function say(state, words) {
        if (!pulse) return;

        pulse.classList.toggle('is-stale', state === 'stale');
        if (pulseText) pulseText.textContent = words;
    }

    function paint(tickets) {
        ORDER.forEach(function (status) {
            var column = board.querySelector('[data-kds-col="' + status + '"]');

            if (!column) return;

            var list = column.querySelector('[data-kds-list]');
            var count = column.querySelector('[data-kds-count]');
            var mine = tickets.filter(function (t) { return t.status === status; });

            if (count) count.textContent = mine.length;

            list.textContent = '';

            if (!mine.length) {
                list.appendChild(el('p', 'nv-kds-empty', 'Nothing here.'));

                return;
            }

            mine.forEach(function (ticket) { list.appendChild(render(ticket)); });
        });

        tick();
    }

    function refresh() {
        // Nothing to gain from asking while the screen is in a background tab.
        if (document.hidden) return;

        fetch(feed, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then(function (response) {
                if (!response.ok) throw new Error(response.status);

                return response.json();
            })
            .then(function (data) {
                paint(data.tickets || []);
                say('live', 'Live');
            })
            .catch(function () {
                // Say so rather than showing a board that has quietly stopped
                // moving. The clocks carry on either way.
                say('stale', 'Not updating — clocks still running');
            });
    }

    setInterval(refresh, Math.max(5, every) * 1000);

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) refresh();
    });

    /* The outlet and department pickers, without a Show button. */
    all('[data-autosubmit]').forEach(function (select) {
        select.addEventListener('change', function () {
            if (select.form) select.form.submit();
        });
    });
}());
