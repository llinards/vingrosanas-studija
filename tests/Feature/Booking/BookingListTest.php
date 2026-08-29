<?php

use App\Enums\AttendanceStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Coach;
use App\Models\Membership;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('booking list page can be rendered', function () {
    $this->get(route('admin.bookings.index'))
        ->assertSuccessful();
});

test('booking list page requires authentication', function () {
    auth()->logout();

    $this->get(route('admin.bookings.index'))
        ->assertRedirect(route('login'));
});

test('booking list displays all bookings', function () {
    $bookings = Booking::factory()->count(3)->create(['booking_date' => today()]);

    $this->get(route('admin.bookings.index'))
        ->assertSuccessful()
        ->assertSee($bookings[0]->name)
        ->assertSee($bookings[1]->name)
        ->assertSee($bookings[2]->name);
});

test('booking list shows empty state when no bookings exist', function () {
    $this->get(route('admin.bookings.index'))
        ->assertSuccessful()
        ->assertSee('Šobrīd nav nevienas rezervācijas!');
});

test('booking list displays customer and service names', function () {
    $booking = Booking::factory()->create(['booking_date' => today()]);

    $this->get(route('admin.bookings.index'))
        ->assertSuccessful()
        ->assertSee($booking->name)
        ->assertSee($booking->schedule->service->name);
});

test('booking list shows payment status badge', function () {
    Booking::factory()->create(['payment_status' => PaymentStatus::Pending, 'booking_date' => today()]);

    $this->get(route('admin.bookings.index'))
        ->assertSuccessful()
        ->assertSee('Gaida apmaksu');
});

test('can destroy a booking', function () {
    $booking = Booking::factory()->create();

    Livewire::test('booking.booking-list')
        ->call('destroy', $booking->id)
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
});

test('bookings list refreshes after deletion', function () {
    $bookings = Booking::factory()->count(2)->create(['booking_date' => today()]);

    $component = Livewire::test('booking.booking-list')
        ->assertSee($bookings[0]->name)
        ->assertSee($bookings[1]->name);

    $component->call('destroy', $bookings[0]->id);

    $component->assertDontSee($bookings[0]->name)
        ->assertSee($bookings[1]->name);
});

test('booking list displays bookings with soonest booking date first by default', function () {
    $earlierBooking = Booking::factory()->create(['booking_date' => now()->addDays(1)]);
    $laterBooking = Booking::factory()->create(['booking_date' => now()->addDays(3)]);

    $component = Livewire::test('booking.booking-list')
        ->set('period', 'all');

    $bookings = $component->instance()->bookings;
    expect($bookings->first()->id)->toBe($earlierBooking->id);
    expect($bookings->last()->id)->toBe($laterBooking->id);
});

test('booking list paginates results', function () {
    Booking::factory()->count(15)->create(['booking_date' => today()]);

    $component = Livewire::test('booking.booking-list');

    $bookings = $component->instance()->bookings;
    expect($bookings)->toHaveCount(10);
    expect($bookings->total())->toBe(15);
});

test('booking list can navigate to second page', function () {
    Booking::factory()->count(15)->create();

    Livewire::test('booking.booking-list')
        ->call('gotoPage', 2)
        ->assertSet('paginators.page', 2);
});

test('booking list pagination is stable when many bookings share date and time', function () {
    $schedule = Schedule::factory()->create();

    $created = Booking::factory()
        ->count(15)
        ->create([
            'schedule_id' => $schedule->id,
            'booking_date' => today(),
        ]);

    $page1 = Livewire::test('booking.booking-list')
        ->instance()->bookings->pluck('id')->all();

    $page2 = Livewire::test('booking.booking-list')
        ->call('gotoPage', 2)
        ->instance()->bookings->pluck('id')->all();

    expect(array_intersect($page1, $page2))->toBeEmpty();
    expect(array_merge($page1, $page2))->toEqualCanonicalizing($created->pluck('id')->all());
});

test('booking list shows all bookings regardless of payment status by default', function () {
    Booking::factory()->create(['payment_status' => PaymentStatus::Paid, 'booking_date' => today()]);
    Booking::factory()->create(['payment_status' => PaymentStatus::Pending, 'booking_date' => today()]);
    Booking::factory()->create(['payment_status' => PaymentStatus::Failed, 'booking_date' => today()]);

    $component = Livewire::test('booking.booking-list');

    $bookings = $component->instance()->bookings;
    expect($bookings)->toHaveCount(3);
});

test('booking list displays participant count', function () {
    Booking::factory()->create(['participant_count' => 3, 'booking_date' => today()]);

    $this->get(route('admin.bookings.index'))
        ->assertSuccessful()
        ->assertSee('Dalībnieki');
});

