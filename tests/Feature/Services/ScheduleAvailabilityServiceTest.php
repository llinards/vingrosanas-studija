<?php

use App\Enums\PaymentStatus;
use App\Enums\SlotStatus;
use App\Models\Booking;
use App\Models\Coach;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\UnavailableDate;
use App\Services\ScheduleAvailabilityService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Monday 5 October 2026, 09:00 — the week under test runs 5–11 October.
 */
function travelToTimetableMonday(): void
{
    test()->travelTo(Carbon::parse('2026-10-05 09:00:00'));
}

/**
 * Create a weekly class on Wednesdays at 18:00 unless overridden.
 *
 * @param  array<string, mixed>  $service
 * @param  array<string, mixed>  $schedule
 */
function createTimetableClass(array $service = [], array $schedule = []): Schedule
{
    return Schedule::factory()->create(array_merge([
        'service_id' => Service::factory()->create($service)->id,
        'day_of_week' => 3,
        'start_time' => '18:00',
        'max_capacity' => 10,
    ], $schedule));
}

function weekTimetable(): array
{
    return app(ScheduleAvailabilityService::class)->weekTimetable(Carbon::parse('2026-10-05'));
}

test('lists a weekly class on its weekday with start time, coach, price and free places', function () {
    travelToTimetableMonday();
    $coach = Coach::factory()->create(['name' => 'Anete']);
    $schedule = createTimetableClass(
        ['name' => 'Pilates', 'coach_id' => $coach->id, 'price' => 1500, 'duration_minutes' => 45],
        ['max_capacity' => 12],
    );

    $week = weekTimetable();

    expect(array_keys($week))->toBe([
        '2026-10-05', '2026-10-06', '2026-10-07', '2026-10-08', '2026-10-09', '2026-10-10', '2026-10-11',
    ]);
    expect($week['2026-10-07'])->toBe([[
        'schedule_id' => $schedule->id,
        'date' => '2026-10-07',
        'start_time' => '18:00',
        'service_name' => 'Pilates',
        'coach_name' => 'Anete',
        'is_exclusive' => false,
        'price_from' => 1500,
        'has_price_range' => false,
        'remaining' => 12,
        'status' => SlotStatus::Available,
    ]]);
    expect($week['2026-10-06'])->toBe([]);
});

test('lists a one-off class only on its date', function () {
    travelToTimetableMonday();
    createTimetableClass(schedule: ['day_of_week' => null, 'date' => '2026-10-09']);

    $week = weekTimetable();

    expect($week['2026-10-09'])->toHaveCount(1);
    expect(collect($week)->flatten(1))->toHaveCount(1);
});

test('orders the classes of a day by start time', function () {
    travelToTimetableMonday();
    createTimetableClass(schedule: ['start_time' => '19:30']);
    createTimetableClass(schedule: ['start_time' => '07:00']);

    $week = weekTimetable();

    expect(array_column($week['2026-10-07'], 'start_time'))->toBe(['07:00', '19:30']);
});

test('does not list classes that customers cannot book', function (array $service, array $schedule) {
    travelToTimetableMonday();
    createTimetableClass($service, $schedule);

    $week = weekTimetable();

    expect(collect($week)->flatten(1))->toBeEmpty();
})->with([
    'inactive schedule' => [[], ['is_active' => false]],
    'inactive service' => [['is_active' => false], []],
    'membership product' => [['is_membership' => true], []],
]);

test('reduces free places by the booked participants and marks a class full at capacity', function () {
    travelToTimetableMonday();
    $filling = createTimetableClass(['name' => 'A'], ['start_time' => '10:00', 'max_capacity' => 5]);
    $full = createTimetableClass(['name' => 'B'], ['start_time' => '11:00', 'max_capacity' => 3]);
    Booking::factory()->paid()->withParticipants(2)->create(['schedule_id' => $filling->id, 'booking_date' => '2026-10-07']);
    Booking::factory()->paid()->withParticipants(2)->create(['schedule_id' => $full->id, 'booking_date' => '2026-10-07']);
    Booking::factory()->create(['schedule_id' => $full->id, 'booking_date' => '2026-10-07', 'expires_at' => now()->addMinutes(30)]);

    [$first, $second] = weekTimetable()['2026-10-07'];

    expect($first)->remaining->toBe(3)->status->toBe(SlotStatus::Available);
    expect($second)->remaining->toBe(0)->status->toBe(SlotStatus::Full);
});

test('does not count bookings from another date of the same class', function () {
    travelToTimetableMonday();
    $schedule = createTimetableClass(schedule: ['max_capacity' => 5]);
    Booking::factory()->paid()->create(['schedule_id' => $schedule->id, 'booking_date' => '2026-10-14']);

    $week = weekTimetable();

    expect($week['2026-10-07'][0]['remaining'])->toBe(5);
});

