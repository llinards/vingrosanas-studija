<?php

use App\Models\Booking;
use App\Models\Coach;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\ServiceType;
use App\Models\UnavailableDate;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

/**
 * Create a class on Wednesdays at 18:00. "Now" in these tests is Monday 5 October 2026, 09:00,
 * so the current week runs 5–11 October and the class falls on Wednesday 7 October.
 *
 * @param  array<string, mixed>  $service
 * @param  array<string, mixed>  $schedule
 */
function createClassForTimetablePage(array $service = [], array $schedule = []): Schedule
{
    test()->travelTo(Carbon::parse('2026-10-05 09:00:00'));

    return Schedule::factory()->create(array_merge([
        'service_id' => Service::factory()->create($service)->id,
        'day_of_week' => 3,
        'start_time' => '18:00',
        'max_capacity' => 10,
    ], $schedule));
}

test('timetable page renders the timetable for guests', function () {
    $this->get(route('timetable'))
        ->assertSeeLivewire('timetable');
});

test('shows a class of the current week with its time, coach, price and free places', function () {
    $coach = Coach::factory()->create(['name' => 'Anete Platkēviča']);
    createClassForTimetablePage(
        ['name' => 'Fusion Pilates', 'coach_id' => $coach->id, 'price' => 1500, 'duration_minutes' => 45],
        ['max_capacity' => 12],
    );

    Livewire::test('timetable')
        ->assertSee('05.10. – 11.10.2026')
        ->assertSee('18:00–18:45')
        ->assertSee('Fusion Pilates')
        ->assertSee('Anete Platkēviča')
        ->assertSee('€15.00')
        ->assertSee('12 vietas')
        ->assertSee('Pieteikties');
});

test('uses the singular for a single free place', function () {
    $class = createClassForTimetablePage(schedule: ['max_capacity' => 2]);
    Booking::factory()->paid()->create(['schedule_id' => $class->id, 'booking_date' => '2026-10-07']);

    Livewire::test('timetable')
        ->assertSee('1 vieta')
        ->assertDontSee('1 vietas');
});

test('shows the classes of the following week after moving forward and back', function () {
    createClassForTimetablePage(['name' => 'Vienreizēja meistarklase'], ['day_of_week' => null, 'date' => '2026-10-14']);

    Livewire::test('timetable')
        ->assertDontSee('Vienreizēja meistarklase')
        ->call('nextWeek')
        ->assertSet('week', 1)
        ->assertSee('12.10. – 18.10.2026')
        ->assertSee('Vienreizēja meistarklase')
        ->call('previousWeek')
        ->assertSet('week', 0)
        ->assertDontSee('Vienreizēja meistarklase');
});

test('does not move before the current week', function () {
    createClassForTimetablePage();

    Livewire::test('timetable')
        ->call('previousWeek')
        ->assertSet('week', 0);
});

test('does not move past the last bookable week', function () {
    createClassForTimetablePage();

    Livewire::test('timetable')
        ->set('week', 12)
        ->call('nextWeek')
        ->assertSet('week', 12)
        ->assertSee('28.12. – 03.01.2027');
});

test('clamps a week taken from the address bar to the bookable range', function (int $requested, int $shown) {
    createClassForTimetablePage();

    Livewire::withQueryParams(['week' => $requested])
        ->test('timetable')
        ->assertSet('week', $shown);
})->with([
    'before the current week' => [-3, 0],
    'after the booking horizon' => [99, 12],
]);

test('falls back to the current week when the week in the address bar is not a number', function () {
    createClassForTimetablePage(['name' => 'Fusion Pilates']);

    $this->get(route('timetable', ['week' => 'abc', 'type' => 'xyz']))
        ->assertSee('05.10. – 11.10.2026')
        ->assertSee('Fusion Pilates');
});

test('shows only the classes of the chosen service type', function () {
    $physio = ServiceType::factory()->create(['name' => 'Fizioterapija']);
    createClassForTimetablePage(['name' => 'Ārstnieciskā vingrošana', 'service_type_id' => $physio->id]);
    createClassForTimetablePage(['name' => 'Funkcionālais treniņš']);

    Livewire::test('timetable')
        ->assertSee('Ārstnieciskā vingrošana')
        ->assertSee('Funkcionālais treniņš')
        ->call('showServiceType', $physio->id)
        ->assertSet('serviceTypeId', $physio->id)
        ->assertSee('Ārstnieciskā vingrošana')
        ->assertDontSee('Funkcionālais treniņš')
        ->call('showServiceType', null)
        ->assertSee('Funkcionālais treniņš');
});

