<?php

use App\Actions\RefundBooking;
use App\Enums\PaymentStatus;
use App\Exceptions\RefundNotAllowedException;
use App\Mail\BookingRefunded;
use App\Models\Booking;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Stripe\HttpClient\CurlClient;

beforeEach(function () {
    Mail::fake();
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('booking is refundable when paid and more than 24 hours before service', function () {
    $schedule = Schedule::factory()->create([
        'start_time' => '10:00',
    ]);

    $booking = Booking::factory()->create([
        'schedule_id' => $schedule->id,
        'booking_date' => now()->addDays(3)->toDateString(),
        'payment_status' => PaymentStatus::Paid,
        'payment_reference' => 'pi_test_123',
    ]);

    expect($booking->isRefundable())->toBeTrue();
});

test('booking is not refundable when less than 24 hours before service', function () {
    $schedule = Schedule::factory()->create([
        'start_time' => now()->addHours(12)->format('H:i'),
    ]);

    $booking = Booking::factory()->create([
        'schedule_id' => $schedule->id,
        'booking_date' => now()->toDateString(),
        'payment_status' => PaymentStatus::Paid,
        'payment_reference' => 'pi_test_123',
    ]);

    expect($booking->isRefundable())->toBeFalse();
});

test('booking is not refundable when not paid', function () {
    $schedule = Schedule::factory()->create([
        'start_time' => '10:00',
    ]);

    $booking = Booking::factory()->create([
        'schedule_id' => $schedule->id,
        'booking_date' => now()->addDays(3)->toDateString(),
        'payment_status' => PaymentStatus::Pending,
    ]);

    expect($booking->isRefundable())->toBeFalse();
});

test('booking is not refundable when already refunded', function () {
    $schedule = Schedule::factory()->create([
        'start_time' => '10:00',
    ]);

    $booking = Booking::factory()->create([
        'schedule_id' => $schedule->id,
        'booking_date' => now()->addDays(3)->toDateString(),
        'payment_status' => PaymentStatus::Refunded,
        'payment_reference' => 'pi_test_123',
        'refund_reference' => 're_test_123',
        'refunded_at' => now(),
    ]);

    expect($booking->isRefundable())->toBeFalse();
});

test('booking is not refundable without payment reference', function () {
    $schedule = Schedule::factory()->create([
        'start_time' => '10:00',
    ]);

    $booking = Booking::factory()->create([
        'schedule_id' => $schedule->id,
        'booking_date' => now()->addDays(3)->toDateString(),
        'payment_status' => PaymentStatus::Paid,
        'payment_reference' => null,
    ]);

    expect($booking->isRefundable())->toBeFalse();
});

test('markAsRefunded updates booking correctly', function () {
    $booking = Booking::factory()->paid()->create([
        'payment_reference' => 'pi_test_123',
    ]);

    $booking->markAsRefunded('re_test_456');

    expect($booking->fresh())
        ->payment_status->toBe(PaymentStatus::Refunded)
        ->refund_reference->toBe('re_test_456')
        ->refunded_at->not->toBeNull();
});

test('getServiceDateTime returns correct datetime', function () {
    $schedule = Schedule::factory()->create([
        'start_time' => '14:30',
    ]);

    $booking = Booking::factory()->create([
        'schedule_id' => $schedule->id,
        'booking_date' => '2026-03-15',
    ]);

    $serviceDateTime = $booking->getServiceDateTime();

    expect($serviceDateTime->format('Y-m-d H:i'))->toBe('2026-03-15 14:30');
});

test('refund action throws exception when booking is not refundable', function () {
    $schedule = Schedule::factory()->create([
        'start_time' => now()->addHours(12)->format('H:i'),
    ]);

    $booking = Booking::factory()->create([
        'schedule_id' => $schedule->id,
        'booking_date' => now()->toDateString(),
        'payment_status' => PaymentStatus::Paid,
        'payment_reference' => 'pi_test_123',
    ]);

    app(RefundBooking::class)->execute($booking);
})->throws(RefundNotAllowedException::class);

test('booking refunded email contains correct data', function () {
    $booking = Booking::factory()->create([
        'payment_status' => PaymentStatus::Refunded,
        'refund_reference' => 're_test_123',
        'refunded_at' => now(),
    ]);
    $booking->load('schedule.service.coach');

    $mailable = new BookingRefunded($booking);

    $mailable->assertSeeInHtml($booking->schedule->service->name);
    $mailable->assertSeeInHtml($booking->schedule->service->coach->name);
    $mailable->assertSeeInHtml($booking->booking_date->format('d.m.Y'));
});

test('stripe charge.refunded webhook marks booking as refunded and sends email', function () {
    $booking = Booking::factory()->paid()->create([
        'payment_reference' => 'pi_test_webhook_refund',
    ]);

    $payload = json_encode([
        'id' => 'evt_test_refund',
        'type' => 'charge.refunded',
        'data' => [
            'object' => [
                'id' => 'ch_test_123',
                'payment_intent' => 'pi_test_webhook_refund',
                'refunds' => [
                    'data' => [
                        ['id' => 're_test_webhook_789'],
                    ],
                ],
            ],
        ],
    ]);

    $timestamp = time();
    $secret = config('services.stripe.webhook_secret');

    if (empty($secret)) {
        $this->markTestSkipped('Stripe webhook secret not configured');
    }

    $signedPayload = "{$timestamp}.{$payload}";
    $signature = hash_hmac('sha256', $signedPayload, $secret);

    $this->postJson(route('stripe.webhook'), json_decode($payload, true), [
        'Stripe-Signature' => "t={$timestamp},v1={$signature}",
    ])->assertSuccessful();

    $booking->refresh();

    expect($booking)
        ->payment_status->toBe(PaymentStatus::Refunded)
        ->refund_reference->toBe('re_test_webhook_789')
        ->refunded_at->not->toBeNull();

    Mail::assertQueued(BookingRefunded::class, function ($mail) use ($booking) {
        return $mail->hasTo($booking->email);
    });
});

test('stripe charge.refunded webhook skips already refunded bookings', function () {
    $booking = Booking::factory()->create([
        'payment_status' => PaymentStatus::Refunded,
        'payment_reference' => 'pi_test_already_refunded',
        'refund_reference' => 're_existing',
        'refunded_at' => now(),
    ]);

    $payload = json_encode([
        'id' => 'evt_test_refund_2',
        'type' => 'charge.refunded',
        'data' => [
            'object' => [
                'id' => 'ch_test_456',
                'payment_intent' => 'pi_test_already_refunded',
                'refunds' => [
                    'data' => [
                        ['id' => 're_test_duplicate'],
                    ],
                ],
            ],
        ],
    ]);

    $timestamp = time();
    $secret = config('services.stripe.webhook_secret');

    if (empty($secret)) {
        $this->markTestSkipped('Stripe webhook secret not configured');
    }

    $signedPayload = "{$timestamp}.{$payload}";
    $signature = hash_hmac('sha256', $signedPayload, $secret);

    $this->postJson(route('stripe.webhook'), json_decode($payload, true), [
        'Stripe-Signature' => "t={$timestamp},v1={$signature}",
    ])->assertSuccessful();

    // Refund reference should remain the original one
    expect($booking->fresh()->refund_reference)->toBe('re_existing');

    Mail::assertNotQueued(BookingRefunded::class);
});

/**
 * Replace Stripe's HTTP client with one that records refund requests and answers with the given status.
 */
function recordStripeRefundsFromAdmin(int $status = 200): object
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
                ? ['{"id":"re_test_admin","object":"refund"}', 200, []]
                : ['{"error":{"type":"api_error","message":"Stripe is down"}}', $this->status, []];
        }
    };

    ApiRequestor::setHttpClient($client);

    return $client;
}

