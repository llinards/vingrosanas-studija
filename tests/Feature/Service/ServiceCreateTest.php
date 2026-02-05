<?php

use App\Models\Coach;
use App\Models\ServiceType;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('service create page can be rendered', function () {
    $this->get(route('service-create'))
        ->assertSuccessful()
        ->assertSeeLivewire('service.service-create');
});

test('service create page requires authentication', function () {
    auth()->logout();

    $this->get(route('service-create'))
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
        ->assertRedirect(route('service-list'));

    $this->assertDatabaseHas('services', [
        'name' => 'Jogas nodarbība',
        'service_type_id' => $serviceType->id,
        'coach_id' => $coach->id,
        'price' => 2500,
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

test('validation messages are in latvian', function () {
    Livewire::test('service.service-create')
        ->set('name', '')
        ->call('save')
        ->assertSee('Nosaukums ir obligāts.');
});

test('can create a new service type from modal', function () {
    Livewire::test('service.service-create')
        ->set('newServiceTypeName', 'Grupu nodarbības')
        ->call('saveServiceType')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('service_types', ['name' => 'Grupu nodarbības']);
});

test('new service type is auto-selected after creation', function () {
    $component = Livewire::test('service.service-create')
        ->set('newServiceTypeName', 'Individuālās nodarbības')
        ->call('saveServiceType')
        ->assertHasNoErrors();

    $serviceType = ServiceType::where('name', 'Individuālās nodarbības')->first();
    $component->assertSet('service_type_id', $serviceType->id);
});

test('new service type name is required', function () {
    Livewire::test('service.service-create')
        ->set('newServiceTypeName', '')
        ->call('saveServiceType')
        ->assertHasErrors(['newServiceTypeName' => 'required']);
});

test('new service type name must be unique', function () {
    ServiceType::factory()->create(['name' => 'Joga']);

    Livewire::test('service.service-create')
        ->set('newServiceTypeName', 'Joga')
        ->call('saveServiceType')
        ->assertHasErrors(['newServiceTypeName' => 'unique']);
});
