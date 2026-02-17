<?php

use App\Models\Booking;
use App\Models\Coach;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\ServicePriceTier;
use App\Models\ServiceType;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

test('booking with multiple participants consumes correct capacity', function () {
    Mail::fake();

    $coach = Coach::factory()->create();
    $serviceType = ServiceType::factory()->create();
    $service = Service::factory()->create([
        'service_type_id' => $serviceType->id,
        'coach_id' => $coach->id,
        'is_active' => true,
    ]);

    ServicePriceTier::factory()->create([
        'service_id' => $service->id,
        'participant_count' => 1,
        'price' => 2000,
    ]);

    ServicePriceTier::factory()->create([
        'service_id' => $service->id,
        'participant_count' => 3,
        'price' => 5000,
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
        ->set('participant_count', 3)
        ->set('selectedDate', $bookingDate)
        ->set('schedule_id', $schedule->id)
        ->set('name', 'Janis')
        ->set('surname', 'Berzins')
        ->set('phone', '+37120000000')
        ->set('email', 'janis@example.com')
        ->call('submitBooking')
        ->assertSet('bookingComplete', true);

    $booking = Booking::where('schedule_id', $schedule->id)->first();

    expect($booking)->not->toBeNull()
        ->and($booking->participant_count)->toBe(3);
});

test('booking is rejected when not enough capacity for participant count', function () {
    Mail::fake();

    $coach = Coach::factory()->create();
    $serviceType = ServiceType::factory()->create();
    $service = Service::factory()->create([
        'service_type_id' => $serviceType->id,
        'coach_id' => $coach->id,
        'is_active' => true,
    ]);

    ServicePriceTier::factory()->create([
        'service_id' => $service->id,
        'participant_count' => 1,
        'price' => 2000,
    ]);

    ServicePriceTier::factory()->create([
        'service_id' => $service->id,
        'participant_count' => 3,
        'price' => 5000,
    ]);

    $schedule = Schedule::factory()->create([
        'service_id' => $service->id,
        'day_of_week' => now()->addDay()->dayOfWeekIso,
        'is_active' => true,
        'max_capacity' => 5,
    ]);
    $bookingDate = now()->addDay()->toDateString();

    // Create existing bookings that consume 3 spots
    Booking::factory()->create([
        'schedule_id' => $schedule->id,
        'booking_date' => $bookingDate,
        'participant_count' => 3,
    ]);

    // Try to book for 3 more (only 2 spots remaining)
    Livewire::test('booking-modal')
        ->set('step', 4)
        ->set('service_type_id', $serviceType->id)
        ->set('service_id', $service->id)
        ->set('participant_count', 3)
        ->set('selectedDate', $bookingDate)
        ->set('schedule_id', $schedule->id)
        ->set('name', 'Janis')
        ->set('surname', 'Berzins')
        ->set('phone', '+37120000000')
        ->set('email', 'janis@example.com')
        ->call('submitBooking')
        ->assertSet('bookingComplete', false)
        ->assertSet('step', 2);

    expect(Booking::where('schedule_id', $schedule->id)->count())->toBe(1);
});

test('available time slots filter by participant count', function () {
    $coach = Coach::factory()->create();
    $serviceType = ServiceType::factory()->create();
    $service = Service::factory()->create([
        'service_type_id' => $serviceType->id,
        'coach_id' => $coach->id,
        'is_active' => true,
    ]);

    ServicePriceTier::factory()->create([
        'service_id' => $service->id,
        'participant_count' => 1,
        'price' => 2000,
    ]);

    ServicePriceTier::factory()->create([
        'service_id' => $service->id,
        'participant_count' => 3,
        'price' => 5000,
    ]);

    $tomorrow = now()->addDay();
    $schedule = Schedule::factory()->create([
        'service_id' => $service->id,
        'day_of_week' => $tomorrow->dayOfWeekIso,
        'start_time' => '10:00',
        'is_active' => true,
        'max_capacity' => 5,
    ]);

    // Create existing booking for 3 participants (2 spots remaining)
    Booking::factory()->create([
        'schedule_id' => $schedule->id,
        'booking_date' => $tomorrow->toDateString(),
        'participant_count' => 3,
    ]);

    // With 1 participant, slot should be available
    $component = Livewire::test('booking-modal')
        ->set('service_type_id', $serviceType->id)
        ->set('service_id', $service->id)
        ->set('participant_count', 1)
        ->set('selectedDate', $tomorrow->toDateString());

    $slots = $component->get('availableTimeSlots');
    expect($slots)->toHaveCount(1)
        ->and($slots[0]['remaining'])->toBe(2);

    // With 3 participants, slot should NOT be available (only 2 remaining)
    $component = Livewire::test('booking-modal')
        ->set('service_type_id', $serviceType->id)
        ->set('service_id', $service->id)
        ->set('participant_count', 3)
        ->set('selectedDate', $tomorrow->toDateString());

    $slots = $component->get('availableTimeSlots');
    expect($slots)->toHaveCount(0);
});

test('available price tiers are shown for service', function () {
    $coach = Coach::factory()->create();
    $serviceType = ServiceType::factory()->create();
    $service = Service::factory()->create([
        'service_type_id' => $serviceType->id,
        'coach_id' => $coach->id,
        'is_active' => true,
    ]);

    Schedule::factory()->create([
        'service_id' => $service->id,
        'day_of_week' => now()->addDay()->dayOfWeekIso,
        'is_active' => true,
    ]);

    ServicePriceTier::factory()->create([
        'service_id' => $service->id,
        'participant_count' => 1,
        'price' => 2000,
    ]);

    ServicePriceTier::factory()->create([
        'service_id' => $service->id,
        'participant_count' => 2,
        'price' => 3500,
    ]);

    ServicePriceTier::factory()->create([
        'service_id' => $service->id,
        'participant_count' => 3,
        'price' => 4500,
    ]);

    $component = Livewire::test('booking-modal')
        ->set('service_type_id', $serviceType->id)
        ->set('service_id', $service->id);

    $tiers = $component->get('availablePriceTiers');

    expect($tiers)->toHaveCount(3)
        ->and($tiers[0]->participant_count)->toBe(1)
        ->and($tiers[0]->price)->toBe(2000)
        ->and($tiers[1]->participant_count)->toBe(2)
        ->and($tiers[1]->price)->toBe(3500)
        ->and($tiers[2]->participant_count)->toBe(3)
        ->and($tiers[2]->price)->toBe(4500);
});

test('selected price reflects chosen participant count', function () {
    $coach = Coach::factory()->create();
    $serviceType = ServiceType::factory()->create();
    $service = Service::factory()->create([
        'service_type_id' => $serviceType->id,
        'coach_id' => $coach->id,
        'is_active' => true,
        'price' => 2000,
    ]);

    Schedule::factory()->create([
        'service_id' => $service->id,
        'day_of_week' => now()->addDay()->dayOfWeekIso,
        'is_active' => true,
    ]);

    ServicePriceTier::factory()->create([
        'service_id' => $service->id,
        'participant_count' => 1,
        'price' => 2000,
    ]);

    ServicePriceTier::factory()->create([
        'service_id' => $service->id,
        'participant_count' => 2,
        'price' => 3500,
    ]);

    $component = Livewire::test('booking-modal')
        ->set('service_type_id', $serviceType->id)
        ->set('service_id', $service->id)
        ->set('participant_count', 1);

    expect($component->get('selectedPrice'))->toBe(2000);

    $component->set('participant_count', 2);

    expect($component->get('selectedPrice'))->toBe(3500);
});

test('changing participant count resets date and schedule selection', function () {
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
    ]);

    ServicePriceTier::factory()->create([
        'service_id' => $service->id,
        'participant_count' => 1,
        'price' => 2000,
    ]);

    ServicePriceTier::factory()->create([
        'service_id' => $service->id,
        'participant_count' => 2,
        'price' => 3500,
    ]);

    Livewire::test('booking-modal')
        ->set('service_type_id', $serviceType->id)
        ->set('service_id', $service->id)
        ->set('selectedDate', now()->addDay()->toDateString())
        ->set('schedule_id', $schedule->id)
        ->set('participant_count', 2)
        ->assertSet('selectedDate', null)
        ->assertSet('schedule_id', null);
});

