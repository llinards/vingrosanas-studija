<?php

use App\Enums\PaymentStatus;
use App\Models\Membership;
use App\Models\SiteSetting;
use Livewire\Livewire;

/**
 * A membership whose customer has paid on Stripe but whose payment is not confirmed yet.
 */
function membershipWaitingForPaymentConfirmation(): Membership
{
    return Membership::factory()->create([
        'payment_status' => PaymentStatus::Pending,
        'stripe_checkout_session_id' => 'cs_test_membership_waiting',
        'expires_at' => now()->addMinutes(20),
    ]);
}

test('success page of an unpaid membership keeps checking for the payment', function () {
    $membership = membershipWaitingForPaymentConfirmation();

    $this->get(route('membership.success', ['membership' => $membership, 'session_id' => 'cs_test_membership_waiting']))
        ->assertSeeLivewire('membership.membership-payment-status');
});

test('success page of a paid membership does not check for the payment', function () {
    $membership = Membership::factory()->paid()->create(['stripe_checkout_session_id' => 'cs_test_membership_paid']);

    $this->get(route('membership.success', ['membership' => $membership, 'session_id' => 'cs_test_membership_paid']))
        ->assertSee('Abonements aktivizēts!')
        ->assertDontSeeLivewire('membership.membership-payment-status');
});

test('success page of a membership that will not be paid says so instead of waiting', function (PaymentStatus $status, string $message) {
    $membership = Membership::factory()->create([
        'payment_status' => $status,
        'stripe_checkout_session_id' => 'cs_test_membership_closed',
    ]);

    $this->get(route('membership.success', ['membership' => $membership, 'session_id' => 'cs_test_membership_closed']))
        ->assertSee($message)
        ->assertDontSee('Maksājums tiek apstrādāts')
        ->assertDontSeeLivewire('membership.membership-payment-status');
})->with([
    'failed' => [PaymentStatus::Failed, 'Ir notikusi kļūda!'],
    'refunded' => [PaymentStatus::Refunded, 'Abonements ir atcelts un tā apmaksa tiks atgriezta!'],
]);

test('keeps waiting while the membership payment is not confirmed', function () {
    $membership = membershipWaitingForPaymentConfirmation();

    Livewire::test('membership.membership-payment-status', ['membership' => $membership, 'sessionId' => 'cs_test_membership_waiting'])
        ->assertSeeHtml('wire:poll.5s="checkPayment"')
        ->call('checkPayment')
        ->assertNoRedirect()
        ->assertSee('Maksājums tiek apstrādāts')
        ->assertSeeHtml('wire:poll.5s="checkPayment"');
});

test('shows the outcome as soon as the membership is no longer waiting for payment', function (PaymentStatus $outcome) {
    $membership = membershipWaitingForPaymentConfirmation();
    $status = Livewire::test('membership.membership-payment-status', ['membership' => $membership, 'sessionId' => 'cs_test_membership_waiting']);
    $membership->update(['payment_status' => $outcome]);

    $status->call('checkPayment')
        ->assertRedirect(route('membership.success', ['membership' => $membership->id, 'session_id' => 'cs_test_membership_waiting']));
})->with([
    'paid' => [PaymentStatus::Paid],
    'failed' => [PaymentStatus::Failed],
    'refunded' => [PaymentStatus::Refunded],
]);

test('stops checking for the membership payment after two minutes and says how to reach the studio', function () {
    SiteSetting::setGroup('contact', ['email' => ['value' => 'studija@example.com']]);
    $membership = membershipWaitingForPaymentConfirmation();

    Livewire::test('membership.membership-payment-status', ['membership' => $membership, 'sessionId' => 'cs_test_membership_waiting'])
        ->set('checks', 23)
        ->call('checkPayment')
        ->assertNoRedirect()
        ->assertSee('Apstiprinājums aizkavējies.')
        ->assertSee('studija@example.com')
        ->assertDontSeeHtml('wire:poll');
});

test('stops checking when the unpaid membership was removed while waiting', function () {
    $membership = membershipWaitingForPaymentConfirmation();
    $status = Livewire::test('membership.membership-payment-status', ['membership' => $membership, 'sessionId' => 'cs_test_membership_waiting']);
    $membership->delete();

    $status->call('checkPayment')
        ->assertNoRedirect()
        ->assertSee('Apstiprinājums aizkavējies.')
        ->assertDontSeeHtml('wire:poll');
});
