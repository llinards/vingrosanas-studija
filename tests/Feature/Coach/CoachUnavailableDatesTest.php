<?php

use App\Models\Coach;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\ServiceType;
use App\Models\UnavailableDate;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->coach = Coach::factory()->create();
});

test('schedule list page shows unavailable dates component for coach', function () {
    $serviceType = ServiceType::factory()->create();
    $service = Service::factory()->create([
        'coach_id' => $this->coach->id,
        'service_type_id' => $serviceType->id,
        'is_active' => true,
    ]);
    Schedule::factory()->create([
        'service_id' => $service->id,
        'is_active' => true,
    ]);

    $this->get(route('admin.schedules.index'))
        ->assertSuccessful()
        ->assertSeeLivewire('coach.coach-unavailable-dates');
});

test('can add an unavailable date range for a coach', function () {
    $startDate = now()->addDays(5)->toDateString();
    $endDate = now()->addDays(10)->toDateString();

    Livewire::test('coach.coach-unavailable-dates', ['coachId' => $this->coach->id])
        ->set('start_date', $startDate)
        ->set('end_date', $endDate)
        ->call('add')
        ->assertHasNoErrors();

    expect(UnavailableDate::where('coach_id', $this->coach->id)
        ->whereDate('start_date', $startDate)
        ->whereDate('end_date', $endDate)
        ->exists())->toBeTrue();
});

test('can delete an unavailable date for a coach', function () {
    $unavailableDate = UnavailableDate::factory()->forCoach($this->coach)->create([
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(5)->toDateString(),
    ]);

    Livewire::test('coach.coach-unavailable-dates', ['coachId' => $this->coach->id])
        ->call('destroy', $unavailableDate->id)
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('unavailable_dates', [
        'id' => $unavailableDate->id,
    ]);
});

test('cannot delete another coach unavailable date', function () {
    $otherCoach = Coach::factory()->create();
    $unavailableDate = UnavailableDate::factory()->forCoach($otherCoach)->create();

    Livewire::test('coach.coach-unavailable-dates', ['coachId' => $this->coach->id])
        ->call('destroy', $unavailableDate->id);

    $this->assertDatabaseHas('unavailable_dates', [
        'id' => $unavailableDate->id,
    ]);
});

test('validation: end_date must be after or equal to start_date', function () {
    Livewire::test('coach.coach-unavailable-dates', ['coachId' => $this->coach->id])
        ->set('start_date', now()->addDays(10)->toDateString())
        ->set('end_date', now()->addDays(5)->toDateString())
        ->call('add')
        ->assertHasErrors(['end_date' => 'after_or_equal']);
});

test('validation: start_date cannot be in the past', function () {
    Livewire::test('coach.coach-unavailable-dates', ['coachId' => $this->coach->id])
        ->set('start_date', now()->subDay()->toDateString())
        ->set('end_date', now()->addDay()->toDateString())
        ->call('add')
        ->assertHasErrors(['start_date' => 'after_or_equal']);
});

test('validation: start_date is required', function () {
    Livewire::test('coach.coach-unavailable-dates', ['coachId' => $this->coach->id])
        ->set('start_date', '')
        ->set('end_date', now()->addDay()->toDateString())
        ->call('add')
        ->assertHasErrors(['start_date' => 'required']);
});

test('unavailable dates list hides past entries', function () {
    $past = UnavailableDate::factory()->forCoach($this->coach)->create([
        'start_date' => now()->subDays(10)->toDateString(),
        'end_date' => now()->subDay()->toDateString(),
    ]);

    Livewire::test('coach.coach-unavailable-dates', ['coachId' => $this->coach->id])
        ->assertDontSee($past->start_date->format('d.m.Y'))
        ->assertDontSee($past->end_date->format('d.m.Y'));
});

test('unavailable dates list shows ongoing entry spanning today', function () {
    UnavailableDate::factory()->forCoach($this->coach)->create([
        'start_date' => now()->subDays(2)->toDateString(),
        'end_date' => now()->addDays(2)->toDateString(),
    ]);

    Livewire::test('coach.coach-unavailable-dates', ['coachId' => $this->coach->id])
        ->assertSee(now()->addDays(2)->format('d.m.Y'));
});

test('can add an unavailable date with a time range', function () {
    $date = now()->addDays(3)->toDateString();

    Livewire::test('coach.coach-unavailable-dates', ['coachId' => $this->coach->id])
        ->set('start_date', $date)
        ->set('end_date', $date)
        ->set('start_time', '15:00')
        ->set('end_time', '20:00')
        ->call('add')
        ->assertHasNoErrors();

    $record = UnavailableDate::where('coach_id', $this->coach->id)->latest('id')->first();

    expect($record->start_time)->toStartWith('15:00')
        ->and($record->end_time)->toStartWith('20:00')
        ->and($record->isFullDay())->toBeFalse();
});

test('full-day block is preserved when both time fields are left blank', function () {
    $date = now()->addDays(3)->toDateString();

    Livewire::test('coach.coach-unavailable-dates', ['coachId' => $this->coach->id])
        ->set('start_date', $date)
        ->set('end_date', $date)
        ->call('add')
        ->assertHasNoErrors();

    $record = UnavailableDate::where('coach_id', $this->coach->id)->latest('id')->first();

    expect($record->start_time)->toBeNull()
        ->and($record->end_time)->toBeNull()
        ->and($record->isFullDay())->toBeTrue();
});

test('validation: end_time must be after start_time', function () {
    Livewire::test('coach.coach-unavailable-dates', ['coachId' => $this->coach->id])
        ->set('start_date', now()->addDay()->toDateString())
        ->set('end_date', now()->addDay()->toDateString())
        ->set('start_time', '20:00')
        ->set('end_time', '15:00')
        ->call('add')
        ->assertHasErrors(['end_time' => 'after']);
});

test('validation: start_time is required when end_time is set', function () {
    Livewire::test('coach.coach-unavailable-dates', ['coachId' => $this->coach->id])
        ->set('start_date', now()->addDay()->toDateString())
        ->set('end_date', now()->addDay()->toDateString())
        ->set('end_time', '20:00')
        ->call('add')
        ->assertHasErrors(['start_time' => 'required_with']);
});

test('deleting a coach cascades to their unavailable dates', function () {
    UnavailableDate::factory()->forCoach($this->coach)->create();
    UnavailableDate::factory()->forCoach($this->coach)->create();

    expect(UnavailableDate::forCoach($this->coach->id)->count())->toBe(2);

    $this->coach->delete();

    expect(UnavailableDate::forCoach($this->coach->id)->count())->toBe(0);
});
