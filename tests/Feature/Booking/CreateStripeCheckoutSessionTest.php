<?php

use App\Actions\CreateStripeCheckoutSession;
use App\Models\Booking;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Stripe\HttpClient\CurlClient;

/**
 * Replace Stripe's HTTP client with one that records what it is asked to create.
 */
function recordStripeCheckoutRequests(): object
{
    config(['cashier.secret' => 'sk_test_fake']);

    $client = new class implements ClientInterface
    {
        /** @var list<array<string, mixed>> */
        public array $requests = [];

        public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
        {
            $this->requests[] = $params;

            return ['{"id":"cs_test_recorded","object":"checkout.session","url":"https://checkout.stripe.com/c/pay/cs_test_recorded"}', 200, []];
        }
    };

    ApiRequestor::setHttpClient($client);

    return $client;
}

afterEach(function () {
    ApiRequestor::setHttpClient(CurlClient::instance());
});

test('asks Stripe to show the payment page in Latvian', function () {
    $stripe = recordStripeCheckoutRequests();
    $booking = Booking::factory()->create();

    app(CreateStripeCheckoutSession::class)->execute($booking, 1500);

    expect($stripe->requests)->toHaveCount(1);
    expect($stripe->requests[0]['locale'] ?? null)->toBe('lv');
});

test('gives the customer 30 minutes to pay on the Stripe page', function () {
    $this->travelTo('2026-10-05 09:00:00');
    $stripe = recordStripeCheckoutRequests();
    $booking = Booking::factory()->create();

    app(CreateStripeCheckoutSession::class)->execute($booking, 1500);

    expect($stripe->requests[0]['expires_at'])->toBe(now()->setTime(9, 30)->timestamp);
});
