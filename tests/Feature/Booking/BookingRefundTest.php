<?php

use App\Actions\RefundBooking;
use App\Enums\PaymentStatus;
use App\Exceptions\RefundNotAllowedException;
use App\Mail\BookingRefunded;
use App\Models\Booking;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('booking is refundable when paid and more than 24 hours before service', function () {
    $schedule = Schedule::factory()->create([
        'start_time' => '10:00',
    ]);

    $booking = Booking::factory()->create([
        'schedule_id' => $schedule->id,
        'booking_date' => now()->addDays(3)->toDateString(),
        'payment_status' => PaymentStatus::Paid,
        'payment_reference' => 'pi_test_123',
    ]);

    expect($booking->isRefundable())->toBeTrue();
});

test('booking is not refundable when less than 24 hours before service', function () {
    $schedule = Schedule::factory()->create([
        'start_time' => now()->addHours(12)->format('H:i'),
    ]);

    $booking = Booking::factory()->create([
        'schedule_id' => $schedule->id,
        'booking_date' => now()->toDateString(),
        'payment_status' => PaymentStatus::Paid,
        'payment_reference' => 'pi_test_123',
    ]);

    expect($booking->isRefundable())->toBeFalse();
});

test('booking is not refundable when not paid', function () {
    $schedule = Schedule::factory()->create([
        'start_time' => '10:00',
    ]);

    $booking = Booking::factory()->create([
        'schedule_id' => $schedule->id,
        'booking_date' => now()->addDays(3)->toDateString(),
        'payment_status' => PaymentStatus::Pending,
    ]);

    expect($booking->isRefundable())->toBeFalse();
});

test('booking is not refundable when already refunded', function () {
    $schedule = Schedule::factory()->create([
        'start_time' => '10:00',
    ]);

    $booking = Booking::factory()->create([
        'schedule_id' => $schedule->id,
        'booking_date' => now()->addDays(3)->toDateString(),
        'payment_status' => PaymentStatus::Refunded,
        'payment_reference' => 'pi_test_123',
        'refund_reference' => 're_test_123',
        'refunded_at' => now(),
    ]);

    expect($booking->isRefundable())->toBeFalse();
});

test('booking is not refundable without payment reference', function () {
    $schedule = Schedule::factory()->create([
        'start_time' => '10:00',
    ]);

    $booking = Booking::factory()->create([
        'schedule_id' => $schedule->id,
        'booking_date' => now()->addDays(3)->toDateString(),
        'payment_status' => PaymentStatus::Paid,
        'payment_reference' => null,
    ]);

    expect($booking->isRefundable())->toBeFalse();
});

test('markAsRefunded updates booking correctly', function () {
    $booking = Booking::factory()->paid()->create([
        'payment_reference' => 'pi_test_123',
    ]);

    $booking->markAsRefunded('re_test_456');

    expect($booking->fresh())
        ->payment_status->toBe(PaymentStatus::Refunded)
        ->refund_reference->toBe('re_test_456')
        ->refunded_at->not->toBeNull();
});

test('getServiceDateTime returns correct datetime', function () {
    $schedule = Schedule::factory()->create([
        'start_time' => '14:30',
    ]);

    $booking = Booking::factory()->create([
        'schedule_id' => $schedule->id,
        'booking_date' => '2026-03-15',
    ]);

    $serviceDateTime = $booking->getServiceDateTime();

    expect($serviceDateTime->format('Y-m-d H:i'))->toBe('2026-03-15 14:30');
});

test('refund action throws exception when booking is not refundable', function () {
    $schedule = Schedule::factory()->create([
        'start_time' => now()->addHours(12)->format('H:i'),
    ]);

    $booking = Booking::factory()->create([
        'schedule_id' => $schedule->id,
        'booking_date' => now()->toDateString(),
        'payment_status' => PaymentStatus::Paid,
        'payment_reference' => 'pi_test_123',
    ]);

    app(RefundBooking::class)->execute($booking);
})->throws(RefundNotAllowedException::class);

test('booking refunded email contains correct data', function () {
    $booking = Booking::factory()->create([
        'payment_status' => PaymentStatus::Refunded,
        'refund_reference' => 're_test_123',
        'refunded_at' => now(),
    ]);
    $booking->load('schedule.service.coach');

    $mailable = new BookingRefunded($booking);

    $mailable->assertSeeInHtml($booking->schedule->service->name);
    $mailable->assertSeeInHtml($booking->schedule->service->coach->name);
    $mailable->assertSeeInHtml($booking->booking_date->format('d.m.Y'));
});

