<?php

use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Membership;
use App\Models\Schedule;
use App\Models\Service;

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

test('membership has correct tier session counts via service', function () {
    $fourSessionService = Service::factory()->membership(4)->create();
    $nineSessionService = Service::factory()->membership(9)->create();

    expect($fourSessionService->sessions_count)->toBe(4);
    expect($nineSessionService->sessions_count)->toBe(9);
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

test('membership tierLabel returns service name', function () {
    $service = Service::factory()->membership(4)->create(['name' => '4 nodarbības mēnesī']);
    $membership = Membership::factory()->paid()->create(['service_id' => $service->id]);

    expect($membership->tierLabel())->toBe('4 nodarbības mēnesī');
});

test('membership tierLabel returns fallback when service is null', function () {
    $membership = Membership::factory()->paid()->create(['service_id' => null]);

    expect($membership->tierLabel())->toBe('—');
});

test('membership service relationship works', function () {
    $service = Service::factory()->membership(4)->create();
    $membership = Membership::factory()->paid()->create(['service_id' => $service->id]);

    expect($membership->service->id)->toBe($service->id);
});