// --- Period filter ---

test('booking list period defaults to today', function () {
    $component = Livewire::test('booking.booking-list');

    expect($component->instance()->period)->toBe('today');
});

test('booking list today period shows only today bookings', function () {
    $todayBooking = Booking::factory()->create(['booking_date' => today()]);
    Booking::factory()->create(['booking_date' => now()->addDay()]);
    Booking::factory()->past()->create();

    $component = Livewire::test('booking.booking-list')
        ->set('period', 'today');

    $bookings = $component->instance()->bookings;
    expect($bookings)->toHaveCount(1);
    expect($bookings->first()->id)->toBe($todayBooking->id);
});

test('booking list future period excludes today bookings', function () {
    $futureBooking = Booking::factory()->create(['booking_date' => now()->addDay()]);
    Booking::factory()->create(['booking_date' => today()]);
    Booking::factory()->past()->create();

    $component = Livewire::test('booking.booking-list')
        ->set('period', 'future');

    $bookings = $component->instance()->bookings;
    expect($bookings)->toHaveCount(1);
    expect($bookings->first()->id)->toBe($futureBooking->id);
});

test('booking list past period shows only past bookings', function () {
    $pastBooking = Booking::factory()->past()->create();
    $todayBooking = Booking::factory()->create(['booking_date' => today()]);
    $futureBooking = Booking::factory()->create(['booking_date' => now()->addDay()]);

    $component = Livewire::test('booking.booking-list')
        ->set('period', 'past');

    $bookings = $component->instance()->bookings;
    expect($bookings)->toHaveCount(1);
    expect($bookings->first()->id)->toBe($pastBooking->id);
});

test('booking list all period shows all bookings', function () {
    $pastBooking = Booking::factory()->past()->create();
    $todayBooking = Booking::factory()->create(['booking_date' => today()]);
    $futureBooking = Booking::factory()->create(['booking_date' => now()->addDay()]);

    $component = Livewire::test('booking.booking-list')
        ->set('period', 'all');

    $bookings = $component->instance()->bookings;
    expect($bookings)->toHaveCount(3);
});

test('booking list period filter resets pagination', function () {
    Booking::factory()->count(15)->create(['booking_date' => today()]);

    Livewire::test('booking.booking-list')
        ->call('gotoPage', 2)
        ->assertSet('paginators.page', 2)
        ->set('period', 'all')
        ->assertSet('paginators.page', 1);
});

test('booking list period filter is reflected in url query string', function () {
    Livewire::withQueryParams(['period' => 'past'])
        ->test('booking.booking-list')
        ->assertSet('period', 'past');
});

// --- Custom date range filter ---

test('booking list custom period shows bookings within the range including both boundaries', function () {
    $beforeRange = Booking::factory()->create(['booking_date' => today()->addDays(4)]);
    $rangeStart = Booking::factory()->create(['booking_date' => today()->addDays(5)]);
    $insideRange = Booking::factory()->create(['booking_date' => today()->addDays(7)]);
    $rangeEnd = Booking::factory()->create(['booking_date' => today()->addDays(10)]);
    $afterRange = Booking::factory()->create(['booking_date' => today()->addDays(11)]);

    $component = Livewire::test('booking.booking-list')
        ->set('period', 'custom')
        ->set('dateRange', [
            'start' => today()->addDays(5)->toDateString(),
            'end' => today()->addDays(10)->toDateString(),
        ]);

    expect($component->instance()->bookings->pluck('id')->all())
        ->toEqualCanonicalizing([$rangeStart->id, $insideRange->id, $rangeEnd->id]);
});

test('booking list custom period shows a single day when start and end are the same', function () {
    $targetBooking = Booking::factory()->create(['booking_date' => today()->addDays(3)]);
    Booking::factory()->create(['booking_date' => today()->addDays(2)]);
    Booking::factory()->create(['booking_date' => today()->addDays(4)]);

    $component = Livewire::test('booking.booking-list')
        ->set('period', 'custom')
        ->set('dateRange', [
            'start' => today()->addDays(3)->toDateString(),
            'end' => today()->addDays(3)->toDateString(),
        ]);

    expect($component->instance()->bookings->pluck('id')->all())->toBe([$targetBooking->id]);
});

