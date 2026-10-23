<?php

use App\Models\Booking;
use App\Models\Schedule;
use App\Models\Service;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * Create a class on Wednesdays at 18:00 that a membership can be used for. "Now" in these tests
 * is Monday 5 October 2026, 09:00, so the class falls on 7, 14, 21 and 28 October.
 *
 * @param  array<string, mixed>  $service
 * @param  array<string, mixed>  $schedule
 */
function createPickerClass(array $service = [], array $schedule = []): Schedule
{
    test()->travelTo(Carbon::parse('2026-10-05 09:00:00'));

    return Schedule::factory()->create(array_merge([
        'service_id' => Service::factory()->membershipEligible()->create($service)->id,
        'day_of_week' => 3,
        'start_time' => '18:00',
        'max_capacity' => 10,
    ], $schedule));
}

/**
 * Open the timetable in membership mode with the given membership chosen.
 */
function openPickerWith(Service $membershipService): Testable
{
    return Livewire::withQueryParams(['veids' => 'abonements'])
        ->test('timetable')
        ->set('membershipServiceId', $membershipService->id);
}

test('opens in single class mode and switches to membership mode', function () {
    createPickerClass();

    Livewire::test('timetable')
        ->assertSet('mode', 'nodarbiba')
        ->assertSee('Vienreizēja nodarbība')
        ->assertSee('Pieteikties')
        ->set('mode', 'abonements')
        ->assertSee('Pievienot')
        ->assertDontSee('Pieteikties');
});

test('falls back to single class mode for an unknown booking type in the address', function () {
    createPickerClass();

    Livewire::withQueryParams(['veids' => 'kaut-kas'])
        ->test('timetable')
        ->assertSet('mode', 'nodarbiba');
});

test('membership mode offers the memberships on sale and only the classes a membership can be used for', function () {
    createPickerClass(['name' => 'Fusion Pilates']);
    Schedule::factory()->create([
        'service_id' => Service::factory()->create(['name' => 'Individuālā konsultācija'])->id,
        'day_of_week' => 3,
    ]);
    Service::factory()->membership(4)->create(['name' => 'Mēneša abonements (4x)', 'price' => 4500]);
    Service::factory()->membership(9)->create(['name' => 'Vecais abonements', 'is_active' => false]);

    Livewire::withQueryParams(['veids' => 'abonements'])
        ->test('timetable')
        ->assertSee('Mēneša abonements (4x)')
        ->assertSee('4 nodarbības · €45.00')
        ->assertDontSee('mēnesī')
        ->assertDontSee('Vecais abonements')
        ->assertSee('Fusion Pilates')
        ->assertDontSee('Individuālā konsultācija');
});

test('does not add a session before a membership is chosen', function () {
    $class = createPickerClass();

    Livewire::withQueryParams(['veids' => 'abonements'])
        ->test('timetable')
        ->call('addSession', $class->id, '2026-10-07')
        ->assertHasErrors('picks')
        ->assertSet('picks', []);
});

test('adds and removes sessions and offers to continue once all are picked', function () {
    $class = createPickerClass();
    $membershipService = Service::factory()->membership(2)->inCategoryOf($class->service)->create();

    openPickerWith($membershipService)
        ->call('addSession', $class->id, '2026-10-07')
        ->assertSee('Izvēlētas 1 no 2')
        ->assertDontSee('Turpināt')
        ->call('addSession', $class->id, '2026-10-14')
        ->assertHasNoErrors()
        ->assertSet('picks', [
            ['schedule_id' => $class->id, 'date' => '2026-10-07'],
            ['schedule_id' => $class->id, 'date' => '2026-10-14'],
        ])
        ->assertSee('Visas 2 nodarbības izvēlētas')
        ->assertSee('Turpināt')
        ->call('removeSession', $class->id, '2026-10-07')
        ->assertSet('picks', [['schedule_id' => $class->id, 'date' => '2026-10-14']]);
});

test('does not add more sessions than the membership has', function () {
    $class = createPickerClass();
    $membershipService = Service::factory()->membership(1)->inCategoryOf($class->service)->create();

    openPickerWith($membershipService)
        ->call('addSession', $class->id, '2026-10-07')
        ->call('addSession', $class->id, '2026-10-14')
        ->assertHasErrors('picks')
        ->assertSet('picks', [['schedule_id' => $class->id, 'date' => '2026-10-07']]);
});

test('keeps every session within one month of the first', function () {
    $class = createPickerClass();
    $membershipService = Service::factory()->membership(2)->inCategoryOf($class->service)->create();

    openPickerWith($membershipService)
        ->call('addSession', $class->id, '2026-10-07')
        ->set('week', 5)
        ->assertSee('Ārpus perioda')
        ->call('addSession', $class->id, '2026-11-11')
        ->assertHasErrors('picks')
        ->assertSet('picks', [['schedule_id' => $class->id, 'date' => '2026-10-07']]);
});

test('does not add a session that cannot be booked', function (Closure $makeClass) {
    $class = $makeClass();
    $membershipService = Service::factory()->membership(2)->inCategoryOf($class->service)->create();

    openPickerWith($membershipService)
        ->call('addSession', $class->id, '2026-10-07')
        ->assertHasErrors('picks')
        ->assertSet('picks', []);
})->with([
    'full class' => function () {
        $class = createPickerClass(schedule: ['max_capacity' => 1]);
        Booking::factory()->paid()->create(['schedule_id' => $class->id, 'booking_date' => '2026-10-07']);

        return $class;
    },
    'class not for memberships' => function () {
        $class = createPickerClass();
        $class->service->update(['is_membership_eligible' => false]);

        return $class;
    },
    'class not on that day' => fn () => createPickerClass(schedule: ['day_of_week' => 4]),
]);

