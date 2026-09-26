<?php

namespace App\Mail;

use App\Models\Notification\AppNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The email a notification turns into.
 *
 * Plain and short on purpose. This goes to a manager's phone at eleven at
 * night; it needs to say what happened and link to the screen, and anything
 * more is something they have to scroll past.
 */
class NotificationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public AppNotification $notification,
        public ?string $hotel = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: trim(($this->hotel ? $this->hotel . ' · ' : '') . $this->notification->title),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.notification');
    }
}
