<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Plan;
use RuntimeException;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Subscription as StripeSubscription;
use Stripe\Webhook;

/**
 * The only place that talks to Stripe. Concrete on purpose: tests swap the whole
 * object with `$this->mock(StripeGateway::class)`, so no interface is needed.
 */
class StripeGateway
{
    private ?StripeClient $client = null;

    private function client(): StripeClient
    {
        return $this->client ??= new StripeClient((string) config('services.stripe.secret'));
    }

    /** Stripe Customer for the company, created once and cached on the row. */
    public function customerFor(Company $company): string
    {
        if ($company->stripe_customer_id) {
            return $company->stripe_customer_id;
        }

        $customer = $this->client()->customers->create([
            'name' => $company->name,
            'metadata' => ['company_id' => (string) $company->getKey()],
        ]);

        $company->forceFill(['stripe_customer_id' => $customer->id])->save();

        return $customer->id;
    }

    /**
     * SetupIntent the app confirms with the card. `off_session` because the
     * recurring charges after the trial happen without the user present.
     *
     * @return array{id: string, client_secret: string}
     */
    public function createSetupIntent(string $customerId): array
    {
        $intent = $this->client()->setupIntents->create([
            'customer' => $customerId,
            'usage' => 'off_session',
            'payment_method_types' => ['card'],
        ]);

        return ['id' => $intent->id, 'client_secret' => (string) $intent->client_secret];
    }

    /** Stripe Price for the plan, created lazily so no dashboard setup is required. */
    public function priceFor(Plan $plan): string
    {
        if ($plan->stripe_price_id) {
            return $plan->stripe_price_id;
        }

        $price = $this->client()->prices->create([
            'currency' => 'eur',
            'unit_amount' => $plan->price_cents,
            'recurring' => ['interval' => $plan->billing_period === 'yearly' ? 'year' : 'month'],
            'product_data' => ['name' => 'Prometeo '.$plan->name],
            'metadata' => ['plan_code' => $plan->code],
        ]);

        $plan->forceFill(['stripe_price_id' => $price->id])->save();

        return $price->id;
    }

    /**
     * Subscription with the trial from the plan. The card is attached as the
     * default payment method, so the first invoice after the trial is charged
     * without asking again.
     *
     * Returned flat, not as a Stripe object: `current_period_end` lives on the
     * subscription in older API versions and on the item in newer ones, and the
     * caller should not have to know which.
     *
     * @return array{id: string, status: string, period_end: ?int}
     */
    public function createSubscription(
        string $customerId,
        string $priceId,
        string $paymentMethodId,
        ?int $trialDays,
    ): array {
        $this->client()->paymentMethods->attach($paymentMethodId, ['customer' => $customerId]);

        $subscription = $this->client()->subscriptions->create(array_filter([
            'customer' => $customerId,
            'items' => [['price' => $priceId]],
            'default_payment_method' => $paymentMethodId,
            'trial_period_days' => $trialDays,
        ]));

        return [
            'id' => $subscription->id,
            'status' => (string) $subscription->status,
            'period_end' => self::periodEnd($subscription),
        ];
    }

    /**
     * Trial end when trialing, otherwise the end of the paid period. Read off
     * the array form because `current_period_end` sits on the subscription in
     * older API versions and on the item in newer ones.
     */
    private static function periodEnd(StripeSubscription $subscription): ?int
    {
        $data = $subscription->toArray();

        return $data['trial_end']
            ?? $data['current_period_end']
            ?? $data['items']['data'][0]['current_period_end']
            ?? null;
    }

    public function cancelSubscription(string $subscriptionId): void
    {
        $this->client()->subscriptions->cancel($subscriptionId);
    }

    /** @throws SignatureVerificationException */
    public function parseWebhook(string $payload, string $signature): Event
    {
        $secret = (string) config('services.stripe.webhook_secret');

        // Stripe's verifier does not reject an empty secret: it HMACs with ''
        // and anyone can then forge a valid Stripe-Signature header. .env.example
        // ships this blank, so a deploy that forgets it would fail open — which
        // is the one direction a payment webhook must never fail.
        if ($secret === '') {
            throw new RuntimeException(
                'STRIPE_WEBHOOK_SECRET is not set; refusing to accept webhooks unverified.'
            );
        }

        return Webhook::constructEvent($payload, $signature, $secret);
    }
}
