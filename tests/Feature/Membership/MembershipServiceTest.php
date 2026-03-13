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

test('new membership service can be created', function () {
    $service = Service::factory()->create();

    Livewire::test('service.service-create')
        ->set('name', '4 nodarbības mēnesī')
        ->set('service_type_id', $service->service_type_id)
        ->set('coach_id', $service->coach_id)
        ->set('price', '40.00')
        ->set('is_active', true)
        ->set('is_membership', true)
        ->set('sessions_count', '4')
        ->call('save')
        ->assertRedirect(route('admin.services.index'));

    $membershipService = Service::where('name', '4 nodarbības mēnesī')->first();

    expect($membershipService)
        ->is_membership->toBeTrue()
        ->sessions_count->toBe(4)
        ->is_exclusive->toBeFalse()
        ->is_membership_eligible->toBeFalse();
});

test('membership service hides exclusive and membership eligible toggles', function () {
    $service = Service::factory()->membership(4)->create();

    $component = Livewire::test('service.service-edit', ['service' => $service]);

    expect($component->get('is_membership'))->toBeTrue();
    expect($component->get('sessions_count'))->toBe('4');
});

test('membership service requires sessions count', function () {
    $service = Service::factory()->create();

    Livewire::test('service.service-create')
        ->set('name', 'Test Membership')
        ->set('service_type_id', $service->service_type_id)
        ->set('coach_id', $service->coach_id)
        ->set('price', '40.00')
        ->set('is_active', true)
        ->set('is_membership', true)
        ->set('sessions_count', '')
        ->call('save')
        ->assertHasErrors(['sessions_count']);
});
