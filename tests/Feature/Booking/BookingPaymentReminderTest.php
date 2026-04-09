<?php

use App\Actions\RetrieveStripeCheckoutUrl;
use App\Enums\PaymentStatus;
use App\Mail\BookingPaymentReminder;
use App\Models\Booking;
use App\Models\Membership;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();

    $this->mock(RetrieveStripeCheckoutUrl::class)
        ->shouldReceive('execute')
        ->andReturn('https://checkout.stripe.com/test');
});

test('reminder is sent for booking in the 10-minute window', function () {
    $booking = Booking::factory()->create([
        'payment_status' => PaymentStatus::Pending,
        'expires_at' => now()->addMinutes(8),
        'stripe_checkout_session_id' => 'cs_test_123',
    ]);

    $this->artisan('payments:send-reminders')->assertSuccessful();

    Mail::assertQueued(BookingPaymentReminder::class, function ($mail) use ($booking) {
        return $mail->hasTo($booking->email);
    });

    expect($booking->fresh()->payment_reminder_sent_at)->not->toBeNull();
});

test('reminder is not sent if already sent', function () {
    Booking::factory()->create([
        'payment_status' => PaymentStatus::Pending,
        'expires_at' => now()->addMinutes(8),
        'stripe_checkout_session_id' => 'cs_test_123',
        'payment_reminder_sent_at' => now()->subMinutes(5),
    ]);

    $this->artisan('payments:send-reminders')->assertSuccessful();

    Mail::assertNothingSent();
});

test('reminder is not sent if booking is not yet in the 10-minute window', function () {
    Booking::factory()->create([
        'payment_status' => PaymentStatus::Pending,
        'expires_at' => now()->addMinutes(25),
        'stripe_checkout_session_id' => 'cs_test_123',
    ]);

    $this->artisan('payments:send-reminders')->assertSuccessful();

    Mail::assertNothingSent();
});

test('reminder is not sent if booking has already expired', function () {
    Booking::factory()->create([
        'payment_status' => PaymentStatus::Pending,
        'expires_at' => now()->subMinutes(1),
        'stripe_checkout_session_id' => 'cs_test_123',
    ]);

    $this->artisan('payments:send-reminders')->assertSuccessful();

    Mail::assertNothingSent();
});

test('reminder is not sent if stripe checkout url cannot be retrieved', function () {
    $this->mock(RetrieveStripeCheckoutUrl::class)
        ->shouldReceive('execute')
        ->andReturn(null);

    $booking = Booking::factory()->create([
        'payment_status' => PaymentStatus::Pending,
        'expires_at' => now()->addMinutes(8),
        'stripe_checkout_session_id' => 'cs_test_123',
    ]);

    $this->artisan('payments:send-reminders')->assertSuccessful();

    Mail::assertNothingSent();

    expect($booking->fresh()->payment_reminder_sent_at)->toBeNull();
});

test('reminder is not sent for membership child bookings', function () {
    $membership = Membership::factory()->paid()->create();

    Booking::factory()->forMembership($membership)->create([
        'expires_at' => now()->addMinutes(8),
        'stripe_checkout_session_id' => 'cs_test_123',
    ]);

    $this->artisan('payments:send-reminders')->assertSuccessful();

    Mail::assertNotQueued(BookingPaymentReminder::class);
});

test('reminder is not sent if booking has no stripe session', function () {
    Booking::factory()->create([
        'payment_status' => PaymentStatus::Pending,
        'expires_at' => now()->addMinutes(8),
        'stripe_checkout_session_id' => null,
    ]);

    $this->artisan('payments:send-reminders')->assertSuccessful();

    Mail::assertNothingSent();
});

test('booking payment reminder email contains correct data', function () {
    $booking = Booking::factory()->create();
    $booking->load('schedule.service.coach');

    $mailable = new BookingPaymentReminder($booking, 'https://checkout.stripe.com/test');

    $mailable->assertSeeInHtml($booking->schedule->service->name, escape: false);
    $mailable->assertSeeInHtml($booking->schedule->service->coach->name, escape: false);
    $mailable->assertSeeInHtml($booking->booking_date->format('d.m.Y'), escape: false);
    $mailable->assertSeeInHtml(substr($booking->schedule->start_time, 0, 5), escape: false);
    $mailable->assertSeeInHtml('https://checkout.stripe.com/test');
});
