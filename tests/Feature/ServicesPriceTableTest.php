<?php

use App\Models\Service;
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
