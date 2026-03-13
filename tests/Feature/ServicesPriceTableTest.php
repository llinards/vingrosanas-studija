<?php

use App\Models\Service;
use App\Models\ServicePriceTier;
use App\Models\ServiceType;

test('price table only shows active services', function () {
    $serviceType = ServiceType::factory()->create();

    $activeService = Service::factory()->create([
        'name' => 'Aktīvs pakalpojums',
        'service_type_id' => $serviceType->id,
        'is_active' => true,
    ]);

    $inactiveService = Service::factory()->create([
        'name' => 'Neaktīvs pakalpojums',
        'service_type_id' => $serviceType->id,
        'is_active' => false,
    ]);

    $this->get('/')
        ->assertSuccessful()
        ->assertSee('Aktīvs pakalpojums')
        ->assertDontSee('Neaktīvs pakalpojums');
});

test('price table deduplicates services with same name and price', function () {
    $serviceType = ServiceType::factory()->create();

    Service::factory()->count(2)->create([
        'name' => 'Unikāls-Dedup-Tests-XYZ',
        'price' => 2000,
        'service_type_id' => $serviceType->id,
        'is_active' => true,
    ]);

    $response = $this->get('/');
    $response->assertSuccessful();

    $content = $response->getContent();
    expect(substr_count($content, 'Unikāls-Dedup-Tests-XYZ'))->toBe(1);
});

test('price table deduplicates services with same name and identical multi-tier pricing', function () {
    $serviceType = ServiceType::factory()->create();

    $services = Service::factory()->count(2)->create([
        'name' => 'Grupu nodarbība',
        'service_type_id' => $serviceType->id,
        'is_active' => true,
    ]);

    foreach ($services as $service) {
        ServicePriceTier::factory()->create([
            'service_id' => $service->id,
            'participant_count' => 1,
            'price' => 3000,
        ]);
        ServicePriceTier::factory()->create([
            'service_id' => $service->id,
            'participant_count' => 2,
            'price' => 2500,
        ]);
    }

    $response = $this->get('/');
    $response->assertSuccessful();

    $content = $response->getContent();
    $count = substr_count($content, 'Grupu nodarbība (1 persona)');
    expect($count)->toBe(1);
});

test('price table does not deduplicate services with same name but different prices', function () {
    $serviceType = ServiceType::factory()->create();

    Service::factory()->create([
        'name' => 'Pilates',
        'price' => 2000,
        'service_type_id' => $serviceType->id,
        'is_active' => true,
    ]);

    Service::factory()->create([
        'name' => 'Pilates',
        'price' => 3000,
        'service_type_id' => $serviceType->id,
        'is_active' => true,
    ]);

    $this->get('/')
        ->assertSuccessful()
        ->assertSee('€20.00')
        ->assertSee('€30.00');
});

test('price table hides service type when all its services are inactive', function () {
    $serviceType = ServiceType::factory()->create(['name' => 'Tukšais veids']);

    Service::factory()->create([
        'service_type_id' => $serviceType->id,
        'is_active' => false,
    ]);

    $this->get('/')
        ->assertSuccessful()
        ->assertDontSee('Tukšais veids');
});
