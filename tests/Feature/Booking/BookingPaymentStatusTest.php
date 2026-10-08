<?php

use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\SiteSetting;
use Livewire\Livewire;

/**
 * A booking whose customer has paid on Stripe but whose payment is not confirmed yet.
 */
function bookingWaitingForPaymentConfirmation(): Booking
{
    return Booking::factory()->create([
        'stripe_checkout_session_id' => 'cs_test_waiting',
        'expires_at' => now()->addMinutes(20),
    ]);
}

test('success page of an unpaid booking keeps checking for the payment', function () {
    $booking = bookingWaitingForPaymentConfirmation();

    $this->get(route('booking.success', ['booking' => $booking, 'session_id' => 'cs_test_waiting']))
        ->assertSeeLivewire('booking.booking-payment-status');
});

test('success page of a paid booking does not check for the payment', function () {
    $booking = Booking::factory()->paid()->create(['stripe_checkout_session_id' => 'cs_test_paid']);

    $this->get(route('booking.success', ['booking' => $booking, 'session_id' => 'cs_test_paid']))
        ->assertDontSeeLivewire('booking.booking-payment-status');
});

test('keeps waiting while the payment is not confirmed', function () {
    $booking = bookingWaitingForPaymentConfirmation();

    Livewire::test('booking.booking-payment-status', ['booking' => $booking, 'sessionId' => 'cs_test_waiting'])
        ->assertSeeHtml('wire:poll.5s="checkPayment"')
        ->call('checkPayment')
        ->assertNoRedirect()
        ->assertSee('Maksājums tiek apstrādāts')
        ->assertSeeHtml('wire:poll.5s="checkPayment"');
});

test('shows the outcome as soon as the booking is no longer waiting for payment', function (PaymentStatus $outcome) {
    $booking = bookingWaitingForPaymentConfirmation();
    $status = Livewire::test('booking.booking-payment-status', ['booking' => $booking, 'sessionId' => 'cs_test_waiting']);
    $booking->update(['payment_status' => $outcome]);

    $status->call('checkPayment')
        ->assertRedirect(route('booking.success', ['booking' => $booking->id, 'session_id' => 'cs_test_waiting']));
})->with([
    'paid' => [PaymentStatus::Paid],
    'failed' => [PaymentStatus::Failed],
    'refunded' => [PaymentStatus::Refunded],
]);

test('stops checking after two minutes and says how to reach the studio', function () {
    SiteSetting::setGroup('contact', ['email' => ['value' => 'studija@example.com']]);
    $booking = bookingWaitingForPaymentConfirmation();

    Livewire::test('booking.booking-payment-status', ['booking' => $booking, 'sessionId' => 'cs_test_waiting'])
        ->set('checks', 23)
        ->call('checkPayment')
        ->assertNoRedirect()
        ->assertSee('Apstiprinājums aizkavējies.')
        ->assertSee('studija@example.com')
        ->assertDontSeeHtml('wire:poll');
});

test('stops checking when the booking was removed while waiting', function () {
    $booking = bookingWaitingForPaymentConfirmation();
    $status = Livewire::test('booking.booking-payment-status', ['booking' => $booking, 'sessionId' => 'cs_test_waiting']);
    $booking->delete();

    $status->call('checkPayment')
        ->assertNoRedirect()
        ->assertSee('Apstiprinājums aizkavējies.')
        ->assertDontSeeHtml('wire:poll');
});
