<?php

use App\Models\Booking;
use App\Models\Coach;
use App\Models\Schedule;
use App\Models\Service;
use App\Services\BookingIcsGenerator;

test('generated ics is wrapped in a vcalendar block with a single vevent', function () {
    $booking = Booking::factory()->create();
    $booking->load('schedule.service.coach');

    $ics = (new BookingIcsGenerator)->generate($booking);

    expect($ics)
        ->toStartWith("BEGIN:VCALENDAR\r\n")
        ->toEndWith("END:VCALENDAR\r\n");

    expect(substr_count($ics, 'BEGIN:VEVENT'))->toBe(1);
    expect(substr_count($ics, 'END:VEVENT'))->toBe(1);
});

test('dtstart is the booking time in utc and dtend is exactly 60 minutes later', function () {
    $coach = Coach::factory()->create(['email' => 'coach@example.com']);
    $service = Service::factory()->exclusive()->create(['coach_id' => $coach->id]);
    $schedule = Schedule::factory()->create([
        'service_id' => $service->id,
        'start_time' => '10:00:00',
    ]);

    $booking = Booking::factory()->create([
        'schedule_id' => $schedule->id,
        'booking_date' => '2026-06-15',
    ]);
    $booking->load('schedule.service.coach');

    $ics = (new BookingIcsGenerator)->generate($booking);

    $expectedStart = $booking->getServiceDateTime()->utc()->format('Ymd\THis\Z');
    $expectedEnd = $booking->getServiceDateTime()->utc()->addMinutes(60)->format('Ymd\THis\Z');

    expect($ics)
        ->toContain("DTSTART:{$expectedStart}")
        ->toContain("DTEND:{$expectedEnd}");
});

test('summary combines service name and customer name and ics omits email invite fields', function () {
    $service = Service::factory()->exclusive()->create([
        'name' => 'Personīgā nodarbība',
    ]);
    $schedule = Schedule::factory()->create(['service_id' => $service->id]);

    $booking = Booking::factory()->create([
        'schedule_id' => $schedule->id,
        'name' => 'Anna',
        'surname' => 'Liepa',
        'email' => 'anna@example.com',
    ]);
    $booking->load('schedule.service.coach');

    $ics = (new BookingIcsGenerator)->generate($booking);

    expect($ics)
        ->toContain('SUMMARY:Personīgā nodarbība — Anna Liepa')
        ->not->toContain('ORGANIZER')
        ->not->toContain('ATTENDEE');
});

test('event includes a 15 minute display reminder', function () {
    $booking = Booking::factory()->create();
    $booking->load('schedule.service.coach');

    $ics = (new BookingIcsGenerator)->generate($booking);

    expect($ics)
        ->toContain('BEGIN:VALARM')
        ->toContain('ACTION:DISPLAY')
        ->toContain('TRIGGER:-PT15M')
        ->toContain('END:VALARM');

    expect(substr_count($ics, 'BEGIN:VALARM'))->toBe(1);
});

test('special characters in service name are escaped per rfc 5545', function () {
    $service = Service::factory()->exclusive()->create([
        'name' => 'Test, Service; With\\Specials',
    ]);
    $schedule = Schedule::factory()->create(['service_id' => $service->id]);

    $booking = Booking::factory()->create([
        'schedule_id' => $schedule->id,
        'name' => 'Anna',
        'surname' => 'Liepa',
    ]);
    $booking->load('schedule.service.coach');

    $ics = (new BookingIcsGenerator)->generate($booking);

    expect($ics)->toContain('SUMMARY:Test\\, Service\\; With\\\\Specials — Anna Liepa');
});
