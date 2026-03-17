<?php

use App\Models\SiteSetting;
use App\Models\User;
use Livewire\Livewire;

test('content contact page requires authentication', function () {
    $this->get('/admin/content/contact')->assertRedirect('/login');
});

test('content contact page is displayed', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/admin/content/contact')->assertOk();
});

test('contact information can be saved', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('content.content-contact')
        ->set('phone', '+37120000000')
        ->set('email', 'test@example.com')
        ->set('address', 'Test iela 1, Rīga')
        ->set('google_maps_url', 'https://maps.google.com/test')
        ->set('instagram_url', 'https://www.instagram.com/test')
        ->set('facebook_url', 'https://www.facebook.com/test')
        ->call('save')
        ->assertHasNoErrors();

    expect(SiteSetting::getValue('contact', 'phone'))->toBe('+37120000000');
    expect(SiteSetting::getValue('contact', 'email'))->toBe('test@example.com');
    expect(SiteSetting::getValue('contact', 'address'))->toBe('Test iela 1, Rīga');
});

test('phone is required', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('content.content-contact')
        ->set('phone', '')
        ->call('save')
        ->assertHasErrors(['phone' => 'required']);
});

test('email must be valid', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('content.content-contact')
        ->set('email', 'not-an-email')
        ->call('save')
        ->assertHasErrors(['email' => 'email']);
});

test('urls must be valid', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('content.content-contact')
        ->set('google_maps_url', 'not-a-url')
        ->call('save')
        ->assertHasErrors(['google_maps_url' => 'url']);
});
