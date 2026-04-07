<?php

use App\Models\Coach;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\ServiceType;
use App\Models\UnavailableDate;
use App\Services\ScheduleAvailabilityService;

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

test('studio-wide closure marks date as unavailable', function () {
    $coach = Coach::factory()->create();
    [$service, $schedule, $tomorrow] = createScheduleForTomorrow($coach);

    UnavailableDate::factory()->range($tomorrow, $tomorrow)->create();

    $availability = new ScheduleAvailabilityService;
    $schedules = Schedule::with('service')->where('id', $schedule->id)->get();

    $unavailable = $availability->unavailableDates($schedules, $tomorrow->copy(), $tomorrow->copy());

    expect($unavailable)->toContain($tomorrow->toDateString());
});

test('studio-wide closure returns empty time slots', function () {
    $coach = Coach::factory()->create();
    [$service, $schedule, $tomorrow] = createScheduleForTomorrow($coach);

    UnavailableDate::factory()->range($tomorrow, $tomorrow)->create();

    $availability = new ScheduleAvailabilityService;
    $schedules = Schedule::with('service')->where('id', $schedule->id)->get();

    $slots = $availability->availableTimeSlots($schedules, $tomorrow->copy());

    expect($slots)->toBeEmpty();
});

test('per-coach unavailability blocks only that coach schedules', function () {
    $coach1 = Coach::factory()->create();
    $coach2 = Coach::factory()->create();

    [$service1, $schedule1, $tomorrow] = createScheduleForTomorrow($coach1);
    [$service2, $schedule2] = createScheduleForTomorrow($coach2, [
        'day_of_week' => $tomorrow->dayOfWeekIso,
    ]);

    UnavailableDate::factory()->forCoach($coach1)->range($tomorrow, $tomorrow)->create();

    $availability = new ScheduleAvailabilityService;
    $schedules = Schedule::with('service')->whereIn('id', [$schedule1->id, $schedule2->id])->get();

    $slots = $availability->availableTimeSlots($schedules, $tomorrow->copy());

    expect($slots)->toHaveCount(1)
        ->and($slots[0]['schedule_id'])->toBe($schedule2->id);
});

test('other coaches remain available when one coach is blocked', function () {
    $coach1 = Coach::factory()->create();
    $coach2 = Coach::factory()->create();

    [$service1, $schedule1, $tomorrow] = createScheduleForTomorrow($coach1);
    [$service2, $schedule2] = createScheduleForTomorrow($coach2, [
        'day_of_week' => $tomorrow->dayOfWeekIso,
    ]);

    UnavailableDate::factory()->forCoach($coach1)->range($tomorrow, $tomorrow)->create();

    $availability = new ScheduleAvailabilityService;
    $schedules = Schedule::with('service')->whereIn('id', [$schedule1->id, $schedule2->id])->get();

    $unavailable = $availability->unavailableDates($schedules, $tomorrow->copy(), $tomorrow->copy());

    expect($unavailable)->not->toContain($tomorrow->toDateString());
});

test('multi-day range blocks all dates in span', function () {
    $coach = Coach::factory()->create();
    $serviceType = ServiceType::factory()->create();
    $service = Service::factory()->create([
        'service_type_id' => $serviceType->id,
        'coach_id' => $coach->id,
        'is_active' => true,
    ]);

    $startDate = now()->addDays(1);
    $endDate = now()->addDays(3);

    // Create schedules for each of the 3 days
    foreach (range(1, 3) as $day) {
        $date = now()->addDays($day);
        Schedule::factory()->create([
            'service_id' => $service->id,
            'date' => $date->toDateString(),
            'day_of_week' => null,
            'start_time' => '10:00',
            'is_active' => true,
            'max_capacity' => 5,
        ]);
    }

    UnavailableDate::factory()->range($startDate, $endDate)->create();

    $availability = new ScheduleAvailabilityService;
    $schedules = Schedule::with('service')->where('service_id', $service->id)->get();

    $unavailable = $availability->unavailableDates($schedules, $startDate->copy(), $endDate->copy());
    $unavailableDates = explode(',', $unavailable);

    expect($unavailableDates)->toContain($startDate->toDateString())
        ->toContain(now()->addDays(2)->toDateString())
        ->toContain($endDate->toDateString());
});

test('dates outside the range remain available', function () {
    $coach = Coach::factory()->create();
    $serviceType = ServiceType::factory()->create();
    $service = Service::factory()->create([
        'service_type_id' => $serviceType->id,
        'coach_id' => $coach->id,
        'is_active' => true,
    ]);

    $blockStart = now()->addDays(2);
    $blockEnd = now()->addDays(3);

    // Create schedules for days 1-4
    foreach (range(1, 4) as $day) {
        $date = now()->addDays($day);
        Schedule::factory()->create([
            'service_id' => $service->id,
            'date' => $date->toDateString(),
            'day_of_week' => null,
            'start_time' => '10:00',
            'is_active' => true,
            'max_capacity' => 5,
        ]);
    }

    UnavailableDate::factory()->range($blockStart, $blockEnd)->create();

    $availability = new ScheduleAvailabilityService;
    $schedules = Schedule::with('service')->where('service_id', $service->id)->get();

    $rangeStart = now()->addDays(1);
    $rangeEnd = now()->addDays(4);
    $unavailable = $availability->unavailableDates($schedules, $rangeStart->copy(), $rangeEnd->copy());

    expect($unavailable)->not->toContain($rangeStart->toDateString())
        ->and($unavailable)->not->toContain($rangeEnd->toDateString());
});

test('overlapping coach and studio-wide blocks are handled correctly', function () {
    $coach = Coach::factory()->create();
    [$service, $schedule, $tomorrow] = createScheduleForTomorrow($coach);

    // Both studio-wide and per-coach block for the same date
    UnavailableDate::factory()->range($tomorrow, $tomorrow)->create(); // studio-wide
    UnavailableDate::factory()->forCoach($coach)->range($tomorrow, $tomorrow)->create(); // per-coach

    $availability = new ScheduleAvailabilityService;
    $schedules = Schedule::with('service')->where('id', $schedule->id)->get();

    $unavailable = $availability->unavailableDates($schedules, $tomorrow->copy(), $tomorrow->copy());
    $slots = $availability->availableTimeSlots($schedules, $tomorrow->copy());

    expect($unavailable)->toContain($tomorrow->toDateString())
        ->and($slots)->toBeEmpty();
});

test('no unavailable date records does not affect existing behavior', function () {
    $coach = Coach::factory()->create();
    [$service, $schedule, $tomorrow] = createScheduleForTomorrow($coach);

    $availability = new ScheduleAvailabilityService;
    $schedules = Schedule::with('service')->where('id', $schedule->id)->get();

    $unavailable = $availability->unavailableDates($schedules, $tomorrow->copy(), $tomorrow->copy());
    $slots = $availability->availableTimeSlots($schedules, $tomorrow->copy());

    expect($unavailable)->not->toContain($tomorrow->toDateString())
        ->and($slots)->toHaveCount(1)
        ->and($slots[0]['schedule_id'])->toBe($schedule->id);
});
