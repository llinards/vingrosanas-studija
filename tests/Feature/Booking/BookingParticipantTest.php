<?php

use App\Models\Booking;
use App\Models\Coach;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\ServicePriceTier;
use App\Models\ServiceType;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

test('booking with multiple participants saves correct participant count', function () {
    Mail::fake();

    $coach = Coach::factory()->create();
    $serviceType = ServiceType::factory()->create();
    $service = Service::factory()->exclusive()->create([
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
        ->set('selectedDate', $bookingDate)
        ->set('schedule_id', $schedule->id)
        ->set('participant_count', 3)
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

test('exclusive service slot is unavailable after any booking is made', function () {
    Mail::fake();

    $coach = Coach::factory()->create();
    $serviceType = ServiceType::factory()->create();
    // Service marked as exclusive
    $service = Service::factory()->exclusive()->create([
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
        'is_active' => true,
        'max_capacity' => 5,
    ]);
    $bookingDate = $tomorrow->toDateString();

    // Create one booking (this should make the slot unavailable for exclusive services)
    Booking::factory()->create([
        'schedule_id' => $schedule->id,
        'booking_date' => $bookingDate,
        'participant_count' => 1,
    ]);

    // For exclusive services, slot should NOT be available after any booking
    $component = Livewire::test('booking-modal')
        ->set('service_type_id', $serviceType->id)
        ->set('service_id', $service->id)
        ->set('selectedDate', $bookingDate);

    $slots = $component->get('availableTimeSlots');
    expect($slots)->toHaveCount(0);
});

test('exclusive booking is rejected when slot already has a booking', function () {
    Mail::fake();

    $coach = Coach::factory()->create();
    $serviceType = ServiceType::factory()->create();
    // Service marked as exclusive
    $service = Service::factory()->exclusive()->create([
        'service_type_id' => $serviceType->id,
        'coach_id' => $coach->id,
        'is_active' => true,
    ]);

    ServicePriceTier::factory()->create([
        'service_id' => $service->id,
        'participant_count' => 1,
        'price' => 2000,
    ]);

    $schedule = Schedule::factory()->create([
        'service_id' => $service->id,
        'day_of_week' => now()->addDay()->dayOfWeekIso,
        'is_active' => true,
        'max_capacity' => 5,
    ]);
    $bookingDate = now()->addDay()->toDateString();

    // Create existing booking
    Booking::factory()->create([
        'schedule_id' => $schedule->id,
        'booking_date' => $bookingDate,
        'participant_count' => 1,
    ]);

    // Try to book the same slot (should fail for exclusive service)
    Livewire::test('booking-modal')
        ->set('step', 4)
        ->set('service_type_id', $serviceType->id)
        ->set('service_id', $service->id)
        ->set('selectedDate', $bookingDate)
        ->set('schedule_id', $schedule->id)
        ->set('participant_count', 1)
        ->set('name', 'Janis')
        ->set('surname', 'Berzins')
        ->set('phone', '+37120000000')
        ->set('email', 'janis@example.com')
        ->call('submitBooking')
        ->assertSet('bookingComplete', false)
        ->assertSet('step', 2);

    // Only the original booking should exist
    expect(Booking::where('schedule_id', $schedule->id)->count())->toBe(1);
});

test('regular service allows multiple bookings until capacity is reached', function () {
    Mail::fake();

    $coach = Coach::factory()->create();
    $serviceType = ServiceType::factory()->create();
    // Service NOT marked as exclusive (regular booking mode)
    $service = Service::factory()->create([
        'service_type_id' => $serviceType->id,
        'coach_id' => $coach->id,
        'is_active' => true,
        'is_exclusive' => false,
    ]);

    $tomorrow = now()->addDay();
    $schedule = Schedule::factory()->create([
        'service_id' => $service->id,
        'day_of_week' => $tomorrow->dayOfWeekIso,
        'start_time' => '10:00',
        'is_active' => true,
        'max_capacity' => 5,
    ]);

    // Create existing booking for 3 participants
    Booking::factory()->create([
        'schedule_id' => $schedule->id,
        'booking_date' => $tomorrow->toDateString(),
        'participant_count' => 3,
    ]);

    // Slot should still be available with 2 remaining spots
    $component = Livewire::test('booking-modal')
        ->set('service_type_id', $serviceType->id)
        ->set('service_id', $service->id)
        ->set('selectedDate', $tomorrow->toDateString());

    $slots = $component->get('availableTimeSlots');
    expect($slots)->toHaveCount(1)
        ->and($slots[0]['remaining'])->toBe(2);
});

test('available price tiers are filtered by schedule max capacity', function () {
    $coach = Coach::factory()->create();
    $serviceType = ServiceType::factory()->create();
    $service = Service::factory()->exclusive()->create([
        'service_type_id' => $serviceType->id,
        'coach_id' => $coach->id,
        'is_active' => true,
    ]);

    $tomorrow = now()->addDay();
    $schedule = Schedule::factory()->create([
        'service_id' => $service->id,
        'day_of_week' => $tomorrow->dayOfWeekIso,
        'is_active' => true,
        'max_capacity' => 2, // Only allows up to 2 participants
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

    // After selecting schedule, only tiers with participant_count <= max_capacity should show
    $component = Livewire::test('booking-modal')
        ->set('service_type_id', $serviceType->id)
        ->set('service_id', $service->id)
        ->set('selectedDate', $tomorrow->toDateString())
        ->set('schedule_id', $schedule->id);

    $tiers = $component->get('availablePriceTiers');

    // Only 2 tiers should be available (1 and 2 participants)
    expect($tiers)->toHaveCount(2)
        ->and($tiers[0]->participant_count)->toBe(1)
        ->and($tiers[1]->participant_count)->toBe(2);
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

test('changing schedule resets participant count', function () {
    $coach = Coach::factory()->create();
    $serviceType = ServiceType::factory()->create();
    $service = Service::factory()->exclusive()->create([
        'service_type_id' => $serviceType->id,
        'coach_id' => $coach->id,
        'is_active' => true,
    ]);

    $tomorrow = now()->addDay();
    $schedule1 = Schedule::factory()->create([
        'service_id' => $service->id,
        'day_of_week' => $tomorrow->dayOfWeekIso,
        'start_time' => '10:00',
        'is_active' => true,
    ]);

    $schedule2 = Schedule::factory()->create([
        'service_id' => $service->id,
        'day_of_week' => $tomorrow->dayOfWeekIso,
        'start_time' => '14:00',
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
        ->set('selectedDate', $tomorrow->toDateString())
        ->set('schedule_id', $schedule1->id)
        ->set('participant_count', 2)
        ->set('schedule_id', $schedule2->id)
        ->assertSet('participant_count', 1);
});

test('isExclusiveService returns true for exclusive services', function () {
    $coach = Coach::factory()->create();
    $serviceType = ServiceType::factory()->create();
    // Service marked as exclusive
    $service = Service::factory()->exclusive()->create([
        'service_type_id' => $serviceType->id,
        'coach_id' => $coach->id,
        'is_active' => true,
    ]);

    Schedule::factory()->create([
        'service_id' => $service->id,
        'day_of_week' => now()->addDay()->dayOfWeekIso,
        'is_active' => true,
    ]);

    $component = Livewire::test('booking-modal')
        ->set('service_type_id', $serviceType->id)
        ->set('service_id', $service->id);

    expect($component->get('isExclusiveService'))->toBeTrue();
});

test('isExclusiveService returns false for non-exclusive services', function () {
    $coach = Coach::factory()->create();
    $serviceType = ServiceType::factory()->create();
    // Service NOT marked as exclusive
    $service = Service::factory()->create([
        'service_type_id' => $serviceType->id,
        'coach_id' => $coach->id,
        'is_active' => true,
        'is_exclusive' => false,
    ]);

    Schedule::factory()->create([
        'service_id' => $service->id,
        'day_of_week' => now()->addDay()->dayOfWeekIso,
        'is_active' => true,
    ]);

    $component = Livewire::test('booking-modal')
        ->set('service_type_id', $serviceType->id)
        ->set('service_id', $service->id);

    expect($component->get('isExclusiveService'))->toBeFalse();
});

test('regular service capacity calculation sums participant counts from all bookings', function () {
    $coach = Coach::factory()->create();
    $serviceType = ServiceType::factory()->create();
    // Service NOT marked as exclusive (regular booking mode)
    $service = Service::factory()->create([
        'service_type_id' => $serviceType->id,
        'coach_id' => $coach->id,
        'is_active' => true,
        'is_exclusive' => false,
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
        ->set('selectedDate', $tomorrow->toDateString());

    $slots = $component->get('availableTimeSlots');
    expect($slots)->toHaveCount(1)
        ->and($slots[0]['remaining'])->toBe(4);
});

test('non-exclusive service with price tiers still allows multiple bookings', function () {
    Mail::fake();

    $coach = Coach::factory()->create();
    $serviceType = ServiceType::factory()->create();
    // Service with price tiers but NOT exclusive
    $service = Service::factory()->create([
        'service_type_id' => $serviceType->id,
        'coach_id' => $coach->id,
        'is_active' => true,
        'is_exclusive' => false,
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
        'max_capacity' => 5,
    ]);

    // Create existing booking
    Booking::factory()->create([
        'schedule_id' => $schedule->id,
        'booking_date' => $tomorrow->toDateString(),
        'participant_count' => 2,
    ]);

    // Slot should still be available (not exclusive, so multiple bookings allowed)
    $component = Livewire::test('booking-modal')
        ->set('service_type_id', $serviceType->id)
        ->set('service_id', $service->id)
        ->set('selectedDate', $tomorrow->toDateString());

    $slots = $component->get('availableTimeSlots');
    expect($slots)->toHaveCount(1)
        ->and($slots[0]['remaining'])->toBe(3);
});
