<?php

use App\Enums\SlotStatus;
use App\Models\Coach;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\ServiceType;
use App\Models\UnavailableDate;
use App\Services\ScheduleAvailabilityService;
use Illuminate\Support\Carbon;

function createScheduleForTomorrow(Coach $coach, array $scheduleOverrides = []): array
{
    $serviceType = ServiceType::factory()->create();
    $service = Service::factory()->create([
        'service_type_id' => $serviceType->id,
        'coach_id' => $coach->id,
        'is_active' => true,
    ]);
    $tomorrow = now()->addDay();
    $schedule = Schedule::factory()->create(array_merge([
        'service_id' => $service->id,
        'day_of_week' => $tomorrow->dayOfWeekIso,
        'start_time' => '10:00',
        'is_active' => true,
        'max_capacity' => 5,
    ], $scheduleOverrides));

    return [$service, $schedule, $tomorrow];
}

/**
 * Create a one-off class at the given time on each of the given days from today.
 *
 * @param  list<int>  $daysFromToday
 * @return list<Schedule>
 */
function createOneOffClasses(Coach $coach, array $daysFromToday, string $startTime): array
{
    $service = Service::factory()->create(['coach_id' => $coach->id, 'is_active' => true]);

    return array_map(fn (int $day) => Schedule::factory()->create([
        'service_id' => $service->id,
        'date' => now()->addDays($day)->toDateString(),
        'day_of_week' => null,
        'start_time' => $startTime,
        'is_active' => true,
        'max_capacity' => 5,
    ]), $daysFromToday);
}

/**
 * The status of a class on a date, as the booking and membership checks see it.
 */
function slotStatusOn(Schedule $schedule, Carbon $date): SlotStatus
{
    return app(ScheduleAvailabilityService::class)->slotStatus($schedule->fresh(['service.coach']), $date->copy());
}

/**
 * The status of a one-off class as the week timetable shows it on its date.
 */
function timetableStatusOf(Schedule $schedule): SlotStatus
{
    $date = $schedule->date->toDateString();
    $week = app(ScheduleAvailabilityService::class)->weekTimetable($schedule->date->copy()->startOfWeek(Carbon::MONDAY));

    return collect($week[$date])->firstWhere('schedule_id', $schedule->id)['status'];
}

test('a studio-wide closure cancels the class', function () {
    $coach = Coach::factory()->create();
    [, $schedule, $tomorrow] = createScheduleForTomorrow($coach);

    UnavailableDate::factory()->range($tomorrow, $tomorrow)->create();

    expect(slotStatusOn($schedule, $tomorrow))->toBe(SlotStatus::Cancelled);
});

test('a coach absence cancels only that coach\'s classes', function () {
    $coach1 = Coach::factory()->create();
    $coach2 = Coach::factory()->create();

    [, $schedule1, $tomorrow] = createScheduleForTomorrow($coach1);
    [, $schedule2] = createScheduleForTomorrow($coach2, [
        'day_of_week' => $tomorrow->dayOfWeekIso,
    ]);

    UnavailableDate::factory()->forCoach($coach1)->range($tomorrow, $tomorrow)->create();

    expect(slotStatusOn($schedule1, $tomorrow))->toBe(SlotStatus::Cancelled)
        ->and(slotStatusOn($schedule2, $tomorrow))->toBe(SlotStatus::Available);
});

test('a multi-day closure cancels every class in the span and none outside it', function () {
    $coach = Coach::factory()->create();
    [$before, $first, $last, $after] = createOneOffClasses($coach, [1, 2, 3, 4], '10:00');

    UnavailableDate::factory()->range(now()->addDays(2), now()->addDays(3))->create();

    expect(timetableStatusOf($before))->toBe(SlotStatus::Available)
        ->and(timetableStatusOf($first))->toBe(SlotStatus::Cancelled)
        ->and(timetableStatusOf($last))->toBe(SlotStatus::Cancelled)
        ->and(timetableStatusOf($after))->toBe(SlotStatus::Available);
});

