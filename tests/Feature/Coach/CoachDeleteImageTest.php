<?php

use App\Models\Coach;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

test('deleteImage removes the image file from storage', function () {
    Storage::disk('public')->put('coaches/photo.jpg', 'content');

    $coach = Coach::factory()->create(['image' => 'storage/coaches/photo.jpg']);

    $coach->deleteImage();

    Storage::disk('public')->assertMissing('coaches/photo.jpg');
});

test('deleteImage does nothing when image is empty', function () {
    $coach = Coach::factory()->create(['image' => '']);

    $coach->deleteImage();

    expect(true)->toBeTrue();
});

test('deleteImage does nothing when image file does not exist', function () {
    $coach = Coach::factory()->create(['image' => 'storage/coaches/nonexistent.jpg']);

    $coach->deleteImage();

    expect(true)->toBeTrue();
});
