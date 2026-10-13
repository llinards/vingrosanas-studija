<?php

use App\Actions\RetrieveStripeCheckoutSessionState;
use App\Enums\PaymentStatus;
use App\Mail\BookingConfirmation;
use App\Mail\NewBookingNotification;
use App\Models\Booking;
use App\Models\Coach;
use App\Models\Schedule;
use App\Models\Service;
use Illuminate\Console\Scheduling\Schedule as TaskSchedule;
use Illuminate\Support\Facades\Mail;

/**
 * An unpaid booking whose hold has run out and whose customer was sent to the given Stripe payment page.
 *
 * @param  array<string, mixed>  $attributes
 */
function overdueUnpaidBooking(array $attributes = []): Booking
{
    return Booking::factory()->create(array_merge([
        'stripe_checkout_session_id' => 'cs_test_overdue',
        'expires_at' => now()->subMinute(),
    ], $attributes));
}

/**
 * Make Stripe answer the clean-up's question about a payment page with the given state.
 *
 * @param  array{status: string, payment_status: string, payment_intent: string|null}|null  $state
 */
function stripeReportsPaymentPage(?array $state): void
{
    test()->mock(RetrieveStripeCheckoutSessionState::class)
        ->shouldReceive('execute')
        ->once()
        ->with('cs_test_overdue')
        ->andReturn($state);
}

test('confirms an overdue booking when Stripe says its payment went through', function () {
    Mail::fake();
    $coach = Coach::factory()->create(['email' => 'coach@example.com']);
    $schedule = Schedule::factory()->create(['service_id' => Service::factory()->create(['coach_id' => $coach->id])->id]);
    $booking = overdueUnpaidBooking(['schedule_id' => $schedule->id, 'email' => 'anna@example.com']);
    stripeReportsPaymentPage(['status' => 'complete', 'payment_status' => 'paid', 'payment_intent' => 'pi_test_late']);

    $this->artisan('bookings:expire-pending')->assertSuccessful();

    expect($booking->fresh())
        ->payment_status->toBe(PaymentStatus::Paid)
        ->payment_reference->toBe('pi_test_late')
        ->expires_at->toBeNull();
    Mail::assertQueued(BookingConfirmation::class, fn (BookingConfirmation $mail) => $mail->hasTo('anna@example.com'));
    Mail::assertQueued(NewBookingNotification::class, fn (NewBookingNotification $mail) => $mail->hasTo('coach@example.com'));
});

test('deletes an overdue booking when its Stripe payment page expired unpaid', function () {
    Mail::fake();
    $booking = overdueUnpaidBooking();
    stripeReportsPaymentPage(['status' => 'expired', 'payment_status' => 'unpaid', 'payment_intent' => null]);

    $this->artisan('bookings:expire-pending')->assertSuccessful();

    $this->assertModelMissing($booking);
    Mail::assertNothingOutgoing();
});

test('leaves an overdue booking alone while its Stripe payment page can still be paid', function () {
    $booking = overdueUnpaidBooking();
    stripeReportsPaymentPage(['status' => 'open', 'payment_status' => 'unpaid', 'payment_intent' => null]);

    $this->artisan('bookings:expire-pending')->assertSuccessful();

    expect($booking->fresh()->payment_status)->toBe(PaymentStatus::Pending);
});

test('leaves an overdue booking alone when the payment was made but is not collected yet', function () {
    $booking = overdueUnpaidBooking();
    stripeReportsPaymentPage(['status' => 'complete', 'payment_status' => 'unpaid', 'payment_intent' => 'pi_test_delayed']);

    $this->artisan('bookings:expire-pending')->assertSuccessful();

    expect($booking->fresh()->payment_status)->toBe(PaymentStatus::Pending);
});

test('leaves an overdue booking alone when Stripe cannot be reached', function () {
    $booking = overdueUnpaidBooking();
    stripeReportsPaymentPage(null);

    $this->artisan('bookings:expire-pending')->assertSuccessful();

    expect($booking->fresh()->payment_status)->toBe(PaymentStatus::Pending);
});

test('deletes an overdue booking that never reached Stripe without asking Stripe', function () {
    $booking = overdueUnpaidBooking(['stripe_checkout_session_id' => null]);
    $this->mock(RetrieveStripeCheckoutSessionState::class)->shouldNotReceive('execute');

    $this->artisan('bookings:expire-pending')->assertSuccessful();

    $this->assertModelMissing($booking);
});

test('does not ask Stripe about a booking that is still within its hold', function () {
    $booking = overdueUnpaidBooking(['expires_at' => now()->addMinutes(10)]);
    $this->mock(RetrieveStripeCheckoutSessionState::class)->shouldNotReceive('execute');

    $this->artisan('bookings:expire-pending')->assertSuccessful();

    $this->assertModelExists($booking);
});

test('runs the clean-up of unpaid bookings every minute', function () {
    $event = collect(app(TaskSchedule::class)->events())
        ->first(fn ($event) => str_contains($event->command, 'bookings:expire-pending'));

    expect($event->expression)->toBe('* * * * *');
});
