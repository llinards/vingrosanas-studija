<?php

use App\Models\Coach;
use App\Models\Service;
use App\Models\ServiceType;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('service list page can be rendered', function () {
    $this->get(route('admin.services.index'))
        ->assertSuccessful();
});

test('service list page requires authentication', function () {
    auth()->logout();

    $this->get(route('admin.services.index'))
        ->assertRedirect(route('login'));
});

test('service list displays all services', function () {
    $services = Service::factory()->count(3)->create();

    $this->get(route('admin.services.index'))
        ->assertSuccessful()
        ->assertSee($services[0]->name)
        ->assertSee($services[1]->name)
        ->assertSee($services[2]->name);
});

test('service list shows empty state when no services exist', function () {
    $this->get(route('admin.services.index'))
        ->assertSuccessful()
        ->assertSee('Šobrīd nav neviena pakalpojuma!');
});

test('service list groups services by service type', function () {
    $serviceTypeA = ServiceType::factory()->create(['name' => 'Grupu nodarbības']);
    $serviceTypeB = ServiceType::factory()->create(['name' => 'Individuālās nodarbības']);
    $coach = Coach::factory()->create(['name' => 'Jānis Bērziņš']);

    Service::factory()->create([
        'name' => 'Joga',
        'service_type_id' => $serviceTypeA->id,
        'coach_id' => $coach->id,
    ]);
    Service::factory()->create([
        'name' => 'Personālais treniņš',
        'service_type_id' => $serviceTypeB->id,
        'coach_id' => $coach->id,
    ]);

    $this->get(route('admin.services.index'))
        ->assertSuccessful()
        ->assertSee('Grupu nodarbības')
        ->assertSee('Individuālās nodarbības')
        ->assertSee('Joga')
        ->assertSee('Personālais treniņš')
        ->assertSee('Jānis Bērziņš');
});

test('service list shows active status badge', function () {
    Service::factory()->create(['name' => 'Aktīvs pakalpojums', 'is_active' => true]);
    Service::factory()->create(['name' => 'Neaktīvs pakalpojums', 'is_active' => false]);

    $this->get(route('admin.services.index'))
        ->assertSuccessful()
        ->assertSee('Aktīvs pakalpojums')
        ->assertSee('Neaktīvs pakalpojums');
});

test('can toggle service active status', function () {
    $service = Service::factory()->create(['is_active' => true]);

    Livewire::test('service.service-list')
        ->call('toggleActive', $service->id)
        ->assertHasNoErrors();

    $this->assertDatabaseHas('services', ['id' => $service->id, 'is_active' => false]);

    Livewire::test('service.service-list')
        ->call('toggleActive', $service->id)
        ->assertHasNoErrors();

    $this->assertDatabaseHas('services', ['id' => $service->id, 'is_active' => true]);
});

test('can destroy a service', function () {
    $service = Service::factory()->create();

    Livewire::test('service.service-list')
        ->call('destroy', $service->id)
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('services', ['id' => $service->id]);
});

test('services list refreshes after deletion', function () {
    $services = Service::factory()->count(2)->create();

    $component = Livewire::test('service.service-list')
        ->assertSee($services[0]->name)
        ->assertSee($services[1]->name);

    $component->call('destroy', $services[0]->id);

    $component->assertDontSee($services[0]->name)
        ->assertSee($services[1]->name);
});