test('does not count bookings that no longer hold a place', function (array $booking) {
    travelToTimetableMonday();
    $schedule = createTimetableClass(schedule: ['max_capacity' => 5]);
    Booking::factory()->create(array_merge(['schedule_id' => $schedule->id, 'booking_date' => '2026-10-07'], $booking));

    $week = weekTimetable();

    expect($week['2026-10-07'][0]['remaining'])->toBe(5);
})->with([
    'refunded' => [['payment_status' => PaymentStatus::Refunded]],
    'failed' => [['payment_status' => PaymentStatus::Failed]],
    'unpaid and expired' => [['payment_status' => PaymentStatus::Pending, 'expires_at' => '2026-10-05 08:59:00']],
]);

test('marks classes that have already started as past', function () {
    travelToTimetableMonday();
    createTimetableClass(['name' => 'Agrā'], ['day_of_week' => 1, 'start_time' => '08:00']);
    createTimetableClass(['name' => 'Tieši tagad'], ['day_of_week' => 1, 'start_time' => '09:00']);
    createTimetableClass(['name' => 'Vēlāk'], ['day_of_week' => 1, 'start_time' => '09:01']);

    $monday = weekTimetable()['2026-10-05'];

    expect(array_column($monday, 'status', 'service_name'))->toBe([
        'Agrā' => SlotStatus::Past,
        'Tieši tagad' => SlotStatus::Past,
        'Vēlāk' => SlotStatus::Available,
    ]);
});

test('marks classes on earlier days of the week as past', function () {
    test()->travelTo(Carbon::parse('2026-10-08 09:00:00'));
    createTimetableClass(schedule: ['day_of_week' => 3, 'start_time' => '18:00']);

    $week = weekTimetable();

    expect($week['2026-10-07'][0]['status'])->toBe(SlotStatus::Past);
});

test('marks a class cancelled during a studio closure', function () {
    travelToTimetableMonday();
    createTimetableClass();
    UnavailableDate::factory()->range(Carbon::parse('2026-10-07'), Carbon::parse('2026-10-07'))->create();

    $week = weekTimetable();

    expect($week['2026-10-07'][0]['status'])->toBe(SlotStatus::Cancelled);
});

test('marks only the unavailable coach\'s classes cancelled', function () {
    travelToTimetableMonday();
    $away = Coach::factory()->create();
    createTimetableClass(['name' => 'Prom', 'coach_id' => $away->id], ['start_time' => '10:00']);
    createTimetableClass(['name' => 'Notiek'], ['start_time' => '11:00']);
    UnavailableDate::factory()->forCoach($away)->range(Carbon::parse('2026-10-07'), Carbon::parse('2026-10-07'))->create();

    $wednesday = weekTimetable()['2026-10-07'];

    expect(array_column($wednesday, 'status', 'service_name'))->toBe([
        'Prom' => SlotStatus::Cancelled,
        'Notiek' => SlotStatus::Available,
    ]);
});

test('keeps a class available when a timed closure ends at its start time', function () {
    travelToTimetableMonday();
    createTimetableClass(schedule: ['start_time' => '18:00']);
    UnavailableDate::factory()
        ->range(Carbon::parse('2026-10-07'), Carbon::parse('2026-10-07'))
        ->withTimeRange('15:00', '18:00')
        ->create();

    $week = weekTimetable();

    expect($week['2026-10-07'][0]['status'])->toBe(SlotStatus::Available);
});

test('lists a free individual session with the price range that fits its group size', function () {
    travelToTimetableMonday();
    $schedule = createTimetableClass(['price' => 3500, 'is_exclusive' => true], ['max_capacity' => 2]);
    $schedule->service->priceTiers()->createMany([
        ['participant_count' => 1, 'price' => 3500],
        ['participant_count' => 2, 'price' => 5000],
        ['participant_count' => 3, 'price' => 6000],
    ]);

    $entry = weekTimetable()['2026-10-07'][0];

    expect($entry)
        ->is_exclusive->toBeTrue()
        ->price_from->toBe(3500)
        ->has_price_range->toBeTrue()
        ->remaining->toBe(2)
        ->status->toBe(SlotStatus::Available);
});

test('shows a single price for an individual session when only one group size fits', function () {
    travelToTimetableMonday();
    $schedule = createTimetableClass(['price' => 3500, 'is_exclusive' => true], ['max_capacity' => 1]);
    $schedule->service->priceTiers()->createMany([
        ['participant_count' => 1, 'price' => 3500],
        ['participant_count' => 2, 'price' => 5000],
    ]);

    $entry = weekTimetable()['2026-10-07'][0];

    expect($entry)->price_from->toBe(3500)->has_price_range->toBeFalse();
});

