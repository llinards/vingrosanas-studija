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
