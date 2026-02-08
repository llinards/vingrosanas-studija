<?php

use App\Models\Service;
use App\Models\ServiceType;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

test('services can be reordered within a type group', function () {
    $user = User::factory()->create();
    actingAs($user);

    $type = ServiceType::factory()->create();

    $firstService = Service::factory()->create(['service_type_id' => $type->id, 'position' => 0]);
    $secondService = Service::factory()->create(['service_type_id' => $type->id, 'position' => 1]);
    $thirdService = Service::factory()->create(['service_type_id' => $type->id, 'position' => 2]);

    Livewire::test('service.⚡service-list')
        ->call('updateServicePosition', $firstService->id, 2);

    expect($firstService->fresh()->position)->toBe(2)
        ->and($secondService->fresh()->position)->toBe(0)
        ->and($thirdService->fresh()->position)->toBe(1);
});

test('services are displayed in position order', function () {
    $user = User::factory()->create();
    actingAs($user);

    $type = ServiceType::factory()->create();

    $firstService = Service::factory()->create(['service_type_id' => $type->id, 'position' => 2, 'name' => 'Service C']);
    $secondService = Service::factory()->create(['service_type_id' => $type->id, 'position' => 0, 'name' => 'Service A']);
    $thirdService = Service::factory()->create(['service_type_id' => $type->id, 'position' => 1, 'name' => 'Service B']);

    $component = Livewire::test('service.⚡service-list');

    $services = $component->get('services');
    $serviceIds = $services->pluck('id')->toArray();

    expect($serviceIds)->toBe([
        $secondService->id,
        $thirdService->id,
        $firstService->id,
    ]);
});

test('position is updated correctly when moving service to beginning', function () {
    $user = User::factory()->create();
    actingAs($user);

    $type = ServiceType::factory()->create();

    $firstService = Service::factory()->create(['service_type_id' => $type->id, 'position' => 0]);
    $secondService = Service::factory()->create(['service_type_id' => $type->id, 'position' => 1]);
    $thirdService = Service::factory()->create(['service_type_id' => $type->id, 'position' => 2]);

    Livewire::test('service.⚡service-list')
        ->call('updateServicePosition', $thirdService->id, 0);

    expect($firstService->fresh()->position)->toBe(1)
        ->and($secondService->fresh()->position)->toBe(2)
        ->and($thirdService->fresh()->position)->toBe(0);
});

test('position is updated correctly when moving service to end', function () {
    $user = User::factory()->create();
    actingAs($user);

    $type = ServiceType::factory()->create();

    $firstService = Service::factory()->create(['service_type_id' => $type->id, 'position' => 0]);
    $secondService = Service::factory()->create(['service_type_id' => $type->id, 'position' => 1]);
    $thirdService = Service::factory()->create(['service_type_id' => $type->id, 'position' => 2]);

    Livewire::test('service.⚡service-list')
        ->call('updateServicePosition', $firstService->id, 2);

    expect($firstService->fresh()->position)->toBe(2)
        ->and($secondService->fresh()->position)->toBe(0)
        ->and($thirdService->fresh()->position)->toBe(1);
});

test('reordering services is scoped to the service type', function () {
    $user = User::factory()->create();
    actingAs($user);

    $typeA = ServiceType::factory()->create();
    $typeB = ServiceType::factory()->create();

    $serviceA1 = Service::factory()->create(['service_type_id' => $typeA->id, 'position' => 0]);
    $serviceA2 = Service::factory()->create(['service_type_id' => $typeA->id, 'position' => 1]);

    $serviceB1 = Service::factory()->create(['service_type_id' => $typeB->id, 'position' => 0]);
    $serviceB2 = Service::factory()->create(['service_type_id' => $typeB->id, 'position' => 1]);

    Livewire::test('service.⚡service-list')
        ->call('updateServicePosition', $serviceA1->id, 1);

    expect($serviceA1->fresh()->position)->toBe(1)
        ->and($serviceA2->fresh()->position)->toBe(0)
        ->and($serviceB1->fresh()->position)->toBe(0)
        ->and($serviceB2->fresh()->position)->toBe(1);
});
