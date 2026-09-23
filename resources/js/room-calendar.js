/*
|------------------------------------------------------------------------------
| Reservation Calendar New — the tape chart
|------------------------------------------------------------------------------
| Drag across free nights to book or block, click a bar to see what it is,
| collapse a category, and fill the "allot a room" dropdowns.
|
| No framework. Everything the server needs still goes through a normal form
| or a normal link, so the chart works even if this file never loads.
*/
(function () {
    'use strict';

    const tape = document.querySelector('[data-tape]');
    const boot = window.TAPE_BOOT || {};

    const $ = (sel, root) => (root || document).querySelector(sel);
    const $$ = (sel, root) => Array.from((root || document).querySelectorAll(sel));

    /* ── Collapse a room category ───────────────────────────────────────── */

    $$('[data-group-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            const group = button.dataset.groupToggle;
            const open = button.getAttribute('aria-expanded') !== 'false';

            button.setAttribute('aria-expanded', open ? 'false' : 'true');
            button.closest('tr').classList.toggle('is-collapsed', open);

            $$('[data-in-group="' + group + '"]').forEach(function (row) {
                row.hidden = open;
            });
        });
    });

    /* ── Fill each "allot a room" dropdown with rooms free for that stay ── */

    $$('[data-assign]').forEach(function (form) {
        const select = $('[data-assign-room]', form);

        const params = new URLSearchParams({
            from: form.dataset.from,
            to: form.dataset.to,
        });

        if (form.dataset.roomType) params.set('room_type_id', form.dataset.roomType);

        fetch(boot.freeRoomsUrl + '?' + params.toString(), { headers: { Accept: 'application/json' } })
            .then((r) => (r.ok ? r.json() : []))
            .then(function (rooms) {
                select.innerHTML = '';

                if (!rooms.length) {
                    // Nothing of that type — offer anything free instead.
                    params.delete('room_type_id');

                    return fetch(boot.freeRoomsUrl + '?' + params.toString(), { headers: { Accept: 'application/json' } })
                        .then((r) => (r.ok ? r.json() : []))
                        .then(function (any) {
                            fill(select, any, true);
                        });
                }

                fill(select, rooms, false);
            })
            .catch(function () {
                select.innerHTML = '<option value="">Could not load rooms</option>';
            });
    });

    function fill(select, rooms, otherType) {
        if (!rooms.length) {
            select.innerHTML = '<option value="">No room free</option>';
            select.disabled = true;

            return;
        }

        select.innerHTML = '<option value="">Choose a room…</option>';

        rooms.forEach(function (room) {
            const option = document.createElement('option');
            option.value = room.id;
            option.textContent = room.room_no + (room.type ? ' — ' + room.type : '');
            select.appendChild(option);
        });

        if (otherType) {
            select.options[0].textContent = 'None of that type — other rooms:';
        }
    }

    /* ── Bar details ────────────────────────────────────────────────────── */

    const barModal = $('[data-bar-modal]');

    if (barModal) {
        const body = $('[data-bar-body]');
        const title = $('[data-bar-title]');
        const openLink = $('[data-bar-open]');
        const unblock = $('[data-unblock]');

        const closeBar = () => barModal.classList.remove('is-open');

        $$('[data-bar]').forEach(function (bar) {
            bar.addEventListener('click', function (event) {
                event.stopPropagation();

                const d = bar.dataset;
                const isBlock = d.barKind === 'blocked';

                title.textContent = isBlock ? 'Blocked room' : d.barReservationNo || 'Booking';

                const rows = [
                    ['Room', d.barRoom],
                    [isBlock ? 'Reason' : 'Guest', d.barLabel || '—'],
                    ['From', d.barFrom],
                    ['To', d.barTo],
                    ['Nights', d.barNights],
                ];

                if (!isBlock && d.barMobile) rows.push(['Mobile', d.barMobile]);

                body.innerHTML = rows
                    .map(function (r) {
                        return '<div class="nv-detail-row"><span>' + r[0] + '</span><b>' + r[1] + '</b></div>';
                    })
                    .join('');

                if (openLink) {
                    openLink.hidden = isBlock || !d.barReservation;
                    if (!openLink.hidden) openLink.href = boot.showUrl.replace('__ID__', d.barReservation);
                }

                if (unblock) {
                    unblock.hidden = !isBlock || !d.barBlock;
                    if (!unblock.hidden) $('[data-unblock-id]', unblock).value = d.barBlock;
                }

                barModal.classList.add('is-open');
            });
        });

        $$('[data-bar-close]').forEach((b) => b.addEventListener('click', closeBar));

        barModal.addEventListener('click', function (e) {
            if (e.target === barModal) closeBar();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && barModal.classList.contains('is-open')) closeBar();
        });
    }

    /* ── Drag a booking onto other nights ───────────────────────────────── */

    /*
     * "Meri booking agli date pe kar do" — pick the bar up and drop it where
     * the guest now wants it. The stay keeps its length, so only the dates
     * (and possibly the room) change; nothing is re-priced.
     *
     * Nothing is saved by the drop itself: it opens a confirm box, and the
     * server checks the room is really free before writing. An accidental
     * drag can always be cancelled.
     */
    const moveModal = document.querySelector('[data-move-modal]');

    if (tape && moveModal && boot.canMove) {
        let held = null;    // the bar being dragged

        const dayMs = 86400000;
        const iso = (d) => new Date(d).toISOString().slice(0, 10);
        const shift = (date, by) => iso(new Date(date).getTime() + by * dayMs);

        const nice = (date) =>
            new Date(date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });

        /** Every night a bar covers: from .. to-1. */
        function nightsOf(from, to) {
            const out = [];

            for (let d = from; d < to; d = shift(d, 1)) out.push(d);

            return out;
        }

        /**
         * Which night the pointer is over.
         *
         * Read off the header cells rather than the row's own cells, because
         * a bar is one <td colspan="3"> — the nights underneath it have no
         * element of their own. Without this you could never slide a stay by
         * a single night, since the very next night is hidden under the bar
         * you are dragging.
         */
        function dateAtX(x) {
            const heads = $$('thead th[data-col-date]', tape);

            for (const head of heads) {
                const box = head.getBoundingClientRect();

                if (x >= box.left && x < box.right) return head.dataset.colDate;
            }

            return null;
        }

        /**
         * Can `held` start on `date` in this row?
         *
         * The bar's own nights count as free when it is staying in the same
         * room — otherwise nudging a stay one day right would clash with
         * itself and the drop would look broken.
         */
        function landingCells(row, date) {
            if (!row) return null;

            const free = new Set(
                $$('.nv-tape-cell.is-free', row).map((c) => c.dataset.date)
            );

            if (row.dataset.roomRow === held.roomId) {
                nightsOf(held.from, held.to).forEach((d) => free.add(d));
            }

            const wanted = [];

            for (let i = 0; i < held.nights; i++) {
                const d = shift(date, i);

                if (!free.has(d)) return null;

                wanted.push(d);
            }

            return wanted;
        }

        function clearPreview() {
            $$('.nv-tape-cell.is-landing, .nv-tape-cell.is-blocked-drop', tape)
                .forEach((c) => c.classList.remove('is-landing', 'is-blocked-drop'));
        }

        tape.addEventListener('dragstart', function (event) {
            const bar = event.target.closest('[data-bar]');

            if (!bar || bar.dataset.barMovable !== '1') {
                event.preventDefault();

                return;
            }

            held = {
                line: bar.dataset.barLine,
                from: bar.dataset.barFrom,
                to: bar.dataset.barTo,
                nights: Number(bar.dataset.barNights) || 1,
                guest: bar.dataset.barLabel,
                reservationNo: bar.dataset.barReservationNo,
                roomId: bar.closest('tr').dataset.roomRow,
                roomNo: bar.closest('tr').dataset.roomRowNo,
            };

            bar.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';
            // Firefox refuses to start a drag with an empty payload.
            event.dataTransfer.setData('text/plain', held.line);
        });

        tape.addEventListener('dragend', function () {
            $$('.nv-bar.is-dragging', tape).forEach((b) => b.classList.remove('is-dragging'));
            clearPreview();
            held = null;
        });

        /** The row and start date under the pointer, if it is over a room row. */
        function dropTarget(event) {
            const row = event.target.closest('tr[data-room-row]');
            if (!row) return null;

            const date = dateAtX(event.clientX);
            if (!date) return null;

            return { row: row, date: date, landing: landingCells(row, date) };
        }

        tape.addEventListener('dragover', function (event) {
            if (!held) return;

            const target = dropTarget(event);
            if (!target) return;

            event.preventDefault();
            clearPreview();

            if (!target.landing) {
                // Show the refusal rather than silently doing nothing.
                const cell = event.target.closest('.nv-tape-cell');
                if (cell) cell.classList.add('is-blocked-drop');
                event.dataTransfer.dropEffect = 'none';

                return;
            }

            event.dataTransfer.dropEffect = 'move';

            const free = $$('.nv-tape-cell.is-free', target.row);

            // Nights hidden under the bar being dragged have no cell to light
            // up; the confirm box spells the dates out either way.
            target.landing.forEach(function (date) {
                const cell = free.filter((c) => c.dataset.date === date)[0];

                if (cell) cell.classList.add('is-landing');
            });
        });

        tape.addEventListener('drop', function (event) {
            if (!held) return;

            const target = dropTarget(event);

            clearPreview();

            if (!target || !target.landing) return;

            event.preventDefault();

            const row = target.row;
            const landing = target.landing;
            const from = landing[0];
            const to = shift(landing[landing.length - 1], 1);
            const roomNo = row.dataset.roomRowNo;

            $('[data-move-guest]').textContent =
                held.guest + ' · ' + (held.reservationNo || '');
            $('[data-move-old]').textContent =
                nice(held.from) + ' → ' + nice(held.to) + ' · ' + held.roomNo;
            $('[data-move-new]').textContent =
                nice(from) + ' → ' + nice(to) + ' · ' + roomNo;
            $('[data-move-note]').textContent =
                held.nights + ' night(s) either way, so the rate and the tax do not change'
                + (roomNo === held.roomNo ? '.' : ' — and the room changes to ' + roomNo + '.');

            $('[data-move-line]').value = held.line;
            $('[data-move-room]').value = row.dataset.roomRow;
            $('[data-move-date]').value = from;

            moveModal.classList.add('is-open');
        });

        const closeMove = () => moveModal.classList.remove('is-open');

        $$('[data-move-cancel]').forEach((b) => b.addEventListener('click', closeMove));

        moveModal.addEventListener('click', function (event) {
            if (event.target === moveModal) closeMove();
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && moveModal.classList.contains('is-open')) closeMove();
        });
    }

    /* ── Drag across free nights ────────────────────────────────────────── */

    if (!tape || tape.dataset.canBook !== '1') return;

    const pop = $('[data-tape-pop]');
    const blockForm = $('[data-block-form]');

    let anchor = null;      // the cell the drag started on
    let selection = [];     // the cells currently highlighted
    let dragging = false;

    const cellsInRow = (row) => $$('.nv-tape-cell.is-free', row);

    /** Highlight every free cell between the anchor and `cell`, same room only. */
    function extendTo(cell) {
        if (!anchor || cell.dataset.room !== anchor.dataset.room) return;

        const row = anchor.closest('tr');
        const free = cellsInRow(row);
        const a = free.indexOf(anchor);
        const b = free.indexOf(cell);

        if (a === -1 || b === -1) return;

        const from = Math.min(a, b);
        const to = Math.max(a, b);

        // A booking must be one unbroken run — stop at the first gap.
        const run = [];

        for (let i = from; i <= to; i++) {
            const previous = run[run.length - 1];

            if (previous && !isNextDay(previous.dataset.date, free[i].dataset.date)) break;

            run.push(free[i]);
        }

        clearSelection();
        selection = run;
        selection.forEach((c) => c.classList.add('is-selected'));
    }

    function isNextDay(a, b) {
        return (new Date(b) - new Date(a)) === 86400000;
    }

    function clearSelection() {
        selection.forEach((c) => c.classList.remove('is-selected'));
        selection = [];
    }

    function hidePop() {
        pop.hidden = true;
        if (blockForm) blockForm.hidden = true;
        clearSelection();
        anchor = null;
    }

    function addDay(date) {
        const d = new Date(date);
        d.setDate(d.getDate() + 1);

        return d.toISOString().slice(0, 10);
    }

    const pretty = (date) =>
        new Date(date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });

    function showPop() {
        if (!selection.length) return hidePop();

        const first = selection[0];
        const last = selection[selection.length - 1];
        const from = first.dataset.date;
        const to = addDay(last.dataset.date);   // departure is the morning after

        $('[data-pop-from]').textContent = pretty(from);
        $('[data-pop-to]').textContent = pretty(to);
        $('[data-pop-room]').textContent = first.dataset.roomNo;
        $('[data-pop-nights]').textContent = selection.length;

        const book = $('[data-pop-book]');

        if (book) {
            book.href = boot.bookUrl + '?date=' + from + '&checkout=' + to + '&room=' + first.dataset.room;
        }

        if (blockForm) {
            blockForm.hidden = true;
            $('[data-block-room]').value = first.dataset.room;
            $('[data-block-from]').value = from;
            $('[data-block-to]').value = to;
        }

        // Sit the popover just under the selection, inside the viewport.
        const box = last.getBoundingClientRect();
        pop.hidden = false;

        const width = pop.offsetWidth;
        const left = Math.min(
            Math.max(8, box.left + window.scrollX - width / 2 + box.width / 2),
            window.scrollX + document.documentElement.clientWidth - width - 8
        );

        pop.style.left = left + 'px';
        pop.style.top = box.bottom + window.scrollY + 8 + 'px';
    }

    tape.addEventListener('mousedown', function (event) {
        const cell = event.target.closest('.nv-tape-cell.is-free');
        if (!cell) return;

        event.preventDefault();
        hidePop();

        dragging = true;
        anchor = cell;
        selection = [cell];
        cell.classList.add('is-selected');
    });

    tape.addEventListener('mouseover', function (event) {
        if (!dragging) return;

        const cell = event.target.closest('.nv-tape-cell.is-free');
        if (cell) extendTo(cell);
    });

    document.addEventListener('mouseup', function () {
        if (!dragging) return;

        dragging = false;
        showPop();
    });

    // Keyboard and touch: a plain click selects the single night.
    tape.addEventListener('click', function (event) {
        const cell = event.target.closest('.nv-tape-cell.is-free');

        if (cell && !selection.length) {
            anchor = cell;
            selection = [cell];
            cell.classList.add('is-selected');
            showPop();
        }
    });

    $$('[data-pop-close]').forEach((b) => b.addEventListener('click', hidePop));

    const blockButton = $('[data-pop-block]');

    if (blockButton && blockForm) {
        blockButton.addEventListener('click', function () {
            blockForm.hidden = false;
            $('input[name="reason"]', blockForm).focus();
        });

        $('[data-block-cancel]').addEventListener('click', function () {
            blockForm.hidden = true;
        });
    }

    document.addEventListener('mousedown', function (event) {
        if (pop.hidden) return;
        if (pop.contains(event.target) || event.target.closest('.nv-tape-cell.is-free')) return;

        hidePop();
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') hidePop();
    });
})();
