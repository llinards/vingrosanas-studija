<?php

use App\Actions\RetrieveStripeCheckoutSessionState;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Stripe\HttpClient\CurlClient;

/**
 * Make Stripe answer every request with the given body and status.
 */
function stripeAnswersWith(string $body, int $status = 200): void
{
    config(['cashier.secret' => 'sk_test_fake']);

    ApiRequestor::setHttpClient(new class($body, $status) implements ClientInterface
    {
        public function __construct(private string $body, private int $status) {}

        public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
        {
            return [$this->body, $this->status, []];
        }
    });
}

afterEach(function () {
    ApiRequestor::setHttpClient(CurlClient::instance());
});

test('reports whether a Stripe payment page was paid', function () {
    stripeAnswersWith('{"id":"cs_test_1","object":"checkout.session","status":"complete","payment_status":"paid","payment_intent":"pi_test_1"}');

    $state = app(RetrieveStripeCheckoutSessionState::class)->execute('cs_test_1');

    expect($state)->toBe(['status' => 'complete', 'payment_status' => 'paid', 'payment_intent' => 'pi_test_1']);
});

test('reports an expired unpaid Stripe payment page', function () {
    stripeAnswersWith('{"id":"cs_test_1","object":"checkout.session","status":"expired","payment_status":"unpaid","payment_intent":null}');

    $state = app(RetrieveStripeCheckoutSessionState::class)->execute('cs_test_1');

    expect($state)->toBe(['status' => 'expired', 'payment_status' => 'unpaid', 'payment_intent' => null]);
});

test('reports nothing when Stripe cannot be reached', function () {
    stripeAnswersWith('{"error":{"type":"api_error","message":"Stripe is down"}}', 500);

    $state = app(RetrieveStripeCheckoutSessionState::class)->execute('cs_test_1');

    expect($state)->toBeNull();
});
