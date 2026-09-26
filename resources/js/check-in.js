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

/*
|------------------------------------------------------------------------------
| Check in Guest — the ID Proof photo
|------------------------------------------------------------------------------
| Independent of the allotment popup above — its own guard, its own scope —
| so nothing here depends on window.__checkIn existing.
|
| One hidden <input type="file" name="id_photo"> is what actually travels
| with the form, however the picture got there. Choosing a file — which on a
| phone is the native camera the `capture` attribute opens — fills it the
| ordinary way. The live desktop camera below fills the same input by
| building a File from a captured frame and handing it over through a
| DataTransfer, the only way JS is allowed to set what a file input holds.
| Either way, store() on the server sees one ordinary uploaded file and never
| needs to know which path it came from.
*/

(function () {
    'use strict';

    const widget = document.querySelector('[data-id-photo]');
    if (!widget) return;

    const $ = (sel) => widget.querySelector(sel);

    const input = $('[data-id-photo-input]');
    const img = $('[data-id-photo-img]');
    const empty = $('[data-id-photo-empty]');
    const video = $('[data-id-photo-video]');
    const status = $('[data-id-photo-status]');
    const cameraBtn = $('[data-id-photo-camera-btn]');
    const captureBtn = $('[data-id-photo-capture-btn]');
    const cancelBtn = $('[data-id-photo-cancel-btn]');
    const chooseBtn = $('[data-id-photo-choose-btn]');
    const removeBtn = $('[data-id-photo-remove-btn]');

    if (!input || !img || !empty || !video || !cameraBtn || !captureBtn || !cancelBtn || !chooseBtn || !removeBtn) {
        return;
    }

    const defaultStatus = status ? status.textContent.trim() : '';
    let stream = null;
    let objectUrl = null;

    function setStatus(text) {
        if (status) status.textContent = text || defaultStatus;
    }

    /* ── Preview ─────────────────────────────────────────────────────────── */

    function showImage(file) {
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        objectUrl = URL.createObjectURL(file);

        img.src = objectUrl;
        img.hidden = false;
        empty.hidden = true;
        video.hidden = true;
        removeBtn.hidden = false;
    }

    function showEmpty() {
        if (objectUrl) {
            URL.revokeObjectURL(objectUrl);
            objectUrl = null;
        }

        img.hidden = true;
        img.removeAttribute('src');
        video.hidden = true;
        empty.hidden = false;
        removeBtn.hidden = true;
    }

    /* ── Choose File (native camera on a phone, a file picker on a desktop) ── */

    input.addEventListener('change', function () {
        const file = input.files && input.files[0];

        if (file) {
            showImage(file);
            setStatus('Photo added — Save the check-in to keep it.');
        }
    });

    removeBtn.addEventListener('click', function () {
        input.value = '';
        showEmpty();
        setStatus(defaultStatus);
    });

    /* ── Live desktop camera ─────────────────────────────────────────────
       Offered only where it can actually work: getUserMedia needs a secure
       context (HTTPS, or localhost), which a front desk reached over plain
       HTTP on the LAN is not. Choose File above is what a phone already uses
       for its native camera, so nothing real is lost by hiding this button
       rather than showing one that would just fail when pressed. */

    const canUseCamera = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
    if (canUseCamera) cameraBtn.hidden = false;

    function stopStream() {
        if (stream) {
            stream.getTracks().forEach((track) => track.stop());
            stream = null;
        }
        video.srcObject = null;
    }

    function setCameraMode(on) {
        cameraBtn.hidden = on || !canUseCamera;
        chooseBtn.hidden = on;
        captureBtn.hidden = !on;
        cancelBtn.hidden = !on;
    }

    cameraBtn.addEventListener('click', function () {
        if (cameraBtn.disabled) return;
        cameraBtn.disabled = true;

        navigator.mediaDevices
            .getUserMedia({ video: { facingMode: 'environment' }, audio: false })
            .then(function (s) {
                cameraBtn.disabled = false;
                stream = s;
                video.srcObject = stream;
                video.hidden = false;
                img.hidden = true;
                empty.hidden = true;
                setCameraMode(true);
                setStatus('Line the card up in the frame, then press Capture.');
            })
            .catch(function () {
                // A desktop with no camera, or the guest said no — Choose
                // File keeps working either way, so this fails quietly
                // rather than with a dialog, and does not offer the button
                // again once it is known not to work here.
                cameraBtn.disabled = false;
                cameraBtn.hidden = true;
                setStatus('Camera not available here — use Choose File instead.');
            });
    });

    captureBtn.addEventListener('click', function () {
        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth || 1280;
        canvas.height = video.videoHeight || 960;
        canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);

        canvas.toBlob(
            function (blob) {
                if (!blob) {
                    setStatus('Could not capture that — try again or use Choose File.');
                    return;
                }

                const file = new File([blob], 'id-proof.jpg', { type: 'image/jpeg' });

                // The only way to hand a JS-built file to a real <input
                // type="file">, so the one input still carries the photo
                // regardless of which path put it there.
                const transfer = new DataTransfer();
                transfer.items.add(file);
                input.files = transfer.files;

                stopStream();
                setCameraMode(false);
                showImage(file);
                setStatus('Photo captured — Save the check-in to keep it.');
            },
            'image/jpeg',
            0.9
        );
    });

    cancelBtn.addEventListener('click', function () {
        stopStream();
        setCameraMode(false);
        video.hidden = true;

        // Cancelling out of the camera returns to whatever was there before
        // — a file already chosen still sits in the input untouched, so it
        // is the preview that is restored, not the file itself.
        if (input.files && input.files[0]) {
            img.hidden = false;
        } else {
            showEmpty();
        }

        setStatus(defaultStatus);
    });

    // Leaving the page mid-capture should not leave the camera light on.
    window.addEventListener('beforeunload', stopStream);
})();
