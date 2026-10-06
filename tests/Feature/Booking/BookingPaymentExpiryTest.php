<?php

use App\Mail\ExpiredBookingPaymentRefunded;
use App\Mail\ExpiredBookingPaymentRefundedNotification;
use App\Models\Booking;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Stripe\HttpClient\CurlClient;

/**
 * Deliver a signed Stripe event to the webhook endpoint.
 *
 * @param  array<string, mixed>  $object
 */
function deliverStripeEvent(string $type, array $object): TestResponse
{
    config(['services.stripe.webhook_secret' => 'whsec_test_secret']);

    $payload = json_encode(['id' => 'evt_test', 'type' => $type, 'data' => ['object' => $object]]);
    $timestamp = time();
    $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", 'whsec_test_secret');

    return test()->call('POST', route('stripe.webhook'), [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
        'CONTENT_TYPE' => 'application/json',
    ], $payload);
}

/**
 * Replace Stripe's HTTP client with one that records refund requests and answers with the given status.
 */
function recordStripeRefundRequests(int $status = 200): object
{
    config(['cashier.secret' => 'sk_test_fake']);

    $client = new class($status) implements ClientInterface
    {
        /** @var list<array{url: string, params: array<string, mixed>}> */
        public array $requests = [];

        public function __construct(private int $status) {}

        public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
        {
            $this->requests[] = ['url' => $absUrl, 'params' => $params];

            return $this->status === 200
                ? ['{"id":"re_test_expired","object":"refund"}', 200, []]
                : ['{"error":{"type":"api_error","message":"Stripe is down"}}', $this->status, []];
        }
    };

    ApiRequestor::setHttpClient($client);

    return $client;
}

/**
 * A completed Stripe checkout for a booking that no longer exists.
 *
 * @return array<string, mixed>
 */
function completedCheckoutForMissingBooking(): array
{
    return [
        'id' => 'cs_test_late',
        'payment_intent' => 'pi_test_late',
        'amount_total' => 1500,
        'customer_details' => ['email' => 'anna@example.com'],
        'metadata' => ['booking_id' => 999999],
    ];
}

afterEach(function () {
    ApiRequestor::setHttpClient(CurlClient::instance());
});

test('releases an unpaid booking when its Stripe payment page expires', function () {
    $booking = Booking::factory()->create([
        'stripe_checkout_session_id' => 'cs_test_expired',
        'expires_at' => now()->addMinutes(5),
    ]);

    deliverStripeEvent('checkout.session.expired', [
        'id' => 'cs_test_expired',
        'metadata' => ['booking_id' => $booking->id],
    ])->assertOk();

    $this->assertModelMissing($booking);
});

test('keeps a paid booking when a Stripe payment page expires', function () {
    $booking = Booking::factory()->paid()->create(['stripe_checkout_session_id' => 'cs_test_expired']);

    deliverStripeEvent('checkout.session.expired', [
        'id' => 'cs_test_expired',
        'metadata' => ['booking_id' => $booking->id],
    ])->assertOk();

    $this->assertModelExists($booking);
});

test('keeps an unpaid booking when a different Stripe payment page expires', function () {
    $booking = Booking::factory()->create([
        'stripe_checkout_session_id' => 'cs_test_current',
        'expires_at' => now()->addMinutes(5),
    ]);

    deliverStripeEvent('checkout.session.expired', [
        'id' => 'cs_test_older',
        'metadata' => ['booking_id' => $booking->id],
    ])->assertOk();

    $this->assertModelExists($booking);
});

test('refunds a payment that arrives for a booking that no longer exists and tells the customer and the studio', function () {
    Mail::fake();
    SiteSetting::setGroup('contact', ['email' => ['value' => 'studija@example.com']]);
    $stripe = recordStripeRefundRequests();

    deliverStripeEvent('checkout.session.completed', completedCheckoutForMissingBooking())->assertOk();

    expect($stripe->requests)->toHaveCount(1);
    expect($stripe->requests[0]['url'])->toEndWith('/v1/refunds');
    expect($stripe->requests[0]['params'])->toBe(['payment_intent' => 'pi_test_late']);
    Mail::assertQueued(ExpiredBookingPaymentRefunded::class, fn (ExpiredBookingPaymentRefunded $mail) => $mail->hasTo('anna@example.com') && $mail->amountInCents === 1500);
    Mail::assertQueued(ExpiredBookingPaymentRefundedNotification::class, fn (ExpiredBookingPaymentRefundedNotification $mail) => $mail->hasTo('studija@example.com')
        && $mail->customerEmail === 'anna@example.com'
        && $mail->paymentIntent === 'pi_test_late');
    expect(Booking::count())->toBe(0);
});

test('tells the customer and the studio only once when Stripe repeats the event', function () {
    Mail::fake();
    SiteSetting::setGroup('contact', ['email' => ['value' => 'studija@example.com']]);
    recordStripeRefundRequests();

    deliverStripeEvent('checkout.session.completed', completedCheckoutForMissingBooking())->assertOk();
    deliverStripeEvent('checkout.session.completed', completedCheckoutForMissingBooking())->assertOk();

    Mail::assertQueuedCount(2);
});

test('asks Stripe to send the event again when the refund could not be made', function () {
    Mail::fake();
    recordStripeRefundRequests(status: 500);

    deliverStripeEvent('checkout.session.completed', completedCheckoutForMissingBooking())->assertStatus(500);

    Mail::assertNothingOutgoing();
});

test('accepts the refund notice Stripe sends for such a refunded payment', function () {
    Mail::fake();
    recordStripeRefundRequests();
    deliverStripeEvent('checkout.session.completed', completedCheckoutForMissingBooking())->assertOk();

    deliverStripeEvent('charge.refunded', ['id' => 'ch_test_late', 'payment_intent' => 'pi_test_late'])->assertOk();
});

test('still reports an unknown refunded payment as not found', function () {
    deliverStripeEvent('charge.refunded', ['id' => 'ch_test_unknown', 'payment_intent' => 'pi_test_unknown'])->assertNotFound();
});

test('customer email about a refunded late payment states the amount', function () {
    $mail = new ExpiredBookingPaymentRefunded(amountInCents: 1500);

    $mail->assertSeeInHtml('15.00 EUR');
});

test('studio email about a refunded late payment names the customer and the payment', function () {
    $mail = new ExpiredBookingPaymentRefundedNotification(customerEmail: 'anna@example.com', amountInCents: 1500, paymentIntent: 'pi_test_late');

    $mail->assertSeeInHtml('anna@example.com');
    $mail->assertSeeInHtml('15.00 EUR');
    $mail->assertSeeInHtml('pi_test_late');
});
