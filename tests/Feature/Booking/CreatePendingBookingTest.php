<?php

use App\Actions\CreatePendingBooking;
use App\Enums\PaymentStatus;
use App\Exceptions\SlotUnavailableException;
use App\Models\Booking;
use App\Models\Coach;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\UnavailableDate;
use Illuminate\Support\Carbon;

/**
 * Create a class on Wednesdays at 18:00. "Now" in these tests is Monday 5 October 2026, 09:00,
 * so the next occurrence is Wednesday 7 October 2026.
 *
 * @param  array<string, mixed>  $service
 * @param  array<string, mixed>  $schedule
 */
function createBookableClass(array $service = [], array $schedule = []): Schedule
{
    test()->travelTo(Carbon::parse('2026-10-05 09:00:00'));

    return Schedule::factory()->create(array_merge([
        'service_id' => Service::factory()->create($service)->id,
        'day_of_week' => 3,
        'start_time' => '18:00',
        'max_capacity' => 10,
    ], $schedule));
}

function createPendingBooking(int $scheduleId, string $date = '2026-10-07', int $participantCount = 1): Booking
{
    return app(CreatePendingBooking::class)->execute($scheduleId, $date, $participantCount, [
        'name' => 'Anna',
        'surname' => 'Liepa',
        'phone' => '+371 20000000',
        'email' => 'anna@example.com',
    ]);
}

test('creates an unpaid booking that holds the place five minutes beyond the 30 minute payment window', function () {
    $schedule = createBookableClass();

    $booking = createPendingBooking($schedule->id);

    expect($booking->fresh())
        ->schedule_id->toBe($schedule->id)
        ->name->toBe('Anna')
        ->surname->toBe('Liepa')
        ->phone->toBe('+371 20000000')
        ->email->toBe('anna@example.com')
        ->participant_count->toBe(1)
        ->payment_status->toBe(PaymentStatus::Pending)
        ->membership_id->toBeNull()
        ->stripe_checkout_session_id->toBeNull();
    expect($booking->fresh()->booking_date->toDateString())->toBe('2026-10-07');
    expect($booking->fresh()->expires_at->toDateTimeString())->toBe('2026-10-05 09:35:00');
});

test('rejects a booking for a slot customers cannot book', function (array $service, array $schedule, string $date) {
    $class = createBookableClass($service, $schedule);

    expect(fn () => createPendingBooking($class->id, $date))->toThrow(SlotUnavailableException::class);
    expect(Booking::count())->toBe(0);
})->with([
    'wrong weekday' => [[], [], '2026-10-08'],
    'date in the past' => [[], ['day_of_week' => 7], '2026-10-04'],
    'class already started today' => [[], ['day_of_week' => 1, 'start_time' => '08:00'], '2026-10-05'],
    'beyond the twelve week horizon' => [[], ['day_of_week' => 2], '2026-12-29'],
    'inactive schedule' => [[], ['is_active' => false], '2026-10-07'],
    'inactive service' => [['is_active' => false], [], '2026-10-07'],
    'membership product' => [['is_membership' => true], [], '2026-10-07'],
    'malformed date' => [[], [], 'next wednesday'],
    'impossible date' => [[], [], '2026-02-31'],
]);

test('rejects a booking for an unknown schedule', function () {
    $class = createBookableClass();

    expect(fn () => createPendingBooking($class->id + 1))->toThrow(SlotUnavailableException::class);
    expect(Booking::count())->toBe(0);
});

test('rejects a booking during a studio closure', function () {
    $class = createBookableClass();
    UnavailableDate::factory()->range(Carbon::parse('2026-10-07'), Carbon::parse('2026-10-07'))->create();

    expect(fn () => createPendingBooking($class->id))->toThrow(SlotUnavailableException::class);
    expect(Booking::count())->toBe(0);
});

test('rejects a booking when the booked participants fill the group class', function () {
    $class = createBookableClass(schedule: ['max_capacity' => 3]);
    Booking::factory()->paid()->withParticipants(2)->create(['schedule_id' => $class->id, 'booking_date' => '2026-10-07']);
    Booking::factory()->create(['schedule_id' => $class->id, 'booking_date' => '2026-10-07', 'expires_at' => now()->addMinutes(10)]);

    expect(fn () => createPendingBooking($class->id))->toThrow(SlotUnavailableException::class);
    expect(Booking::count())->toBe(2);
});

test('books the last place of a group class', function () {
    $class = createBookableClass(schedule: ['max_capacity' => 3]);
    Booking::factory()->paid()->withParticipants(2)->create(['schedule_id' => $class->id, 'booking_date' => '2026-10-07']);

    $booking = createPendingBooking($class->id);

    $this->assertModelExists($booking);
});

