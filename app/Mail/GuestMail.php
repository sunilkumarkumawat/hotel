<?php

namespace App\Mail;

use App\Support\GuestDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The guest's own document, sent to their inbox with the PDF attached.
 *
 * This is the half of the pair that works everywhere. WhatsApp is handed a link
 * and goes and fetches the file, so it needs this installation to be reachable
 * from the internet; email carries the bytes itself, so it works from a laptop
 * on a hotel's own wifi exactly as it does from a server. A hotel that is not
 * online yet still sends its guests a proper confirmation.
 *
 * The body says the same things as the PDF, laid out as an email rather than as
 * a page, and both are built from the one definition in
 * config/guest-documents.php — so the wording is changed in one place and the
 * two can never drift apart.
 */
class GuestMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $definition  one entry from config/guest-documents.php
     * @param  array<string, mixed>  $data  the placeholders, already resolved
     * @param  array{path: string, token: string, name: string}|null  $document
     */
    public function __construct(
        private array $definition,
        private array $data,
        private ?array $document,
        private string $hotel,
    ) {
    }

    public function envelope(): Envelope
    {
        $title = (string) ($this->definition['title'] ?? 'Your booking');
        $reference = trim((string) ($this->data['__reference'] ?? ''));

        return new Envelope(
            // "Booking Confirmation RSV-1-0001 · Rukmani Palace" — the subject
            // line a guest can find again by searching their inbox for the
            // number the hotel keeps quoting at them.
            subject: trim($title . ($reference !== '' ? ' ' . $reference : '')) . ' · ' . $this->hotel,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.guest', with: [
            'definition' => $this->definition,
            'data' => $this->data,
            'hotel' => $this->hotel,
            'attached' => $this->document !== null,
        ]);
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        /*
         * A missing file is not an error worth failing the email over — the
         * body says everything the PDF says. It only stops being attached.
         */
        if (! $this->document || ! is_file($this->document['path'])) {
            return [];
        }

        return [
            Attachment::fromPath($this->document['path'])
                ->as($this->document['name'])
                ->withMime('application/pdf'),
        ];
    }
}