test('booking list custom period with only a start date shows that day and later', function () {
    Booking::factory()->create(['booking_date' => today()->addDays(2)]);
    $startDayBooking = Booking::factory()->create(['booking_date' => today()->addDays(3)]);
    $laterBooking = Booking::factory()->create(['booking_date' => today()->addDays(9)]);

    $component = Livewire::test('booking.booking-list')
        ->set('period', 'custom')
        ->set('dateRange', ['start' => today()->addDays(3)->toDateString(), 'end' => null]);

    expect($component->instance()->bookings->pluck('id')->all())
        ->toEqualCanonicalizing([$startDayBooking->id, $laterBooking->id]);
});

test('booking list custom period with only an end date shows that day and earlier', function () {
    $earlierBooking = Booking::factory()->past()->create();
    $endDayBooking = Booking::factory()->create(['booking_date' => today()->addDays(3)]);
    Booking::factory()->create(['booking_date' => today()->addDays(4)]);

    $component = Livewire::test('booking.booking-list')
        ->set('period', 'custom')
        ->set('dateRange', ['start' => null, 'end' => today()->addDays(3)->toDateString()]);

    expect($component->instance()->bookings->pluck('id')->all())
        ->toEqualCanonicalizing([$earlierBooking->id, $endDayBooking->id]);
});

test('booking list custom period without any dates shows all bookings', function () {
    Booking::factory()->past()->create();
    Booking::factory()->create(['booking_date' => today()]);
    Booking::factory()->create(['booking_date' => today()->addDay()]);

    $component = Livewire::test('booking.booking-list')
        ->set('period', 'custom');

    expect($component->instance()->bookings)->toHaveCount(3);
});

test('booking list clears the date range when the period changes away from custom', function () {
    Livewire::test('booking.booking-list')
        ->set('period', 'custom')
        ->set('dateRange', [
            'start' => today()->toDateString(),
            'end' => today()->addDay()->toDateString(),
        ])
        ->set('period', 'future')
        ->assertSet('dateRange', ['start' => null, 'end' => null]);
});

test('booking list date range is reflected in url query string', function () {
    Livewire::withQueryParams([
        'period' => 'custom',
        'dateRange' => ['start' => '2026-03-01', 'end' => '2026-03-31'],
    ])
        ->test('booking.booking-list')
        ->assertSet('dateRange', ['start' => '2026-03-01', 'end' => '2026-03-31']);
});

test('booking list shows the date picker only for the custom period', function () {
    Booking::factory()->create(['booking_date' => today()]);

    Livewire::test('booking.booking-list')
        ->assertDontSee('Datumu diapazons')
        ->set('period', 'custom')
        ->assertSee('Datumu diapazons');
});

// --- Payment status filter ---

test('booking list payment status defaults to all', function () {
    $component = Livewire::test('booking.booking-list');

    expect($component->instance()->paymentStatus)->toBe('');
});

test('booking list filters bookings by payment status', function () {
    $paidBooking = Booking::factory()->paid()->create(['booking_date' => today()]);
    Booking::factory()->create(['payment_status' => PaymentStatus::Pending, 'booking_date' => today()]);
    Booking::factory()->create(['payment_status' => PaymentStatus::Failed, 'booking_date' => today()]);

    $component = Livewire::test('booking.booking-list')
        ->set('paymentStatus', PaymentStatus::Paid->value);

    $bookings = $component->instance()->bookings;
    expect($bookings)->toHaveCount(1);
    expect($bookings->first()->id)->toBe($paidBooking->id);
});

test('booking list resets pagination when payment status filter changes', function () {
    Booking::factory()->paid()->count(15)->create(['booking_date' => today()]);

    Livewire::test('booking.booking-list')
        ->call('gotoPage', 2)
        ->assertSet('paginators.page', 2)
        ->set('paymentStatus', PaymentStatus::Paid->value)
        ->assertSet('paginators.page', 1);
});

test('booking list payment status filter is reflected in url query string', function () {
    Livewire::withQueryParams(['paymentStatus' => PaymentStatus::Paid->value])
        ->test('booking.booking-list')
        ->assertSet('paymentStatus', PaymentStatus::Paid->value);
});

// --- Attendance status filter ---

test('booking list attendance status defaults to all', function () {
    $component = Livewire::test('booking.booking-list');

    expect($component->instance()->attendanceStatus)->toBe('');
});

test('booking list filters bookings by attendance status', function () {
    $attendedBooking = Booking::factory()->attended()->create(['booking_date' => today()]);
    Booking::factory()->missed()->create(['booking_date' => today()]);
    Booking::factory()->create(['booking_date' => today()]);

    $component = Livewire::test('booking.booking-list')
        ->set('attendanceStatus', AttendanceStatus::Attended->value);

    $bookings = $component->instance()->bookings;
    expect($bookings)->toHaveCount(1);
    expect($bookings->first()->id)->toBe($attendedBooking->id);
});

