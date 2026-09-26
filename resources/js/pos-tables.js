/*
|------------------------------------------------------------------------------
| POS Setup — the three dialogs
|------------------------------------------------------------------------------
| Tables uses two (a group and the seats inside it) and Slots uses one. They are
| all the same job: a button carries the row's values as data attributes, this
| file copies them into the dialog's fields, and the dialog posts to the same
| endpoint whether it was opened empty or full.
|
| One dialog per shape rather than one per row: a floor plan with two hundred
| tables would otherwise ship two hundred copies of the same form.
|
| Nothing here is load-bearing on the server. Every field the dialog fills is
| validated again when it arrives, and the group and outlet a row is posted
| against are re-checked against the branch — so with this file missing the
| buttons simply do nothing, which is the safe way to fail.
*/

(function () {
    'use strict';

    var all = function (sel, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(sel));
    };

    /* ── Dialogs ────────────────────────────────────────────────────────── */

    function closeModal(modal) {
        modal.classList.remove('is-open');
        document.body.style.overflow = '';
    }

    function openModal(name) {
        var modal = document.querySelector('[data-modal="' + name + '"]');

        if (!modal) return null;

        modal.classList.add('is-open');
        document.body.style.overflow = 'hidden';

        return modal;
    }

    all('[data-modal]').forEach(function (modal) {
        all('[data-modal-close]', modal).forEach(function (button) {
            button.addEventListener('click', function () { closeModal(modal); });
        });

        modal.addEventListener('click', function (event) {
            if (event.target === modal) closeModal(modal);
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') all('[data-modal].is-open').forEach(closeModal);
    });

    /**
     * Put one value into whichever control carries this name inside the dialog.
     *
     * A checkbox is ticked or cleared; everything else takes the value as text.
     * An empty string is a real answer — it is how a dialog is emptied out for
     * a new row.
     */
    function fill(modal, field, value) {
        var control = modal.querySelector('[data-field="' + field + '"]');

        if (!control) return;

        if (control.type === 'checkbox') {
            control.checked = value === '1' || value === true;

            return;
        }

        control.value = value === null || value === undefined ? '' : value;
    }

    function focusFirst(modal) {
        var first = modal.querySelector('input:not([type=hidden]):not([readonly]), select');

        if (first) first.focus();
    }

    /**
     * Wire every button that opens one dialog.
     *
     * `map` says which data attribute lands in which field. A button with no
     * `data-id` is an Add button, so the title says so and every field it does
     * not carry is blanked — a dialog remembering the last row it showed is the
     * classic way to edit the wrong record.
     */
    function wire(selector, modalName, titleId, map, titleFor) {
        all(selector).forEach(function (button) {
            button.addEventListener('click', function () {
                var modal = openModal(modalName);

                if (!modal) return;

                var editing = !!button.getAttribute('data-id');

                Object.keys(map).forEach(function (attribute) {
                    fill(modal, map[attribute], button.getAttribute('data-' + attribute));
                });

                // A new row starts switched on; an existing one keeps whatever
                // it had, which the loop above has already set.
                if (!editing) fill(modal, 'status', '1');

                var title = document.getElementById(titleId);

                if (title) title.textContent = titleFor(button, editing);

                focusFirst(modal);
            });
        });
    }

    wire(
        '[data-group-form]',
        'group',
        'group-title',
        {
            id: 'id',
            outlet: 'outlet_id',
            'outlet-name': 'outlet_name',
            name: 'name',
            kind: 'kind',
            status: 'status'
        },
        function (button, editing) {
            return (editing ? 'Edit group' : 'Create group') + ' — ' + (button.getAttribute('data-outlet-name') || '');
        }
    );

    wire(
        '[data-table-form]',
        'table',
        'table-title',
        {
            id: 'id',
            group: 'pos_table_group_id',
            'group-name': 'group_name',
            'outlet-name': 'outlet_name',
            name: 'name',
            capacity: 'capacity',
            status: 'status'
        },
        function (button, editing) {
            var kind = button.getAttribute('data-kind-label') || 'Table';

            return (editing ? 'Edit ' : 'Create ') + kind.toLowerCase();
        }
    );

    wire(
        '[data-slot-form]',
        'slot',
        'slot-title',
        {
            id: 'id',
            outlet: 'outlet_id',
            time: 'slot_time',
            max: 'max_booking',
            status: 'status'
        },
        function (button, editing) {
            return editing ? 'Edit slot' : 'Create slot';
        }
    );

    /* ── Collapse All ───────────────────────────────────────────────────── */

    var collapse = document.querySelector('[data-collapse-all]');
    var blocks = all('[data-outlet-block]');

    if (collapse && blocks.length) {
        // Hidden in the markup so it never appears without a script to run it.
        collapse.hidden = false;

        collapse.addEventListener('click', function () {
            var anyOpen = blocks.some(function (block) { return block.open; });

            blocks.forEach(function (block) { block.open = !anyOpen; });

            var label = collapse.querySelector('[data-collapse-label]');

            if (label) label.textContent = anyOpen ? 'Expand All' : 'Collapse All';
        });
    }
})();
