<?php

use App\Models\Service;
use App\Models\ServiceType;

it('has many services', function () {
    $serviceType = ServiceType::factory()->create();
    $services = Service::factory()->count(3)->for($serviceType)->create();

    expect($serviceType->services)
        ->toHaveCount(3)
        ->each->toBeInstanceOf(Service::class);
});
