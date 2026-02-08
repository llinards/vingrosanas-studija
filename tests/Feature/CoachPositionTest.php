<?php

use App\Models\Coach;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

test('coaches can be reordered via drag and drop', function () {
    $user = User::factory()->create();
    actingAs($user);

    $firstCoach = Coach::factory()->create(['position' => 0]);
    $secondCoach = Coach::factory()->create(['position' => 1]);
    $thirdCoach = Coach::factory()->create(['position' => 2]);

    Livewire::test('coach.⚡coach-list')
        ->call('updatePosition', $firstCoach->id, 2);

    expect($firstCoach->fresh()->position)->toBe(2)
        ->and($secondCoach->fresh()->position)->toBe(0)
        ->and($thirdCoach->fresh()->position)->toBe(1);
});

test('coaches are displayed in position order', function () {
    $user = User::factory()->create();
    actingAs($user);

    $firstCoach = Coach::factory()->create(['position' => 2]);
    $secondCoach = Coach::factory()->create(['position' => 0]);
    $thirdCoach = Coach::factory()->create(['position' => 1]);

    $component = Livewire::test('coach.⚡coach-list');

    $orderedCoaches = $component->get('coaches');

    expect($orderedCoaches->pluck('id')->toArray())->toBe([
        $secondCoach->id,
        $thirdCoach->id,
        $firstCoach->id,
    ]);
});

test('position is updated correctly when moving coach to beginning', function () {
    $user = User::factory()->create();
    actingAs($user);

    $firstCoach = Coach::factory()->create(['position' => 0]);
    $secondCoach = Coach::factory()->create(['position' => 1]);
    $thirdCoach = Coach::factory()->create(['position' => 2]);

    Livewire::test('coach.⚡coach-list')
        ->call('updatePosition', $thirdCoach->id, 0);

    expect($firstCoach->fresh()->position)->toBe(1)
        ->and($secondCoach->fresh()->position)->toBe(2)
        ->and($thirdCoach->fresh()->position)->toBe(0);
});

test('position is updated correctly when moving coach to end', function () {
    $user = User::factory()->create();
    actingAs($user);

    $firstCoach = Coach::factory()->create(['position' => 0]);
    $secondCoach = Coach::factory()->create(['position' => 1]);
    $thirdCoach = Coach::factory()->create(['position' => 2]);

    Livewire::test('coach.⚡coach-list')
        ->call('updatePosition', $firstCoach->id, 2);

    expect($firstCoach->fresh()->position)->toBe(2)
        ->and($secondCoach->fresh()->position)->toBe(0)
        ->and($thirdCoach->fresh()->position)->toBe(1);
});
