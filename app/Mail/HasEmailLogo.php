<?php

namespace App\Mail;

use Symfony\Component\Mime\Email;

/**
 * Embeds the wordmark the published `mail::header` component renders as
 * `cid:logo`.
 *
 * Embedded rather than linked because there is no public host serving
 * `public/images/` yet, and because remote images are blocked by default in
 * most clients even once there is one. Costs ~17KB per send.
 *
 * Blade components do not inherit the parent view's data, so `$message->embed()`
 * is unreachable from inside the header component — the Content-ID has to be
 * fixed here instead.
 */
trait HasEmailLogo
{
    protected function embedLogo(): void
    {
        $this->withSymfonyMessage(
            fn (Email $message) => $message->embedFromPath(
                public_path('images/logo-email.png'),
                'logo',
            ),
        );
    }
}
