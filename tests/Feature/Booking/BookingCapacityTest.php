<?php

use App\Actions\CreateStripeCheckoutSession;
use App\Enums\PaymentStatus;
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

test('booking is rejected when schedule is at full capacity', function () {
    Mail::fake();

    $coach = Coach::factory()->create();
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
        'max_capacity' => 2,
    ]);
    $bookingDate = now()->addDay()->toDateString();

    Booking::factory()->count(2)->create([
        'schedule_id' => $schedule->id,
        'booking_date' => $bookingDate,
    ]);

    Livewire::test('booking-modal')
        ->set('step', 4)
        ->set('service_type_id', $serviceType->id)
        ->set('service_id', $service->id)
        ->set('selectedDate', $bookingDate)
        ->set('schedule_id', $schedule->id)
        ->set('name', 'Jānis')
        ->set('surname', 'Bērziņš')
        ->set('phone', '+37120000000')
        ->set('email', 'janis@example.com')
        ->call('submitBooking')
        ->assertSet('bookingComplete', false)
        ->assertSet('step', 2);

    expect(Booking::where('schedule_id', $schedule->id)->count())->toBe(2);
});

test('booking succeeds when capacity is available', function () {
    Mail::fake();

    $coach = Coach::factory()->create();
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
        'max_capacity' => 5,
    ]);
    $bookingDate = now()->addDay()->toDateString();

    Booking::factory()->count(3)->create([
        'schedule_id' => $schedule->id,
        'booking_date' => $bookingDate,
    ]);

    Livewire::test('booking-modal')
        ->set('step', 4)
        ->set('service_type_id', $serviceType->id)
        ->set('service_id', $service->id)
        ->set('selectedDate', $bookingDate)
        ->set('schedule_id', $schedule->id)
        ->set('name', 'Jānis')
        ->set('surname', 'Bērziņš')
        ->set('phone', '+37120000000')
        ->set('email', 'janis@example.com')
        ->call('submitBooking')
        ->assertRedirect('https://checkout.stripe.com/test');

    expect(Booking::where('schedule_id', $schedule->id)->count())->toBe(4);
});

test('unavailable dates exclude fully booked days', function () {
    $coach = Coach::factory()->create();
    $serviceType = ServiceType::factory()->create();
    $service = Service::factory()->create([
        'service_type_id' => $serviceType->id,
        'coach_id' => $coach->id,
        'is_active' => true,
    ]);

    $tomorrow = now()->addDay();
    $schedule = Schedule::factory()->create([
        'service_id' => $service->id,
        'day_of_week' => $tomorrow->dayOfWeekIso,
        'is_active' => true,
        'max_capacity' => 1,
    ]);

    Booking::factory()->create([
        'schedule_id' => $schedule->id,
        'booking_date' => $tomorrow->toDateString(),
    ]);

    $component = Livewire::test('booking-modal')
        ->set('service_type_id', $serviceType->id)
        ->set('service_id', $service->id);

    $unavailable = $component->get('unavailableDates');
    expect($unavailable)->toContain($tomorrow->toDateString());
});

test('available time slots show remaining capacity', function () {
    $coach = Coach::factory()->create();
    $serviceType = ServiceType::factory()->create();
    $service = Service::factory()->create([
        'service_type_id' => $serviceType->id,
        'coach_id' => $coach->id,
        'is_active' => true,
    ]);

    $tomorrow = now()->addDay();
    $schedule = Schedule::factory()->create([
        'service_id' => $service->id,
        'day_of_week' => $tomorrow->dayOfWeekIso,
        'start_time' => '10:00',
        'is_active' => true,
        'max_capacity' => 5,
    ]);

    Booking::factory()->count(2)->create([
        'schedule_id' => $schedule->id,
        'booking_date' => $tomorrow->toDateString(),
    ]);

    $component = Livewire::test('booking-modal')
        ->set('service_type_id', $serviceType->id)
        ->set('service_id', $service->id)
        ->set('selectedDate', $tomorrow->toDateString());

    $slots = $component->get('availableTimeSlots');
    expect($slots)->toHaveCount(1)
        ->and($slots[0]['remaining'])->toBe(3)
        ->and($slots[0]['start_time'])->toBe('10:00');
});

test('booking creates record with pending payment status and expires_at', function () {
    Mail::fake();

    $coach = Coach::factory()->create();
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
        ->set('step', 4)
        ->set('service_type_id', $serviceType->id)
        ->set('service_id', $service->id)
        ->set('selectedDate', $bookingDate)
        ->set('schedule_id', $schedule->id)
        ->set('name', 'Jānis')
        ->set('surname', 'Bērziņš')
        ->set('phone', '+37120000000')
        ->set('email', 'janis@example.com')
        ->call('submitBooking')
        ->assertRedirect('https://checkout.stripe.com/test');

    $booking = Booking::where('schedule_id', $schedule->id)->latest()->first();
    expect($booking)->not->toBeNull()
        ->and($booking->name)->toBe('Jānis')
        ->and($booking->surname)->toBe('Bērziņš')
        ->and($booking->payment_status)->toBe(PaymentStatus::Pending)
        ->and($booking->booking_date->format('Y-m-d'))->toBe($bookingDate)
        ->and($booking->expires_at)->not->toBeNull();
});
