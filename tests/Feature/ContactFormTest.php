<?php

use App\Mail\ContactFormSubmission;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Honeypot\Honeypot;

test('contact form sends email on valid submission', function () {
    Mail::fake();

    Livewire::test('contact-form')
        ->set('name', 'Jānis')
        ->set('surname', 'Bērziņš')
        ->set('email', 'janis@example.com')
        ->set('message', 'Mans jautājums par nodarbībām.')
        ->set('terms', true)
        ->call('submit')
        ->assertHasNoErrors();

    Mail::assertQueued(ContactFormSubmission::class, function ($mail) {
        return $mail->hasTo(config('mail.contact_email'))
            && $mail->name === 'Jānis'
            && $mail->surname === 'Bērziņš'
            && $mail->email === 'janis@example.com'
            && $mail->contactMessage === 'Mans jautājums par nodarbībām.';
    });
});

test('contact form resets fields after successful submission', function () {
    Mail::fake();

    Livewire::test('contact-form')
        ->set('name', 'Jānis')
        ->set('surname', 'Bērziņš')
        ->set('email', 'janis@example.com')
        ->set('message', 'Mans jautājums.')
        ->set('terms', true)
        ->call('submit')
        ->assertSet('name', '')
        ->assertSet('surname', '')
        ->assertSet('email', '')
        ->assertSet('message', '')
        ->assertSet('terms', false);
});

test('contact form validates required fields', function () {
    Mail::fake();

    Livewire::test('contact-form')
        ->call('submit')
        ->assertHasErrors(['name', 'surname', 'email', 'message', 'terms']);

    Mail::assertNothingSent();
});

test('contact form validates email format', function () {
    Mail::fake();

    Livewire::test('contact-form')
        ->set('name', 'Jānis')
        ->set('surname', 'Bērziņš')
        ->set('email', 'not-an-email')
        ->set('message', 'Test')
        ->set('terms', true)
        ->call('submit')
        ->assertHasErrors(['email']);

    Mail::assertNothingSent();
});

test('contact form validates terms must be accepted', function () {
    Mail::fake();

    Livewire::test('contact-form')
        ->set('name', 'Jānis')
        ->set('surname', 'Bērziņš')
        ->set('email', 'janis@example.com')
        ->set('message', 'Test')
        ->set('terms', false)
        ->call('submit')
        ->assertHasErrors(['terms']);

    Mail::assertNothingSent();
});

test('contact form blocks spam when honeypot field is filled', function () {
    Mail::fake();

    $honeypot = app(Honeypot::class);
    $nameField = $honeypot->unrandomizedNameFieldName();

    Livewire::test('contact-form')
        ->set('name', 'Spammer')
        ->set('surname', 'Bot')
        ->set('email', 'spam@example.com')
        ->set('message', 'Buy now!')
        ->set('terms', true)
        ->set("extraFields.{$nameField}", 'I am a bot')
        ->call('submit')
        ->assertForbidden();

    Mail::assertNothingSent();
});