test('does not list an individual session that is not bookable', function (Closure $makeUnbookable) {
    travelToTimetableMonday();
    $schedule = createTimetableClass(['is_exclusive' => true], ['day_of_week' => 1, 'start_time' => '12:00', 'max_capacity' => 3]);
    $makeUnbookable($schedule);

    $week = weekTimetable();

    expect($week['2026-10-05'])->toBe([]);
})->with([
    'already booked' => [fn (Schedule $schedule) => Booking::factory()->paid()->create(['schedule_id' => $schedule->id, 'booking_date' => '2026-10-05'])],
    'already started' => [fn (Schedule $schedule) => test()->travelTo(Carbon::parse('2026-10-05 12:30:00'))],
    'studio closed' => [fn (Schedule $schedule) => UnavailableDate::factory()->range(Carbon::parse('2026-10-05'), Carbon::parse('2026-10-05'))->create()],
]);

test('does not list classes beyond the twelve week booking horizon', function () {
    travelToTimetableMonday();
    createTimetableClass(['name' => 'Pēdējā diena'], ['day_of_week' => 1]);
    createTimetableClass(['name' => 'Par vēlu'], ['day_of_week' => 2]);

    $week = app(ScheduleAvailabilityService::class)->weekTimetable(Carbon::parse('2026-12-28'));

    expect(collect($week)->flatten(1)->pluck('service_name')->all())->toBe(['Pēdējā diena']);
});

test('loads the week with the same number of queries however many classes it holds', function () {
    travelToTimetableMonday();
    createTimetableClass();
    DB::enableQueryLog();
    weekTimetable();
    $queriesForOneClass = count(DB::getQueryLog());

    createTimetableClass();
    createTimetableClass(schedule: ['day_of_week' => 5]);
    DB::flushQueryLog();
    weekTimetable();

    expect(count(DB::getQueryLog()))->toBe($queriesForOneClass);
});

test('reports whether a single class date can be booked', function (array $schedule, string $date, SlotStatus $expected) {
    travelToTimetableMonday();
    $class = createTimetableClass(schedule: $schedule);

    $status = app(ScheduleAvailabilityService::class)->slotStatus($class->load('service'), Carbon::parse($date));

    expect($status)->toBe($expected);
})->with([
    'matching weekday' => [[], '2026-10-07', SlotStatus::Available],
    'wrong weekday' => [[], '2026-10-08', SlotStatus::Unavailable],
    'last day of the horizon' => [['day_of_week' => 1], '2026-12-28', SlotStatus::Available],
    'beyond the horizon' => [['day_of_week' => 2], '2026-12-29', SlotStatus::Unavailable],
    'inactive schedule' => [['is_active' => false], '2026-10-07', SlotStatus::Unavailable],
    'earlier today' => [['day_of_week' => 1, 'start_time' => '08:00'], '2026-10-05', SlotStatus::Past],
    'yesterday' => [['day_of_week' => 7], '2026-10-04', SlotStatus::Past],
]);

test('reports a single class date as full or cancelled', function () {
    travelToTimetableMonday();
    $full = createTimetableClass(schedule: ['max_capacity' => 1]);
    Booking::factory()->paid()->create(['schedule_id' => $full->id, 'booking_date' => '2026-10-07']);
    $cancelled = createTimetableClass(schedule: ['day_of_week' => 4]);
    UnavailableDate::factory()->range(Carbon::parse('2026-10-08'), Carbon::parse('2026-10-08'))->create();
    $availability = app(ScheduleAvailabilityService::class);

    expect($availability->slotStatus($full->load('service'), Carbon::parse('2026-10-07')))->toBe(SlotStatus::Full);
    expect($availability->slotStatus($cancelled->load('service'), Carbon::parse('2026-10-08')))->toBe(SlotStatus::Cancelled);
});

test('reports a class of an unbookable service as unavailable', function (array $service) {
    travelToTimetableMonday();
    $class = createTimetableClass($service);

    $status = app(ScheduleAvailabilityService::class)->slotStatus($class->load('service'), Carbon::parse('2026-10-07'));

    expect($status)->toBe(SlotStatus::Unavailable);
})->with([
    'inactive service' => [['is_active' => false]],
    'membership product' => [['is_membership' => true]],
]);

test('does not list the classes of an inactive coach', function () {
    travelToTimetableMonday();
    $inactiveCoach = Coach::factory()->create(['is_active' => false]);
    createTimetableClass(['name' => 'Neaktīva trenera nodarbība', 'coach_id' => $inactiveCoach->id]);
    createTimetableClass(['name' => 'Aktīva trenera nodarbība']);

    $week = weekTimetable();

    expect(collect($week)->flatten(1)->pluck('service_name')->all())->toBe(['Aktīva trenera nodarbība']);
});

test('reports a class of an inactive coach as unavailable', function () {
    travelToTimetableMonday();
    $inactiveCoach = Coach::factory()->create(['is_active' => false]);
    $class = createTimetableClass(['coach_id' => $inactiveCoach->id]);

    $status = app(ScheduleAvailabilityService::class)->slotStatus($class->load('service'), Carbon::parse('2026-10-07'));

    expect($status)->toBe(SlotStatus::Unavailable);
});
