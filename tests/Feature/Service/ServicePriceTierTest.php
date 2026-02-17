<?php

use App\Models\Coach;
use App\Models\Service;
use App\Models\ServicePriceTier;
use App\Models\ServiceType;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $user = User::factory()->create();
    test()->actingAs($user);
});

test('service can have multiple price tiers', function () {
    $service = Service::factory()->create();

    ServicePriceTier::factory()->create([
        'service_id' => $service->id,
        'participant_count' => 1,
        'price' => 2000,
    ]);

    ServicePriceTier::factory()->create([
        'service_id' => $service->id,
        'participant_count' => 2,
        'price' => 3500,
    ]);

    ServicePriceTier::factory()->create([
        'service_id' => $service->id,
        'participant_count' => 3,
        'price' => 4500,
    ]);

    expect($service->priceTiers)->toHaveCount(3)
        ->and($service->maxParticipants())->toBe(3);
});

test('price tiers are ordered by participant count', function () {
    $service = Service::factory()->create();

    ServicePriceTier::factory()->create([
        'service_id' => $service->id,
        'participant_count' => 3,
        'price' => 4500,
    ]);

    ServicePriceTier::factory()->create([
        'service_id' => $service->id,
        'participant_count' => 1,
        'price' => 2000,
    ]);

    ServicePriceTier::factory()->create([
        'service_id' => $service->id,
        'participant_count' => 2,
        'price' => 3500,
    ]);

    $tiers = $service->priceTiers;

    expect($tiers[0]->participant_count)->toBe(1)
        ->and($tiers[1]->participant_count)->toBe(2)
        ->and($tiers[2]->participant_count)->toBe(3);
});

test('creating a service creates default price tier', function () {
    $coach = Coach::factory()->create();
    $serviceType = ServiceType::factory()->create();

    Livewire::test('service.service-create')
        ->set('name', 'Individual Training')
        ->set('coach_id', $coach->id)
        ->set('service_type_id', $serviceType->id)
        ->set('price', '25.00')
        ->set('is_active', true)
        ->call('save');

    $service = Service::where('name', 'Individual Training')->first();

    expect($service)->not->toBeNull()
        ->and($service->priceTiers)->toHaveCount(1)
        ->and($service->priceTiers->first()->participant_count)->toBe(1)
        ->and($service->priceTiers->first()->price)->toBe(2500);
});

test('creating a service with additional price tiers', function () {
    $coach = Coach::factory()->create();
    $serviceType = ServiceType::factory()->create();

    Livewire::test('service.service-create')
        ->set('name', 'Individual Training')
        ->set('coach_id', $coach->id)
        ->set('service_type_id', $serviceType->id)
        ->set('price', '25.00')
        ->set('is_active', true)
        ->set('priceTiers', [
            ['participant_count' => '2', 'price' => '40.00'],
            ['participant_count' => '3', 'price' => '50.00'],
        ])
        ->call('save');

    $service = Service::where('name', 'Individual Training')->first();

    expect($service)->not->toBeNull()
        ->and($service->priceTiers)->toHaveCount(3)
        ->and($service->maxParticipants())->toBe(3);

    $tierFor2 = $service->priceTiers->where('participant_count', 2)->first();
    $tierFor3 = $service->priceTiers->where('participant_count', 3)->first();

    expect($tierFor2->price)->toBe(4000)
        ->and($tierFor3->price)->toBe(5000);
});

test('editing a service updates price tiers', function () {
    $coach = Coach::factory()->create();
    $serviceType = ServiceType::factory()->create();
    $service = Service::factory()->create([
        'coach_id' => $coach->id,
        'service_type_id' => $serviceType->id,
        'price' => 2500,
    ]);

    ServicePriceTier::factory()->create([
        'service_id' => $service->id,
        'participant_count' => 1,
        'price' => 2500,
    ]);

    ServicePriceTier::factory()->create([
        'service_id' => $service->id,
        'participant_count' => 2,
        'price' => 4000,
    ]);

    Livewire::test('service.service-edit', ['service' => $service])
        ->set('price', '30.00')
        ->set('priceTiers', [
            ['participant_count' => '2', 'price' => '50.00'],
            ['participant_count' => '3', 'price' => '65.00'],
        ])
        ->call('save');

    $service->refresh();

    expect($service->priceTiers)->toHaveCount(3);

    $tierFor1 = $service->priceTiers->where('participant_count', 1)->first();
    $tierFor2 = $service->priceTiers->where('participant_count', 2)->first();
    $tierFor3 = $service->priceTiers->where('participant_count', 3)->first();

    expect($tierFor1->price)->toBe(3000)
        ->and($tierFor2->price)->toBe(5000)
        ->and($tierFor3->price)->toBe(6500);
});

test('removing price tier from form deletes it', function () {
    $coach = Coach::factory()->create();
    $serviceType = ServiceType::factory()->create();
    $service = Service::factory()->create([
        'coach_id' => $coach->id,
        'service_type_id' => $serviceType->id,
        'price' => 2500,
    ]);

    ServicePriceTier::factory()->create([
        'service_id' => $service->id,
        'participant_count' => 1,
        'price' => 2500,
    ]);

    ServicePriceTier::factory()->create([
        'service_id' => $service->id,
        'participant_count' => 2,
        'price' => 4000,
    ]);

    ServicePriceTier::factory()->create([
        'service_id' => $service->id,
        'participant_count' => 3,
        'price' => 5000,
    ]);

    expect($service->priceTiers)->toHaveCount(3);

    // Remove the tier for 3 participants
    Livewire::test('service.service-edit', ['service' => $service])
        ->set('priceTiers', [
            ['participant_count' => '2', 'price' => '40.00'],
        ])
        ->call('save');

    $service->refresh();

    expect($service->priceTiers)->toHaveCount(2)
        ->and($service->maxParticipants())->toBe(2);
});

test('add price tier button adds new tier with incremented count', function () {
    $coach = Coach::factory()->create();
    $serviceType = ServiceType::factory()->create();

    Livewire::test('service.service-create')
        ->set('name', 'Test Service')
        ->set('coach_id', $coach->id)
        ->set('service_type_id', $serviceType->id)
        ->set('price', '25.00')
        ->assertSet('priceTiers', [])
        ->call('addPriceTier')
        ->assertSet('priceTiers.0.participant_count', '2')
        ->call('addPriceTier')
        ->assertSet('priceTiers.1.participant_count', '3');
});

test('remove price tier button removes tier', function () {
    $coach = Coach::factory()->create();
    $serviceType = ServiceType::factory()->create();

    Livewire::test('service.service-create')
        ->set('name', 'Test Service')
        ->set('coach_id', $coach->id)
        ->set('service_type_id', $serviceType->id)
        ->set('price', '25.00')
        ->set('priceTiers', [
            ['participant_count' => '2', 'price' => '40.00'],
            ['participant_count' => '3', 'price' => '50.00'],
        ])
        ->call('removePriceTier', 0)
        ->assertSet('priceTiers.0.participant_count', '3');
});
