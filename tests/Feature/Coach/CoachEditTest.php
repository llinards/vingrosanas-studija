<?php

use App\Models\Coach;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    Storage::fake('public');
});

test('coach edit page can be rendered', function () {
    $coach = Coach::factory()->create();

    $this->get(route('coach.edit', $coach))
        ->assertSuccessful()
        ->assertSeeLivewire('coach.coach-edit');
});

test('coach edit page requires authentication', function () {
    $coach = Coach::factory()->create();

    auth()->logout();

    $this->get(route('coach.edit', $coach))
        ->assertRedirect(route('login'));
});

test('coach edit form is populated with existing data', function () {
    $coach = Coach::factory()->create([
        'name' => 'Jānis Bērziņš',
        'email' => 'janis@example.com',
        'phone' => '+371 20000000',
        'title' => 'Fitnesa treneris',
        'bio' => 'Pieredzējis treneris.',
        'is_active' => true,
    ]);

    Livewire::test('coach.coach-edit', ['coach' => $coach])
        ->assertSet('name', 'Jānis Bērziņš')
        ->assertSet('email', 'janis@example.com')
        ->assertSet('phone', '+371 20000000')
        ->assertSet('title', 'Fitnesa treneris')
        ->assertSet('bio', 'Pieredzējis treneris.')
        ->assertSet('is_active', true);
});

test('can update a coach without changing image', function () {
    $imagePath = 'coaches/existing-image.jpg';
    Storage::disk('public')->put($imagePath, 'fake image content');

    $coach = Coach::factory()->create([
        'name' => 'Old Name',
        'image' => 'storage/'.$imagePath,
    ]);

    Livewire::test('coach.coach-edit', ['coach' => $coach])
        ->set('name', 'New Name')
        ->set('title', 'New Title')
        ->set('bio', 'New bio text')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('coach-list'));

    $this->assertDatabaseHas('coaches', [
        'id' => $coach->id,
        'name' => 'New Name',
        'title' => 'New Title',
        'bio' => 'New bio text',
    ]);
});

test('can update a coach with a new image', function () {
    $oldImagePath = 'coaches/old-image.jpg';
    Storage::disk('public')->put($oldImagePath, 'old image content');

    $coach = Coach::factory()->create([
        'image' => 'storage/'.$oldImagePath,
    ]);

    $newImage = UploadedFile::fake()->image('new-coach.jpg');

    Livewire::test('coach.coach-edit', ['coach' => $coach])
        ->set('name', 'Updated Coach')
        ->set('title', 'Updated Title')
        ->set('bio', 'Updated bio')
        ->set('newImage', $newImage)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('coach-list'));

    $this->assertDatabaseHas('coaches', [
        'id' => $coach->id,
        'name' => 'Updated Coach',
    ]);

    Storage::disk('public')->assertMissing($oldImagePath);
    Storage::disk('public')->assertExists('coaches/'.$newImage->hashName());
});

test('name is required', function () {
    $coach = Coach::factory()->create();

    Livewire::test('coach.coach-edit', ['coach' => $coach])
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name' => 'required']);
});

test('title is required', function () {
    $coach = Coach::factory()->create();

    Livewire::test('coach.coach-edit', ['coach' => $coach])
        ->set('title', '')
        ->call('save')
        ->assertHasErrors(['title' => 'required']);
});

test('bio is required', function () {
    $coach = Coach::factory()->create();

    Livewire::test('coach.coach-edit', ['coach' => $coach])
        ->set('bio', '')
        ->call('save')
        ->assertHasErrors(['bio' => 'required']);
});

test('email must be valid', function () {
    $coach = Coach::factory()->create();

    Livewire::test('coach.coach-edit', ['coach' => $coach])
        ->set('email', 'invalid-email')
        ->call('save')
        ->assertHasErrors(['email' => 'email']);
});

test('email must be unique except for current coach', function () {
    $existingCoach = Coach::factory()->create(['email' => 'existing@example.com']);
    $coach = Coach::factory()->create(['email' => 'current@example.com']);

    Livewire::test('coach.coach-edit', ['coach' => $coach])
        ->set('email', 'existing@example.com')
        ->call('save')
        ->assertHasErrors(['email' => 'unique']);
});

test('coach can keep their own email', function () {
    $imagePath = 'coaches/test-image.jpg';
    Storage::disk('public')->put($imagePath, 'fake image content');

    $coach = Coach::factory()->create([
        'email' => 'same@example.com',
        'image' => 'storage/'.$imagePath,
    ]);

    Livewire::test('coach.coach-edit', ['coach' => $coach])
        ->set('email', 'same@example.com')
        ->call('save')
        ->assertHasNoErrors(['email']);
});

test('phone must be unique except for current coach', function () {
    $existingCoach = Coach::factory()->create(['phone' => '+371 29999999']);
    $coach = Coach::factory()->create(['phone' => '+371 20000000']);

    Livewire::test('coach.coach-edit', ['coach' => $coach])
        ->set('phone', '+371 29999999')
        ->call('save')
        ->assertHasErrors(['phone' => 'unique']);
});

test('name cannot exceed 255 characters', function () {
    $coach = Coach::factory()->create();

    Livewire::test('coach.coach-edit', ['coach' => $coach])
        ->set('name', str_repeat('a', 256))
        ->call('save')
        ->assertHasErrors(['name' => 'max']);
});

test('title cannot exceed 255 characters', function () {
    $coach = Coach::factory()->create();

    Livewire::test('coach.coach-edit', ['coach' => $coach])
        ->set('title', str_repeat('a', 256))
        ->call('save')
        ->assertHasErrors(['title' => 'max']);
});

test('bio cannot exceed 10000 characters', function () {
    $coach = Coach::factory()->create();

    Livewire::test('coach.coach-edit', ['coach' => $coach])
        ->set('bio', str_repeat('a', 10001))
        ->call('save')
        ->assertHasErrors(['bio' => 'max']);
});

test('can remove new uploaded image', function () {
    $coach = Coach::factory()->create();
    $image = UploadedFile::fake()->image('coach.jpg');

    Livewire::test('coach.coach-edit', ['coach' => $coach])
        ->set('newImage', $image)
        ->assertSet('newImage', fn ($value) => $value !== null)
        ->call('removeNewImage')
        ->assertSet('newImage', null);
});

test('can remove existing image', function () {
    $coach = Coach::factory()->create([
        'image' => 'storage/coaches/existing.jpg',
    ]);

    Livewire::test('coach.coach-edit', ['coach' => $coach])
        ->assertSet('existingImage', 'storage/coaches/existing.jpg')
        ->call('removeExistingImage')
        ->assertSet('existingImage', null);
});

test('image is required when existing image is removed and no new image provided', function () {
    $coach = Coach::factory()->create([
        'image' => 'storage/coaches/existing.jpg',
    ]);

    Livewire::test('coach.coach-edit', ['coach' => $coach])
        ->call('removeExistingImage')
        ->call('save')
        ->assertHasErrors(['newImage']);
});

test('validation messages are in latvian', function () {
    $coach = Coach::factory()->create();

    Livewire::test('coach.coach-edit', ['coach' => $coach])
        ->set('name', '')
        ->call('save')
        ->assertSee('Vārds un uzvārds ir obligāts.');
});
