/*
|------------------------------------------------------------------------------
| Check in Guest — the Allot Room popup
|------------------------------------------------------------------------------
| The popup is a picker, not a saver. Ticking rooms here only records the
| choice on the page; nothing reaches the database until the form is saved,
| which is why a half-finished check-in can always be abandoned by leaving.
|
| The server re-checks every room on save, so this file may be optimistic:
| the worst a stale list can do is earn a "that room was taken" message.
*/

(function () {
    'use strict';

    const boot = window.__checkIn;
    const form = document.querySelector('[data-check-in]');

    if (!boot || !form) return;

    const $ = (sel, root) => (root || document).querySelector(sel);
    const $$ = (sel, root) => Array.prototype.slice.call((root || document).querySelectorAll(sel));

    const modal = $('[data-allot-modal]');
    const body = $('[data-allot-body]', modal);
    const inputs = $('[data-allot-inputs]', form);
    const summary = $('[data-allot-summary]', form);

    /* What has been picked so far: { rowId: [{id, room_no}, …] } */
    const picked = {};

    /* The row whose popup is open, and the ticks inside it before Save. */
    let openRow = null;
    let draft = [];
    let listed = [];

    const rowById = (id) => boot.rows.filter((r) => String(r.id) === String(id))[0] || null;

    /* Rooms already spoken for by a *different* row on this page. */
    function claimedElsewhere(rowId) {
        return Object.keys(picked)
            .filter((key) => String(key) !== String(rowId))
            .reduce((all, key) => all.concat(picked[key].map((r) => r.id)), []);
    }

    function totalPicked() {
        return Object.keys(picked).reduce((n, key) => n + picked[key].length, 0);
    }

    /* ── The page behind the popup ───────────────────────────────────────── */

    function renderPage() {
        boot.rows.forEach(function (row) {
            const tr = $('tr[data-row="' + row.id + '"]', form);
            if (!tr) return;

            const cell = $('[data-room-cell]', tr);
            const mine = picked[row.id] || [];

            if (!mine.length) {
                cell.innerHTML = '<span class="nv-muted">Not allotted</span>' +
                    (row.total > 1 ? '<span class="nv-sub">' + row.pending + ' rooms to allot</span>' : '');

                return;
            }

            cell.innerHTML = '<strong class="nv-mono">' +
                mine.map((r) => r.room_no).join(', ') + '</strong>' +
                (mine.length < row.pending
                    ? '<span class="nv-sub">' + (row.pending - mine.length) + ' still to allot</span>'
                    : '');
        });

        // Hidden fields are rebuilt wholesale — simpler than patching, and it
        // cannot leave a room behind after it is un-ticked.
        inputs.innerHTML = '';

        Object.keys(picked).forEach(function (rowId) {
            picked[rowId].forEach(function (room) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'rooms[' + rowId + '][]';
                input.value = room.id;
                inputs.appendChild(input);
            });
        });

        if (summary) {
            const n = totalPicked();
            summary.textContent = n + ' of ' + boot.pending + ' allotted';
            summary.classList.toggle('is-success', n > 0);
            summary.classList.toggle('is-warning', n === 0);
        }
    }

    /* ── The popup ───────────────────────────────────────────────────────── */

    function openModal(rowId) {
        openRow = rowById(rowId);
        if (!openRow) return;

        draft = (picked[rowId] || []).map((r) => r.id);
        listed = [];

        $('[data-allot-booking]', modal).textContent =
            openRow.category + ' · ' + openRow.type + ' — ' + openRow.total + ' room(s)';
        $('[data-allot-pax]', modal).innerHTML =
            '<span>Male : ' + openRow.male + '</span><span>Female : ' + openRow.female +
            '</span><span>Child : ' + openRow.child + '</span>';

        body.innerHTML = '<tr><td colspan="7" class="nv-allot-empty">Loading free rooms…</td></tr>';
        modal.classList.add('is-open');
        document.body.style.overflow = 'hidden';

        renderTotals();

        fetch(boot.roomsUrl + '?row=' + encodeURIComponent(rowId), {
            headers: { Accept: 'application/json' },
        })
            .then((r) => (r.ok ? r.json() : null))
            .then(function (data) {
                if (!data) throw new Error('no data');

                // Trust the server's pending count over the page's, in case
                // somebody else checked a guest in while this tab was open.
                openRow.pending = data.row.pending;
                listed = data.rooms.filter((room) => claimedElsewhere(rowId).indexOf(room.id) === -1);

                renderRooms();
                renderTotals();
            })
            .catch(function () {
                body.innerHTML =
                    '<tr><td colspan="7" class="nv-allot-empty">Could not load rooms. Close this and try again.</td></tr>';
            });
    }

    function renderRooms() {
        if (!listed.length) {
            body.innerHTML =
                '<tr><td colspan="7" class="nv-allot-empty">No room of this type is free for these dates.</td></tr>';

            return;
        }

        body.innerHTML = listed
            .map(function (room, i) {
                const on = draft.indexOf(room.id) !== -1;

                return '<tr class="' + (on ? 'is-picked' : '') + '" data-room="' + room.id + '">' +
                    '<td class="is-num nv-muted">' + (i + 1) + '</td>' +
                    '<td><strong class="nv-mono">' + room.room_no + '</strong></td>' +
                    '<td>' + (room.type || room.category || '—') +
                    // Saying so beats silently moving a guest to another type.
                    (room.as_booked ? '' : '<span class="nv-sub">not the booked type</span>') +
                    '</td>' +
                    '<td class="nv-muted">' + (room.housekeeping || '—').replace(/_/g, ' ') + '</td>' +
                    '<td class="nv-nowrap nv-muted">' + openRow.arrival + '</td>' +
                    '<td class="nv-nowrap nv-muted">' + openRow.checkout + '</td>' +
                    '<td class="is-end"><input type="checkbox" class="nv-check" data-pick="' + room.id + '"' +
                    (on ? ' checked' : '') + ' aria-label="Allot room ' + room.room_no + '" /></td>' +
                    '</tr>';
            })
            .join('');
    }

    function renderTotals() {
        const left = Math.max(0, openRow.pending - draft.length);

        $('[data-allot-picked]', modal).textContent =
            draft.length + ' of ' + openRow.pending + ' picked';
        $('[data-allot-balance]', modal).textContent =
            left === 0 ? 'None — this line is full' : left + ' room(s) still to pick';
        $('[data-allot-hint]', modal).textContent =
            left === 0
                ? 'Press Save to put these rooms on the check-in.'
                : 'Tick up to ' + openRow.pending + ' room(s), then Save.';
    }

    function closeModal() {
        modal.classList.remove('is-open');
        document.body.style.overflow = '';
        openRow = null;
    }

    /* ── Events ──────────────────────────────────────────────────────────── */

    $$('[data-allot]', form).forEach(function (button) {
        button.addEventListener('click', function () {
            openModal(button.getAttribute('data-allot'));
        });
    });

    body.addEventListener('change', function (event) {
        const box = event.target.closest('[data-pick]');
        if (!box || !openRow) return;

        const id = Number(box.getAttribute('data-pick'));

        if (box.checked) {
            // One more than the line owes would price a room nobody booked.
            if (draft.length >= openRow.pending) {
                box.checked = false;
                $('[data-allot-hint]', modal).textContent =
                    'This line is only for ' + openRow.pending + ' room(s). Un-tick one first.';

                return;
            }

            draft.push(id);
        } else {
            draft = draft.filter((x) => x !== id);
        }

        box.closest('tr').classList.toggle('is-picked', box.checked);
        renderTotals();
    });

    $$('[data-allot-save]', modal).forEach(function (button) {
        button.addEventListener('click', function () {
            if (!openRow) return;

            const rowId = openRow.id;

            if (!draft.length) {
                delete picked[rowId];
            } else {
                picked[rowId] = draft
                    .map((id) => listed.filter((r) => r.id === id)[0])
                    .filter(Boolean)
                    .map((r) => ({ id: r.id, room_no: r.room_no }));
            }

            closeModal();
            renderPage();
        });
    });

    $('[data-allot-close]', modal).addEventListener('click', closeModal);

    modal.addEventListener('click', function (event) {
        if (event.target === modal) closeModal();
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) closeModal();

        // F10 saves, the way the old system did.
        if (event.key === 'F10' && !modal.classList.contains('is-open')) {
            event.preventDefault();
            form.requestSubmit();
        }
    });

    // Saving with nothing allotted would create an empty check-in.
    form.addEventListener('submit', function (event) {
        if (totalPicked() === 0) {
            event.preventDefault();
            alert('Allot at least one room first — press Allot Room on a line.');
        }
    });

    renderPage();
})();
