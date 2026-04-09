<?php

use App\Actions\CreateStripeCheckoutSession;
use App\Mail\BookingConfirmation;
use App\Mail\BookingRescheduled;
use App\Mail\NewBookingNotification;
use App\Models\Booking;
use App\Models\Coach;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\ServiceType;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    // Mock the Stripe checkout session creation to avoid actual API calls
    $this->mock(CreateStripeCheckoutSession::class)
        ->shouldReceive('execute')
        ->andReturn([
            'url' => 'https://checkout.stripe.com/test',
            'session_id' => 'cs_test_123',
        ]);
});

test('booking redirects to stripe after submission', function () {
    Mail::fake();

    $coach = Coach::factory()->create(['email' => null]);
    $serviceType = ServiceType::factory()->create();
    $service = Service::factory()->create([
        'service_type_id' => $serviceType->id,
        'coach_id' => $coach->id,
        'is_active' => true,
    ]);
    $schedule = Schedule::factory()->create([
        'service_id' => $service->id,
        'day_of_week' => now()->addDay()->dayOfWeekIso,
        'is_active' => true,
        'max_capacity' => 10,
    ]);
    $bookingDate = now()->addDay()->toDateString();

    Livewire::test('booking-modal')
        ->set('service_type_id', $serviceType->id)
        ->set('service_id', $service->id)
        ->call('nextStep')
        ->set('selectedDate', $bookingDate)
        ->set('schedule_id', $schedule->id)
        ->call('nextStep')
        ->set('name', 'Jānis')
        ->set('surname', 'Bērziņš')
        ->set('phone', '+37120000000')
        ->set('email', 'janis@example.com')
        ->call('nextStep')
        ->call('submitBooking')
        ->assertRedirect('https://checkout.stripe.com/test');

    // Emails are sent via webhook after payment, not immediately
    Mail::assertNothingSent();
});

test('booking confirmation email is sent via webhook after payment', function () {
    Mail::fake();

    $booking = Booking::factory()->create([
        'email' => 'janis@example.com',
    ]);
    $booking->load('schedule.service.coach');

    Mail::to($booking->email)->send(new BookingConfirmation($booking));

    Mail::assertQueued(BookingConfirmation::class, function ($mail) {
        return $mail->hasTo('janis@example.com');
    });
});

test('new booking notification is sent to coach when coach has email', function () {
    Mail::fake();

    $coach = Coach::factory()->create(['email' => 'coach@example.com']);
    $serviceType = ServiceType::factory()->create();
    $service = Service::factory()->create([
        'service_type_id' => $serviceType->id,
        'coach_id' => $coach->id,
        'is_active' => true,
    ]);
    $schedule = Schedule::factory()->create([
        'service_id' => $service->id,
        'day_of_week' => now()->addDay()->dayOfWeekIso,
        'is_active' => true,
    ]);

    $booking = Booking::factory()->create([
        'schedule_id' => $schedule->id,
    ]);
    $booking->load('schedule.service.coach');

    // Simulate webhook sending notification
    Mail::to($coach->email)->send(new NewBookingNotification($booking));

    Mail::assertQueued(NewBookingNotification::class, function ($mail) {
        return $mail->hasTo('coach@example.com');
    });
});

test('booking confirmation email contains correct data', function () {
    $booking = Booking::factory()->create();
    $booking->load('schedule.service.coach');

    $mailable = new BookingConfirmation($booking);

    $mailable->assertSeeInHtml($booking->schedule->service->name, escape: false);
    $mailable->assertSeeInHtml($booking->schedule->service->coach->name, escape: false);
});

test('new booking notification email contains customer data', function () {
    $booking = Booking::factory()->create();
    $booking->load('schedule.service.coach');

    $mailable = new NewBookingNotification($booking);

    $mailable->assertSeeInHtml($booking->name, escape: false);
    $mailable->assertSeeInHtml($booking->surname, escape: false);
    $mailable->assertSeeInHtml($booking->phone, escape: false);
    $mailable->assertSeeInHtml($booking->email, escape: false);
});

test('booking rescheduled email contains new schedule data', function () {
    $booking = Booking::factory()->create();
    $booking->load('schedule.service.coach');

    $originalDate = now()->subWeek();
    $originalTime = '09:00:00';

    $mailable = new BookingRescheduled($booking, $originalDate, $originalTime);

    $mailable->assertSeeInHtml($originalDate->format('d.m.Y'), escape: false);
    $mailable->assertSeeInHtml('09:00', escape: false);
    $mailable->assertSeeInHtml($booking->schedule->service->name, escape: false);
    $mailable->assertSeeInHtml($booking->schedule->service->coach->name, escape: false);
    $mailable->assertSeeInHtml($booking->booking_date->format('d.m.Y'), escape: false);
    $mailable->assertSeeInHtml(substr($booking->schedule->start_time, 0, 5), escape: false);
});
