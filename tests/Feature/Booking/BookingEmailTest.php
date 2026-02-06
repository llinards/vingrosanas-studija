<?php

use App\Mail\BookingConfirmation;
use App\Mail\NewBookingNotification;
use App\Models\Booking;
use App\Models\Coach;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\ServiceType;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

test('booking confirmation email is sent to customer', function () {
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
        ->assertSet('bookingComplete', true);

    Mail::assertSent(BookingConfirmation::class, function ($mail) {
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
        ->call('submitBooking');

    Mail::assertSent(NewBookingNotification::class, function ($mail) {
        return $mail->hasTo('coach@example.com');
    });
});

test('new booking notification is not sent when coach has no email', function () {
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
        ->call('submitBooking');

    Mail::assertNotSent(NewBookingNotification::class);
});

test('booking confirmation email contains correct data', function () {
    $booking = Booking::factory()->create();
    $booking->load('schedule.service.coach');

    $mailable = new BookingConfirmation($booking);

    $mailable->assertSeeInHtml($booking->name);
    $mailable->assertSeeInHtml($booking->schedule->service->name);
    $mailable->assertSeeInHtml($booking->schedule->service->coach->name);
});

test('new booking notification email contains customer data', function () {
    $booking = Booking::factory()->create();
    $booking->load('schedule.service.coach');

    $mailable = new NewBookingNotification($booking);

    $mailable->assertSeeInHtml($booking->name);
    $mailable->assertSeeInHtml($booking->surname);
    $mailable->assertSeeInHtml($booking->phone);
    $mailable->assertSeeInHtml($booking->email);
});