test('books a place freed by a booking that no longer holds it', function (array $earlierBooking) {
    $class = createBookableClass(schedule: ['max_capacity' => 1]);
    Booking::factory()->create(array_merge(['schedule_id' => $class->id, 'booking_date' => '2026-10-07'], $earlierBooking));

    $booking = createPendingBooking($class->id);

    $this->assertModelExists($booking);
})->with([
    'refunded' => [['payment_status' => PaymentStatus::Refunded]],
    'failed' => [['payment_status' => PaymentStatus::Failed]],
    'unpaid and expired' => [['payment_status' => PaymentStatus::Pending, 'expires_at' => '2026-10-05 08:59:00']],
]);

test('rejects any participant count other than one for a group class', function (int $participantCount) {
    $class = createBookableClass(['price' => 1500]);
    $class->service->priceTiers()->createMany([
        ['participant_count' => 1, 'price' => 1500],
        ['participant_count' => 2, 'price' => 2500],
    ]);

    expect(fn () => createPendingBooking($class->id, participantCount: $participantCount))->toThrow(SlotUnavailableException::class);
    expect(Booking::count())->toBe(0);
})->with([
    'zero' => [0],
    'negative' => [-1],
    'two' => [2],
]);

test('books an individual session for a group size that has a price tier', function () {
    $class = createBookableClass(['is_exclusive' => true, 'price' => 3500], ['max_capacity' => 2]);
    $class->service->priceTiers()->createMany([
        ['participant_count' => 1, 'price' => 3500],
        ['participant_count' => 2, 'price' => 5000],
    ]);

    $booking = createPendingBooking($class->id, participantCount: 2);

    expect($booking->fresh()->participant_count)->toBe(2);
});

test('rejects an individual session booking with an invalid group size', function (int $participantCount) {
    $class = createBookableClass(['is_exclusive' => true, 'price' => 3500], ['max_capacity' => 2]);
    $class->service->priceTiers()->createMany([
        ['participant_count' => 1, 'price' => 3500],
        ['participant_count' => 3, 'price' => 6000],
    ]);

    expect(fn () => createPendingBooking($class->id, participantCount: $participantCount))->toThrow(SlotUnavailableException::class);
    expect(Booking::count())->toBe(0);
})->with([
    'zero' => [0],
    'no price tier for the size' => [2],
    'tier larger than the session allows' => [3],
]);

test('rejects an individual session that already has a booking', function () {
    $class = createBookableClass(['is_exclusive' => true], ['max_capacity' => 3]);
    Booking::factory()->create(['schedule_id' => $class->id, 'booking_date' => '2026-10-07', 'expires_at' => now()->addMinutes(10)]);

    expect(fn () => createPendingBooking($class->id))->toThrow(SlotUnavailableException::class);
    expect(Booking::count())->toBe(1);
});

test('rejects a booking when an unpaid booking without an expiry holds the last place', function () {
    $class = createBookableClass(schedule: ['max_capacity' => 1]);
    Booking::factory()->create(['schedule_id' => $class->id, 'booking_date' => '2026-10-07', 'expires_at' => null]);

    expect(fn () => createPendingBooking($class->id))->toThrow(SlotUnavailableException::class);
    expect(Booking::count())->toBe(1);
});

test('takes several bookings for a group class that has price tiers', function () {
    $class = createBookableClass(['price' => 1500], ['max_capacity' => 10]);
    $class->service->priceTiers()->createMany([
        ['participant_count' => 1, 'price' => 1500],
        ['participant_count' => 2, 'price' => 2500],
    ]);

    createPendingBooking($class->id);
    createPendingBooking($class->id);

    expect(Booking::count())->toBe(2);
});

test('records the price of the class on the booking', function () {
    $class = createBookableClass(['price' => 1500]);

    $booking = createPendingBooking($class->id);

    expect($booking->fresh()->price)->toBe(1500);
});

test('records the price of the chosen group size on an individual session booking', function () {
    $class = createBookableClass(['is_exclusive' => true, 'price' => 3500], ['max_capacity' => 2]);
    $class->service->priceTiers()->createMany([
        ['participant_count' => 1, 'price' => 3500],
        ['participant_count' => 2, 'price' => 5000],
    ]);

    $booking = createPendingBooking($class->id, participantCount: 2);

    expect($booking->fresh()->price)->toBe(5000);
});

test('rejects a booking for a class of an inactive coach', function () {
    $class = createBookableClass(['coach_id' => Coach::factory()->create(['is_active' => false])->id]);

    expect(fn () => createPendingBooking($class->id))->toThrow(SlotUnavailableException::class);
    expect(Booking::count())->toBe(0);
});
