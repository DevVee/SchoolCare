<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * An email the assistant prepared and a staff member confirmed
 * (App\Services\Coco\Actions\SendEmail). Sent right away, never queued, so
 * the card can say whether it went out. Uses the branded mail layout.
 */
class AssistantMessageMail extends Mailable
{
    use Queueable;

    public function __construct(
        public string $subjectLine,
        public string $body,
        public string $senderName,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.assistant-message', with: [
            // Paragraphs split on blank lines; single line breaks are kept inside each.
            'paragraphs' => array_values(array_filter(
                array_map('trim', preg_split('/\R\s*\R/u', trim($this->body)) ?: []),
                fn ($p) => $p !== ''
            )),
        ]);
    }
}
