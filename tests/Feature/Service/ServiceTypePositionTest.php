<?php

use App\Models\Service;
use App\Models\ServiceType;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

test('service types can be reordered via drag and drop', function () {
    $user = User::factory()->create();
    actingAs($user);

    $firstType = ServiceType::factory()->create(['position' => 0]);
    $secondType = ServiceType::factory()->create(['position' => 1]);
    $thirdType = ServiceType::factory()->create(['position' => 2]);

    Service::factory()->create(['service_type_id' => $firstType->id]);
    Service::factory()->create(['service_type_id' => $secondType->id]);
    Service::factory()->create(['service_type_id' => $thirdType->id]);

    Livewire::test('service.⚡service-list')
        ->call('updateServiceTypePosition', $firstType->id, 2);

    expect($firstType->fresh()->position)->toBe(2)
        ->and($secondType->fresh()->position)->toBe(0)
        ->and($thirdType->fresh()->position)->toBe(1);
});

test('service types are displayed in position order', function () {
    $user = User::factory()->create();
    actingAs($user);

    $firstType = ServiceType::factory()->create(['position' => 2, 'name' => 'Type C']);
    $secondType = ServiceType::factory()->create(['position' => 0, 'name' => 'Type A']);
    $thirdType = ServiceType::factory()->create(['position' => 1, 'name' => 'Type B']);

    Service::factory()->create(['service_type_id' => $firstType->id]);
    Service::factory()->create(['service_type_id' => $secondType->id]);
    Service::factory()->create(['service_type_id' => $thirdType->id]);

    $component = Livewire::test('service.⚡service-list');

    $services = $component->get('services');
    $typeIds = $services->pluck('service_type_id')->unique()->values()->toArray();

    expect($typeIds)->toBe([
        $secondType->id,
        $thirdType->id,
        $firstType->id,
    ]);
});

test('position is updated correctly when moving service type to beginning', function () {
    $user = User::factory()->create();
    actingAs($user);

    $firstType = ServiceType::factory()->create(['position' => 0]);
    $secondType = ServiceType::factory()->create(['position' => 1]);
    $thirdType = ServiceType::factory()->create(['position' => 2]);

    Service::factory()->create(['service_type_id' => $firstType->id]);
    Service::factory()->create(['service_type_id' => $secondType->id]);
    Service::factory()->create(['service_type_id' => $thirdType->id]);

    Livewire::test('service.⚡service-list')
        ->call('updateServiceTypePosition', $thirdType->id, 0);

    expect($firstType->fresh()->position)->toBe(1)
        ->and($secondType->fresh()->position)->toBe(2)
        ->and($thirdType->fresh()->position)->toBe(0);
});

test('position is updated correctly when moving service type to end', function () {
    $user = User::factory()->create();
    actingAs($user);

    $firstType = ServiceType::factory()->create(['position' => 0]);
    $secondType = ServiceType::factory()->create(['position' => 1]);
    $thirdType = ServiceType::factory()->create(['position' => 2]);

    Service::factory()->create(['service_type_id' => $firstType->id]);
    Service::factory()->create(['service_type_id' => $secondType->id]);
    Service::factory()->create(['service_type_id' => $thirdType->id]);

    Livewire::test('service.⚡service-list')
        ->call('updateServiceTypePosition', $firstType->id, 2);

    expect($firstType->fresh()->position)->toBe(2)
        ->and($secondType->fresh()->position)->toBe(0)
        ->and($thirdType->fresh()->position)->toBe(1);
});
