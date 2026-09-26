<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Models\Reservation\Reservation;
use App\Models\Website\WebsitePayment;
use App\Support\Money;
use App\Support\Notify;
use App\Support\GuestMessage;
use App\Support\Website\BookingService;
use App\Support\Website\Razorpay;
use App\Support\Website\RoomAvailability;
use App\Support\Website\RoomsUnavailableException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

/**
 * The booking wizard and its payment.
 *
 * The shape of one booking, start to finish:
 *
 *   1. GET  .../book      the form — dates, room type and guest details
 *   2. POST .../book/order   validated, priced, and a Razorpay order opened.
 *      Nothing is booked yet: a website_payments row (status "created")
 *      holds the guest's intended booking as a snapshot. Razorpay's
 *      Checkout then takes payment in the guest's own browser — card and
 *      UPI details go straight to Razorpay, never through this server.
 *   3. POST /book/verify   Checkout's own success handler calls this with
 *      the signed proof of payment. Once verified, the reservation this
 *      payment paid for is finally created — see finalizeCapturedPayment().
 *   4. POST /razorpay/webhook   the same finalize step, reachable a second
 *      way, for the rare case where step 3 never arrives (the guest closed
 *      the tab right after paying). Optional — see config/services.php.
 */
class BookingController extends Controller
{
    public function __construct(private readonly HotelSiteController $sites) {}

    /** GET /hotels/{slug}/book */
    public function create(string $slug, Request $request): View
    {
        $site = $this->sites->resolveSite($slug);
        $dates = HotelSiteController::resolveSearch($request);

        $roomTypeId = $request->integer('room_type_id') ?: null;

        $quote = null;
        $left = null;

        if ($roomTypeId) {
            $roomType = \App\Models\Master\RoomType::query()->forBranch($site->branch_id)->active()->find($roomTypeId);

            if ($roomType) {
                $this->sites->guardRoomType($roomType, $site);
                $left = RoomAvailability::left($roomType->id, $site->branch_id, $dates['arrival_date'], $dates['checkout_date']);
                $quote = BookingService::quote($roomType->id, $site->branch_id, $dates['arrival_date'], $dates['checkout_date'], $dates['no_of_rooms']);
            }
        }

        return view('site.book', [
            'site' => $site,
            'dates' => $dates,
            'quote' => $quote,
            'left' => $left,
            'razorpayReady' => Razorpay::isConfigured(),
            'razorpayKey' => Razorpay::keyId(),
        ]);
    }

    /** POST /hotels/{slug}/book/order — AJAX: price the stay, open a Razorpay order. */
    public function order(string $slug, Request $request): JsonResponse
    {
        $site = $this->sites->resolveSite($slug);

        if (! Razorpay::isConfigured()) {
            return response()->json([
                'message' => 'Online booking is not switched on for this hotel yet — please call the hotel directly to book.',
            ], 503);
        }

        $data = $request->validate([
            'title' => 'nullable|string|max:10',
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'required|email|max:255',
            'mobile' => 'required|string|max:20',
            'arrival_date' => 'required|date|after_or_equal:today',
            'checkout_date' => 'required|date|after:arrival_date',
            'room_type_id' => 'required|integer',
            'no_of_rooms' => 'required|integer|min:1|max:10',
            'adults' => 'nullable|integer|min:1|max:20',
            'children' => 'nullable|integer|min:0|max:20',
            'remark' => 'nullable|string|max:1000',
        ]);

        $roomType = \App\Models\Master\RoomType::query()->forBranch($site->branch_id)->active()->find($data['room_type_id']);
        abort_unless($roomType, 404);
        $this->sites->guardRoomType($roomType, $site);

        // An unlocked, friendly pre-check — so a guest hears "sold out"
        // before entering payment details, not after. The locked, binding
        // check happens again the instant the payment actually clears, in
        // BookingService::createPaidBooking().
        $left = RoomAvailability::left($data['room_type_id'], $site->branch_id, $data['arrival_date'], $data['checkout_date']);

        if ($left < $data['no_of_rooms']) {
            return response()->json([
                'message' => $left > 0
                    ? "Sorry, only {$left} room(s) of this type are left for these dates."
                    : 'Sorry, no rooms of this type are left for these dates.',
            ], 422);
        }

        $quote = BookingService::quote($data['room_type_id'], $site->branch_id, $data['arrival_date'], $data['checkout_date'], $data['no_of_rooms']);

        try {
            $order = Razorpay::createOrder((float) $quote['net_amount'], Razorpay::receiptFor($site->branch_id));
        } catch (Throwable $e) {
            Log::warning('[Website booking] Razorpay order failed: ' . $e->getMessage());

            return response()->json(['message' => 'We could not start the payment just now. Please try again in a moment.'], 502);
        }

        $payment = WebsitePayment::create([
            'branch_id' => $site->branch_id,
            'confirmation_token' => WebsitePayment::newToken(),
            'gateway' => 'razorpay',
            'gateway_order_id' => $order['id'],
            'amount' => $quote['net_amount'],
            'currency' => $order['currency'] ?? 'INR',
            'status' => 'created',
            'booking_snapshot' => $data,
        ]);

        return response()->json([
            'key' => Razorpay::keyId(),
            'order_id' => $order['id'],
            'amount' => $order['amount'],
            'currency' => $order['currency'],
            'name' => $site->branch->legal_name ?: $site->branch->branch_name,
            'description' => $roomType->name . ' · ' . $quote['nights'] . ' night' . ($quote['nights'] > 1 ? 's' : ''),
            'prefill' => [
                'name' => trim($data['first_name'] . ' ' . ($data['last_name'] ?? '')),
                'email' => $data['email'],
                'contact' => $data['mobile'],
            ],
            'notes' => ['payment_ref' => $payment->id],
        ]);
    }

