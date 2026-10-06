<?php

use App\Models\Coach;
use App\Models\Service;
use App\Models\ServiceType;

it('belongs to a service type', function () {
    $service = Service::factory()->create();

    expect($service->serviceType)->toBeInstanceOf(ServiceType::class);
});

it('belongs to a coach', function () {
    $service = Service::factory()->create();

    expect($service->coach)->toBeInstanceOf(Coach::class);
});

it('is accessible through coach relationship', function () {
    $coach = Coach::factory()->create();
    $services = Service::factory()->count(3)->for($coach)->create();

    expect($coach->services)
        ->toHaveCount(3)
        ->each->toBeInstanceOf(Service::class);
});

it('prices a group by its participant tier', function () {
    $service = Service::factory()->create(['price' => 3500]);
    $service->priceTiers()->createMany([
        ['participant_count' => 1, 'price' => 3500],
        ['participant_count' => 2, 'price' => 5000],
    ]);

    expect($service->priceFor(2))->toBe(5000);
});

it('falls back to the base price when no tier matches the participant count', function () {
    $service = Service::factory()->create(['price' => 3500]);
    $service->priceTiers()->create(['participant_count' => 1, 'price' => 3500]);

    expect($service->priceFor(4))->toBe(3500);
});