test('capacity calculation sums participant counts from all bookings', function () {
    $coach = Coach::factory()->create();
    $serviceType = ServiceType::factory()->create();
    $service = Service::factory()->create([
        'service_type_id' => $serviceType->id,
        'coach_id' => $coach->id,
        'is_active' => true,
    ]);

    ServicePriceTier::factory()->create([
        'service_id' => $service->id,
        'participant_count' => 1,
        'price' => 2000,
    ]);

    $tomorrow = now()->addDay();
    $schedule = Schedule::factory()->create([
        'service_id' => $service->id,
        'day_of_week' => $tomorrow->dayOfWeekIso,
        'start_time' => '10:00',
        'is_active' => true,
        'max_capacity' => 10,
    ]);

    // Create multiple bookings with different participant counts
    Booking::factory()->create([
        'schedule_id' => $schedule->id,
        'booking_date' => $tomorrow->toDateString(),
        'participant_count' => 2,
    ]);

    Booking::factory()->create([
        'schedule_id' => $schedule->id,
        'booking_date' => $tomorrow->toDateString(),
        'participant_count' => 3,
    ]);

    Booking::factory()->create([
        'schedule_id' => $schedule->id,
        'booking_date' => $tomorrow->toDateString(),
        'participant_count' => 1,
    ]);

    // Total booked: 2 + 3 + 1 = 6, remaining: 10 - 6 = 4
    $component = Livewire::test('booking-modal')
        ->set('service_type_id', $serviceType->id)
        ->set('service_id', $service->id)
        ->set('participant_count', 1)
        ->set('selectedDate', $tomorrow->toDateString());

    $slots = $component->get('availableTimeSlots');
    expect($slots)->toHaveCount(1)
        ->and($slots[0]['remaining'])->toBe(4);
});
