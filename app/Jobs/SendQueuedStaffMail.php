<?php

namespace App\Jobs;

use App\Mail\NotificationMail;
use App\Models\Notification\AppNotification;
use App\Models\Notification\NotificationDelivery;
use App\Support\Smtp;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends the staff-facing notification email for a delivery row that already
 * exists (see Notify::postMail()).
 *
 * Same reasoning as SendQueuedWhatsApp: an SMTP conversation used to happen
 * inline during the request (reservation created, checkout done, a shift
 * closed...); now it happens here, off the request thread, whenever a queue
 * worker is running. The delivery row is what the Notification Settings
 * screen reads, so it is still updated with the real outcome once the send
 * actually happens — not optimistically at dispatch time.
 */
class SendQueuedStaffMail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 20;

    public function __construct(
        public int $deliveryId,
        public int $notificationId,
        public string $address,
        public ?string $hotel,
    ) {}

    public function handle(): void
    {
        $delivery = NotificationDelivery::find($this->deliveryId);
        $notification = AppNotification::find($this->notificationId);

        if (! $delivery || ! $notification) {
            return;
        }

        try {
            Mail::to($this->address)->send(new NotificationMail($notification, $this->hotel));

            $delivery->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (Throwable $e) {
            $delivery->update(['status' => 'failed', 'error' => Smtp::readable($e->getMessage())]);
        }
    }
}
