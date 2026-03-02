<?php

use App\Models\User;

it('creates a user', function () {
    $this->artisan('users:create')
        ->expectsQuestion('Name', 'John')
        ->expectsQuestion('Surname', 'Doe')
        ->expectsQuestion('Email', 'john@example.com')
        ->expectsQuestion('Password', 'password123')
        ->expectsOutputToContain('User john@example.com created successfully.')
        ->assertSuccessful();

    $this->assertDatabaseHas('users', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    $user = User::where('email', 'john@example.com')->first();

    expect($user)
        ->email_verified_at->not->toBeNull()
        ->and(Hash::check('password123', $user->password))->toBeTrue();
});
