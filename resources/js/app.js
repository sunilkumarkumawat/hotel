/**
 * Nova Admin — theme behaviour.
 * Zero dependencies on purpose: sidebar, dark mode, dropdowns, tabs.
 */

const STORAGE_KEY = 'nova.theme';
const SIDEBAR_KEY = 'nova.sidebar';

/* ----------------------------------------------------------------- theme */

function currentTheme() {
    try {
        const saved = localStorage.getItem(STORAGE_KEY);
        if (saved === 'light' || saved === 'dark') return saved;
    } catch (e) {
        /* storage may be unavailable (private mode) — fall through */
    }

    // Dark is the app's default face now — a first-time visitor sees the
    // same theme the dashboard was designed in, not whatever their OS
    // happens to prefer. Anyone who picks light gets it remembered above.
    return 'dark';
}

function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);

    try {
        localStorage.setItem(STORAGE_KEY, theme);
    } catch (e) {
        /* ignore */
    }
}

/* --------------------------------------------------------------- sidebar */

function isDesktop() {
    return window.matchMedia('(min-width: 1025px)').matches;
}

function applySidebar(state) {
    const root = document.documentElement;

    if (state === 'collapsed' || state === 'open') {
        root.setAttribute('data-sidebar', state);
    } else {
        root.removeAttribute('data-sidebar');
    }
}

function toggleSidebar() {
    const root = document.documentElement;
    const state = root.getAttribute('data-sidebar');

    if (isDesktop()) {
        const next = state === 'collapsed' ? '' : 'collapsed';
        applySidebar(next);

        try {
            localStorage.setItem(SIDEBAR_KEY, next);
        } catch (e) {
            /* ignore */
        }

        return;
    }

    applySidebar(state === 'open' ? '' : 'open');
}

/* ---------------------------------------------------------- "More" sheet */

// The mobile bottom bar's fifth tab (partials/mobile-nav.blade.php) — the
// same open/close-by-attribute shape as the sidebar above, just its own
// attribute so opening one never has to know or care about the other.
function applyMoreSheet(open) {
    document.documentElement.toggleAttribute('data-more-open', open);

    const trigger = document.querySelector('[data-toggle="more-sheet"]');
    if (trigger) {
        trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
}

function toggleMoreSheet() {
    applyMoreSheet(!document.documentElement.hasAttribute('data-more-open'));
}

/* --------------------------------------------------------------- tooltip */

/*
 * A module or submodule label the sidebar has truncated — or, once the
 * sidebar is collapsed to icons, every label — gets its full name back on
 * hover or keyboard focus. `.nv-brand` clips overflow and `.nv-nav`
 * scrolls, so a plain CSS ::after would be clipped away invisible; this
 * renders one shared element on <body> instead and positions it with
 * getBoundingClientRect(), which escapes both ancestors.
 */
let tooltipEl = null;

function hideTooltip() {
    if (tooltipEl) tooltipEl.classList.remove('is-visible');
}

function showTooltip(trigger) {
    const label = trigger.getAttribute('data-tooltip');
    if (!label) return;

    const collapsed = document.documentElement.getAttribute('data-sidebar') === 'collapsed';
    const text = trigger.querySelector('.nv-nav-text, .nv-brand-name');
    const truncated = !!text && text.scrollWidth > text.clientWidth + 1;

    if (!collapsed && !truncated) return;

    if (!tooltipEl) {
        tooltipEl = document.createElement('div');
        tooltipEl.className = 'nv-tooltip-portal';
        document.body.appendChild(tooltipEl);
    }

    tooltipEl.textContent = label;

    const rect = trigger.getBoundingClientRect();
    tooltipEl.style.top = `${rect.top + rect.height / 2}px`;
    tooltipEl.style.left = `${rect.right + 12}px`;
    tooltipEl.classList.add('is-visible');
}

function initTooltips() {
    document.querySelectorAll('[data-tooltip]').forEach((trigger) => {
        trigger.addEventListener('mouseenter', () => showTooltip(trigger));
        trigger.addEventListener('mouseleave', hideTooltip);
        trigger.addEventListener('focus', () => showTooltip(trigger));
        trigger.addEventListener('blur', hideTooltip);
    });

    // The collapsed sidebar's own list scrolls; don't leave a stale
    // tooltip stranded away from the icon that triggered it.
    const nav = document.querySelector('.nv-nav');
    if (nav) nav.addEventListener('scroll', hideTooltip);
    window.addEventListener('resize', hideTooltip);
}

/* ----------------------------------------------------------- AI Assistant */

/*
 * The dashboard's "Ask AI" box. It posts to /ai/chat, a thin read-only
 * endpoint AiAssistantController documents — this file only renders the
 * conversation and keeps a short client-side history so a follow-up
 * question makes sense without resending everything the dashboard knows.
 */
function initAiChat() {
    const chat = document.querySelector('[data-ai-chat]');
    if (!chat) return;

    const log = chat.querySelector('[data-ai-log]');
    const form = chat.querySelector('[data-ai-form]');
    const input = chat.querySelector('[data-ai-input]');
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    const endpoint = chat.dataset.aiEndpoint;
    const history = [];

    if (!log || !endpoint) return;

    const addBubble = (role, text) => {
        const bubble = document.createElement('div');
        bubble.className = 'nv-ai-msg is-' + (role === 'user' ? 'user' : 'ai');
        bubble.textContent = text;
        log.appendChild(bubble);
        log.scrollTop = log.scrollHeight;
        return bubble;
    };

    const send = async (message) => {
        if (!message.trim()) return;

        addBubble('user', message);
        history.push({ role: 'user', content: message });

        const pending = addBubble('assistant', 'Thinking…');
        pending.classList.add('is-pending');

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': token || '',
                },
                body: JSON.stringify({ message, history: history.slice(-8) }),
            });

            const data = await response.json();
            const reply = data.reply || "Didn't get a usable reply — try again.";

            pending.textContent = reply;
            pending.classList.remove('is-pending');
            history.push({ role: 'assistant', content: reply });
        } catch (e) {
            pending.textContent = "Couldn't reach the AI service — check the connection and try again.";
            pending.classList.remove('is-pending');
        }
    };

    if (form) {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            const message = input.value;
            input.value = '';
            send(message);
        });
    }

    chat.querySelectorAll('[data-ai-quick]').forEach((chip) => {
        chip.addEventListener('click', () => send(chip.getAttribute('data-ai-quick')));
    });
}