    /** POST /book/verify — AJAX, called by Checkout.js's own success handler. */
    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'razorpay_order_id' => 'required|string',
            'razorpay_payment_id' => 'required|string',
            'razorpay_signature' => 'required|string',
        ]);

        $payment = WebsitePayment::where('gateway_order_id', $data['razorpay_order_id'])->first();
        abort_if(! $payment, 404, 'Booking not found.');

        if (! Razorpay::verifyPaymentSignature($data['razorpay_order_id'], $data['razorpay_payment_id'], $data['razorpay_signature'])) {
            return response()->json(['message' => 'This payment could not be verified.'], 422);
        }

        $result = $this->finalizeCapturedPayment($payment, $data['razorpay_payment_id'], $data['razorpay_signature']);

        return $this->respondToFinalize($result);
    }

    /** POST /razorpay/webhook — server-to-server, optional (needs RAZORPAY_WEBHOOK_SECRET). */
    public function webhook(Request $request): Response
    {
        $raw = $request->getContent();
        $signature = (string) $request->header('X-Razorpay-Signature', '');

        if (! Razorpay::verifyWebhookSignature($raw, $signature)) {
            return response('invalid signature', 400);
        }

        $payload = json_decode($raw, true) ?: [];

        if (($payload['event'] ?? '') !== 'payment.captured') {
            return response('ignored', 200);
        }

        $entity = $payload['payload']['payment']['entity'] ?? null;

        if (! is_array($entity) || empty($entity['order_id']) || empty($entity['id'])) {
            return response('malformed', 200);
        }

        $payment = WebsitePayment::where('gateway_order_id', $entity['order_id'])->first();

        if (! $payment) {
            return response('unknown order', 200);
        }

        $this->finalizeCapturedPayment($payment, $entity['id'], null);

        return response('ok', 200);
    }

    /** GET /booking/{token} — the guest's own link back to their booking. */
    public function confirmation(string $token): View
    {
        $payment = WebsitePayment::where('confirmation_token', $token)
            ->with(['reservation.rooms.type', 'branch.website'])
            ->firstOrFail();

        return view('site.confirmation', compact('payment'));
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * The one place a captured payment turns into a reservation — reachable
     * from both the browser callback and the webhook, and safe to call
     * twice for the same payment: the row lock below means only the first
     * caller actually creates anything, and the second is told "already
     * done" rather than creating a duplicate.
     *
     * @return array{ok: bool, already?: bool, reason?: string, payment: WebsitePayment, reservation?: Reservation}
     */
    private function finalizeCapturedPayment(WebsitePayment $payment, string $gatewayPaymentId, ?string $signature): array
    {
        $result = DB::transaction(function () use ($payment, $gatewayPaymentId, $signature) {
            /** @var WebsitePayment $locked */
            $locked = WebsitePayment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($locked->status === 'paid') {
                return ['ok' => true, 'already' => true, 'payment' => $locked, 'reservation' => $locked->reservation];
            }

            if ($locked->status === 'needs_review') {
                return ['ok' => false, 'reason' => 'needs_review', 'payment' => $locked];
            }

            $locked->gateway_payment_id = $gatewayPaymentId;
            $locked->gateway_signature = $signature;

            try {
                $reservation = BookingService::createPaidBooking($locked->booking_snapshot, $locked->branch_id, $locked);

                $locked->status = 'paid';
                $locked->reservation_id = $reservation->id;
                $locked->save();

                return ['ok' => true, 'already' => false, 'payment' => $locked, 'reservation' => $reservation];
            } catch (RoomsUnavailableException $e) {
                // Extremely rare: the rooms sold out in the seconds between
                // the order opening and the payment landing. The guest has
                // genuinely paid, so this is never treated as a failure —
                // staff are told at once and settle it by hand (see
                // config/services.php's note on this status).
                $locked->status = 'needs_review';
                $locked->save();

                Log::warning('[Website booking] payment captured but rooms sold out: payment #' . $locked->id . ' — ' . $e->getMessage());

                return ['ok' => false, 'reason' => 'needs_review', 'payment' => $locked];
            }
        });

        if ($result['ok'] && ! $result['already']) {
            $this->announceNewBooking($result['payment'], $result['reservation']);
        }

        if (! $result['ok'] && $result['reason'] === 'needs_review') {
            $this->announceNeedsReview($result['payment']);
        }

        return $result;
    }

    private function respondToFinalize(array $result): JsonResponse
    {
        if (! $result['ok']) {
            return response()->json([
                'message' => 'Your payment was received, but we could not confirm the room automatically — '
                    . 'our team will reach out shortly to confirm, and you will receive a full refund if we '
                    . 'cannot accommodate you. Your payment reference is ' . $result['payment']->gateway_payment_id . '.',
            ], 200);
        }

        return response()->json([
            'redirect' => route('site.booking.confirmation', $result['payment']->confirmation_token),
        ]);
    }

    /** The same guest WhatsApp/email confirmation and staff bell an admin-made booking sends. */
    private function announceNewBooking(WebsitePayment $payment, Reservation $reservation): void
    {
        $stay = $reservation->rooms()->with('type')->get();
        $firstStay = $stay->first();

        $nights = $firstStay
            ? Money::nights($firstStay->arrival_date->toDateString(), $firstStay->checkout_date->toDateString())
            : 0;

        $adultGuests = $stay->sum(fn ($row) => (int) $row->male + (int) $row->female);
        $childGuests = $stay->sum(fn ($row) => (int) $row->child);
        $guestCount = trim($adultGuests . ' Adults' . ($childGuests ? ', ' . $childGuests . ' Child' . ($childGuests > 1 ? 'ren' : '') : ''));
        $total = (float) $reservation->net_amount;

        GuestMessage::send('guest.booking', $reservation->mobile, [
            'guest' => $reservation->guest_name,
            'guest_email' => $reservation->email,
            'guest_phone' => $reservation->mobile,
            'reservation_no' => $reservation->reservation_no,
            'booking_date' => optional($reservation->reservation_date)->format('d M Y'),
            'arrival' => optional($stay->min('arrival_date'))->format('d M Y'),
            'arrival_time' => '12:00 PM',
            'departure' => optional($stay->max('checkout_date'))->format('d M Y'),
            'departure_time' => '11:00 AM',
            'nights' => $nights,
            'rooms' => $stay->sum('no_of_rooms'),
            'guests' => $guestCount ?: '1 Adult',
            'room_type' => (string) ($firstStay?->type?->name ?? 'Room'),
            'room_no' => 'Subject to availability',
            'meal_plan' => 'Room Only',
            'payment_mode' => 'Paid online (Razorpay)',
            'payment_status' => 'Paid',
            'amount' => '₹ ' . number_format($total, 2),
            'advance' => '₹ ' . number_format($total, 2),
            'balance' => '₹ 0.00',
        ], $reservation->branch_id);

        Notify::event('reservation.created')
            ->title('New online booking — ' . $reservation->reservation_no)
            ->body(trim($reservation->guest_name . ' · ' . $stay->sum('no_of_rooms')
                . ' room(s) · ₹ ' . number_format($total, 2) . ' · paid via website'))
            ->url(route('reservation.show', $reservation))
            ->send();
    }

    /**
     * A guest has paid but no reservation could be made — reuses the same
     * "new booking" bell (rather than a brand new notification event that
     * would need its own registration) so it reaches the same staff who
     * already watch for bookings, with a title that cannot be mistaken for
     * a routine one.
     */
    private function announceNeedsReview(WebsitePayment $payment): void
    {
        $snapshot = $payment->booking_snapshot ?? [];
        $guest = trim(($snapshot['first_name'] ?? '') . ' ' . ($snapshot['last_name'] ?? ''));

        Notify::event('reservation.created')
            ->title('⚠ Website payment needs manual review')
            ->body(trim(($guest ?: 'A guest') . ' paid ₹' . number_format((float) $payment->amount, 2)
                . ' online, but the room sold out first. Payment ref: ' . $payment->gateway_payment_id
                . '. Please contact the guest and refund via the Razorpay dashboard if the room cannot be given.'))
            ->force()
            ->send();
    }
}
