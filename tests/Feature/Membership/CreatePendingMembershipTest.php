<?php

use App\Actions\CreatePendingMembership;
use App\Enums\PaymentStatus;
use App\Exceptions\InvalidMembershipSelectionException;
use App\Exceptions\SlotUnavailableException;
use App\Models\Booking;
use App\Models\Coach;
use App\Models\Membership;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\UnavailableDate;
use App\Services\ScheduleAvailabilityService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-05 09:00:00'));
});

/**
 * Create a membership-eligible class on Wednesdays at 18:00. "Now" in these tests is
 * Monday 5 October 2026, 09:00, so its next occurrences are 7 and 14 October 2026.
 *
 * @param  array<string, mixed>  $service
 * @param  array<string, mixed>  $schedule
 */
function createMembershipClass(array $service = [], array $schedule = []): Schedule
{
    return Schedule::factory()->create([
        'service_id' => Service::factory()->membershipEligible()->create($service)->id,
        'day_of_week' => 3,
        'start_time' => '18:00',
        'max_capacity' => 10,
        'is_active' => true,
        ...$schedule,
    ]);
}

/**
 * @param  list<array{schedule_id: int, date: string}>  $sessions
 */
function createPendingMembership(Service $membershipService, array $sessions): Membership
{
    return app(CreatePendingMembership::class)->execute($membershipService->id, $sessions, [
        'name' => 'Anna',
        'surname' => 'Liepa',
        'phone' => '+371 20000000',
        'email' => 'anna@example.com',
    ]);
}

test('creates an unpaid membership whose sessions wait for payment and hold their places', function () {
    $class = createMembershipClass();
    $membershipService = Service::factory()->membership(2)->inCategoryOf($class->service)->create(['price' => 4500]);

    $membership = createPendingMembership($membershipService, [
        ['schedule_id' => $class->id, 'date' => '2026-10-14'],
        ['schedule_id' => $class->id, 'date' => '2026-10-07'],
    ]);

    expect($membership->fresh())
        ->service_id->toBe($membershipService->id)
        ->price->toBe(4500)
        ->sessions_total->toBe(2)
        ->email->toBe('anna@example.com')
        ->payment_status->toBe(PaymentStatus::Pending);
    expect($membership->period_start->toDateString())->toBe('2026-10-07')
        ->and($membership->period_end->toDateString())->toBe('2026-11-07')
        ->and($membership->expires_at->toDateTimeString())->toBe('2026-10-05 09:35:00');

    expect($membership->bookings()->orderBy('booking_date')->get())
        ->toHaveCount(2)
        ->each(fn ($session) => $session
            ->schedule_id->toBe($class->id)
            ->participant_count->toBe(1)
            ->payment_status->toBe(PaymentStatus::Pending)
            ->expires_at->toBeNull()
            ->name->toBe('Anna'));
    expect(app(ScheduleAvailabilityService::class)->remainingCapacity($class, '2026-10-07'))->toBe(9);
});

