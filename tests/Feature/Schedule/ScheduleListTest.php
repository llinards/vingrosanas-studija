<?php

use App\Enums\DayOfWeek;
use App\Models\Schedule;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('schedule list page can be rendered', function () {
    $this->get(route('schedule-list'))
        ->assertSuccessful();
});

test('schedule list page requires authentication', function () {
    auth()->logout();

    $this->get(route('schedule-list'))
        ->assertRedirect(route('login'));
});

test('schedule list displays all schedules', function () {
    $schedules = Schedule::factory()->count(3)->create();

    $this->get(route('schedule-list'))
        ->assertSuccessful()
        ->assertSee($schedules[0]->service->name)
        ->assertSee($schedules[1]->service->name)
        ->assertSee($schedules[2]->service->name);
});

test('schedule list shows empty state when no schedules exist', function () {
    $this->get(route('schedule-list'))
        ->assertSuccessful()
        ->assertSee('Šobrīd nav neviena grafika!');
});

test('schedule list displays service and coach names', function () {
    $schedule = Schedule::factory()->create();

    $this->get(route('schedule-list'))
        ->assertSuccessful()
        ->assertSee($schedule->service->name)
        ->assertSee($schedule->service->coach->name);
});

test('schedule list shows recurring badge for recurring schedules', function () {
    Schedule::factory()->create(['day_of_week' => DayOfWeek::Monday->value]);

    $this->get(route('schedule-list'))
        ->assertSuccessful()
        ->assertSee('Regulārs');
});

test('schedule list shows specific badge for specific date schedules', function () {
    Schedule::factory()->specificDate()->create();

    $this->get(route('schedule-list'))
        ->assertSuccessful()
        ->assertSee('Vienreizējs');
});

test('can toggle schedule active status', function () {
    $schedule = Schedule::factory()->create(['is_active' => true]);

    Livewire::test('schedule.schedule-list')
        ->call('toggleActive', $schedule->id)
        ->assertHasNoErrors();

    $this->assertDatabaseHas('schedules', ['id' => $schedule->id, 'is_active' => false]);

    Livewire::test('schedule.schedule-list')
        ->call('toggleActive', $schedule->id)
        ->assertHasNoErrors();

    $this->assertDatabaseHas('schedules', ['id' => $schedule->id, 'is_active' => true]);
});

test('can destroy a schedule', function () {
    $schedule = Schedule::factory()->create();

    Livewire::test('schedule.schedule-list')
        ->call('destroy', $schedule->id)
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('schedules', ['id' => $schedule->id]);
});

test('schedules list refreshes after deletion', function () {
    $schedules = Schedule::factory()->count(2)->create();

    $component = Livewire::test('schedule.schedule-list')
        ->assertSee($schedules[0]->service->name)
        ->assertSee($schedules[1]->service->name);

    $component->call('destroy', $schedules[0]->id);

    $component->assertDontSee($schedules[0]->service->name)
        ->assertSee($schedules[1]->service->name);
});
