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

test('service create page can be rendered', function () {
    $this->get(route('admin.services.create'))
        ->assertSuccessful()
        ->assertSeeLivewire('service.service-create');
});

test('service create page requires authentication', function () {
    auth()->logout();

    $this->get(route('admin.services.create'))
        ->assertRedirect(route('login'));
});

test('can create a service with valid data', function () {
    $serviceType = ServiceType::factory()->create();
    $coach = Coach::factory()->create();

    Livewire::test('service.service-create')
        ->set('name', 'Jogas nodarbība')
        ->set('service_type_id', $serviceType->id)
        ->set('coach_id', $coach->id)
        ->set('price', '25.00')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.services.index'));

    $this->assertDatabaseHas('services', [
        'name' => 'Jogas nodarbība',
        'service_type_id' => $serviceType->id,
        'coach_id' => $coach->id,
        'price' => 2500,
        'is_active' => false,
    ]);
});

test('name is required', function () {
    Livewire::test('service.service-create')
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name' => 'required']);
});

test('service type is required', function () {
    Livewire::test('service.service-create')
        ->set('service_type_id', null)
        ->call('save')
        ->assertHasErrors(['service_type_id' => 'required']);
});

test('coach is required', function () {
    Livewire::test('service.service-create')
        ->set('coach_id', null)
        ->call('save')
        ->assertHasErrors(['coach_id' => 'required']);
});

test('price is required', function () {
    Livewire::test('service.service-create')
        ->set('price', '')
        ->call('save')
        ->assertHasErrors(['price' => 'required']);
});

test('price must be numeric', function () {
    Livewire::test('service.service-create')
        ->set('price', 'abc')
        ->call('save')
        ->assertHasErrors(['price' => 'numeric']);
});

test('price cannot be negative', function () {
    Livewire::test('service.service-create')
        ->set('price', '-5')
        ->call('save')
        ->assertHasErrors(['price' => 'min']);
});

test('service type must exist', function () {
    Livewire::test('service.service-create')
        ->set('service_type_id', 99999)
        ->call('save')
        ->assertHasErrors(['service_type_id' => 'exists']);
});

test('coach must exist', function () {
    Livewire::test('service.service-create')
        ->set('coach_id', 99999)
        ->call('save')
        ->assertHasErrors(['coach_id' => 'exists']);
});

test('name cannot exceed 255 characters', function () {
    Livewire::test('service.service-create')
        ->set('name', str_repeat('a', 256))
        ->call('save')
        ->assertHasErrors(['name' => 'max']);
});

test('can create a service with is_active enabled', function () {
    $serviceType = ServiceType::factory()->create();
    $coach = Coach::factory()->create();

    Livewire::test('service.service-create')
        ->set('name', 'Aktīvs pakalpojums')
        ->set('service_type_id', $serviceType->id)
        ->set('coach_id', $coach->id)
        ->set('price', '15.00')
        ->set('is_active', true)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.services.index'));

    $this->assertDatabaseHas('services', [
        'name' => 'Aktīvs pakalpojums',
        'is_active' => true,
    ]);
});

test('validation messages are in latvian', function () {
    Livewire::test('service.service-create')
        ->set('name', '')
        ->call('save')
        ->assertSee('Nosaukums ir obligāts.');
});

test('can create a new service type inline', function () {
    Livewire::test('service.service-create')
        ->set('serviceTypeSearch', 'Grupu nodarbības')
        ->call('createServiceType')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('service_types', ['name' => 'Grupu nodarbības']);
});

test('new service type is auto-selected after creation', function () {
    $component = Livewire::test('service.service-create')
        ->set('serviceTypeSearch', 'Individuālās nodarbības')
        ->call('createServiceType')
        ->assertHasNoErrors();

    $serviceType = ServiceType::where('name', 'Individuālās nodarbības')->first();
    $component->assertSet('service_type_id', $serviceType->id);
});

test('new service type name is required', function () {
    Livewire::test('service.service-create')
        ->set('serviceTypeSearch', '')
        ->call('createServiceType')
        ->assertHasErrors(['serviceTypeSearch' => 'required']);
});

test('new service type name must be unique', function () {
    ServiceType::factory()->create(['name' => 'Joga']);

    Livewire::test('service.service-create')
        ->set('serviceTypeSearch', 'Joga')
        ->call('createServiceType')
        ->assertHasErrors(['serviceTypeSearch' => 'unique']);
});

test('can delete a service type with no services', function () {
    $serviceType = ServiceType::factory()->create(['name' => 'Dzēšamais']);

    Livewire::test('service.service-create')
        ->call('deleteServiceType', $serviceType->id);

    $this->assertDatabaseMissing('service_types', ['id' => $serviceType->id]);
});

test('cannot delete a service type that has services', function () {
    $serviceType = ServiceType::factory()->create();
    Service::factory()->create(['service_type_id' => $serviceType->id]);

    Livewire::test('service.service-create')
        ->call('deleteServiceType', $serviceType->id);

    $this->assertDatabaseHas('service_types', ['id' => $serviceType->id]);
});

test('deleting selected service type clears selection', function () {
    $serviceType = ServiceType::factory()->create();

    Livewire::test('service.service-create')
        ->set('service_type_id', $serviceType->id)
        ->call('deleteServiceType', $serviceType->id)
        ->assertSet('service_type_id', null);
});
