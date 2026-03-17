<?php

use App\Mail\NewMembershipNotification;
use App\Models\Membership;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
});

test('new membership notification can be sent to coach', function () {
    $membership = Membership::factory()->create();

    Mail::to('coach@example.com')->send(new NewMembershipNotification($membership));

    Mail::assertQueued(NewMembershipNotification::class, function ($mail) {
        return $mail->hasTo('coach@example.com');
    });
});

test('new membership notification email contains customer data', function () {
    $membership = Membership::factory()->create();
    $mailable = new NewMembershipNotification($membership);

    $mailable->assertSeeInHtml($membership->name, escape: false);
    $mailable->assertSeeInHtml($membership->surname, escape: false);
    $mailable->assertSeeInHtml($membership->phone, escape: false);
    $mailable->assertSeeInHtml($membership->email, escape: false);
});

test('new membership notification email contains membership details', function () {
    $membership = Membership::factory()->create();
    $mailable = new NewMembershipNotification($membership);

    $mailable->assertSeeInHtml($membership->tierLabel(), escape: false);
    $mailable->assertSeeInHtml((string) $membership->sessions_total, escape: false);
});
