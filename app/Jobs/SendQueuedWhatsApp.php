<?php

namespace App\Jobs;

use App\Models\Notification\NotificationDelivery;
use App\Support\GuestMessage;
use App\Support\WhatsApp;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Places one WhatsApp call for a delivery row that already exists.
 *
 * Added so a save (booking, check-in, checkout, a POS bill, a staff alert...)
 * never has to sit and wait on the Chatway gateway before the page can
 * respond — that wait used to happen inline, right inside the web request,
 * bounded only by WHATSAPP_TIMEOUT (12 seconds by default). Moving the actual
 * network call here means the request that triggered it returns immediately;
 * this job runs afterwards, whenever `php artisan queue:work` next picks it
 * up.
 *
 * Behaviour is unchanged from the old inline code — same gateway call, same
 * `notification_deliveries` row, same "logged" handling via
 * GuestMessage::markSent()/markFailed() — only *when* it runs is different.
 * If no worker is running, messages simply wait in the `jobs` table instead
 * of being lost; nothing here silently drops a message.
 */
class SendQueuedWhatsApp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** A gateway hiccup gets two retries before the row is left as failed. */
    public int $tries = 3;

    public int $backoff = 20;

    public function __construct(
        public int $deliveryId,
        public string $mobile,
        public string $message,
        public ?string $fileUrl = null,
        public ?string $fileName = null,
    ) {}

    public function handle(): void
    {
        $delivery = NotificationDelivery::find($this->deliveryId);

        if (! $delivery) {
            return;
        }

        try {
            GuestMessage::markSent(
                $delivery,
                WhatsApp::send($this->mobile, $this->message, $this->fileUrl, $this->fileName)
            );
        } catch (Throwable $e) {
            GuestMessage::markFailed($delivery, $e->getMessage());
        }
    }
}
