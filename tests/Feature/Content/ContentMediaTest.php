<?php

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('content media page requires authentication', function () {
    $this->get('/admin/content/media')->assertRedirect('/login');
});

test('content media page is displayed', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/admin/content/media')->assertOk();
});

test('gallery images can be uploaded', function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->create());

    Livewire::test('content.content-media')
        ->set('new_gallery_images', [UploadedFile::fake()->image('gallery1.jpg')])
        ->call('save')
        ->assertHasNoErrors();

    $setting = SiteSetting::getValue('media', 'gallery_images');
    $images = json_decode($setting, true);
    expect($images)->toHaveCount(1);
    expect($images[0])->toStartWith('storage/content/media/');
});
