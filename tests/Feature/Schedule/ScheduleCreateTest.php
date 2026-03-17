<?php

use App\Enums\DayOfWeek;
use App\Models\Service;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('schedule create page can be rendered', function () {
    $this->get(route('admin.schedules.create'))
        ->assertSuccessful()
        ->assertSeeLivewire('schedule.schedule-create');
});

test('schedule create page requires authentication', function () {
    auth()->logout();

    $this->get(route('admin.schedules.create'))
        ->assertRedirect(route('login'));
});

test('can create a recurring schedule with valid data', function () {
    $service = Service::factory()->create();

    Livewire::test('schedule.schedule-create')
        ->set('coach_id', $service->coach_id)
        ->set('service_id', $service->id)
        ->set('schedule_type', 'recurring')
        ->set('day_of_week', DayOfWeek::Monday->value)
        ->set('start_time', '10:00')
        ->set('max_capacity', '8')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.schedules.index'));

    $this->assertDatabaseHas('schedules', [
        'service_id' => $service->id,
        'day_of_week' => DayOfWeek::Monday->value,
        'date' => null,
        'start_time' => '10:00',
        'max_capacity' => 8,
        'is_active' => false,
    ]);
});

test('can create a specific date schedule with valid data', function () {
    $service = Service::factory()->create();

    Livewire::test('schedule.schedule-create')
        ->set('coach_id', $service->coach_id)
        ->set('service_id', $service->id)
        ->set('schedule_type', 'specific')
        ->set('date', '2026-03-15')
        ->set('start_time', '15:00')
        ->set('max_capacity', '10')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.schedules.index'));

    $this->assertDatabaseHas('schedules', [
        'service_id' => $service->id,
        'day_of_week' => null,
        'start_time' => '15:00',
        'max_capacity' => 10,
    ]);
});

test('service is required', function () {
    Livewire::test('schedule.schedule-create')
        ->set('service_id', null)
        ->call('save')
        ->assertHasErrors(['service_id' => 'required']);
});

test('service must exist', function () {
    Livewire::test('schedule.schedule-create')
        ->set('service_id', 99999)
        ->call('save')
        ->assertHasErrors(['service_id' => 'exists']);
});

test('day_of_week is required for recurring schedules', function () {
    Livewire::test('schedule.schedule-create')
        ->set('schedule_type', 'recurring')
        ->set('day_of_week', null)
        ->call('save')
        ->assertHasErrors(['day_of_week' => 'required']);
});

test('day_of_week must be between 1 and 7', function () {
    $service = Service::factory()->create();

    Livewire::test('schedule.schedule-create')
        ->set('service_id', $service->id)
        ->set('schedule_type', 'recurring')
        ->set('day_of_week', 8)
        ->set('start_time', '10:00')
        ->set('max_capacity', '5')
        ->call('save')
        ->assertHasErrors(['day_of_week' => 'between']);
});

test('date is required for specific schedules', function () {
    Livewire::test('schedule.schedule-create')
        ->set('schedule_type', 'specific')
        ->set('date', null)
        ->call('save')
        ->assertHasErrors(['date' => 'required']);
});

test('date must be a valid date', function () {
    Livewire::test('schedule.schedule-create')
        ->set('schedule_type', 'specific')
        ->set('date', 'not-a-date')
        ->call('save')
        ->assertHasErrors(['date' => 'date']);
});

test('start_time is required', function () {
    Livewire::test('schedule.schedule-create')
        ->set('start_time', '')
        ->call('save')
        ->assertHasErrors(['start_time' => 'required']);
});

test('start_time must be valid time format', function () {
    Livewire::test('schedule.schedule-create')
        ->set('start_time', 'invalid')
        ->call('save')
        ->assertHasErrors(['start_time' => 'date_format']);
});

test('max_capacity is required', function () {
    Livewire::test('schedule.schedule-create')
        ->set('max_capacity', '')
        ->call('save')
        ->assertHasErrors(['max_capacity' => 'required']);
});

test('max_capacity must be an integer', function () {
    Livewire::test('schedule.schedule-create')
        ->set('max_capacity', 'abc')
        ->call('save')
        ->assertHasErrors(['max_capacity' => 'integer']);
});

test('max_capacity must be at least 1', function () {
    Livewire::test('schedule.schedule-create')
        ->set('max_capacity', '0')
        ->call('save')
        ->assertHasErrors(['max_capacity' => 'min']);
});

test('can create a schedule with is_active enabled', function () {
    $service = Service::factory()->create();

    Livewire::test('schedule.schedule-create')
        ->set('coach_id', $service->coach_id)
        ->set('service_id', $service->id)
        ->set('schedule_type', 'recurring')
        ->set('day_of_week', DayOfWeek::Wednesday->value)
        ->set('start_time', '18:00')
        ->set('max_capacity', '12')
        ->set('is_active', true)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.schedules.index'));

    $this->assertDatabaseHas('schedules', [
        'service_id' => $service->id,
        'is_active' => true,
    ]);
});

test('validation messages are in latvian', function () {
    Livewire::test('schedule.schedule-create')
        ->set('service_id', null)
        ->call('save')
        ->assertSee('Pakalpojums ir obligāts.');
});
