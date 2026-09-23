/*
 * Adding and removing rows on a Setup list that saves a batch at once.
 *
 * The server renders three blank rows and a <template> holding a fourth with
 * __i__ where its number goes. Everything here does is copy that template,
 * stamp the next number into it, and put it above the button bar. Nothing is
 * validated, nothing is posted, nothing is remembered: the form is an ordinary
 * form and the server is the only thing that decides what a valid row is.
 *
 * The two buttons ship hidden and are revealed here, so a browser with scripts
 * blocked never shows a button that does nothing — it simply gets the three
 * rows the server rendered, which still save.
 */
(function () {
    'use strict';

    var template = document.getElementById('bulk-row-template');
    var actions = document.querySelector('[data-bulk-actions]');
    var addButton = document.querySelector('[data-add-row]');

    if (!template || !actions || !addButton) {
        return;
    }

    var body = actions.parentNode;

    if (!body) {
        return;
    }

    /*
     * The next number to give a row. Counting the rows on screen would hand
     * out a number twice the moment somebody removes one from the middle, and
     * two rows posting as rows[2] means one of them is silently lost — so the
     * highest number ever used is what it counts from.
     */
    function nextIndex() {
        var highest = -1;

        document.querySelectorAll('[data-bulk-row]').forEach(function (row) {
            var input = row.querySelector('[name^="rows["]');

            if (!input) {
                return;
            }

            var found = input.getAttribute('name').match(/^rows\[(\d+)\]/);

            if (found) {
                highest = Math.max(highest, parseInt(found[1], 10));
            }
        });

        return highest + 1;
    }

    function reveal(row) {
        row.querySelectorAll('[data-row-remove]').forEach(function (button) {
            button.hidden = false;
        });
    }

    function addRow() {
        var index = nextIndex();

        // innerHTML on the template gives the row's markup with its
        // placeholders still in it; replacing them as text is what keeps the
        // name, the id and the `for` of every control in step with each other.
        var markup = template.innerHTML.split('__i__').join(String(index));
        var holder = document.createElement('tbody');

        holder.innerHTML = markup;

        var row = holder.querySelector('[data-bulk-row]');

        if (!row) {
            return;
        }

        reveal(row);
        body.insertBefore(row, actions);

        var first = row.querySelector('input, select');

        if (first) {
            first.focus();
        }
    }

    /*
     * A row is only ever removed while it is still blank-ish or while the
     * person is looking at it, so there is nothing to confirm. What it must not
     * do is leave no rows at all — an empty batch form is a screen with nothing
     * to type into — so the last one is emptied instead of removed.
     */
    function removeRow(row) {
        if (document.querySelectorAll('[data-bulk-row]').length > 1) {
            row.remove();

            return;
        }

        row.querySelectorAll('input').forEach(function (input) {
            if (input.type === 'checkbox') {
                input.checked = input.name.indexOf('[status]') > -1;
            } else if (input.type !== 'hidden') {
                input.value = '';
            }
        });

        row.querySelectorAll('select').forEach(function (select) {
            select.selectedIndex = 0;
        });
    }

    addButton.hidden = false;
    document.querySelectorAll('[data-bulk-row]').forEach(reveal);

    addButton.addEventListener('click', addRow);

    // One listener on the table rather than one per button, so rows added
    // later work without anything being wired up again.
    body.addEventListener('click', function (event) {
        var button = event.target.closest ? event.target.closest('[data-row-remove]') : null;

        if (!button) {
            return;
        }

        var row = button.closest('[data-bulk-row]');

        if (row) {
            removeRow(row);
        }
    });

    /*
     * Enter in a text box would otherwise submit the whole batch from
     * wherever the person had got to. On these rows it moves to the next row
     * instead — or makes one, if they are at the end — which is how a list
     * gets typed in without touching the mouse.
     */
    body.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter' || event.target.tagName !== 'INPUT' || event.target.type === 'checkbox') {
            return;
        }

        var row = event.target.closest('[data-bulk-row]');

        if (!row) {
            return;
        }

        event.preventDefault();

        if (!row.nextElementSibling || !row.nextElementSibling.hasAttribute('data-bulk-row')) {
            addRow();

            return;
        }

        var name = event.target.getAttribute('name') || '';
        var field = name.replace(/^rows\[\d+\]/, '');
        var next = row.nextElementSibling.querySelector('[name$="' + field + '"]');

        if (next) {
            next.focus();
        }
    });
})();
