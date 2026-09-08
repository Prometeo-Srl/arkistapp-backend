<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The one-time code that proves a new address before it becomes the account's
 * own (prototype 080, step one).
 *
 * Markdown rather than a plain Blade view: it renders a themed HTML part and a
 * plain-text alternative from the same source, and `ChangeEmailTest` reads the
 * code out of the text part. The text view is overridden explicitly so the
 * enlarged code markup in the HTML does not leak tags into it.
 *
 * Not queueable on purpose — the caller sends it inline. See the note in
 * [AuthController::requestEmailChange].
 */
class EmailChangeCode extends Mailable
{
    use HasEmailLogo, Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public int $expiresInMinutes,
    ) {
        $this->embedLogo();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Conferma la tua nuova email',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.email-change-code',
            text: 'emails.email-change-code-text',
        );
    }
}