test('shows every class when the chosen service type has nothing to book', function () {
    $empty = ServiceType::factory()->create();
    createClassForTimetablePage(['name' => 'Funkcionālais treniņš']);

    Livewire::withQueryParams(['type' => $empty->id])
        ->test('timetable')
        ->assertSet('serviceTypeId', null)
        ->assertSee('Funkcionālais treniņš');
});

test('offers a filter only for service types that have bookable classes, in their set order', function () {
    $second = ServiceType::factory()->create(['name' => 'Fizioterapija', 'position' => 2]);
    $first = ServiceType::factory()->create(['name' => 'Grupu nodarbības', 'position' => 1]);
    ServiceType::factory()->create(['name' => 'Masāža', 'position' => 0]);
    $membershipOnly = ServiceType::factory()->create(['name' => 'Tikai abonementiem', 'position' => 3]);
    createClassForTimetablePage(['service_type_id' => $second->id]);
    createClassForTimetablePage(['service_type_id' => $first->id]);
    createClassForTimetablePage(['service_type_id' => $membershipOnly->id, 'is_membership' => true]);

    Livewire::test('timetable')
        ->assertSeeInOrder(['Visi', 'Grupu nodarbības', 'Fizioterapija'])
        ->assertDontSee('Masāža')
        ->assertDontSee('Tikai abonementiem');
});

test('shows a full class without a way to book it', function () {
    $class = createClassForTimetablePage(schedule: ['max_capacity' => 1]);
    Booking::factory()->paid()->create(['schedule_id' => $class->id, 'booking_date' => '2026-10-07']);

    Livewire::test('timetable')
        ->assertSee('Pilns')
        ->assertDontSee('Pieteikties');
});

test('shows a class that has already started without a way to book it', function () {
    createClassForTimetablePage(['name' => 'Rīta vingrošana'], ['day_of_week' => 1, 'start_time' => '07:00']);

    Livewire::test('timetable')
        ->assertSee('Rīta vingrošana')
        ->assertSee('Notikusi')
        ->assertDontSee('Pieteikties');
});

test('shows a class cancelled by a closure without a way to book it', function () {
    createClassForTimetablePage(['name' => 'Fusion Pilates']);
    UnavailableDate::factory()->range(Carbon::parse('2026-10-07'), Carbon::parse('2026-10-07'))->create();

    Livewire::test('timetable')
        ->assertSee('Fusion Pilates')
        ->assertSee('Nenotiek')
        ->assertDontSee('Pieteikties');
});

test('labels an individual session instead of showing free places', function () {
    $class = createClassForTimetablePage(['name' => 'Individuālā nodarbība', 'is_exclusive' => true, 'price' => 3500], ['max_capacity' => 2]);
    $class->service->priceTiers()->createMany([
        ['participant_count' => 1, 'price' => 3500],
        ['participant_count' => 2, 'price' => 5000],
    ]);

    Livewire::test('timetable')
        ->assertSee('Individuālā nodarbība')
        ->assertSee('Individuāli')
        ->assertSee('no €35.00')
        ->assertDontSee('vietas')
        ->assertSee('Pieteikties');
});

test('tells the visitor when a week has no classes', function () {
    createClassForTimetablePage(schedule: ['day_of_week' => null, 'date' => '2026-10-14']);

    Livewire::test('timetable')
        ->assertSee('Šajā nedēļā nodarbību nav.');
});

test('timetable page includes the booking checkout', function () {
    $this->get(route('timetable'))
        ->assertSeeLivewire('booking.booking-checkout');
});

test('tells a customer returning from a cancelled payment that the place is still held', function () {
    createClassForTimetablePage();

    Livewire::withQueryParams(['booking_cancelled' => 1])
        ->test('timetable')
        ->assertSee('Maksājums netika pabeigts.');
});

test('does not show the cancelled payment notice on a normal visit', function () {
    createClassForTimetablePage();

    Livewire::test('timetable')
        ->assertDontSee('Maksājums netika pabeigts.');
});

test('home page sends visitors who want to book to the timetable', function () {
    $this->get(route('home'))
        ->assertSee('href="'.route('timetable').'"', escape: false);
});

test('timetable page offers the membership purchase', function () {
    $this->get(route('timetable'))
        ->assertSee('Abonementi')
        ->assertSeeLivewire('booking-modal');
});

test('filters by the service type taken from the address bar', function () {
    $physio = ServiceType::factory()->create();
    createClassForTimetablePage(['name' => 'Ārstnieciskā vingrošana', 'service_type_id' => $physio->id]);
    createClassForTimetablePage(['name' => 'Funkcionālais treniņš']);

    Livewire::withQueryParams(['type' => $physio->id])
        ->test('timetable')
        ->assertSet('serviceTypeId', $physio->id)
        ->assertSee('Ārstnieciskā vingrošana')
        ->assertDontSee('Funkcionālais treniņš');
});
