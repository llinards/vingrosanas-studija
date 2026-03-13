<?php

use App\Enums\MembershipTier;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Membership;
use App\Models\Schedule;

test('membership can be marked as paid', function () {
    $membership = Membership::factory()->create([
        'payment_status' => PaymentStatus::Pending,
        'expires_at' => now()->addMinutes(30),
    ]);

    $membership->markAsPaid('pi_test_123');

    expect($membership->fresh())
        ->payment_status->toBe(PaymentStatus::Paid)
        ->payment_reference->toBe('pi_test_123')
        ->expires_at->toBeNull();
});

test('membership correctly identifies pending payment status', function () {
    $pendingMembership = Membership::factory()->create([
        'payment_status' => PaymentStatus::Pending,
        'expires_at' => now()->addMinutes(30),
    ]);

    $expiredMembership = Membership::factory()->create([
        'payment_status' => PaymentStatus::Pending,
        'expires_at' => now()->subMinutes(5),
    ]);

    $paidMembership = Membership::factory()->paid()->create();

    expect($pendingMembership->isPendingPayment())->toBeTrue();
    expect($expiredMembership->isPendingPayment())->toBeFalse();
    expect($paidMembership->isPendingPayment())->toBeFalse();
});

test('membership correctly identifies expired status', function () {
    $pendingMembership = Membership::factory()->create([
        'payment_status' => PaymentStatus::Pending,
        'expires_at' => now()->addMinutes(30),
    ]);

    $expiredMembership = Membership::factory()->create([
        'payment_status' => PaymentStatus::Pending,
        'expires_at' => now()->subMinutes(5),
    ]);

    expect($pendingMembership->isExpired())->toBeFalse();
    expect($expiredMembership->isExpired())->toBeTrue();
});

test('membership correctly identifies active status', function () {
    $activeMembership = Membership::factory()->paid()->create([
        'period_start' => today()->startOfMonth(),
        'period_end' => today()->endOfMonth(),
    ]);

    $expiredPeriod = Membership::factory()->paid()->expired()->create();

    $unpaidMembership = Membership::factory()->create([
        'period_start' => today()->startOfMonth(),
        'period_end' => today()->endOfMonth(),
    ]);

    expect($activeMembership->isActive())->toBeTrue();
    expect($expiredPeriod->isActive())->toBeFalse();
    expect($unpaidMembership->isActive())->toBeFalse();
});

test('membership tracks sessions used correctly', function () {
    $membership = Membership::factory()->paid()->fourSessions()->create();
    $schedule = Schedule::factory()->create();

    // Create 2 active bookings
    Booking::factory()->forMembership($membership)->count(2)->create([
        'schedule_id' => $schedule->id,
    ]);

    // Create 1 refunded booking (should not count)
    Booking::factory()->forMembership($membership)->refunded()->create([
        'schedule_id' => $schedule->id,
    ]);

    expect($membership->sessionsUsed())->toBe(2);
    expect($membership->sessionsRemaining())->toBe(2);
});

test('membership can be marked as refunded', function () {
    $membership = Membership::factory()->paid()->create();

    $membership->markAsRefunded('re_test_123');

    expect($membership->fresh())
        ->payment_status->toBe(PaymentStatus::Refunded)
        ->refund_reference->toBe('re_test_123')
        ->refunded_at->not->toBeNull();
});

test('membership has correct tier session counts', function () {
    expect(MembershipTier::FourSessions->sessionCount())->toBe(4);
    expect(MembershipTier::NineSessions->sessionCount())->toBe(9);
});

test('membership scope active filters correctly', function () {
    $active = Membership::factory()->paid()->create([
        'period_start' => today()->startOfMonth(),
        'period_end' => today()->endOfMonth(),
    ]);

    $expired = Membership::factory()->paid()->expired()->create();
    $pending = Membership::factory()->create();

    $activeMemberships = Membership::active()->get();

    expect($activeMemberships)->toHaveCount(1);
    expect($activeMemberships->first()->id)->toBe($active->id);
});

test('membership scope forEmail filters correctly', function () {
    $m1 = Membership::factory()->create(['email' => 'test@example.com']);
    $m2 = Membership::factory()->create(['email' => 'other@example.com']);

    $results = Membership::forEmail('test@example.com')->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($m1->id);
});

test('membership bookings relationship works', function () {
    $membership = Membership::factory()->paid()->create();
    $schedule = Schedule::factory()->create();

    $booking = Booking::factory()->forMembership($membership)->create([
        'schedule_id' => $schedule->id,
    ]);

    expect($membership->bookings)->toHaveCount(1);
    expect($membership->bookings->first()->id)->toBe($booking->id);
});

test('booking identifies membership booking correctly', function () {
    $membership = Membership::factory()->paid()->create();
    $schedule = Schedule::factory()->create();

    $membershipBooking = Booking::factory()->forMembership($membership)->create([
        'schedule_id' => $schedule->id,
    ]);

    $regularBooking = Booking::factory()->create();

    expect($membershipBooking->isMembershipBooking())->toBeTrue();
    expect($regularBooking->isMembershipBooking())->toBeFalse();
});

test('membership bookings are not individually refundable', function () {
    $membership = Membership::factory()->paid()->create();
    $schedule = Schedule::factory()->create();

    $booking = Booking::factory()->forMembership($membership)->create([
        'schedule_id' => $schedule->id,
        'booking_date' => now()->addDays(5),
        'payment_reference' => 'pi_test_123',
    ]);

    expect($booking->isRefundable())->toBeFalse();
});
