<?php

use App\Enums\DayOfWeek;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('schedule edit page can be rendered', function () {
    $schedule = Schedule::factory()->create();

    $this->get(route('schedule.edit', $schedule))
        ->assertSuccessful()
        ->assertSeeLivewire('schedule.schedule-edit');
});

test('schedule edit page requires authentication', function () {
    $schedule = Schedule::factory()->create();

    auth()->logout();

    $this->get(route('schedule.edit', $schedule))
        ->assertRedirect(route('login'));
});

test('schedule edit form is populated with recurring schedule data', function () {
    $schedule = Schedule::factory()->create([
        'day_of_week' => DayOfWeek::Tuesday->value,
        'start_time' => '10:30',
        'max_capacity' => 8,
        'is_active' => true,
    ]);

    Livewire::test('schedule.schedule-edit', ['schedule' => $schedule])
        ->assertSet('service_id', $schedule->service_id)
        ->assertSet('schedule_type', 'recurring')
        ->assertSet('day_of_week', DayOfWeek::Tuesday->value)
        ->assertSet('start_time', '10:30')
        ->assertSet('max_capacity', '8')
        ->assertSet('is_active', true);
});

test('schedule edit form is populated with specific date schedule data', function () {
    $schedule = Schedule::factory()->specificDate()->create([
        'date' => '2026-03-15',
        'start_time' => '14:00',
        'max_capacity' => 10,
    ]);

    Livewire::test('schedule.schedule-edit', ['schedule' => $schedule])
        ->assertSet('schedule_type', 'specific')
        ->assertSet('date', '2026-03-15')
        ->assertSet('day_of_week', null);
});

test('can update a recurring schedule', function () {
    $schedule = Schedule::factory()->create();
    $newService = Service::factory()->create();

    Livewire::test('schedule.schedule-edit', ['schedule' => $schedule])
        ->set('service_id', $newService->id)
        ->set('schedule_type', 'recurring')
        ->set('day_of_week', DayOfWeek::Friday->value)
        ->set('start_time', '09:00')
        ->set('max_capacity', '15')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('schedule-list'));

    $this->assertDatabaseHas('schedules', [
        'id' => $schedule->id,
        'service_id' => $newService->id,
        'day_of_week' => DayOfWeek::Friday->value,
        'date' => null,
        'start_time' => '09:00',
        'max_capacity' => 15,
    ]);
});

test('can update a schedule from recurring to specific date', function () {
    $schedule = Schedule::factory()->create(['day_of_week' => DayOfWeek::Monday->value]);

    Livewire::test('schedule.schedule-edit', ['schedule' => $schedule])
        ->set('schedule_type', 'specific')
        ->set('date', '2026-04-01')
        ->set('start_time', '16:00')
        ->set('max_capacity', '6')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('schedule-list'));

    $schedule->refresh();
    expect($schedule->day_of_week)->toBeNull()
        ->and($schedule->date->format('Y-m-d'))->toBe('2026-04-01');
});

test('can update a schedule from specific date to recurring', function () {
    $schedule = Schedule::factory()->specificDate()->create(['date' => '2026-03-15']);

    Livewire::test('schedule.schedule-edit', ['schedule' => $schedule])
        ->set('schedule_type', 'recurring')
        ->set('day_of_week', DayOfWeek::Wednesday->value)
        ->set('start_time', '11:00')
        ->set('max_capacity', '10')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('schedule-list'));

    $this->assertDatabaseHas('schedules', [
        'id' => $schedule->id,
        'day_of_week' => DayOfWeek::Wednesday->value,
        'date' => null,
    ]);
});

test('service is required', function () {
    $schedule = Schedule::factory()->create();

    Livewire::test('schedule.schedule-edit', ['schedule' => $schedule])
        ->set('service_id', null)
        ->call('save')
        ->assertHasErrors(['service_id' => 'required']);
});

test('start_time is required', function () {
    $schedule = Schedule::factory()->create();

    Livewire::test('schedule.schedule-edit', ['schedule' => $schedule])
        ->set('start_time', '')
        ->call('save')
        ->assertHasErrors(['start_time' => 'required']);
});

test('max_capacity is required', function () {
    $schedule = Schedule::factory()->create();

    Livewire::test('schedule.schedule-edit', ['schedule' => $schedule])
        ->set('max_capacity', '')
        ->call('save')
        ->assertHasErrors(['max_capacity' => 'required']);
});

test('can toggle is_active on a schedule', function () {
    $schedule = Schedule::factory()->create(['is_active' => true]);

    Livewire::test('schedule.schedule-edit', ['schedule' => $schedule])
        ->set('is_active', false)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('schedule-list'));

    $this->assertDatabaseHas('schedules', [
        'id' => $schedule->id,
        'is_active' => false,
    ]);
});

test('validation messages are in latvian', function () {
    $schedule = Schedule::factory()->create();

    Livewire::test('schedule.schedule-edit', ['schedule' => $schedule])
        ->set('service_id', null)
        ->call('save')
        ->assertSee('Pakalpojums ir obligāts.');
});
