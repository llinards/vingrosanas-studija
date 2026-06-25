<?php

use App\Models\Coach;
use App\Models\UnavailableDate;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('closure list page can be rendered', function () {
    $this->get(route('admin.closures.index'))
        ->assertSuccessful()
        ->assertSeeLivewire('closure.closure-list');
});

test('closure list page requires authentication', function () {
    auth()->logout();

    $this->get(route('admin.closures.index'))
        ->assertRedirect(route('login'));
});

test('can add a studio-wide closure', function () {
    $startDate = now()->addDays(5)->toDateString();
    $endDate = now()->addDays(10)->toDateString();

    Livewire::test('closure.closure-list')
        ->set('start_date', $startDate)
        ->set('end_date', $endDate)
        ->call('add')
        ->assertHasNoErrors();

    expect(UnavailableDate::studioWide()
        ->whereDate('start_date', $startDate)
        ->whereDate('end_date', $endDate)
        ->exists())->toBeTrue();
});

test('can delete a studio-wide closure', function () {
    $closure = UnavailableDate::factory()->create([
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(5)->toDateString(),
    ]);

    Livewire::test('closure.closure-list')
        ->call('destroy', $closure->id)
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('unavailable_dates', [
        'id' => $closure->id,
    ]);
});

test('cannot delete a per-coach unavailable date from closures page', function () {
    $coach = Coach::factory()->create();
    $unavailableDate = UnavailableDate::factory()->forCoach($coach)->create();

    Livewire::test('closure.closure-list')
        ->call('destroy', $unavailableDate->id);

    $this->assertDatabaseHas('unavailable_dates', [
        'id' => $unavailableDate->id,
    ]);
});

test('validation: end_date must be after or equal to start_date', function () {
    Livewire::test('closure.closure-list')
        ->set('start_date', now()->addDays(10)->toDateString())
        ->set('end_date', now()->addDays(5)->toDateString())
        ->call('add')
        ->assertHasErrors(['end_date' => 'after_or_equal']);
});

test('validation: start_date cannot be in the past', function () {
    Livewire::test('closure.closure-list')
        ->set('start_date', now()->subDay()->toDateString())
        ->set('end_date', now()->addDay()->toDateString())
        ->call('add')
        ->assertHasErrors(['start_date' => 'after_or_equal']);
});

test('closures page shows existing closures', function () {
    $date = now()->addDays(5);

    UnavailableDate::factory()->create([
        'start_date' => $date->toDateString(),
        'end_date' => $date->toDateString(),
    ]);

    Livewire::test('closure.closure-list')
        ->assertSee($date->format('d.m.Y'));
});

test('closures page hides past closures', function () {
    $past = UnavailableDate::factory()->create([
        'start_date' => now()->subDays(10)->toDateString(),
        'end_date' => now()->subDay()->toDateString(),
    ]);

    Livewire::test('closure.closure-list')
        ->assertDontSee($past->start_date->format('d.m.Y'))
        ->assertDontSee($past->end_date->format('d.m.Y'));
});

test('closures page shows ongoing closure spanning today', function () {
    UnavailableDate::factory()->create([
        'start_date' => now()->subDays(2)->toDateString(),
        'end_date' => now()->addDays(2)->toDateString(),
    ]);

    Livewire::test('closure.closure-list')
        ->assertSee(now()->addDays(2)->format('d.m.Y'));
});

test('can add a studio closure with a time range', function () {
    $date = now()->addDays(3)->toDateString();

    Livewire::test('closure.closure-list')
        ->set('start_date', $date)
        ->set('end_date', $date)
        ->set('start_time', '15:00')
        ->set('end_time', '20:00')
        ->call('add')
        ->assertHasNoErrors();

    $record = UnavailableDate::studioWide()->latest('id')->first();

    expect($record->start_time)->toStartWith('15:00')
        ->and($record->end_time)->toStartWith('20:00')
        ->and($record->isFullDay())->toBeFalse();
});

test('full-day studio closure is preserved when both time fields are left blank', function () {
    $date = now()->addDays(3)->toDateString();

    Livewire::test('closure.closure-list')
        ->set('start_date', $date)
        ->set('end_date', $date)
        ->call('add')
        ->assertHasNoErrors();

    $record = UnavailableDate::studioWide()->latest('id')->first();

    expect($record->start_time)->toBeNull()
        ->and($record->end_time)->toBeNull()
        ->and($record->isFullDay())->toBeTrue();
});

test('closure validation: end_time must be after start_time', function () {
    Livewire::test('closure.closure-list')
        ->set('start_date', now()->addDay()->toDateString())
        ->set('end_date', now()->addDay()->toDateString())
        ->set('start_time', '20:00')
        ->set('end_time', '15:00')
        ->call('add')
        ->assertHasErrors(['end_time' => 'after']);
});

test('closure validation: start_time is required when end_time is set', function () {
    Livewire::test('closure.closure-list')
        ->set('start_date', now()->addDay()->toDateString())
        ->set('end_date', now()->addDay()->toDateString())
        ->set('end_time', '20:00')
        ->call('add')
        ->assertHasErrors(['start_time' => 'required_with']);
});