test('a chosen membership lists only the classes of its own category', function () {
    createPickerClass(['name' => 'Fusion Pilates']);
    $physio = createPickerClass(['name' => 'Ārstnieciskā vingrošana'], ['start_time' => '10:00']);
    $physioMembership = Service::factory()->membership(4)->inCategoryOf($physio->service)->create();

    Livewire::withQueryParams(['veids' => 'abonements'])
        ->test('timetable')
        ->assertSee('Fusion Pilates')
        ->assertSee('Ārstnieciskā vingrošana')
        ->set('membershipServiceId', $physioMembership->id)
        ->assertSee('Ārstnieciskā vingrošana')
        ->assertDontSee('Fusion Pilates');
});

test('does not add a class from another category than the membership', function () {
    $pilates = createPickerClass();
    $physio = createPickerClass(schedule: ['start_time' => '10:00']);
    $physioMembership = Service::factory()->membership(2)->inCategoryOf($physio->service)->create();

    openPickerWith($physioMembership)
        ->call('addSession', $pilates->id, '2026-10-07')
        ->assertHasErrors('picks')
        ->assertSet('picks', []);
});

test('switching to a membership of another category drops the sessions it does not cover', function () {
    $pilates = createPickerClass();
    $physio = createPickerClass(schedule: ['start_time' => '10:00']);
    $physio->service->serviceType->update(['name' => 'Fizioterapija']);
    $groupMembership = Service::factory()->membership(2)->inCategoryOf($pilates->service)->create();
    $physioMembership = Service::factory()->membership(2)->inCategoryOf($physio->service)->create();

    openPickerWith($groupMembership)
        ->call('addSession', $pilates->id, '2026-10-07')
        ->set('membershipServiceId', $physioMembership->id)
        ->assertSet('picks', [])
        ->assertSee('tikai kategorijas „Fizioterapija” nodarbībām');
});

test('switching to a smaller membership keeps the earliest sessions', function () {
    $class = createPickerClass();
    $threeSessions = Service::factory()->membership(3)->inCategoryOf($class->service)->create();
    $twoSessions = Service::factory()->membership(2)->inCategoryOf($class->service)->create();

    openPickerWith($threeSessions)
        ->call('addSession', $class->id, '2026-10-21')
        ->call('addSession', $class->id, '2026-10-07')
        ->call('addSession', $class->id, '2026-10-14')
        ->set('membershipServiceId', $twoSessions->id)
        ->assertSet('picks', [
            ['schedule_id' => $class->id, 'date' => '2026-10-07'],
            ['schedule_id' => $class->id, 'date' => '2026-10-14'],
        ])
        ->assertSee('atstātas pirmās 2');
});

test('ignores a membership that is not on sale', function () {
    createPickerClass();
    $retired = Service::factory()->membership(2)->create(['is_active' => false]);

    openPickerWith($retired)
        ->assertSet('membershipServiceId', null);
});

test('continue opens the membership checkout with the picked sessions', function () {
    $class = createPickerClass();
    $membershipService = Service::factory()->membership(2)->inCategoryOf($class->service)->create();

    openPickerWith($membershipService)
        ->call('addSession', $class->id, '2026-10-07')
        ->call('continueToCheckout')
        ->assertNotDispatched('open-membership-checkout')
        ->call('addSession', $class->id, '2026-10-14')
        ->call('continueToCheckout')
        ->assertDispatched('open-membership-checkout',
            membershipServiceId: $membershipService->id,
            sessions: [
                ['schedule_id' => $class->id, 'date' => '2026-10-07'],
                ['schedule_id' => $class->id, 'date' => '2026-10-14'],
            ],
        );
});

test('drops a picked session that was taken meanwhile when the timetable refreshes', function () {
    $class = createPickerClass(schedule: ['max_capacity' => 1]);
    $membershipService = Service::factory()->membership(2)->inCategoryOf($class->service)->create();
    $picker = openPickerWith($membershipService)
        ->call('addSession', $class->id, '2026-10-07')
        ->call('addSession', $class->id, '2026-10-14');

    Booking::factory()->paid()->create(['schedule_id' => $class->id, 'booking_date' => '2026-10-14']);

    $picker->dispatch('timetable-refresh')
        ->assertSet('picks', [['schedule_id' => $class->id, 'date' => '2026-10-07']])
        ->assertSee('vairs nav pieejams');
});

test('the picked sessions cannot be changed from the browser', function () {
    $class = createPickerClass();

    openPickerWith(Service::factory()->membership(2)->inCategoryOf($class->service)->create())
        ->set('picks', [['schedule_id' => $class->id, 'date' => '2026-10-07']]);
})->throws(CannotUpdateLockedPropertyException::class);

test('timetable page includes the membership checkout', function () {
    $this->get(route('timetable', ['veids' => 'abonements']))
        ->assertSeeLivewire('membership.membership-checkout');
});

test('tells a customer returning from a cancelled membership payment that the sessions are still held', function () {
    createPickerClass();

    Livewire::withQueryParams(['veids' => 'abonements', 'membership_cancelled' => 1])
        ->test('timetable')
        ->assertSet('mode', 'abonements')
        ->assertSee('Maksājums netika pabeigts.');
});
