<?php

use App\Mail\ContactFormSubmission;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

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

    Mail::assertSent(ContactFormSubmission::class, function ($mail) {
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
        ->assertSet('terms', false)
        ->assertSet('showConfirmModal', true);
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
