<?php

use App\Actions\RetrieveStripeCheckoutSessionState;
use App\Enums\PaymentStatus;
use App\Mail\MembershipConfirmation;
use App\Models\Booking;
use App\Models\Membership;
use Illuminate\Console\Scheduling\Schedule as TaskSchedule;
use Illuminate\Support\Facades\Mail;

/**
 * An unpaid membership with one waiting session, whose hold has run out and whose customer was sent to the given Stripe payment page.
 *
 * @param  array<string, mixed>  $attributes
 */
function overdueUnpaidMembership(array $attributes = []): Membership
{
    $membership = Membership::factory()->create([
        'payment_status' => PaymentStatus::Pending,
        'stripe_checkout_session_id' => 'cs_test_overdue_membership',
        'expires_at' => now()->subMinute(),
        ...$attributes,
    ]);

    Booking::factory()->forMembership($membership)->create(['payment_status' => PaymentStatus::Pending]);

    return $membership;
}

/**
 * Make Stripe answer the clean-up's question about the membership's payment page with the given state.
 *
 * @param  array{status: string, payment_status: string, payment_intent: string|null}|null  $state
 */
function stripeReportsMembershipPaymentPage(?array $state): void
{
    test()->mock(RetrieveStripeCheckoutSessionState::class)
        ->shouldReceive('execute')
        ->once()
        ->with('cs_test_overdue_membership')
        ->andReturn($state);
}

test('confirms an overdue membership and its sessions when Stripe says the payment went through', function () {
    Mail::fake();
    $membership = overdueUnpaidMembership(['email' => 'anna@example.com']);
    stripeReportsMembershipPaymentPage(['status' => 'complete', 'payment_status' => 'paid', 'payment_intent' => 'pi_test_late_membership']);

    $this->artisan('memberships:expire-pending')->assertSuccessful();

    expect($membership->fresh())
        ->payment_status->toBe(PaymentStatus::Paid)
        ->payment_reference->toBe('pi_test_late_membership')
        ->expires_at->toBeNull();
    expect($membership->bookings()->sole()->payment_status)->toBe(PaymentStatus::Paid);
    Mail::assertQueued(MembershipConfirmation::class, fn (MembershipConfirmation $mail) => $mail->hasTo('anna@example.com'));
});

test('deletes an overdue membership and frees its sessions when its Stripe payment page expired unpaid', function () {
    Mail::fake();
    $membership = overdueUnpaidMembership();
    stripeReportsMembershipPaymentPage(['status' => 'expired', 'payment_status' => 'unpaid', 'payment_intent' => null]);

    $this->artisan('memberships:expire-pending')->assertSuccessful();

    $this->assertModelMissing($membership);
    expect(Booking::count())->toBe(0);
    Mail::assertNothingOutgoing();
});

test('leaves an overdue membership alone while Stripe has not settled its payment page', function (?array $state) {
    $membership = overdueUnpaidMembership();
    stripeReportsMembershipPaymentPage($state);

    $this->artisan('memberships:expire-pending')->assertSuccessful();

    expect($membership->fresh()->payment_status)->toBe(PaymentStatus::Pending)
        ->and($membership->bookings()->count())->toBe(1);
})->with([
    'the page can still be paid' => [['status' => 'open', 'payment_status' => 'unpaid', 'payment_intent' => null]],
    'the payment is made but not collected yet' => [['status' => 'complete', 'payment_status' => 'unpaid', 'payment_intent' => 'pi_test_delayed']],
    'Stripe cannot be reached' => [null],
]);

test('deletes an overdue membership that never reached Stripe without asking Stripe', function () {
    $membership = overdueUnpaidMembership(['stripe_checkout_session_id' => null]);
    $this->mock(RetrieveStripeCheckoutSessionState::class)->shouldNotReceive('execute');

    $this->artisan('memberships:expire-pending')->assertSuccessful();

    $this->assertModelMissing($membership);
});

test('does not ask Stripe about a membership that is still within its hold', function () {
    $membership = overdueUnpaidMembership(['expires_at' => now()->addMinutes(10)]);
    $this->mock(RetrieveStripeCheckoutSessionState::class)->shouldNotReceive('execute');

    $this->artisan('memberships:expire-pending')->assertSuccessful();

    $this->assertModelExists($membership);
});

test('runs the clean-up of unpaid memberships every minute', function () {
    $event = collect(app(TaskSchedule::class)->events())
        ->first(fn ($event) => str_contains($event->command, 'memberships:expire-pending'));

    expect($event->expression)->toBe('* * * * *');
});
