<?php

use App\Actions\RefundBooking;
use App\Enums\PaymentStatus;
use App\Exceptions\RefundNotAllowedException;
use App\Models\Booking;
use App\Models\Schedule;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();
});

test('cancel button is visible on success page for refundable booking', function () {
    $schedule = Schedule::factory()->create([
        'start_time' => '10:00',
    ]);

    $booking = Booking::factory()->create([
        'schedule_id' => $schedule->id,
        'booking_date' => now()->addDays(3)->toDateString(),
        'payment_status' => PaymentStatus::Paid,
        'payment_reference' => 'pi_test_123',
        'stripe_checkout_session_id' => 'cs_test_123',
    ]);

    $this->get(route('booking.success', ['booking' => $booking, 'session_id' => 'cs_test_123']))
        ->assertSuccessful()
        ->assertSee(__('Atcelt rezervāciju'));
});

test('cancel button is not visible on success page when less than 24 hours before service', function () {
    $schedule = Schedule::factory()->create([
        'start_time' => now()->addHours(12)->format('H:i'),
    ]);

    $booking = Booking::factory()->create([
        'schedule_id' => $schedule->id,
        'booking_date' => now()->toDateString(),
        'payment_status' => PaymentStatus::Paid,
        'payment_reference' => 'pi_test_123',
        'stripe_checkout_session_id' => 'cs_test_123',
    ]);

    $this->get(route('booking.success', ['booking' => $booking, 'session_id' => 'cs_test_123']))
        ->assertSuccessful()
        ->assertDontSee(__('Atcelt rezervāciju'));
});

test('cancel button is not visible for pending booking', function () {
    $schedule = Schedule::factory()->create([
        'start_time' => '10:00',
    ]);

    $booking = Booking::factory()->create([
        'schedule_id' => $schedule->id,
        'booking_date' => now()->addDays(3)->toDateString(),
        'payment_status' => PaymentStatus::Pending,
        'stripe_checkout_session_id' => 'cs_test_123',
    ]);

    $this->get(route('booking.success', ['booking' => $booking, 'session_id' => 'cs_test_123']))
        ->assertSuccessful()
        ->assertDontSee(__('Atcelt rezervāciju'));
});

test('customer can cancel a refundable booking via livewire component', function () {
    $schedule = Schedule::factory()->create([
        'start_time' => '10:00',
    ]);

    $booking = Booking::factory()->create([
        'schedule_id' => $schedule->id,
        'booking_date' => now()->addDays(3)->toDateString(),
        'payment_status' => PaymentStatus::Paid,
        'payment_reference' => 'pi_test_123',
    ]);

    $mockRefundBooking = Mockery::mock(RefundBooking::class);
    $mockRefundBooking->shouldReceive('execute')
        ->once()
        ->with(Mockery::on(fn ($b) => $b->id === $booking->id), true);

    app()->instance(RefundBooking::class, $mockRefundBooking);

    Livewire::test('booking.booking-cancel', ['booking' => $booking])
        ->assertSee(__('Atcelt rezervāciju'))
        ->call('cancel')
        ->assertRedirect(route('booking.success', $booking));
});

test('cancel redirect preserves session_id query parameter', function () {
    $schedule = Schedule::factory()->create([
        'start_time' => '10:00',
    ]);

    $booking = Booking::factory()->create([
        'schedule_id' => $schedule->id,
        'booking_date' => now()->addDays(3)->toDateString(),
        'payment_status' => PaymentStatus::Paid,
        'payment_reference' => 'pi_test_123',
        'stripe_checkout_session_id' => 'cs_test_456',
    ]);

    $mockRefundBooking = Mockery::mock(RefundBooking::class);
    $mockRefundBooking->shouldReceive('execute')
        ->once()
        ->with(Mockery::any(), true);

    app()->instance(RefundBooking::class, $mockRefundBooking);

    Livewire::withQueryParams(['session_id' => 'cs_test_456'])
        ->test('booking.booking-cancel', ['booking' => $booking])
        ->call('cancel')
        ->assertRedirect(route('booking.success', ['booking' => $booking, 'session_id' => 'cs_test_456']));
});

test('customer cannot cancel a non-refundable booking via livewire component', function () {
    $schedule = Schedule::factory()->create([
        'start_time' => now()->addHours(12)->format('H:i'),
    ]);

    $booking = Booking::factory()->create([
        'schedule_id' => $schedule->id,
        'booking_date' => now()->toDateString(),
        'payment_status' => PaymentStatus::Paid,
        'payment_reference' => 'pi_test_123',
    ]);

    Livewire::test('booking.booking-cancel', ['booking' => $booking])
        ->assertDontSee(__('Atcelt rezervāciju'));
});

test('cancel action handles refund not allowed exception gracefully', function () {
    $schedule = Schedule::factory()->create([
        'start_time' => '10:00',
    ]);

    $booking = Booking::factory()->create([
        'schedule_id' => $schedule->id,
        'booking_date' => now()->addDays(3)->toDateString(),
        'payment_status' => PaymentStatus::Paid,
        'payment_reference' => 'pi_test_123',
    ]);

    $mockRefundBooking = Mockery::mock(RefundBooking::class);
    $mockRefundBooking->shouldReceive('execute')
        ->once()
        ->andThrow(new RefundNotAllowedException($booking));

    app()->instance(RefundBooking::class, $mockRefundBooking);

    Livewire::test('booking.booking-cancel', ['booking' => $booking])
        ->call('cancel')
        ->assertNoRedirect();
});

test('cancel action handles generic exception gracefully', function () {
    $schedule = Schedule::factory()->create([
        'start_time' => '10:00',
    ]);

    $booking = Booking::factory()->create([
        'schedule_id' => $schedule->id,
        'booking_date' => now()->addDays(3)->toDateString(),
        'payment_status' => PaymentStatus::Paid,
        'payment_reference' => 'pi_test_123',
    ]);

    $mockRefundBooking = Mockery::mock(RefundBooking::class);
    $mockRefundBooking->shouldReceive('execute')
        ->once()
        ->andThrow(new RuntimeException('Stripe API error'));

    app()->instance(RefundBooking::class, $mockRefundBooking);

    Livewire::test('booking.booking-cancel', ['booking' => $booking])
        ->call('cancel')
        ->assertNoRedirect();
});
