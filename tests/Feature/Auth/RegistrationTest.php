<?php

test('registration screen cannot be rendered', function () {
    $response = $this->get('/register');

    $response->assertNotFound();
});

test('new users cannot register', function () {
    $response = $this->post('/register', [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertNotFound();
});