test('refuses a membership that is not on sale or a session list that does not fit it', function (Closure $membershipService, Closure $sessions) {
    $class = createMembershipClass();

    expect(fn () => createPendingMembership($membershipService(), $sessions($class)))
        ->toThrow(InvalidMembershipSelectionException::class);

    expect(Membership::count())->toBe(0)
        ->and(Booking::count())->toBe(0);
})->with([
    'an inactive membership' => [
        fn () => Service::factory()->membership(1)->create(['is_active' => false]),
        fn (Schedule $class) => [['schedule_id' => $class->id, 'date' => '2026-10-07']],
    ],
    'a service that is not a membership' => [
        fn () => Service::factory()->create(['sessions_count' => 1]),
        fn (Schedule $class) => [['schedule_id' => $class->id, 'date' => '2026-10-07']],
    ],
    'too few sessions' => [
        fn () => Service::factory()->membership(2)->create(),
        fn (Schedule $class) => [['schedule_id' => $class->id, 'date' => '2026-10-07']],
    ],
    'too many sessions' => [
        fn () => Service::factory()->membership(1)->create(),
        fn (Schedule $class) => [['schedule_id' => $class->id, 'date' => '2026-10-07'], ['schedule_id' => $class->id, 'date' => '2026-10-14']],
    ],
    'the same session twice' => [
        fn () => Service::factory()->membership(2)->create(),
        fn (Schedule $class) => [['schedule_id' => $class->id, 'date' => '2026-10-07'], ['schedule_id' => $class->id, 'date' => '2026-10-07']],
    ],
    'a session after the one-month period' => [
        fn () => Service::factory()->membership(2)->create(),
        fn (Schedule $class) => [['schedule_id' => $class->id, 'date' => '2026-10-07'], ['schedule_id' => $class->id, 'date' => '2026-11-11']],
    ],
    'a malformed date' => [
        fn () => Service::factory()->membership(1)->create(),
        fn (Schedule $class) => [['schedule_id' => $class->id, 'date' => '07.10.2026']],
    ],
]);

test('refuses a session the class rules do not allow', function (Closure $createClass, string $date) {
    $class = $createClass();
    $membershipService = Service::factory()->membership(1)->inCategoryOf($class->service)->create();

    expect(fn () => createPendingMembership($membershipService, [['schedule_id' => $class->id, 'date' => $date]]))
        ->toThrow(SlotUnavailableException::class);

    expect(Membership::count())->toBe(0)
        ->and(Booking::where('schedule_id', $class->id)->where('payment_status', PaymentStatus::Pending)->count())->toBe(0);
})->with([
    'a class not allowed for memberships' => [fn () => createMembershipClass(['is_membership_eligible' => false]), '2026-10-07'],
    'a class of an inactive coach' => [fn () => createMembershipClass(['coach_id' => Coach::factory()->create(['is_active' => false])->id]), '2026-10-07'],
    'a switched-off class' => [fn () => createMembershipClass(schedule: ['is_active' => false]), '2026-10-07'],
    'a day the class does not run' => [fn () => createMembershipClass(), '2026-10-08'],
    'a day the studio is closed' => [function () {
        UnavailableDate::factory()->range(Carbon::parse('2026-10-07'), Carbon::parse('2026-10-07'))->create();

        return createMembershipClass();
    }, '2026-10-07'],
    'a past date' => [fn () => createMembershipClass(), '2026-09-30'],
    'beyond the booking horizon' => [fn () => createMembershipClass(), '2027-01-06'],
    'a full class' => [function () {
        $class = createMembershipClass(schedule: ['max_capacity' => 1]);
        Booking::factory()->paid()->create(['schedule_id' => $class->id, 'booking_date' => '2026-10-07']);

        return $class;
    }, '2026-10-07'],
    'a taken individual session' => [function () {
        $class = createMembershipClass(['is_exclusive' => true]);
        Booking::factory()->paid()->create(['schedule_id' => $class->id, 'booking_date' => '2026-10-07']);

        return $class;
    }, '2026-10-07'],
]);

test('refuses a session of a class from another category than the membership', function () {
    $class = createMembershipClass();
    $membershipService = Service::factory()->membership(1)->create();

    expect(fn () => createPendingMembership($membershipService, [['schedule_id' => $class->id, 'date' => '2026-10-07']]))
        ->toThrow(SlotUnavailableException::class);

    expect(Membership::count())->toBe(0)
        ->and(Booking::count())->toBe(0);
});

test('refuses a session of a class that does not exist', function () {
    $membershipService = Service::factory()->membership(1)->create();

    expect(fn () => createPendingMembership($membershipService, [['schedule_id' => 999999, 'date' => '2026-10-07']]))
        ->toThrow(SlotUnavailableException::class);

    expect(Membership::count())->toBe(0);
});
