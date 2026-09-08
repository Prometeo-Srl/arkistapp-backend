# Handoff — a real template for transactional mail

Written 2026-09-08. Branch `develop`, last commits `c09148d` (the flow) and `4b3f008` (the
transport). Working tree clean, `sail artisan test` 214 passing, `sail pint` clean.

Nothing here is blocked on code. One item is blocked on a brand answer, see "Open question".

## Where the work stands

"modifica email" (prototype 080) ships and works end-to-end: the Flutter three-step screen
(`prometeo-flutter`, commit `837e9fa`) against `POST /auth/me/email` and
`POST /auth/me/email/verify`, with the code read out of Mailtrap's sandbox inbox.

What is *not* done is the mail itself. It is two lines of plain text produced by `Mail::raw`
in `AuthController::requestEmailChange`:

```php
Mail::raw(
    "Il codice per confermare la tua nuova email è {$code}.\n"
    .'Scade tra 10 minuti.',
    fn (Message $message) => $message->to($email)
        ->subject('Conferma la tua nuova email'),
);
```

That was deliberate — six digits and one sentence did not need a view, and there was no other
transactional mail in the app to share a layout with. The next mail changes that calculus.

## Fix this first: the mail is currently signed "Laravel"

`.env` still carries the framework default:

```
APP_NAME=Laravel
```

and `config/mail.php` composes the sender as `MAIL_FROM_NAME="${APP_NAME}"`. So every message
the app sends arrives **From: Laravel <no-reply@arkistapp.it>**. It is visible in the Mailtrap
inbox right now.

One line, in `.env` on every environment (`.env.example` cannot fix it — `APP_NAME` is not
mail-specific and the example file is not what runs):

```
APP_NAME=Prometeo
```

then `sail artisan config:clear`. Worth doing before touching templates: no amount of layout
saves a mail whose sender name is the framework.

Note the agent hook `laravel-hooks.sh guard` blocks edits to `.env`, so this is a manual step.

## Two things the template must not break

1. **`ChangeEmailTest` reads the code out of the mail body.** `mailedCode()` regexes
   `/\b(\d{6})\b/` against `getOriginalMessage()->getTextBody()` — the array transport's plain
   part. An HTML-only mailable returns null there and takes six tests with it. Send
   **multipart**: keep a text alternative (a markdown mailable gives one automatically; a plain
   Blade `view()` does not — pair it with `->text()`). If the text part is dropped on purpose,
   the test has to move to `getHtmlBody()` in the same commit.

2. **The send is inline, not queued, and must stay that way for now.** `QUEUE_CONNECTION=redis`
   but no worker is supervised — `composer dev` starts one, Sail does not — so a queued code
   would sit in Redis and never arrive. The caller pays the SMTP round trip. A heavier template
   makes `POST /auth/me/email` slower to answer, which the Flutter screen absorbs (the CTA
   disables while busy), but it is the reason not to get ambitious with remote images. Queue it
   the day a worker is supervised, and revisit then.

## Suggested shape

A **markdown mailable** is the lazy correct answer: `sail artisan make:mail EmailChangeCode
--markdown=emails.email-change-code` gives a themed, responsive, multipart mail for free, and
`sail artisan vendor:publish --tag=laravel-mail` exposes the theme CSS
(`resources/css/vendor/mail/html/themes/default.css`) for the brand colours. Point 1 above is
satisfied without thinking about it.

There is no `app/Mail/` directory yet — this is the project's first Mailable.

Existing precedent for a styled view is `resources/views/pdf/incident.blade.php`: inline `<style>`
in the template, no build step, greys from the Tailwind palette (`#6b7280`, `#e5e7eb`). Email
clients need inline CSS for the same reason Dompdf does, so the shape carries over.

Brand colours, from `prometeo-flutter/lib/core/utils/app_theme.dart` (`AppTheme`, bottom of the
file — that is the source of truth, not these copies):

| Token | Hex |
|---|---|
| `blueMarin` / floating label | `#040A50` |
| field focused border | `#3D4AED` |
| `lightSteel` | `#6796D9` |
| `mercury` | `#DCE1F3` |
| `divider` | `#E9ECFF` |
| `red` | `#FF0753` |

## Design it for the second caller, not just this one

`routes/slices/auth.php` has **no forgot-password route**, while the Flutter app already declares
`AppRoutes.forgotPassword` and a screen behind it. That flow needs the same mail: a code or a
link, the same header, the same footer, the same sender. Whatever layout lands here should be a
layout the reset can extend rather than copy — that is most of the argument for a shared
markdown theme over a one-off Blade view.

Invitations (`throttle:invitations` exists, the tenancy slice issues them) are a third caller
whenever they start mailing.

## Open question — which brand goes in the email?

The app is **Prometeo** in every namespace, docblock and route in this repo. But the sending
address is `no-reply@arkistapp.it`, and the Flutter contact screen
(`lib/features/profile/presentation/pages/contact_details_page.dart`) prints `info@arkistapp.it`
and `www.arkistapp.it` as the customer-facing contacts.

So a user receiving this mail sees a name that appears nowhere they have ever looked. Needs a
client/designer answer before a logo or a footer is written:

- Which name signs the mail — Prometeo, Arkist, or the client's own?
- Which logo? **The backend repo has no logo asset** — only `public/favicon.ico`. The artwork
  lives in the Flutter repo (`lib/gen/assets.gen.dart`). An email needs it as an absolute
  `https://` URL on a host that will still be up in a year, or as a CID attachment on every
  send. Neither exists yet.
- What footer does the client want — company address, a support address, an unsubscribe line?
  (Transactional mail needs no unsubscribe, but Italian B2B clients often ask.)

Until that is answered, a themed markdown mail with the correct sender name and no logo is
strictly better than what is there now, and does not paint the wrong brand into a template.

## Commands

```bash
./vendor/bin/sail artisan make:mail EmailChangeCode --markdown=emails.email-change-code
./vendor/bin/sail artisan vendor:publish --tag=laravel-mail   # theme CSS, optional
./vendor/bin/sail artisan test --filter=ChangeEmailTest       # the six tests that read the body
./vendor/bin/sail pint
```

Mailtrap's sandbox inbox has an HTML check and a spam score per message — the closest thing to
deliverability feedback available before a real sending domain exists.