test('booking list attendance status filter is reflected in url query string', function () {
    Livewire::withQueryParams(['attendanceStatus' => AttendanceStatus::Attended->value])
        ->test('booking.booking-list')
        ->assertSet('attendanceStatus', AttendanceStatus::Attended->value);
});

// --- Coach filter ---

test('booking list filters bookings by coach', function () {
    $coach1 = Coach::factory()->create();
    $coach2 = Coach::factory()->create();

    $service1 = Service::factory()->create(['coach_id' => $coach1->id]);
    $service2 = Service::factory()->create(['coach_id' => $coach2->id]);

    $schedule1 = Schedule::factory()->create(['service_id' => $service1->id]);
    $schedule2 = Schedule::factory()->create(['service_id' => $service2->id]);

    $booking1 = Booking::factory()->create(['schedule_id' => $schedule1->id, 'booking_date' => today()]);
    $booking2 = Booking::factory()->create(['schedule_id' => $schedule2->id, 'booking_date' => today()]);

    $component = Livewire::test('booking.booking-list')
        ->set('coachId', (string) $coach1->id);

    $bookings = $component->instance()->bookings;
    expect($bookings)->toHaveCount(1);
    expect($bookings->first()->id)->toBe($booking1->id);
});

test('booking list coach filter is reflected in url query string', function () {
    $coach = Coach::factory()->create();

    Livewire::withQueryParams(['coachId' => (string) $coach->id])
        ->test('booking.booking-list')
        ->assertSet('coachId', (string) $coach->id);
});

// --- Service filter ---

test('booking list filters bookings by service', function () {
    $service1 = Service::factory()->create();
    $service2 = Service::factory()->create();

    $schedule1 = Schedule::factory()->create(['service_id' => $service1->id]);
    $schedule2 = Schedule::factory()->create(['service_id' => $service2->id]);

    $booking1 = Booking::factory()->create(['schedule_id' => $schedule1->id, 'booking_date' => today()]);
    $booking2 = Booking::factory()->create(['schedule_id' => $schedule2->id, 'booking_date' => today()]);

    $component = Livewire::test('booking.booking-list')
        ->set('serviceId', (string) $service1->id);

    $bookings = $component->instance()->bookings;
    expect($bookings)->toHaveCount(1);
    expect($bookings->first()->id)->toBe($booking1->id);
});

test('booking list service filter is reflected in url query string', function () {
    $service = Service::factory()->create();

    Livewire::withQueryParams(['serviceId' => (string) $service->id])
        ->test('booking.booking-list')
        ->assertSet('serviceId', (string) $service->id);
});

// --- Booking type filter ---

test('booking list filters membership bookings', function () {
    $membership = Membership::factory()->paid()->create();
    $membershipBooking = Booking::factory()->forMembership($membership)->create(['booking_date' => today()]);
    $regularBooking = Booking::factory()->create(['booking_date' => today()]);

    $component = Livewire::test('booking.booking-list')
        ->set('bookingType', 'membership');

    $bookings = $component->instance()->bookings;
    expect($bookings)->toHaveCount(1);
    expect($bookings->first()->id)->toBe($membershipBooking->id);
});

test('booking list filters regular bookings', function () {
    $membership = Membership::factory()->paid()->create();
    Booking::factory()->forMembership($membership)->create(['booking_date' => today()]);
    $regularBooking = Booking::factory()->create(['booking_date' => today()]);

    $component = Livewire::test('booking.booking-list')
        ->set('bookingType', 'regular');

    $bookings = $component->instance()->bookings;
    expect($bookings)->toHaveCount(1);
    expect($bookings->first()->id)->toBe($regularBooking->id);
});

test('booking list booking type filter is reflected in url query string', function () {
    Livewire::withQueryParams(['bookingType' => 'membership'])
        ->test('booking.booking-list')
        ->assertSet('bookingType', 'membership');
});

// --- Search ---

test('booking list search is empty by default', function () {
    $component = Livewire::test('booking.booking-list');

    expect($component->instance()->search)->toBe('');
});

test('booking list filters by search query', function () {
    $foundBooking = Booking::factory()->create(['name' => 'TestName', 'surname' => 'TestSurname', 'booking_date' => today()]);
    Booking::factory()->create(['name' => 'Other', 'surname' => 'Person', 'booking_date' => today()]);

    $component = Livewire::test('booking.booking-list')
        ->set('search', 'TestName');

    $bookings = $component->instance()->bookings;
    expect($bookings)->toHaveCount(1);
    expect($bookings->first()->id)->toBe($foundBooking->id);
});

