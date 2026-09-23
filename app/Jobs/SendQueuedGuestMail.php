<?php

namespace App\Jobs;

use App\Mail\GuestMail;
use App\Models\Notification\NotificationDelivery;
use App\Support\GuestMessage;
use App\Support\Smtp;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends the guest's own confirmation email (with the PDF attached) for a
 * delivery row that already exists (see GuestMessage::mail()).
 *
 * This is the other half of a booking/check-in/checkout save that used to
 * wait on an SMTP round trip before the page could respond. The PDF and the
 * template text are resolved once, synchronously, when the row is created —
 * cheap, no network involved — and only the actual send happens here.
 */
class SendQueuedGuestMail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 20;

    /**
     * @param  array<string, mixed>  $definition  one entry from config/guest-documents.php
     * @param  array<string, mixed>  $payload  the placeholders, already resolved
     * @param  array{path: string, token: string, name: string}|null  $document
     */
    public function __construct(
        public int $deliveryId,
        public string $email,
        public array $definition,
        public array $payload,
        public ?array $document,
        public string $hotelName,
    ) {}

    public function handle(): void
    {
        $delivery = NotificationDelivery::find($this->deliveryId);

        if (! $delivery) {
            return;
        }

        try {
            Mail::to($this->email)->send(new GuestMail($this->definition, $this->payload, $this->document, $this->hotelName));

            GuestMessage::markSent($delivery, 'sent');
        } catch (Throwable $e) {
            GuestMessage::markFailed($delivery, Smtp::readable($e->getMessage()));
        }
    }
}
