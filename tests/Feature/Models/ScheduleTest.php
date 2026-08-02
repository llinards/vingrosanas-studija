<?php

use App\Enums\DayOfWeek;
use App\Models\Schedule;
use App\Models\Service;
use Illuminate\Support\Carbon;

it('belongs to a service', function () {
    $schedule = Schedule::factory()->create();

    expect($schedule->service)->toBeInstanceOf(Service::class);
});

it('is accessible through service relationship', function () {
    $service = Service::factory()->create();
    $schedules = Schedule::factory()->count(3)->for($service)->create();

    expect($service->schedules)
        ->toHaveCount(3)
        ->each->toBeInstanceOf(Schedule::class);
});

it('casts day_of_week to DayOfWeek enum', function () {
    $schedule = Schedule::factory()->create(['day_of_week' => 1]);

    expect($schedule->day_of_week)->toBe(DayOfWeek::Monday);
});

it('casts date to carbon instance', function () {
    $schedule = Schedule::factory()->specificDate()->create();

    expect($schedule->date)->toBeInstanceOf(Carbon::class);
});

it('casts is_active to boolean', function () {
    $schedule = Schedule::factory()->create(['is_active' => true]);

    expect($schedule->is_active)->toBeTrue()->toBeBool();
});

it('can create recurring schedule via factory', function () {
    $schedule = Schedule::factory()->create();

    expect($schedule->day_of_week)->toBeInstanceOf(DayOfWeek::class)
        ->and($schedule->date)->toBeNull();
});

it('can create specific date schedule via factory', function () {
    $schedule = Schedule::factory()->specificDate()->create();

    expect($schedule->day_of_week)->toBeNull()
        ->and($schedule->date)->not->toBeNull();
});
