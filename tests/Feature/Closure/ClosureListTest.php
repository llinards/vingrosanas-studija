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
    UnavailableDate::factory()->create([
        'start_date' => '2026-06-15',
        'end_date' => '2026-06-15',
    ]);

    Livewire::test('closure.closure-list')
        ->assertSee('15.06.2026');
});
