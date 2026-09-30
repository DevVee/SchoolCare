<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The 6-digit email sign-in code (App\Services\SignInCodes). Sent right away,
 * never queued, so a delivery failure is known before the verify page shows.
 * Uses the branded mail layout (resources/views/vendor/mail).
 */
class SignInCodeMail extends Mailable
{
    use Queueable;

    public function __construct(
        public string $code,
        public int $minutes,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your sign-in code');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.sign-in-code');
    }
}
