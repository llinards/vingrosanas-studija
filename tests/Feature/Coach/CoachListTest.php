<?php

use App\Models\Coach;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    Storage::fake('public');
});

test('coach list page can be rendered', function () {
    $this->get(route('admin.coaches.index'))
        ->assertSuccessful();
});

test('coach list page requires authentication', function () {
    auth()->logout();

    $this->get(route('admin.coaches.index'))
        ->assertRedirect(route('login'));
});

test('coach list displays all coaches', function () {
    $coaches = Coach::factory()->count(3)->create();

    $this->get(route('admin.coaches.index'))
        ->assertSuccessful()
        ->assertSee($coaches[0]->name)
        ->assertSee($coaches[1]->name)
        ->assertSee($coaches[2]->name);
});

test('coach list shows empty state when no coaches exist', function () {
    $this->get(route('admin.coaches.index'))
        ->assertSuccessful()
        ->assertSee('Šobrīd nav neviena aktīva trenera!');
});

test('can destroy a coach', function () {
    $imagePath = 'coaches/test-image.jpg';
    Storage::disk('public')->put($imagePath, 'fake image content');

    $coach = Coach::factory()->create([
        'image' => 'storage/'.$imagePath,
    ]);

    Livewire::test('coach.coach-list')
        ->call('destroy', $coach->id)
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('coaches', ['id' => $coach->id]);
    Storage::disk('public')->assertMissing($imagePath);
});

test('destroy removes coach even if image does not exist', function () {
    $coach = Coach::factory()->create([
        'image' => 'storage/coaches/non-existent.jpg',
    ]);

    Livewire::test('coach.coach-list')
        ->call('destroy', $coach->id)
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('coaches', ['id' => $coach->id]);
});

test('coaches list refreshes after deletion', function () {
    $coaches = Coach::factory()->count(2)->create();

    $component = Livewire::test('coach.coach-list')
        ->assertSee($coaches[0]->name)
        ->assertSee($coaches[1]->name);

    $component->call('destroy', $coaches[0]->id);

    $component->assertDontSee($coaches[0]->name)
        ->assertSee($coaches[1]->name);
});