test('overlapping coach and studio-wide closures cancel the class', function () {
    $coach = Coach::factory()->create();
    [, $schedule, $tomorrow] = createScheduleForTomorrow($coach);

    UnavailableDate::factory()->range($tomorrow, $tomorrow)->create();
    UnavailableDate::factory()->forCoach($coach)->range($tomorrow, $tomorrow)->create();

    expect(slotStatusOn($schedule, $tomorrow))->toBe(SlotStatus::Cancelled);
});

test('a coach time window cancels only the classes starting inside it', function () {
    $coach = Coach::factory()->create();
    [, $morning, $tomorrow] = createScheduleForTomorrow($coach, ['start_time' => '10:00']);
    [, $afternoon] = createScheduleForTomorrow($coach, ['start_time' => '17:00']);
    [, $evening] = createScheduleForTomorrow($coach, ['start_time' => '21:00']);

    UnavailableDate::factory()
        ->forCoach($coach)
        ->range($tomorrow, $tomorrow)
        ->withTimeRange('15:00', '20:00')
        ->create();

    expect(slotStatusOn($morning, $tomorrow))->toBe(SlotStatus::Available)
        ->and(slotStatusOn($afternoon, $tomorrow))->toBe(SlotStatus::Cancelled)
        ->and(slotStatusOn($evening, $tomorrow))->toBe(SlotStatus::Available);
});

test('a time window includes its start and excludes its end', function () {
    $coach = Coach::factory()->create();
    [, $atStart, $tomorrow] = createScheduleForTomorrow($coach, ['start_time' => '15:00']);
    [, $atEnd] = createScheduleForTomorrow($coach, ['start_time' => '20:00']);

    UnavailableDate::factory()
        ->forCoach($coach)
        ->range($tomorrow, $tomorrow)
        ->withTimeRange('15:00', '20:00')
        ->create();

    expect(slotStatusOn($atStart, $tomorrow))->toBe(SlotStatus::Cancelled)
        ->and(slotStatusOn($atEnd, $tomorrow))->toBe(SlotStatus::Available);
});

test('a studio-wide time window cancels the matching classes of every coach', function () {
    $coach1 = Coach::factory()->create();
    $coach2 = Coach::factory()->create();

    [, $schedule1, $tomorrow] = createScheduleForTomorrow($coach1, ['start_time' => '17:00']);
    [, $schedule2] = createScheduleForTomorrow($coach2, [
        'day_of_week' => $tomorrow->dayOfWeekIso,
        'start_time' => '17:00',
    ]);

    UnavailableDate::factory()
        ->range($tomorrow, $tomorrow)
        ->withTimeRange('15:00', '20:00')
        ->create();

    expect(slotStatusOn($schedule1, $tomorrow))->toBe(SlotStatus::Cancelled)
        ->and(slotStatusOn($schedule2, $tomorrow))->toBe(SlotStatus::Cancelled);
});

test('a time window applies on each day of a multi-day closure', function () {
    $coach = Coach::factory()->create();
    $classes = createOneOffClasses($coach, [1, 2, 3], '17:00');

    UnavailableDate::factory()
        ->forCoach($coach)
        ->range(now()->addDay(), now()->addDays(3))
        ->withTimeRange('15:00', '20:00')
        ->create();

    foreach ($classes as $class) {
        expect(timetableStatusOf($class))->toBe(SlotStatus::Cancelled);
    }
});

test('a full-day closure on one date and a time window on the next both apply', function () {
    $coach = Coach::factory()->create();
    [$firstDay, $secondDay] = createOneOffClasses($coach, [1, 2], '17:00');

    UnavailableDate::factory()->forCoach($coach)->range(now()->addDay(), now()->addDay())->create();
    UnavailableDate::factory()
        ->forCoach($coach)
        ->range(now()->addDays(2), now()->addDays(2))
        ->withTimeRange('15:00', '20:00')
        ->create();

    expect(slotStatusOn($firstDay, now()->addDay()))->toBe(SlotStatus::Cancelled)
        ->and(slotStatusOn($secondDay, now()->addDays(2)))->toBe(SlotStatus::Cancelled);
});

test('a class stays available when there are no closures', function () {
    $coach = Coach::factory()->create();
    [, $schedule, $tomorrow] = createScheduleForTomorrow($coach);

    expect(slotStatusOn($schedule, $tomorrow))->toBe(SlotStatus::Available);
});
