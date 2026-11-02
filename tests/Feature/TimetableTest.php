<?php

use App\Models\Booking;
use App\Models\Coach;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\ServiceType;
use App\Models\UnavailableDate;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportTesting\Testable;
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

/**
 * Names of the classes listed in the week the timetable shows.
 *
 * @return list<string>
 */
function classesInShownWeek(Testable $component): array
{
    return collect($component->instance()->days)
        ->flatMap(fn (array $day) => array_column($day['entries'], 'service_name'))
        ->all();
}

test('timetable page renders the timetable for guests', function () {
    $this->get(route('timetable'))
        ->assertSeeLivewire('timetable');
});

test('shows a class of the current week with its start time, coach, price and free places', function () {
    $coach = Coach::factory()->create(['name' => 'Anete Platkēviča']);
    createClassForTimetablePage(
        ['name' => 'Fusion Pilates', 'coach_id' => $coach->id, 'price' => 1500, 'duration_minutes' => 45],
        ['max_capacity' => 12],
    );

    Livewire::test('timetable')
        ->assertSee('05.10. – 11.10.2026')
        ->assertSee('18:00')
        ->assertDontSee('18:45')
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

    $component = Livewire::test('timetable');
    expect(classesInShownWeek($component))->not->toContain('Vienreizēja meistarklase');

    $component->call('nextWeek')
        ->assertSet('week', 1)
        ->assertSee('12.10. – 18.10.2026');
    expect(classesInShownWeek($component))->toContain('Vienreizēja meistarklase');

    $component->call('previousWeek')->assertSet('week', 0);
    expect(classesInShownWeek($component))->not->toContain('Vienreizēja meistarklase');
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

    $this->get(route('timetable', ['week' => 'abc']))
        ->assertSee('05.10. – 11.10.2026')
        ->assertSee('Fusion Pilates');
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

test('labels the days on the small-screen day strip with the standard Latvian abbreviations', function () {
    createClassForTimetablePage();

    $days = Livewire::test('timetable')->instance()->days;

    expect(array_column($days, 'weekday_short'))->toBe(['P', 'O', 'T', 'C', 'Pk', 'S', 'Sv']);
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

test('the menu has no separate memberships entry, as memberships are bought on the timetable', function () {
    $this->get(route('timetable'))
        ->assertSee('href="'.route('timetable').'"', escape: false)
        ->assertDontSee('Abonementi');
});

test('timetable opens on single classes with a switch to memberships', function () {
    createClassForTimetablePage();

    Livewire::test('timetable')
        ->assertSee('Vienreizēja nodarbība')
        ->assertSee('Abonements')
        ->assertDontSee('Pievienot');
});

test('shows the classes of every service type together without a filter', function () {
    $physio = ServiceType::factory()->create(['name' => 'Fizioterapija']);
    createClassForTimetablePage(['name' => 'Ārstnieciskā vingrošana', 'service_type_id' => $physio->id]);
    createClassForTimetablePage(['name' => 'Funkcionālais treniņš']);

    Livewire::test('timetable')
        ->assertSee('Ārstnieciskā vingrošana')
        ->assertSee('Funkcionālais treniņš')
        ->assertDontSee('Visi')
        ->assertDontSee('Fizioterapija');
});

test('hides the classes of a coach who is switched to inactive and shows them again when active', function () {
    $coach = Coach::factory()->create();
    createClassForTimetablePage(['name' => 'Fusion Pilates', 'coach_id' => $coach->id]);
    createClassForTimetablePage(['name' => 'Funkcionālais treniņš']);

    $coach->update(['is_active' => false]);

    Livewire::test('timetable')
        ->assertDontSee('Fusion Pilates')
        ->assertSee('Funkcionālais treniņš');

    $coach->update(['is_active' => true]);

    Livewire::test('timetable')
        ->assertSee('Fusion Pilates');
});

test('swiping back from a Monday opens the previous week on Sunday, while the arrows open weeks on Monday', function () {
    createClassForTimetablePage();

    Livewire::test('timetable')
        ->set('week', 2)
        ->call('previousWeek', true)
        ->assertSet('week', 1)
        ->assertSee("day: '2026-10-18'", escape: false)
        ->call('nextWeek')
        ->assertSee("day: '2026-10-19'", escape: false)
        ->call('previousWeek')
        ->assertSee("day: '2026-10-12'", escape: false);
});

test('lists each class name once in the class picker, without classes the timetable does not show', function () {
    createClassForTimetablePage(['name' => 'Pilates REFORMER']);
    createClassForTimetablePage(['name' => 'Pilates REFORMER']);
    createClassForTimetablePage(['name' => 'Vienreizēja meistarklase'], ['day_of_week' => null, 'date' => '2026-10-14']);
    createClassForTimetablePage(['name' => 'Pagājusī meistarklase'], ['day_of_week' => null, 'date' => '2026-10-01']);
    createClassForTimetablePage(['name' => 'Izslēgta nodarbība', 'is_active' => false]);
    createClassForTimetablePage(['name' => 'Izslēgts laiks'], ['is_active' => false]);
    createClassForTimetablePage(['name' => 'Neaktīva trenera nodarbība', 'coach_id' => Coach::factory()->create(['is_active' => false])->id]);
    Schedule::factory()->create(['service_id' => Service::factory()->membership()->create(['name' => 'Mēneša abonements'])->id]);
    Service::factory()->create(['name' => 'Nodarbība bez laika']);

    $component = Livewire::test('timetable');

    expect($component->instance()->classNames)->toBe(['Pilates REFORMER', 'Vienreizēja meistarklase']);
});

test('shows only the picked class in the week and keeps it when moving to the next week', function () {
    createClassForTimetablePage(['name' => 'Pilates REFORMER']);
    createClassForTimetablePage(['name' => 'Pilates REFORMER'], ['day_of_week' => 5]);
    createClassForTimetablePage(['name' => 'Funkcionālais treniņš']);

    $component = Livewire::test('timetable')->set('className', 'Pilates REFORMER');

    expect(classesInShownWeek($component))->toBe(['Pilates REFORMER', 'Pilates REFORMER']);
    $component->call('nextWeek');
    expect(classesInShownWeek($component))->toBe(['Pilates REFORMER', 'Pilates REFORMER']);
});

test('shows every class again after picking all classes', function () {
    createClassForTimetablePage(['name' => 'Pilates REFORMER']);
    createClassForTimetablePage(['name' => 'Funkcionālais treniņš']);

    $component = Livewire::test('timetable')
        ->set('className', 'Pilates REFORMER')
        ->set('className', '')
        ->assertSet('className', null);

    expect(classesInShownWeek($component))->toBe(['Funkcionālais treniņš', 'Pilates REFORMER']);
});

test('shows the earliest free session of the picked class, skipping started, full and cancelled ones', function () {
    createClassForTimetablePage(['name' => 'Pilates REFORMER'], ['day_of_week' => 1, 'start_time' => '07:00']);
    $fullClass = createClassForTimetablePage(['name' => 'Pilates REFORMER'], ['max_capacity' => 1]);
    Booking::factory()->paid()->create(['schedule_id' => $fullClass->id, 'booking_date' => '2026-10-07']);
    createClassForTimetablePage(['name' => 'Pilates REFORMER'], ['day_of_week' => 4]);
    UnavailableDate::factory()->range(Carbon::parse('2026-10-08'), Carbon::parse('2026-10-08'))->create();
    $freeClass = createClassForTimetablePage(['name' => 'Pilates REFORMER'], ['day_of_week' => 5, 'start_time' => '10:00']);
    createClassForTimetablePage(['name' => 'Funkcionālais treniņš'], ['day_of_week' => 2]);

    $component = Livewire::test('timetable')->set('className', 'Pilates REFORMER');

    expect($component->instance()->nearestSession)
        ->toMatchArray(['schedule_id' => $freeClass->id, 'date' => '2026-10-09', 'start_time' => '10:00']);
    $component->assertSee('Tuvākā brīvā nodarbība')->assertSee('Piektdiena, 09.10.');
});

test('looks for the nearest free session past the current week', function () {
    $class = createClassForTimetablePage(['name' => 'Pilates REFORMER'], ['max_capacity' => 1]);
    Booking::factory()->paid()->create(['schedule_id' => $class->id, 'booking_date' => '2026-10-07']);

    $component = Livewire::test('timetable')->set('className', 'Pilates REFORMER');

    expect($component->instance()->nearestSession)->toMatchArray(['schedule_id' => $class->id, 'date' => '2026-10-14']);
});

test('tells the customer when the picked class has no free place within the booking window', function () {
    createClassForTimetablePage(['name' => 'Ziemas meistarklase'], ['day_of_week' => null, 'date' => '2027-01-20']);

    $component = Livewire::test('timetable')->set('className', 'Ziemas meistarklase');

    expect($component->instance()->nearestSession)->toBeNull();
    $component->assertSee('Tuvākajās 12 nedēļās brīvu vietu nav.');
});

test('labels the nearest session today or tomorrow instead of the weekday', function (int $dayOfWeek, string $label) {
    createClassForTimetablePage(['name' => 'Pilates REFORMER'], ['day_of_week' => $dayOfWeek]);

    Livewire::test('timetable')
        ->set('className', 'Pilates REFORMER')
        ->assertSee($label);
})->with([
    'today' => [1, 'Šodien, 05.10.'],
    'tomorrow' => [2, 'Rīt, 06.10.'],
    'later' => [3, 'Trešdiena, 07.10.'],
]);

test('opens with the class from the address bar picked', function () {
    createClassForTimetablePage(['name' => 'Pilates REFORMER']);
    createClassForTimetablePage(['name' => 'Funkcionālais treniņš']);

    $component = Livewire::withQueryParams(['class' => 'Pilates REFORMER'])->test('timetable');

    $component->assertSet('className', 'Pilates REFORMER');
    expect(classesInShownWeek($component))->toBe(['Pilates REFORMER']);
});

test('shows every class when the address bar names a class it cannot pick', function (array $queryParams) {
    createClassForTimetablePage(['name' => 'Pilates REFORMER']);

    Livewire::withQueryParams($queryParams)
        ->test('timetable')
        ->assertSet('className', null);
})->with([
    'unknown class' => [['class' => 'Nav tādas nodarbības']],
    'membership mode' => [['class' => 'Pilates REFORMER', 'veids' => 'abonements']],
]);

test('clears the picked class when switching to memberships', function () {
    createClassForTimetablePage(['name' => 'Pilates REFORMER']);

    Livewire::test('timetable')
        ->set('className', 'Pilates REFORMER')
        ->set('mode', 'abonements')
        ->assertSet('className', null)
        ->assertDontSee('Tuvākā brīvā nodarbība');
});
