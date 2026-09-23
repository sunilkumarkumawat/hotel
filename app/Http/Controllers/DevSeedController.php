<?php

namespace App\Http\Controllers;

/**
 * RAN ONCE, ON PURPOSE — safe to delete.
 *
 * This generated the demo/seed data across Guests, Reservations, Front
 * Office and Accounting on 2026-09-22, requested to populate the working
 * database with realistic volume without running `php artisan migrate`. It
 * has already run to completion (130 guests, 215 reservations, 116
 * check-ins, 100 bills, 146 advance deposits, 110 accounting vouchers, zero
 * errors) and its route was removed from routes/web.php in the same pass,
 * so nothing can reach this class any more.
 *
 * Every row it wrote is still tagged, independently of this file, so that
 * data can be found — and bulk-deleted, if ever wanted:
 *   - created_by is NULL on all of it (a real screen always stamps the
 *     signed-in user; nothing else in this app leaves it empty)
 *   - every remark/narration field on it reads "[DEMO SEED]"
 *   - every seeded guest's email ends "@example-demo.test"
 *
 * Left here only as a record of what ran and why; there is nothing left to
 * run. Delete this file whenever convenient — nothing references it.
 */
class DevSeedController extends Controller
{
    //
}
