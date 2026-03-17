<?php

use App\Models\SiteSetting;
use App\Models\User;
use Livewire\Livewire;

test('content seo page requires authentication', function () {
    $this->get('/admin/content/seo')->assertRedirect('/login');
});

test('content seo page is displayed', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/admin/content/seo')->assertOk();
});

test('seo content can be saved', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('content.content-seo')
        ->set('meta_description', 'Test meta description for SEO')
        ->call('save')
        ->assertHasNoErrors();

    expect(SiteSetting::getValue('seo', 'meta_description'))->toBe('Test meta description for SEO');
});

test('meta description is required', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('content.content-seo')
        ->set('meta_description', '')
        ->call('save')
        ->assertHasErrors(['meta_description' => 'required']);
});