test('stripe charge.refunded webhook marks booking as refunded and sends email', function () {
    $booking = Booking::factory()->paid()->create([
        'payment_reference' => 'pi_test_webhook_refund',
    ]);

    $payload = json_encode([
        'id' => 'evt_test_refund',
        'type' => 'charge.refunded',
        'data' => [
            'object' => [
                'id' => 'ch_test_123',
                'payment_intent' => 'pi_test_webhook_refund',
                'refunds' => [
                    'data' => [
                        ['id' => 're_test_webhook_789'],
                    ],
                ],
            ],
        ],
    ]);

    $timestamp = time();
    $secret = config('services.stripe.webhook_secret');

    if (empty($secret)) {
        $this->markTestSkipped('Stripe webhook secret not configured');
    }

    $signedPayload = "{$timestamp}.{$payload}";
    $signature = hash_hmac('sha256', $signedPayload, $secret);

    $this->postJson(route('stripe.webhook'), json_decode($payload, true), [
        'Stripe-Signature' => "t={$timestamp},v1={$signature}",
    ])->assertSuccessful();

    $booking->refresh();

    expect($booking)
        ->payment_status->toBe(PaymentStatus::Refunded)
        ->refund_reference->toBe('re_test_webhook_789')
        ->refunded_at->not->toBeNull();

    Mail::assertQueued(BookingRefunded::class, function ($mail) use ($booking) {
        return $mail->hasTo($booking->email);
    });
});

test('stripe charge.refunded webhook skips already refunded bookings', function () {
    $booking = Booking::factory()->create([
        'payment_status' => PaymentStatus::Refunded,
        'payment_reference' => 'pi_test_already_refunded',
        'refund_reference' => 're_existing',
        'refunded_at' => now(),
    ]);

    $payload = json_encode([
        'id' => 'evt_test_refund_2',
        'type' => 'charge.refunded',
        'data' => [
            'object' => [
                'id' => 'ch_test_456',
                'payment_intent' => 'pi_test_already_refunded',
                'refunds' => [
                    'data' => [
                        ['id' => 're_test_duplicate'],
                    ],
                ],
            ],
        ],
    ]);

    $timestamp = time();
    $secret = config('services.stripe.webhook_secret');

    if (empty($secret)) {
        $this->markTestSkipped('Stripe webhook secret not configured');
    }

    $signedPayload = "{$timestamp}.{$payload}";
    $signature = hash_hmac('sha256', $signedPayload, $secret);

    $this->postJson(route('stripe.webhook'), json_decode($payload, true), [
        'Stripe-Signature' => "t={$timestamp},v1={$signature}",
    ])->assertSuccessful();

    // Refund reference should remain the original one
    expect($booking->fresh()->refund_reference)->toBe('re_existing');

    Mail::assertNotQueued(BookingRefunded::class);
});

test('admin can see refund button for refundable booking', function () {
    $schedule = Schedule::factory()->create([
        'start_time' => '10:00',
    ]);

    $booking = Booking::factory()->create([
        'schedule_id' => $schedule->id,
        'booking_date' => now()->addDays(3)->toDateString(),
        'payment_status' => PaymentStatus::Paid,
        'payment_reference' => 'pi_test_123',
    ]);

    Livewire::test('booking.booking-edit', ['booking' => $booking])
        ->assertSee(__('Veikt atmaksu'));
});

test('admin cannot see refund button for non-refundable booking', function () {
    $schedule = Schedule::factory()->create([
        'start_time' => now()->addHours(12)->format('H:i'),
    ]);

    $booking = Booking::factory()->create([
        'schedule_id' => $schedule->id,
        'booking_date' => now()->toDateString(),
        'payment_status' => PaymentStatus::Paid,
        'payment_reference' => 'pi_test_123',
    ]);

    Livewire::test('booking.booking-edit', ['booking' => $booking])
        ->assertDontSee(__('Veikt atmaksu'));
});

test('admin cannot see refund button for pending booking', function () {
    $schedule = Schedule::factory()->create([
        'start_time' => '10:00',
    ]);

    $booking = Booking::factory()->create([
        'schedule_id' => $schedule->id,
        'booking_date' => now()->addDays(3)->toDateString(),
        'payment_status' => PaymentStatus::Pending,
    ]);

    Livewire::test('booking.booking-edit', ['booking' => $booking])
        ->assertDontSee(__('Veikt atmaksu'));
});
