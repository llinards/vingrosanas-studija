<?php

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('content hero page requires authentication', function () {
    $this->get('/admin/content/hero')->assertRedirect('/login');
});

test('content hero page is displayed', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/admin/content/hero')->assertOk();
});

test('hero content can be saved', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('content.content-hero')
        ->set('heading', 'Test Heading')
        ->set('cta_text', 'Click Me')
        ->call('save')
        ->assertHasNoErrors();

    expect(SiteSetting::getValue('hero', 'heading'))->toBe('Test Heading');
    expect(SiteSetting::getValue('hero', 'cta_text'))->toBe('Click Me');
});

test('hero background image can be uploaded', function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->create());

    Livewire::test('content.content-hero')
        ->set('heading', 'Test')
        ->set('cta_text', 'CTA')
        ->set('background_image', UploadedFile::fake()->image('hero.jpg'))
        ->call('save')
        ->assertHasNoErrors();

    $setting = SiteSetting::getValue('hero', 'background_image');
    expect($setting)->toStartWith('storage/content/hero/');
});

test('heading is required', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('content.content-hero')
        ->set('heading', '')
        ->call('save')
        ->assertHasErrors(['heading' => 'required']);
});
