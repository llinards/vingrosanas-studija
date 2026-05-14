<?php

use App\Models\Coach;
use App\Models\UnavailableDate;
use Illuminate\Support\Carbon;

it('belongs to a coach', function () {
    $coach = Coach::factory()->create();
    $unavailableDate = UnavailableDate::factory()->forCoach($coach)->create();

    expect($unavailableDate->coach)->toBeInstanceOf(Coach::class)
        ->and($unavailableDate->coach->id)->toBe($coach->id);
});

it('can be studio-wide with null coach', function () {
    $unavailableDate = UnavailableDate::factory()->create();

    expect($unavailableDate->coach_id)->toBeNull()
        ->and($unavailableDate->coach)->toBeNull();
});

it('casts dates to carbon instances', function () {
    $unavailableDate = UnavailableDate::factory()->create();

    expect($unavailableDate->start_date)->toBeInstanceOf(Carbon::class)
        ->and($unavailableDate->end_date)->toBeInstanceOf(Carbon::class);
});

it('forDate scope returns records covering the given date', function () {
    $start = now()->addDays(5);
    $end = now()->addDays(10);

    UnavailableDate::factory()->range($start, $end)->create();

    $midDate = now()->addDays(7);

    expect(UnavailableDate::forDate($midDate)->count())->toBe(1);
});

it('forDate scope includes start and end date boundaries', function () {
    $start = now()->addDays(5);
    $end = now()->addDays(10);

    UnavailableDate::factory()->range($start, $end)->create();

    expect(UnavailableDate::forDate($start)->count())->toBe(1)
        ->and(UnavailableDate::forDate($end)->count())->toBe(1);
});

it('forDate scope excludes dates outside the range', function () {
    $start = now()->addDays(5);
    $end = now()->addDays(10);

    UnavailableDate::factory()->range($start, $end)->create();

    expect(UnavailableDate::forDate(now()->addDays(4))->count())->toBe(0)
        ->and(UnavailableDate::forDate(now()->addDays(11))->count())->toBe(0);
});

it('studioWide scope returns only records with null coach_id', function () {
    $coach = Coach::factory()->create();

    UnavailableDate::factory()->create(); // studio-wide
    UnavailableDate::factory()->forCoach($coach)->create(); // per-coach

    expect(UnavailableDate::studioWide()->count())->toBe(1)
        ->and(UnavailableDate::studioWide()->first()->coach_id)->toBeNull();
});

it('forCoach scope returns only records for the specific coach', function () {
    $coach1 = Coach::factory()->create();
    $coach2 = Coach::factory()->create();

    UnavailableDate::factory()->forCoach($coach1)->create();
    UnavailableDate::factory()->forCoach($coach2)->create();
    UnavailableDate::factory()->create(); // studio-wide

    expect(UnavailableDate::forCoach($coach1->id)->count())->toBe(1)
        ->and(UnavailableDate::forCoach($coach2->id)->count())->toBe(1);
});

it('is accessible through coach relationship', function () {
    $coach = Coach::factory()->create();
    UnavailableDate::factory()->count(3)->forCoach($coach)->create();

    expect($coach->unavailableDates)
        ->toHaveCount(3)
        ->each->toBeInstanceOf(UnavailableDate::class);
});

it('is deleted when coach is deleted', function () {
    $coach = Coach::factory()->create();
    UnavailableDate::factory()->forCoach($coach)->create();

    expect(UnavailableDate::count())->toBe(1);

    $coach->delete();

    expect(UnavailableDate::count())->toBe(0);
});

it('treats a record without start_time or end_time as full-day', function () {
    $unavailableDate = UnavailableDate::factory()->create();

    expect($unavailableDate->isFullDay())->toBeTrue()
        ->and($unavailableDate->coversTime('00:00:00'))->toBeTrue()
        ->and($unavailableDate->coversTime('23:59:59'))->toBeTrue();
});

it('covers times inside a half-open time-range', function () {
    $unavailableDate = UnavailableDate::factory()
        ->withTimeRange('15:00', '20:00')
        ->create();

    expect($unavailableDate->isFullDay())->toBeFalse()
        ->and($unavailableDate->coversTime('15:00:00'))->toBeTrue()
        ->and($unavailableDate->coversTime('17:30:00'))->toBeTrue()
        ->and($unavailableDate->coversTime('19:59:59'))->toBeTrue()
        ->and($unavailableDate->coversTime('20:00:00'))->toBeFalse()
        ->and($unavailableDate->coversTime('14:59:59'))->toBeFalse();
});
