<?php

namespace App\Support\Website;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * A small, direct client for the two Razorpay calls this app needs — create
 * an order, and check whether a payment for it is genuine.
 *
 * No razorpay/razorpay Composer package. This machine's booking pipeline has
 * no way to run `composer install` after this code is deployed, so pulling
 * in a package here would ship code that could never actually load. Razorpay's
 * own API is small enough not to need it: creating an order is one POST with
 * Basic Auth, and verifying a payment is one line of hash_hmac — both of
 * which Laravel already has everything for.
 */
class Razorpay
{
    public static function isConfigured(): bool
    {
        return filled(config('services.razorpay.key_id')) && filled(config('services.razorpay.key_secret'));
    }

    public static function keyId(): ?string
    {
        return config('services.razorpay.key_id');
    }

    /**
     * Open an order for this amount. Razorpay wants the amount as an integer
     * number of paise, not rupees — ₹1,234.50 is 123450.
     *
     * @return array{id: string, amount: int, currency: string}
     *
     * @throws \RuntimeException when Razorpay refuses the request
     */
    public static function createOrder(float $amountRupees, string $receipt, string $currency = 'INR'): array
    {
        $response = Http::withBasicAuth(config('services.razorpay.key_id'), config('services.razorpay.key_secret'))
            ->asJson()
            ->timeout(15)
            ->post('https://api.razorpay.com/v1/orders', [
                'amount' => (int) round($amountRupees * 100),
                'currency' => $currency,
                // Razorpay requires this unique per order; the website_payments
                // row id is not known yet at this point, so a random receipt
                // tag is used instead and the real link back to that row is
                // gateway_order_id, stored the moment the order comes back.
                'receipt' => $receipt,
                'payment_capture' => 1,
            ]);

        if ($response->failed()) {
            $message = $response->json('error.description') ?: 'Razorpay did not accept the order.';

            throw new \RuntimeException('Razorpay: ' . $message);
        }

        return $response->json();
    }

    /**
     * The signature Razorpay's Checkout.js hands back after a successful
     * payment, checked against the order + payment ids using the account's
     * own secret. This is what proves the payment is real and was not typed
     * into the browser by hand — nobody without the key secret can produce a
     * signature that passes this check.
     */
    public static function verifyPaymentSignature(string $orderId, string $paymentId, string $signature): bool
    {
        if (! self::isConfigured()) {
            return false;
        }

        $expected = hash_hmac('sha256', $orderId . '|' . $paymentId, (string) config('services.razorpay.key_secret'));

        return hash_equals($expected, $signature);
    }

    /**
     * The signature Razorpay sends on a webhook POST, checked against the
     * separate webhook secret (Settings → Webhooks in the Razorpay
     * dashboard — not the same secret as the API key). Optional: a hotel
     * that has not set one up yet simply never has a webhook to verify, and
     * the browser-side confirmation in BookingController::verify() is what
     * confirms every booking either way.
     */
    public static function verifyWebhookSignature(string $rawPayload, string $signature): bool
    {
        $secret = config('services.razorpay.webhook_secret');

        if (blank($secret)) {
            return false;
        }

        $expected = hash_hmac('sha256', $rawPayload, (string) $secret);

        return hash_equals($expected, $signature);
    }

    /** A short, unique-enough tag for the `receipt` field above. */
    public static function receiptFor(int $branchId): string
    {
        return 'web-' . $branchId . '-' . now()->format('YmdHis') . '-' . Str::lower(Str::random(6));
    }
}
