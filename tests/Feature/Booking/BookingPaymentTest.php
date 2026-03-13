<?php

use App\Enums\PaymentStatus;
use App\Mail\BookingConfirmation;
use App\Mail\NewBookingNotification;
use App\Models\Booking;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
});

test('booking model can be marked as paid', function () {
    $booking = Booking::factory()->create([
        'payment_status' => PaymentStatus::Pending,
        'expires_at' => now()->addMinutes(30),
    ]);

    $booking->markAsPaid('pi_test_123');

    expect($booking->fresh())
        ->payment_status->toBe(PaymentStatus::Paid)
        ->payment_reference->toBe('pi_test_123')
        ->expires_at->toBeNull();
});

test('booking model correctly identifies pending payment status', function () {
    $pendingBooking = Booking::factory()->create([
        'payment_status' => PaymentStatus::Pending,
        'expires_at' => now()->addMinutes(30),
    ]);

    $expiredBooking = Booking::factory()->create([
        'payment_status' => PaymentStatus::Pending,
        'expires_at' => now()->subMinutes(5),
    ]);

    $paidBooking = Booking::factory()->create([
        'payment_status' => PaymentStatus::Paid,
        'expires_at' => null,
    ]);

    expect($pendingBooking->isPendingPayment())->toBeTrue();
    expect($expiredBooking->isPendingPayment())->toBeFalse();
    expect($paidBooking->isPendingPayment())->toBeFalse();
});

test('booking model correctly identifies expired status', function () {
    $pendingBooking = Booking::factory()->create([
        'payment_status' => PaymentStatus::Pending,
        'expires_at' => now()->addMinutes(30),
    ]);

    $expiredBooking = Booking::factory()->create([
        'payment_status' => PaymentStatus::Pending,
        'expires_at' => now()->subMinutes(5),
    ]);

    expect($pendingBooking->isExpired())->toBeFalse();
    expect($expiredBooking->isExpired())->toBeTrue();
});

test('booking success page displays booking details', function () {
    $booking = Booking::factory()->paid()->create([
        'stripe_checkout_session_id' => 'cs_test_123',
    ]);

    $this->get(route('booking.success', ['booking' => $booking, 'session_id' => 'cs_test_123']))
        ->assertSuccessful()
        ->assertSee($booking->schedule->service->name);
});

test('booking success page shows processing message for pending bookings', function () {
    $booking = Booking::factory()->create([
        'payment_status' => PaymentStatus::Pending,
        'stripe_checkout_session_id' => 'cs_test_456',
    ]);

    $this->get(route('booking.success', ['booking' => $booking, 'session_id' => 'cs_test_456']))
        ->assertSuccessful()
        ->assertSee(__('Maksājums tiek apstrādāts'));
});

test('expire pending bookings command deletes expired bookings', function () {
    // Create an expired booking
    $expiredBooking = Booking::factory()->create([
        'payment_status' => PaymentStatus::Pending,
        'expires_at' => now()->subMinutes(5),
    ]);

    // Create a non-expired booking
    $activeBooking = Booking::factory()->create([
        'payment_status' => PaymentStatus::Pending,
        'expires_at' => now()->addMinutes(25),
    ]);

    // Create a paid booking
    $paidBooking = Booking::factory()->paid()->create();

    $this->artisan('bookings:expire-pending')
        ->assertSuccessful();

    $this->assertDatabaseMissing('bookings', ['id' => $expiredBooking->id]);
    $this->assertDatabaseHas('bookings', ['id' => $activeBooking->id]);
    $this->assertDatabaseHas('bookings', ['id' => $paidBooking->id]);
});

test('stripe webhook requires valid signature', function () {
    $this->post(route('stripe.webhook'), [], [
        'Stripe-Signature' => 'invalid',
    ])->assertStatus(400);
});

test('stripe webhook handles checkout session completed event', function () {
    $booking = Booking::factory()->create([
        'payment_status' => PaymentStatus::Pending,
        'stripe_checkout_session_id' => 'cs_test_123',
        'expires_at' => now()->addMinutes(30),
    ]);

    // Build the payload
    $payload = json_encode([
        'id' => 'evt_test_123',
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'id' => 'cs_test_123',
                'payment_intent' => 'pi_test_456',
                'metadata' => [
                    'booking_id' => $booking->id,
                ],
            ],
        ],
    ]);

    // Generate a valid signature
    $timestamp = time();
    $secret = config('services.stripe.webhook_secret');

    // Skip test if no webhook secret configured
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
        ->payment_status->toBe(PaymentStatus::Paid)
        ->payment_reference->toBe('pi_test_456');

    Mail::assertQueued(BookingConfirmation::class);
    Mail::assertQueued(NewBookingNotification::class);
});
