<?php

namespace App\Http\Controllers;

use App\Models\FrontOffice\CheckIn;
use App\Models\Reservation\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


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