test('booking list searches by surname', function () {
    $foundBooking = Booking::factory()->create(['surname' => 'Smith', 'booking_date' => today()]);
    Booking::factory()->create(['surname' => 'Johnson', 'booking_date' => today()]);

    $component = Livewire::test('booking.booking-list')
        ->set('search', 'Smith');

    $bookings = $component->instance()->bookings;
    expect($bookings)->toHaveCount(1);
    expect($bookings->first()->id)->toBe($foundBooking->id);
});

test('booking list searches by phone', function () {
    $foundBooking = Booking::factory()->create(['phone' => '+37112345678', 'booking_date' => today()]);
    Booking::factory()->create(['phone' => '+37187654321', 'booking_date' => today()]);

    $component = Livewire::test('booking.booking-list')
        ->set('search', '12345678');

    $bookings = $component->instance()->bookings;
    expect($bookings)->toHaveCount(1);
    expect($bookings->first()->id)->toBe($foundBooking->id);
});

test('booking list searches by email', function () {
    $foundBooking = Booking::factory()->create(['email' => 'unique123@test.com', 'booking_date' => today()]);
    Booking::factory()->create(['email' => 'different456@test.com', 'booking_date' => today()]);

    $component = Livewire::test('booking.booking-list')
        ->set('search', 'unique123');

    $bookings = $component->instance()->bookings;
    expect($bookings)->toHaveCount(1);
    expect($bookings->first()->id)->toBe($foundBooking->id);
});

test('booking list search respects payment status filter', function () {
    Booking::factory()->paid()->create(['name' => 'Test User', 'booking_date' => today()]);
    Booking::factory()->create(['name' => 'Test User', 'payment_status' => PaymentStatus::Pending, 'booking_date' => today()]);

    $component = Livewire::test('booking.booking-list')
        ->set('search', 'Test')
        ->set('paymentStatus', PaymentStatus::Paid->value);

    $bookings = $component->instance()->bookings;
    expect($bookings)->toHaveCount(1);
    expect($bookings->first()->payment_status)->toBe(PaymentStatus::Paid);
});

test('booking list search resets pagination', function () {
    Booking::factory()->paid()->count(15)->create(['booking_date' => today()]);

    Livewire::test('booking.booking-list')
        ->set('search', 'Test')
        ->call('gotoPage', 2)
        ->assertSet('paginators.page', 2)
        ->set('search', 'Other')
        ->assertSet('paginators.page', 1);
});

test('booking list search is reflected in url query string', function () {
    Livewire::withQueryParams(['search' => 'test'])
        ->test('booking.booking-list')
        ->assertSet('search', 'test');
});

test('booking list displays search input', function () {
    Booking::factory()->create(['booking_date' => today()]);

    $this->get(route('admin.bookings.index'))
        ->assertSuccessful()
        ->assertSee('Meklēt rezervācijas');
});

// --- Combined filters ---

test('booking list period works with payment status filter', function () {
    $todayPaid = Booking::factory()->paid()->create(['booking_date' => today()]);
    Booking::factory()->create(['booking_date' => today(), 'payment_status' => PaymentStatus::Pending]);
    Booking::factory()->paid()->create(['booking_date' => now()->addDay()]);

    $component = Livewire::test('booking.booking-list')
        ->set('period', 'today')
        ->set('paymentStatus', PaymentStatus::Paid->value);

    $bookings = $component->instance()->bookings;
    expect($bookings)->toHaveCount(1);
    expect($bookings->first()->id)->toBe($todayPaid->id);
});

test('booking list combines multiple filters', function () {
    $coach = Coach::factory()->create();
    $service = Service::factory()->create(['coach_id' => $coach->id]);
    $schedule = Schedule::factory()->create(['service_id' => $service->id]);

    $matchingBooking = Booking::factory()->paid()->create([
        'schedule_id' => $schedule->id,
        'booking_date' => today(),
    ]);

    // Wrong coach
    Booking::factory()->paid()->create(['booking_date' => today()]);
    // Wrong payment status
    Booking::factory()->create([
        'schedule_id' => $schedule->id,
        'booking_date' => today(),
        'payment_status' => PaymentStatus::Pending,
    ]);
    // Wrong period
    Booking::factory()->paid()->past()->create(['schedule_id' => $schedule->id]);

    $component = Livewire::test('booking.booking-list')
        ->set('period', 'today')
        ->set('paymentStatus', PaymentStatus::Paid->value)
        ->set('coachId', (string) $coach->id);

    $bookings = $component->instance()->bookings;
    expect($bookings)->toHaveCount(1);
    expect($bookings->first()->id)->toBe($matchingBooking->id);
});
