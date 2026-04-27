<?php

use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Membership;
use App\Models\MembershipAuditLog;
use App\Models\Schedule;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->actingAs($this->admin);

    $this->membership = Membership::factory()->paid()->create([
        'email' => 'customer@example.com',
        'sessions_total' => 4,
    ]);
});

test('eligible bookings include matching email standalone bookings', function () {
    $standalone = Booking::factory()->paid()->create([
        'email' => 'customer@example.com',
        'membership_id' => null,
    ]);

    Livewire::test('membership.membership-edit', ['membership' => $this->membership])
        ->assertSee($standalone->schedule->service->name);
});

test('eligible bookings exclude bookings with a different email', function () {
    $other = Booking::factory()->paid()->create([
        'email' => 'someone@example.com',
        'membership_id' => null,
    ]);

    $component = Livewire::test('membership.membership-edit', ['membership' => $this->membership]);

    $eligible = $component->instance()->eligibleBookings;

    expect($eligible->pluck('id'))->not->toContain($other->id);
});

test('eligible bookings exclude bookings already attached to any membership', function () {
    $otherMembership = Membership::factory()->paid()->create([
        'email' => 'customer@example.com',
    ]);

    $alreadyAttached = Booking::factory()->forMembership($otherMembership)->create();

    $component = Livewire::test('membership.membership-edit', ['membership' => $this->membership]);

    $eligible = $component->instance()->eligibleBookings;

    expect($eligible->pluck('id'))->not->toContain($alreadyAttached->id);
});

test('attaching a booking links it to the membership', function () {
    $standalone = Booking::factory()->paid()->create([
        'email' => 'customer@example.com',
        'membership_id' => null,
    ]);

    Livewire::test('membership.membership-edit', ['membership' => $this->membership])
        ->set('bookingToAttach', (string) $standalone->id)
        ->call('attachBooking');

    expect($standalone->fresh()->membership_id)->toBe($this->membership->id);
});

test('attaching a booking does not change sessions_total', function () {
    $standalone = Booking::factory()->paid()->create([
        'email' => 'customer@example.com',
        'membership_id' => null,
    ]);

    Livewire::test('membership.membership-edit', ['membership' => $this->membership])
        ->set('bookingToAttach', (string) $standalone->id)
        ->call('attachBooking');

    expect($this->membership->fresh()->sessions_total)->toBe(4);
});

test('attaching a booking leaves payment fields untouched', function () {
    $standalone = Booking::factory()->create([
        'email' => 'customer@example.com',
        'membership_id' => null,
        'payment_status' => PaymentStatus::Paid,
        'payment_reference' => 'pi_test_existing',
    ]);

    Livewire::test('membership.membership-edit', ['membership' => $this->membership])
        ->set('bookingToAttach', (string) $standalone->id)
        ->call('attachBooking');

    $fresh = $standalone->fresh();
    expect($fresh->payment_status)->toBe(PaymentStatus::Paid);
    expect($fresh->payment_reference)->toBe('pi_test_existing');
});

test('attaching a booking writes an audit log row', function () {
    $standalone = Booking::factory()->paid()->create([
        'email' => 'customer@example.com',
        'membership_id' => null,
    ]);

    Livewire::test('membership.membership-edit', ['membership' => $this->membership])
        ->set('bookingToAttach', (string) $standalone->id)
        ->call('attachBooking');

    $log = MembershipAuditLog::query()
        ->where('membership_id', $this->membership->id)
        ->where('booking_id', $standalone->id)
        ->first();

    expect($log)->not->toBeNull();
    expect($log->action)->toBe('booking_attached');
    expect($log->user_id)->toBe($this->admin->id);
    expect($log->metadata)->toMatchArray([
        'payment_status' => PaymentStatus::Paid->value,
    ]);
});

test('attaching a booking that became ineligible is a no-op', function () {
    $otherMembership = Membership::factory()->paid()->create([
        'email' => 'customer@example.com',
    ]);

    $standalone = Booking::factory()->paid()->create([
        'email' => 'customer@example.com',
        'membership_id' => null,
    ]);

    $component = Livewire::test('membership.membership-edit', ['membership' => $this->membership])
        ->set('bookingToAttach', (string) $standalone->id);

    // Race: booking attaches to a different membership before our call commits.
    $standalone->update(['membership_id' => $otherMembership->id]);

    $component->call('attachBooking');

    expect($standalone->fresh()->membership_id)->toBe($otherMembership->id);
    expect(MembershipAuditLog::query()->where('membership_id', $this->membership->id)->count())->toBe(0);
});

test('service-type override is allowed when attaching', function () {
    $otherTypeBooking = Booking::factory()->paid()->create([
        'email' => 'customer@example.com',
        'membership_id' => null,
        // Booking factory creates its own Schedule -> Service -> ServiceType,
        // which is a different service type than the membership's service.
    ]);

    Livewire::test('membership.membership-edit', ['membership' => $this->membership])
        ->set('bookingToAttach', (string) $otherTypeBooking->id)
        ->call('attachBooking');

    expect($otherTypeBooking->fresh()->membership_id)->toBe($this->membership->id);
});

test('date override is allowed when attaching outside membership period', function () {
    $outOfPeriod = Booking::factory()->paid()->create([
        'email' => 'customer@example.com',
        'membership_id' => null,
        'booking_date' => $this->membership->period_end->copy()->addMonths(6)->toDateString(),
    ]);

    Livewire::test('membership.membership-edit', ['membership' => $this->membership])
        ->set('bookingToAttach', (string) $outOfPeriod->id)
        ->call('attachBooking');

    expect($outOfPeriod->fresh()->membership_id)->toBe($this->membership->id);
});
