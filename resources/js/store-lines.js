/*
 * The lines on a store document — and on a recipe, which is the same table
 * with two fewer columns.
 *
 * Three jobs, and nothing else: add and remove rows, fill in a rate when an
 * item is picked, and keep the totals honest as somebody types. The server
 * works the totals out again on save — this is only so the storekeeper can see
 * what they are about to commit to before they commit to it.
 *
 * No framework, no build step. The page ships a <template> of one blank row and
 * this clones it. Every field is looked up defensively, because a recipe row
 * has no rate box and a document row does.
 */
(function () {
    'use strict';

    const form = document.querySelector('[data-store-doc], [data-recipe]');
    if (!form) return;

    const boot = window.storeDoc || { items: [], signed: false, kind: '' };
    const byId = new Map(boot.items.map((i) => [String(i.id), i]));

    // A recipe costs its lines at the store's average; it never moves stock.
    const isRecipe = form.hasAttribute('data-recipe');

    const body = form.querySelector('[data-line-body]');
    const template = form.querySelector('[data-line-template]');
    const $ = (sel, root) => (root || form).querySelector(sel);
    const $$ = (sel, root) => Array.from((root || form).querySelectorAll(sel));

    const num = (el) => (el ? Number(el.value) || 0 : 0);

    const money = (n) =>
        (Math.round((Number(n) || 0) * 100) / 100).toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });

    /*
     * The next index for a new row's field names.
     *
     * Counted from the HIGHEST index on the page, never from the row count:
     * remove the second of three rows and a count would hand the next row the
     * name of one that still exists, and the two would overwrite each other on
     * the way to the server.
     */
    function nextIndex() {
        let top = -1;

        $$('[data-line] [data-qty]').forEach(function (input) {
            const match = /lines\[(\d+)\]/.exec(input.name || '');
            if (match) top = Math.max(top, Number(match[1]));
        });

        return top + 1;
    }

    /** What one line comes to. A recipe line is priced at the average rate. */
    function lineAmount(row) {
        const qty = Math.abs(num($('[data-qty]', row)));
        const item = byId.get(String(($('[data-item]', row) || {}).value));

        if (isRecipe) {
            return qty * (item ? item.rate : 0);
        }

        const amount = qty * num($('[data-rate]', row));

        return amount + (amount * num($('[data-tax]', row))) / 100;
    }

    /** One line's amount, and the note under the item saying what is in stock. */
    function refreshLine(row) {
        const amountBox = $('[data-amount]', row);
        if (amountBox) amountBox.textContent = money(lineAmount(row));

        const note = $('[data-stock]', row);
        if (!note) return;

        const item = byId.get(String(($('[data-item]', row) || {}).value));

        if (!item) {
            note.textContent = '';
            note.classList.remove('is-short');
            return;
        }

        const qty = num($('[data-qty]', row));

        /*
         * What this line would leave on the shelf. A purchase order is a
         * promise and a recipe is a plan — neither moves anything, so both
         * show the stock and stop. An arrow there would be telling somebody
         * something untrue.
         */
        let after = null;

        if (boot.kind === 'grn') {
            after = item.stock + Math.abs(qty);
        } else if (boot.signed) {
            after = item.stock + qty;
        } else if (!isRecipe && boot.kind !== 'po') {
            after = item.stock - Math.abs(qty);
        }

        note.textContent =
            'In stock ' + money(item.stock === undefined ? 0 : item.stock) + ' ' + item.unit +
            (qty && after !== null ? ' → ' + money(after) + ' ' + item.unit : '') +
            (isRecipe && item.rate ? ' · ' + money(item.rate) + ' a ' + item.unit : '');

        // Only worth shouting about when this line is what takes it under.
        note.classList.toggle('is-short', after !== null && after < 0 && item.stock >= 0);
    }

    function refreshTotals() {
        let sub = 0;
        let tax = 0;

        $$('[data-line]').forEach(function (row) {
            if (isRecipe) {
                sub += lineAmount(row);

                return;
            }

            const amount = Math.abs(num($('[data-qty]', row))) * num($('[data-rate]', row));

            sub += amount;
            tax += (amount * num($('[data-tax]', row))) / 100;
        });

        const subBox = $('[data-sub]');
        const taxBox = $('[data-tax-total]');
        const netBox = $('[data-net]');

        if (subBox) subBox.textContent = money(sub);
        if (taxBox) taxBox.textContent = money(tax);
        if (netBox) netBox.textContent = money(sub + tax);
    }

    function refresh(row) {
        if (row) refreshLine(row);
        refreshTotals();
    }

    /*
     * Picking an item fills in the rate — the moving average where there is
     * one, the last price paid otherwise. It only ever fills a rate box that
     * is empty: a rate somebody typed is their decision, and a supplier who
     * put the price up is exactly when they would have typed one.
     */
    form.addEventListener('change', function (event) {
        const row = event.target.closest('[data-line]');
        if (!row) return;

        if (event.target.matches('[data-item]')) {
            const item = byId.get(String(event.target.value));
            const rateBox = $('[data-rate]', row);
            const taxBox = $('[data-tax]', row);

            if (item && rateBox && !rateBox.value) {
                rateBox.value = (item.rate || item.last || 0).toFixed(2);
            }

            if (item && taxBox && !taxBox.value && item.tax) {
                taxBox.value = item.tax;
            }
        }

        refresh(row);
    });

    form.addEventListener('input', function (event) {
        const row = event.target.closest('[data-line]');
        if (row) refresh(row);
    });

    form.addEventListener('click', function (event) {
        const remove = event.target.closest('[data-line-remove]');

        if (remove) {
            const rows = $$('[data-line]');

            // Never leave the table with nothing in it — clear the last row
            // instead of removing it, or there is nothing to type into.
            if (rows.length === 1) {
                $$('input, select', rows[0]).forEach((el) => (el.value = ''));
                refresh(rows[0]);

                return;
            }

            remove.closest('[data-line]').remove();
            refreshTotals();

            return;
        }

        if (event.target.closest('[data-line-add]')) {
            const clone = template.content.firstElementChild.cloneNode(true);
            const index = nextIndex();

            clone.querySelectorAll('[name]').forEach(function (el) {
                el.name = el.name.replace('__i__', index);
            });

            body.appendChild(clone);

            const picker = $('[data-item]', clone);
            if (picker) picker.focus();
        }
    });

    /*
     * A scan (or a typed code + Enter) drops the item into an empty line
     * rather than filtering anything, because the point of a scanner is not
     * stopping to look at a list. Enter is caught here so it never falls
     * through to submitting the whole document — the form has no other
     * listener for this box. Picking the item by its id and dispatching the
     * same change event the dropdown fires fills the rate, tax and stock
     * note exactly as choosing it by hand would.
     */
    const scanInput = form.querySelector('[data-barcode-scan]');
    const scanMsg = form.querySelector('[data-barcode-msg]');
    const byCode = new Map(
        boot.items.filter((i) => i.code).map((i) => [String(i.code).toLowerCase(), i])
    );

    if (scanInput) {
        scanInput.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter') return;
            event.preventDefault();

            const code = scanInput.value.trim();
            scanInput.value = '';

            if (!code) return;

            const item = byCode.get(code.toLowerCase());

            if (!item) {
                if (scanMsg) scanMsg.textContent = 'No item is set up with the code "' + code + '".';

                return;
            }

            if (scanMsg) scanMsg.textContent = '';

            // The first line with nothing chosen yet — same as a blank
            // document opens with — or a fresh one when every line is full.
            let row = $$('[data-line]').find(function (candidate) {
                const picker = $('[data-item]', candidate);

                return picker && !picker.value;
            });

            if (!row) {
                const clone = template.content.firstElementChild.cloneNode(true);
                const index = nextIndex();

                clone.querySelectorAll('[name]').forEach(function (el) {
                    el.name = el.name.replace('__i__', index);
                });

                body.appendChild(clone);
                row = clone;
            }

            const picker = $('[data-item]', row);
            picker.value = String(item.id);
            picker.dispatchEvent(new Event('change', { bubbles: true }));

            const qtyBox = $('[data-qty]', row);

            if (qtyBox) {
                qtyBox.focus();
                qtyBox.select();
            }
        });
    }

    // The Remove buttons are revealed by script, so a page without JavaScript
    // never shows a button that would do nothing.
    $$('[data-line-remove]').forEach((btn) => btn.classList.remove('nv-hidden'));

    $$('[data-line]').forEach(refreshLine);
    refreshTotals();
})();