/**
 * A booking a customer paid through Stripe, for a class starting at the given moment.
 */
function stripePaidBookingStartingAt(Carbon $start): Booking
{
    $schedule = Schedule::factory()->create([
        'day_of_week' => $start->dayOfWeekIso,
        'start_time' => $start->format('H:i'),
    ]);

    return Booking::factory()->paid()->create([
        'schedule_id' => $schedule->id,
        'booking_date' => $start->toDateString(),
        'payment_reference' => 'pi_test_admin',
        'email' => 'anna@example.com',
        'phone' => '+371 20000000',
    ]);
}

afterEach(function () {
    ApiRequestor::setHttpClient(CurlClient::instance());
});

test('edit screen has no separate refund button', function () {
    $booking = stripePaidBookingStartingAt(now()->addDays(3));

    Livewire::test('booking.booking-edit', ['booking' => $booking])
        ->assertDontSee('Veikt atmaksu')
        ->assertDontSee('Atmaksa ir iespējama');
});

test('refunds through Stripe when a Stripe-paid booking is saved as refunded, whenever the class is', function (Closure $start) {
    $this->travelTo('2026-10-07 12:00:00');
    $stripe = recordStripeRefundsFromAdmin();
    $booking = stripePaidBookingStartingAt($start());

    Livewire::test('booking.booking-edit', ['booking' => $booking])
        ->set('payment_status', 'refunded')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.bookings.index'));

    expect($stripe->requests)->toHaveCount(1);
    expect($stripe->requests[0]['url'])->toEndWith('/v1/refunds');
    expect($stripe->requests[0]['params'])->toBe(['payment_intent' => 'pi_test_admin']);
    expect($booking->fresh())
        ->payment_status->toBe(PaymentStatus::Refunded)
        ->refund_reference->toBe('re_test_admin')
        ->refunded_at->not->toBeNull();
    Mail::assertQueued(BookingRefunded::class, fn (BookingRefunded $mail) => $mail->hasTo('anna@example.com'));
})->with([
    'three days ahead' => [fn () => Carbon::parse('2026-10-10 18:00:00')],
    'two hours ahead' => [fn () => Carbon::parse('2026-10-07 14:00:00')],
    'yesterday' => [fn () => Carbon::parse('2026-10-06 18:00:00')],
]);

