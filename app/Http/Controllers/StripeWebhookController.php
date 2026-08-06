<?php

namespace App\Http\Controllers;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Support\StripeGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;

/**
 * Stripe is the source of truth for what a subscription is doing: the trial
 * ending, a card failing, a cancellation from the dashboard. This mirrors those
 * changes onto our own rows so entitlement checks never call out to Stripe.
 */
class StripeWebhookController extends Controller
{
    public function __construct(private readonly StripeGateway $stripe) {}

    public function __invoke(Request $request)
    {
        try {
            $event = $this->stripe->parseWebhook(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
            );
        } catch (SignatureVerificationException $e) {
            // Unsigned means it did not come from Stripe: refuse, do not retry.
            return response()->json(['message' => 'Invalid signature.'], 400);
        }

        $object = $event->data->object->toArray();

        match ($event->type) {
            'customer.subscription.updated',
            'customer.subscription.deleted' => $this->syncStatus($object),
            'invoice.paid' => $this->recordPayment($object, 'paid'),
            'invoice.payment_failed' => $this->recordPayment($object, 'failed'),
            default => Log::info('Unhandled Stripe event', ['type' => $event->type]),
        };

        return response()->noContent();
    }

    /** @param array<string, mixed> $stripeSubscription */
    private function syncStatus(array $stripeSubscription): void
    {
        $subscription = $this->find($stripeSubscription['id'] ?? null);

        if (! $subscription) {
            return;
        }

        $status = SubscriptionStatus::tryFrom((string) ($stripeSubscription['status'] ?? ''));

        // A status we do not model (`incomplete`, `unpaid`, `paused`) must not
        // silently keep entitling, so it lands on past_due.
        $status ??= SubscriptionStatus::PastDue;

        $periodEnd = $stripeSubscription['trial_end']
            ?? $stripeSubscription['current_period_end']
            ?? $stripeSubscription['items']['data'][0]['current_period_end']
            ?? null;

        $subscription->update([
            'status' => $status,
            'current_period_end' => $periodEnd ? now()->setTimestamp($periodEnd) : $subscription->current_period_end,
            'canceled_at' => $status === SubscriptionStatus::Canceled
                ? ($subscription->canceled_at ?? now())
                : $subscription->canceled_at,
        ]);
    }

    /** @param array<string, mixed> $invoice */
    private function recordPayment(array $invoice, string $status): void
    {
        $subscription = $this->find($invoice['subscription'] ?? $invoice['parent']['subscription_details']['subscription'] ?? null);

        if (! $subscription) {
            return;
        }

        // Stripe retries webhooks, so key on the invoice id instead of inserting twice.
        $subscription->payments()->updateOrCreate(
            ['provider_ref' => $invoice['id']],
            [
                'amount_cents' => $invoice['amount_paid'] ?? $invoice['amount_due'] ?? 0,
                'currency' => strtoupper((string) ($invoice['currency'] ?? 'eur')),
                'status' => $status,
                'paid_at' => $status === 'paid' ? now() : null,
            ],
        );

        if ($status === 'failed') {
            $subscription->update(['status' => SubscriptionStatus::PastDue]);
        }
    }

    private function find(?string $stripeId): ?Subscription
    {
        return $stripeId ? Subscription::where('provider_ref', $stripeId)->first() : null;
    }
}
