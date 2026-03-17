<?php

use App\Models\SiteSetting;
use App\Models\User;
use Livewire\Livewire;

test('content about page requires authentication', function () {
    $this->get('/admin/content/about')->assertRedirect('/login');
});

test('content about page is displayed', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/admin/content/about')->assertOk();
});

test('about content can be saved', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('content.content-about')
        ->set('about_content', '<p>About us content</p>')
        ->set('goal_content', '<p>Goal content</p>')
        ->set('vision_content', '<p>Vision content</p>')
        ->set('mission_content', '<p>Mission content</p>')
        ->call('save')
        ->assertHasNoErrors();

    expect(SiteSetting::getValue('about', 'about_content'))->toBe('<p>About us content</p>');
    expect(SiteSetting::getValue('about', 'goal_content'))->toBe('<p>Goal content</p>');
});

test('all about sections are required', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('content.content-about')
        ->set('about_content', '')
        ->set('goal_content', '')
        ->set('vision_content', '')
        ->set('mission_content', '')
        ->call('save')
        ->assertHasErrors([
            'about_content' => 'required',
            'goal_content' => 'required',
            'vision_content' => 'required',
            'mission_content' => 'required',
        ]);
});
