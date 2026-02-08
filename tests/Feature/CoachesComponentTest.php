<?php

use App\Models\Coach;
use Livewire\Livewire;

test('public coaches component only shows active coaches', function () {
    $active = Coach::factory()->create(['is_active' => true, 'name' => 'Active Coach']);
    $inactive = Coach::factory()->create(['is_active' => false, 'name' => 'Inactive Coach']);

    $component = Livewire::test('coaches');

    $coaches = $component->get('coaches');

    expect($coaches->pluck('id')->toArray())->toBe([$active->id]);
});

test('public coaches component orders by position', function () {
    $third = Coach::factory()->create(['is_active' => true, 'position' => 2]);
    $first = Coach::factory()->create(['is_active' => true, 'position' => 0]);
    $second = Coach::factory()->create(['is_active' => true, 'position' => 1]);

    $component = Livewire::test('coaches');

    $coaches = $component->get('coaches');

    expect($coaches->pluck('id')->toArray())->toBe([
        $first->id,
        $second->id,
        $third->id,
    ]);
});
