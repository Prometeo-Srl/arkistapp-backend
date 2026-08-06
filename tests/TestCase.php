<?php

namespace Tests;

use App\Support\StripeGateway;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Mockery\MockInterface;

abstract class TestCase extends BaseTestCase
{
    /**
     * Swaps out the only class that talks to Stripe, so subscription tests never
     * reach the network. Returns the mock for tests that want to assert on it.
     */
    protected function fakeStripe(string $status = 'trialing', ?int $periodEnd = null): MockInterface
    {
        return $this->mock(StripeGateway::class, function (MockInterface $stripe) use ($status, $periodEnd) {
            $stripe->shouldReceive('customerFor')->andReturn('cus_test');
            $stripe->shouldReceive('priceFor')->andReturn('price_test');
            $stripe->shouldReceive('createSetupIntent')
                ->andReturn(['id' => 'seti_test', 'client_secret' => 'seti_test_secret']);
            $stripe->shouldReceive('createSubscription')->andReturn([
                'id' => 'sub_test',
                'status' => $status,
                'period_end' => $periodEnd ?? now()->addDays(30)->timestamp,
            ]);
            $stripe->shouldReceive('cancelSubscription')->andReturnNull();
        });
    }
}
