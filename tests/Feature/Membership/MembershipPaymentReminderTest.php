<?php

use App\Actions\RetrieveStripeCheckoutUrl;
use App\Enums\PaymentStatus;
use App\Mail\MembershipPaymentReminder;
use App\Models\Booking;
use App\Models\Membership;
use App\Models\Schedule;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();

    $this->mock(RetrieveStripeCheckoutUrl::class)
        ->shouldReceive('execute')
        ->andReturn('https://checkout.stripe.com/test');
});

test('reminder is sent for membership in the 10-minute window', function () {
    $membership = Membership::factory()->create([
        'payment_status' => PaymentStatus::Pending,
        'expires_at' => now()->addMinutes(8),
        'stripe_checkout_session_id' => 'cs_test_123',
    ]);

    $this->artisan('payments:send-reminders')->assertSuccessful();

    Mail::assertQueued(MembershipPaymentReminder::class, function ($mail) use ($membership) {
        return $mail->hasTo($membership->email);
    });

    expect($membership->fresh()->payment_reminder_sent_at)->not->toBeNull();
});

test('reminder is not sent if already sent', function () {
    Membership::factory()->create([
        'payment_status' => PaymentStatus::Pending,
        'expires_at' => now()->addMinutes(8),
        'stripe_checkout_session_id' => 'cs_test_123',
        'payment_reminder_sent_at' => now()->subMinutes(5),
    ]);

    $this->artisan('payments:send-reminders')->assertSuccessful();

    Mail::assertNotQueued(MembershipPaymentReminder::class);
});

test('reminder is not sent if membership is not yet in the 10-minute window', function () {
    Membership::factory()->create([
        'payment_status' => PaymentStatus::Pending,
        'expires_at' => now()->addMinutes(25),
        'stripe_checkout_session_id' => 'cs_test_123',
    ]);

    $this->artisan('payments:send-reminders')->assertSuccessful();

    Mail::assertNotQueued(MembershipPaymentReminder::class);
});

test('reminder is not sent if membership has already expired', function () {
    Membership::factory()->create([
        'payment_status' => PaymentStatus::Pending,
        'expires_at' => now()->subMinutes(1),
        'stripe_checkout_session_id' => 'cs_test_123',
    ]);

    $this->artisan('payments:send-reminders')->assertSuccessful();

    Mail::assertNotQueued(MembershipPaymentReminder::class);
});

test('reminder is not sent if stripe checkout url cannot be retrieved', function () {
    $this->mock(RetrieveStripeCheckoutUrl::class)
        ->shouldReceive('execute')
        ->andReturn(null);

    $membership = Membership::factory()->create([
        'payment_status' => PaymentStatus::Pending,
        'expires_at' => now()->addMinutes(8),
        'stripe_checkout_session_id' => 'cs_test_123',
    ]);

    $this->artisan('payments:send-reminders')->assertSuccessful();

    Mail::assertNotQueued(MembershipPaymentReminder::class);

    expect($membership->fresh()->payment_reminder_sent_at)->toBeNull();
});

test('membership payment reminder email contains correct data', function () {
    $membership = Membership::factory()->create();
    $schedule = Schedule::factory()->create();

    Booking::factory()->forMembership($membership)->create([
        'schedule_id' => $schedule->id,
    ]);

    $membership->load('bookings.schedule.service');

    $mailable = new MembershipPaymentReminder($membership, 'https://checkout.stripe.com/test');

    $mailable->assertSeeInHtml($membership->tierLabel(), escape: false);
    $mailable->assertSeeInHtml($membership->period_start->format('d.m.Y'), escape: false);
    $mailable->assertSeeInHtml($membership->period_end->format('d.m.Y'), escape: false);
    $mailable->assertSeeInHtml('https://checkout.stripe.com/test');
});
