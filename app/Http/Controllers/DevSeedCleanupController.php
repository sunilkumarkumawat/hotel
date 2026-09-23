<?php

namespace App\Http\Controllers;

use App\Models\FrontOffice\CheckIn;
use App\Models\Reservation\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * TEMPORARY — one-shot repair for a side effect of DevSeedController's demo
 * data (see that file's docblock; it has already run and been neutered).
 *
 * The "in house" demo scenario always spans "today" by design (arrival
 * today-0..3, departure today+2..6), so it could only ever have as many
 * genuinely successful check-ins as the property has physical rooms. Every
 * attempt beyond that still left behind a `reservation_rooms` row — with no
 * room_id, but a real room_type_id and date range, and its parent
 * `reservations.status` still 'confirmed' — because the seeding script
 * skipped the check-in step on failure without also marking the booking
 * anything else. The Reservation Calendar Monthly report (App\Support\
 * MonthlyPosition::applyBookings) correctly counts every confirmed/tentative
 * reservation_rooms row as demand regardless of whether a room number was
 * ever assigned — that is by design, for ordinary advance bookings — so all
 * of those leftover rows piled up as phantom demand on top of the rooms that
 * were genuinely, successfully occupied, which is what produced the negative
 * "Position" and >100% "Occupancy %" figures.
 *
 * The fix cancels — never deletes — only [DEMO SEED] rows whose stay covers
 * today, which by construction is exactly (and only) the "in house" scenario
 * batch, both the phantom room-less ones and the genuinely-occupied ones:
 *   - reservations: status -> 'cancelled' where remark = '[DEMO SEED]' and a
 *     reservation_rooms row has arrival_date <= today < checkout_date
 *   - check_ins: status -> 'cancelled' the same way, using checkin_date /
 *     COALESCE(actual_checkout_date, expected_checkout_date)
 *
 * Historical (already checked-out / no-show / cancelled) and future-dated
 * seed data — which never triggered this, since it does not cover today —
 * is untouched, so every other module keeps its demo volume.
 *
 * Self-guarded exactly like DevSeedController was: secret key, non-production
 * only, localhost only. Safe to delete once run.
 */
class DevSeedCleanupController extends Controller
{
    private const SECRET_KEY = 'pms-demo-cleanup-7b3e91';

    public function run(Request $request): JsonResponse
    {
        if ($request->query('key') !== self::SECRET_KEY) {
            abort(404);
        }

        if (app()->environment('production')) {
            abort(404);
        }

        if (! in_array($request->ip(), ['127.0.0.1', '::1'], true)) {
            abort(404);
        }

        $today = CarbonImmutable::today()->toDateString();
        $errors = [];

        $reservationIds = DB::table('reservation_rooms as rr')
            ->join('reservations as r', 'r.id', '=', 'rr.reservation_id')
            ->where('r.remark', '[DEMO SEED]')
            ->whereIn('r.status', ['confirmed', 'tentative', 'checked_in'])
            ->where('rr.arrival_date', '<=', $today)
            ->where('rr.checkout_date', '>', $today)
            ->distinct()
            ->pluck('r.id');

        $checkInIds = DB::table('check_ins')
            ->where('remark', '[DEMO SEED]')
            ->where('status', '!=', 'cancelled')
            ->where('checkin_date', '<=', $today)
            ->whereRaw('COALESCE(actual_checkout_date, expected_checkout_date) > ?', [$today])
            ->pluck('id');

        $reservationsCancelled = 0;
        $checkInsCancelled = 0;

        DB::transaction(function () use ($reservationIds, $checkInIds, &$reservationsCancelled, &$checkInsCancelled, &$errors, $today) {
            foreach ($reservationIds as $id) {
                try {
                    $reservation = Reservation::find($id);

                    if ($reservation && $reservation->status !== 'cancelled') {
                        $reservation->status = 'cancelled';
                        $reservation->cancelled_on = $today;
                        $reservation->save();
                        $reservationsCancelled++;
                    }
                } catch (\Throwable $e) {
                    $errors[] = 'reservation ' . $id . ': ' . $e->getMessage();
                }
            }

            foreach ($checkInIds as $id) {
                try {
                    $checkIn = CheckIn::find($id);

                    if ($checkIn && $checkIn->status !== 'cancelled') {
                        $checkIn->status = 'cancelled';
                        $checkIn->save();
                        $checkInsCancelled++;
                    }
                } catch (\Throwable $e) {
                    $errors[] = 'checkin ' . $id . ': ' . $e->getMessage();
                }
            }
        });

        return response()->json([
            'ok' => true,
            'today' => $today,
            'reservations_matched' => $reservationIds->count(),
            'reservations_cancelled' => $reservationsCancelled,
            'checkins_matched' => $checkInIds->count(),
            'checkins_cancelled' => $checkInsCancelled,
            'error_count' => count($errors),
            'errors' => array_slice($errors, 0, 15),
            'note' => 'Only [DEMO SEED]-tagged rows whose stay covered today were cancelled. Historical and future-dated demo data is untouched.',
        ]);
    }
}