test('shows before saving that the customer will be refunded through Stripe', function () {
    $booking = stripePaidBookingStartingAt(now()->addDays(3));

    Livewire::test('booking.booking-edit', ['booking' => $booking])
        ->assertDontSee('Saglabājot klientam caur Stripe tiks atmaksāta pilna summa.')
        ->set('payment_status', 'refunded')
        ->assertSee('Saglabājot klientam caur Stripe tiks atmaksāta pilna summa.');
});

test('only changes the status when a booking not paid through Stripe is saved as refunded', function () {
    $stripe = recordStripeRefundsFromAdmin();
    $booking = Booking::factory()->paid()->create([
        'booking_date' => now()->addDays(3)->toDateString(),
        'payment_reference' => null,
    ]);

    Livewire::test('booking.booking-edit', ['booking' => $booking])
        ->set('payment_status', 'refunded')
        ->assertDontSee('Saglabājot klientam caur Stripe tiks atmaksāta pilna summa.')
        ->call('save')
        ->assertHasNoErrors();

    expect($stripe->requests)->toBe([]);
    expect($booking->fresh()->payment_status)->toBe(PaymentStatus::Refunded);
    Mail::assertNotQueued(BookingRefunded::class);
});

test('does not refund when other details of a Stripe-paid booking are saved', function () {
    $stripe = recordStripeRefundsFromAdmin();
    $booking = stripePaidBookingStartingAt(now()->addDays(3));

    Livewire::test('booking.booking-edit', ['booking' => $booking])
        ->set('phone', '+371 29999999')
        ->call('save')
        ->assertHasNoErrors();

    expect($stripe->requests)->toBe([]);
    expect($booking->fresh())
        ->payment_status->toBe(PaymentStatus::Paid)
        ->phone->toBe('+371 29999999');
});

test('saves nothing when Stripe cannot make the refund', function () {
    recordStripeRefundsFromAdmin(status: 500);
    $booking = stripePaidBookingStartingAt(now()->addDays(3));

    Livewire::test('booking.booking-edit', ['booking' => $booking])
        ->set('payment_status', 'refunded')
        ->set('phone', '+371 29999999')
        ->call('save')
        ->assertNoRedirect();

    expect($booking->fresh())
        ->payment_status->toBe(PaymentStatus::Paid)
        ->refund_reference->toBeNull()
        ->phone->toBe('+371 20000000');
    Mail::assertNotQueued(BookingRefunded::class);
});

test('does not let a booking refunded through Stripe be switched to another payment status', function () {
    $stripe = recordStripeRefundsFromAdmin();
    $booking = Booking::factory()->refunded()->create(['booking_date' => now()->addDays(3)->toDateString()]);

    Livewire::test('booking.booking-edit', ['booking' => $booking])
        ->set('payment_status', 'paid')
        ->call('save')
        ->assertHasErrors('payment_status')
        ->assertNoRedirect();

    expect($booking->fresh()->payment_status)->toBe(PaymentStatus::Refunded);
    expect($stripe->requests)->toBe([]);
});

test('still saves other details of a booking that was refunded through Stripe', function () {
    $booking = Booking::factory()->refunded()->create(['booking_date' => now()->addDays(3)->toDateString()]);

    Livewire::test('booking.booking-edit', ['booking' => $booking])
        ->set('phone', '+371 29999999')
        ->call('save')
        ->assertHasNoErrors();

    expect($booking->fresh()->phone)->toBe('+371 29999999');
});

test('refund action still refuses a customer refund within 24 hours but lets the studio refund', function () {
    $this->travelTo('2026-10-07 12:00:00');
    recordStripeRefundsFromAdmin();
    $booking = stripePaidBookingStartingAt(Carbon::parse('2026-10-07 14:00:00'));

    expect(fn () => app(RefundBooking::class)->execute($booking))->toThrow(RefundNotAllowedException::class);

    app(RefundBooking::class)->execute($booking, enforceCancellationWindow: false);

    expect($booking->fresh()->payment_status)->toBe(PaymentStatus::Refunded);
});
