<?php

use App\Models\SiteSetting;

test('phone is shown across the site when it is set', function () {
    SiteSetting::factory()->create([
        'group' => 'contact',
        'key' => 'phone',
        'value' => '+37120000000',
    ]);

    $this->get('/')
        ->assertSee('tel:+37120000000', false)
        ->assertSee('+371 20000000')
        ->assertSee('"telephone": "+37120000000"', false);
});

test('phone is hidden across the site when it is not set', function () {
    $this->get('/')
        ->assertDontSee('tel:', false)
        ->assertDontSee('telephone', false);
});

test('structured data stays valid json when phone is not set', function () {
    preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $this->get('/')->getContent(), $matches);

    expect(json_decode($matches[1], true))
        ->toBeArray()
        ->not->toHaveKey('telephone');
});

test('phone is hidden across the site when it is saved empty', function () {
    SiteSetting::factory()->create([
        'group' => 'contact',
        'key' => 'phone',
        'value' => '',
    ]);

    $this->get('/')
        ->assertDontSee('tel:', false)
        ->assertDontSee('telephone', false);
});
