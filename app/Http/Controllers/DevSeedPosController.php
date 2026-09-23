<?php

namespace App\Http\Controllers;

/**
 * RAN ONCE, ON PURPOSE — safe to delete.
 *
 * This generated demo data for the POS module on 2026-09-22, requested after
 * the front-office/accounting demo seed (see DevSeedController, also already
 * run and neutered) left POS out. It ran to completion in one pass: 130
 * pos_orders, zero errors. Every one of them was left either 'settled' or
 * 'cancelled' — none 'open' or 'billed' — so none of it shows up as tables
 * currently occupied on any live screen. Its route was removed from
 * routes/web.php in the same pass, so nothing can reach this class any more.
 *
 * It reused whatever real Outlet, table group, department and menu
 * categories the property already had configured (found here: an "Restaurant"
 * outlet with a table group, department and categories already set up, but
 * no tables beyond 4 and no menu items) and only added what was missing —
 * 4 more tables and 17 menu items — rather than creating a parallel, unused
 * set of masters. Those added masters are real, ordinary rows now; they are
 * not tagged and are not part of the "demo data" that would ever need
 * removing — deleting them would take the POS module's menu away.
 *
 * The orders themselves are tagged, independently of this file, so that data
 * can be found — and bulk-cancelled, if ever wanted:
 *   - created_by is NULL on every pos_order (a real screen always stamps the
 *     signed-in user; nothing else in this app leaves it empty)
 *   - pos_orders.remark is exactly "[DEMO SEED]" on every one of them
 *   - pos_invoices, pos_payments and pos_order_items are not tagged
 *     themselves — find them by joining back to pos_order_id / the invoice's
 *     own pos_order_id
 *
 * Left here only as a record of what ran and why; there is nothing left to
 * run. Delete this file whenever convenient — nothing references it.
 */
class DevSeedPosController extends Controller
{
    //
}
