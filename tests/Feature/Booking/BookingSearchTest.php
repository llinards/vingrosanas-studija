<?php

use App\Models\Booking;

test('search matches a single token against the name', function () {
    $match = Booking::factory()->create(['name' => 'John', 'surname' => 'Doe']);
    Booking::factory()->create(['name' => 'Alice', 'surname' => 'Smith']);

    $results = Booking::query()->search('John')->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($match->id);
});

test('search matches a single token against the surname', function () {
    $match = Booking::factory()->create(['name' => 'John', 'surname' => 'Doe']);
    Booking::factory()->create(['name' => 'Alice', 'surname' => 'Smith']);

    $results = Booking::query()->search('Doe')->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($match->id);
});

test('search matches a single token against the email', function () {
    $match = Booking::factory()->create(['email' => 'jane@example.com']);
    Booking::factory()->create(['email' => 'other@example.com']);

    $results = Booking::query()->search('jane@')->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($match->id);
});

test('search matches a single token against the phone', function () {
    $match = Booking::factory()->create(['phone' => '+371 20000001']);
    Booking::factory()->create(['phone' => '+371 99999999']);

    $results = Booking::query()->search('20000001')->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($match->id);
});

test('search matches name and surname split across separate columns', function () {
    $match = Booking::factory()->create(['name' => 'John', 'surname' => 'Doe']);
    Booking::factory()->create(['name' => 'John', 'surname' => 'Smith']);
    Booking::factory()->create(['name' => 'Alice', 'surname' => 'Doe']);

    $results = Booking::query()->search('John Doe')->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($match->id);
});

test('search matches regardless of name and surname order', function () {
    $match = Booking::factory()->create(['name' => 'John', 'surname' => 'Doe']);
    Booking::factory()->create(['name' => 'Alice', 'surname' => 'Smith']);

    $results = Booking::query()->search('Doe John')->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($match->id);
});

test('search matches partial tokens across columns', function () {
    $match = Booking::factory()->create(['name' => 'Jonathan', 'surname' => 'Donovan']);
    Booking::factory()->create(['name' => 'Alice', 'surname' => 'Smith']);

    $results = Booking::query()->search('Jo Do')->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($match->id);
});

test('search excludes rows when any token does not match', function () {
    Booking::factory()->create(['name' => 'John', 'surname' => 'Doe']);

    $results = Booking::query()->search('John Smith')->get();

    expect($results)->toBeEmpty();
});

test('search with a whitespace-only term returns all rows', function () {
    Booking::factory()->count(3)->create();

    $results = Booking::query()->search('   ')->get();

    expect($results)->toHaveCount(3);
});
