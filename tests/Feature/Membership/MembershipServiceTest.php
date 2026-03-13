<?php

use App\Models\Service;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('service edit shows membership eligible toggle', function () {
    $service = Service::factory()->create();

    $this->get(route('admin.services.edit', $service))
        ->assertSuccessful()
        ->assertSee(__('Pieejams abonementam'));
});

test('service can be marked as membership eligible', function () {
    $service = Service::factory()->create([
        'is_membership_eligible' => false,
    ]);

    $service->priceTiers()->create([
        'participant_count' => 1,
        'price' => $service->price,
    ]);

    Livewire::test('service.service-edit', ['service' => $service])
        ->set('is_membership_eligible', true)
        ->call('save')
        ->assertRedirect(route('admin.services.index'));

    expect($service->fresh()->is_membership_eligible)->toBeTrue();
});

test('new service can be created with membership eligible flag', function () {
    $service = Service::factory()->create();

    Livewire::test('service.service-create')
        ->set('name', 'Test Service')
        ->set('service_type_id', $service->service_type_id)
        ->set('coach_id', $service->coach_id)
        ->set('price', '25.00')
        ->set('is_active', true)
        ->set('is_membership_eligible', true)
        ->call('save')
        ->assertRedirect(route('admin.services.index'));

    $newService = Service::where('name', 'Test Service')->first();

    expect($newService->is_membership_eligible)->toBeTrue();
});