/* ------------------------------------------------- isometric floor view */

/*
 * Deliberately not the generic [data-tabs] handler above: these floor
 * buttons live inside a [data-tabs] "3D View" panel, and reusing that
 * handler here would let its descendant-wide querySelectorAll sweep these
 * buttons and panels up too, fighting the outer List/3D switch. A few
 * lines on their own data attributes keep the two switches from stepping
 * on each other.
 */
function initIsoFloors() {
    document.querySelectorAll('[data-floor-select]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const card = btn.closest('.nv-iso-card');
            const floor = btn.getAttribute('data-floor-select');
            if (!card) return;

            card.querySelectorAll('[data-floor-panel]').forEach((panel) => {
                panel.hidden = panel.getAttribute('data-floor-panel') !== floor;
            });

            card.querySelectorAll('[data-floor-select]').forEach((b) => {
                b.classList.toggle('is-active', b === btn);
            });
        });
    });
}

/* ---------------------------------------------------------------- boot */

function boot() {
    /* Theme toggle */
    document.querySelectorAll('[data-toggle="theme"]').forEach((el) => {
        el.addEventListener('click', () => {
            applyTheme(document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
        });
    });

    /* Sidebar toggle + overlay */
    document.querySelectorAll('[data-toggle="sidebar"]').forEach((el) => {
        el.addEventListener('click', toggleSidebar);
    });

    const overlay = document.querySelector('[data-close="sidebar"]');
    if (overlay) {
        overlay.addEventListener('click', () => applySidebar(''));
    }

    /* "More" sheet (mobile bottom bar) */
    document.querySelectorAll('[data-toggle="more-sheet"]').forEach((el) => {
        el.addEventListener('click', toggleMoreSheet);
    });

    document.querySelectorAll('[data-close="more-sheet"]').forEach((el) => {
        el.addEventListener('click', () => applyMoreSheet(false));
    });

    /* Collapsed-sidebar hover tooltips */
    initTooltips();

    /* Dashboard: AI Assistant chat + isometric floor view */
    initAiChat();
    initIsoFloors();

    /* Dropdowns */
    document.querySelectorAll('[data-dropdown]').forEach((dd) => {
        const trigger = dd.querySelector('[data-dropdown-trigger]');
        if (!trigger) return;

        trigger.addEventListener('click', (event) => {
            event.stopPropagation();
            const open = dd.classList.contains('is-open');

            document.querySelectorAll('[data-dropdown].is-open').forEach((o) => o.classList.remove('is-open'));

            dd.classList.toggle('is-open', !open);
        });
    });

    document.addEventListener('click', () => {
        document.querySelectorAll('[data-dropdown].is-open').forEach((o) => o.classList.remove('is-open'));
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;

        document.querySelectorAll('[data-dropdown].is-open').forEach((o) => o.classList.remove('is-open'));
        if (!isDesktop()) applySidebar('');
        applyMoreSheet(false);
    });

    /* Tabs */
    document.querySelectorAll('[data-tabs]').forEach((group) => {
        const tabs = group.querySelectorAll('[data-tab]');

        tabs.forEach((tab) => {
            tab.addEventListener('click', () => {
                const target = tab.getAttribute('data-tab');

                tabs.forEach((t) => t.classList.toggle('is-active', t === tab));

                group.querySelectorAll('[data-tab-panel]').forEach((panel) => {
                    panel.classList.toggle('is-active', panel.getAttribute('data-tab-panel') === target);
                });
            });
        });
    });

    /* Cascading selects — country -> state -> city on the branch form */
    document.querySelectorAll('[data-cascade]').forEach((select) => {
        const targetName = select.dataset.cascade;
        const target = document.querySelector(`[data-cascade-target="${targetName}"]`);
        if (!target) return;

        select.addEventListener('change', async () => {
            const reset = (el, label) => {
                el.innerHTML = `<option value="">${label}</option>`;
            };

            reset(target, 'Loading…');

            // Clear anything further down the chain too.
            const next = target.dataset.cascade
                ? document.querySelector(`[data-cascade-target="${target.dataset.cascade}"]`)
                : null;
            if (next) reset(next, 'Choose…');

            if (!select.value) {
                reset(target, 'Choose…');
                return;
            }

            try {
                const response = await fetch(`${select.dataset.cascadeUrl}/${select.value}`, {
                    headers: { Accept: 'application/json' },
                });
                const rows = await response.json();

                reset(target, 'Choose…');
                rows.forEach((row) => {
                    const option = document.createElement('option');
                    option.value = row.id;
                    option.textContent = row.name;
                    target.appendChild(option);
                });
            } catch (e) {
                reset(target, 'Could not load — try again');
            }
        });
    });

    /* Show/hide a target element — inline edit rows on the modules screen */
    document.querySelectorAll('[data-toggle-row]').forEach((button) => {
        button.addEventListener('click', () => {
            const target = document.getElementById(button.dataset.toggleRow);
            if (target) target.hidden = !target.hidden;
        });
    });

    /* Sidebar module groups — open/close, remembering the last one opened */
    document.querySelectorAll('[data-nav-group]').forEach((group) => {
        const toggle = group.querySelector('[data-nav-toggle]');
        if (!toggle) return;

        toggle.addEventListener('click', () => {
            const open = group.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    });

    /* Permission matrix — row / column / group / master toggles */
    document.querySelectorAll('[data-matrix]').forEach((matrix) => {
        const cells = () => Array.from(matrix.querySelectorAll('[data-matrix-cell]'));
        const master = matrix.querySelector('[data-matrix-all]');
        const counter = matrix.querySelector('[data-matrix-selected]');

        const rowCells = (toggle) => Array.from(toggle.closest('tr').querySelectorAll('[data-matrix-cell]'));
        const colCells = (action) => cells().filter((c) => c.dataset.action === action);
        const groupCells = (group) => cells().filter((c) => c.dataset.group === group);

        /** Reflect a set of cells onto its toggle: checked / unchecked / partial. */
        const reflect = (toggle, list) => {
            const checked = list.filter((c) => c.checked).length;
            toggle.checked = list.length > 0 && checked === list.length;
            toggle.indeterminate = checked > 0 && checked < list.length;
        };

        /**
         * "View" is what the sidebar reads, so it is never optional: ticking
         * Add / Edit / Delete switches it on, and switching it off clears the row.
         */
        const syncView = () => {
            matrix.querySelectorAll('tbody tr').forEach((row) => {
                const inRow = Array.from(row.querySelectorAll('[data-matrix-cell]'));
                if (!inRow.length) return;

                const view = inRow.find((c) => c.dataset.action === 'view');
                const rest = inRow.filter((c) => c.dataset.action !== 'view');
                if (!view) return;

                if (rest.some((c) => c.checked)) view.checked = true;
            });
        };

        const refresh = () => {
            syncView();

            matrix.querySelectorAll('[data-matrix-row]').forEach((t) => reflect(t, rowCells(t)));
            matrix.querySelectorAll('[data-matrix-col]').forEach((t) => reflect(t, colCells(t.dataset.matrixCol)));
            matrix.querySelectorAll('[data-matrix-group]').forEach((t) =>
                reflect(t, groupCells(t.dataset.matrixGroup))
            );

            if (master) reflect(master, cells());
            if (counter) counter.textContent = cells().filter((c) => c.checked).length;
        };

        const apply = (list, checked) => {
            list.forEach((cell) => {
                if (!cell.disabled) cell.checked = checked;
            });
            refresh();
        };

        matrix.querySelectorAll('[data-matrix-row]').forEach((toggle) => {
            toggle.addEventListener('change', () => apply(rowCells(toggle), toggle.checked));
        });

        matrix.querySelectorAll('[data-matrix-col]').forEach((toggle) => {
            toggle.addEventListener('change', () => {
                // Clearing the View column clears everything, for the same reason.
                const list =
                    toggle.dataset.matrixCol === 'view' && !toggle.checked
                        ? cells()
                        : colCells(toggle.dataset.matrixCol);

                apply(list, toggle.checked);
            });
        });

        matrix.querySelectorAll('[data-matrix-group]').forEach((toggle) => {
            toggle.addEventListener('change', () => apply(groupCells(toggle.dataset.matrixGroup), toggle.checked));
        });

        if (master) {
            master.addEventListener('change', () => apply(cells(), master.checked));
        }

        cells().forEach((cell) =>
            cell.addEventListener('change', () => {
                // Turning View off takes the whole screen away.
                if (cell.dataset.action === 'view' && !cell.checked) {
                    cell.closest('tr')
                        .querySelectorAll('[data-matrix-cell]')
                        .forEach((c) => (c.checked = false));
                }

                refresh();
            })
        );

        refresh();
    });

    /* Confirmation dialog for destructive forms */
    const modal = document.querySelector('[data-confirm-modal]');

    if (modal) {
        let pending = null;

        const close = () => {
            modal.classList.remove('is-open');
            pending = null;
        };

        document.querySelectorAll('form[data-confirm]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (form.dataset.confirmed === 'yes') return;

                event.preventDefault();
                pending = form;

                modal.querySelector('[data-confirm-title]').textContent =
                    form.dataset.confirmTitle || 'Are you sure?';
                modal.querySelector('[data-confirm-text]').textContent = form.dataset.confirm;
                modal.querySelector('[data-confirm-accept]').textContent =
                    form.dataset.confirmAction || 'Delete';

                modal.classList.add('is-open');
                modal.querySelector('[data-confirm-accept]').focus();
            });
        });

        modal.querySelector('[data-confirm-accept]').addEventListener('click', () => {
            if (!pending) return;

            pending.dataset.confirmed = 'yes';
            pending.submit();
            close();
        });

        modal.querySelector('[data-confirm-cancel]').addEventListener('click', close);
        modal.addEventListener('click', (event) => {
            if (event.target === modal) close();
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') close();
        });
    }

    /* "Select all" checkbox in tables */
    document.querySelectorAll('[data-check-all]').forEach((master) => {
        const scope = master.closest('table') || document;

        master.addEventListener('change', () => {
            scope.querySelectorAll('[data-check-row]').forEach((row) => {
                row.checked = master.checked;
            });
        });
    });
}

/* Apply stored preferences as early as possible to avoid a flash. */
applyTheme(currentTheme());

try {
    if (localStorage.getItem(SIDEBAR_KEY) === 'collapsed') applySidebar('collapsed');
} catch (e) {
    /* ignore */
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}
