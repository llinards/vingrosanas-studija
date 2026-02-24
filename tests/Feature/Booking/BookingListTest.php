<?php

use App\Enums\PaymentStatus;
use App\Models\Booking;
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
    $bookings = Booking::factory()->count(3)->create();

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
    $booking = Booking::factory()->create();

    $this->get(route('admin.bookings.index'))
         ->assertSuccessful()
         ->assertSee($booking->name)
         ->assertSee($booking->schedule->service->name);
});

test('booking list shows payment status badge', function () {
    Booking::factory()->create(['payment_status' => PaymentStatus::Pending]);

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
    $bookings = Booking::factory()->count(2)->create();

    $component = Livewire::test('booking.booking-list')
                         ->assertSee($bookings[0]->name)
                         ->assertSee($bookings[1]->name);

    $component->call('destroy', $bookings[0]->id);

    $component->assertDontSee($bookings[0]->name)
              ->assertSee($bookings[1]->name);
});

test('booking list displays bookings with soonest booking date first by default', function () {
    $earlierBooking = Booking::factory()->create(['booking_date' => now()->addDays(1)]);
    $laterBooking   = Booking::factory()->create(['booking_date' => now()->addDays(3)]);

    $component = Livewire::test('booking.booking-list');

    $bookings = $component->instance()->bookings;
    expect($bookings->first()->id)->toBe($earlierBooking->id);
    expect($bookings->last()->id)->toBe($laterBooking->id);
});

test('booking list paginates results', function () {
    Booking::factory()->count(15)->create();

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

test('booking list shows all bookings regardless of payment status', function () {
    Booking::factory()->create(['payment_status' => PaymentStatus::Paid]);
    Booking::factory()->create(['payment_status' => PaymentStatus::Pending]);
    Booking::factory()->create(['payment_status' => PaymentStatus::Failed]);

    $component = Livewire::test('booking.booking-list');

    $bookings = $component->instance()->bookings;
    expect($bookings)->toHaveCount(3);
});

test('booking list excludes past bookings by default', function () {
    $futureBooking = Booking::factory()->create(['booking_date' => now()->addDays(1)]);
    $pastBooking   = Booking::factory()->past()->create();

    $component = Livewire::test('booking.booking-list');

    $bookings = $component->instance()->bookings;
    expect($bookings)->toHaveCount(1);
    expect($bookings->first()->id)->toBe($futureBooking->id);
});

test('booking list displays participant count', function () {
    Booking::factory()->create(['participant_count' => 3]);

    $this->get(route('admin.bookings.index'))
         ->assertSuccessful()
         ->assertSee('Dalībnieki');
});

test('booking list includes today bookings by default', function () {
    $todayBooking = Booking::factory()->create(['booking_date' => today()]);
    $pastBooking  = Booking::factory()->past()->create();

    $component = Livewire::test('booking.booking-list');

    $bookings = $component->instance()->bookings;
    expect($bookings)->toHaveCount(1);
    expect($bookings->first()->id)->toBe($todayBooking->id);
});

test('booking list initializes with all payment statuses selected', function () {
    $component = Livewire::test('booking.booking-list');

    $expectedStatuses = collect(PaymentStatus::cases())
        ->map(fn(PaymentStatus $status) => $status->value)
        ->all();

    expect($component->instance()->paymentStatuses)->toBe($expectedStatuses);
});

test('booking list filters bookings by selected payment statuses', function () {
    $paidBooking    = Booking::factory()->paid()->create();
    $pendingBooking = Booking::factory()->create(['payment_status' => PaymentStatus::Pending]);
    $failedBooking  = Booking::factory()->create(['payment_status' => PaymentStatus::Failed]);

    $component = Livewire::test('booking.booking-list')
                         ->set('paymentStatuses', [PaymentStatus::Paid->value]);

    $bookings = $component->instance()->bookings;
    expect($bookings)->toHaveCount(1);
    expect($bookings->first()->id)->toBe($paidBooking->id);
});

test('booking list can filter by multiple payment statuses', function () {
    Booking::factory()->paid()->create();
    Booking::factory()->create(['payment_status' => PaymentStatus::Pending]);
    Booking::factory()->create(['payment_status' => PaymentStatus::Failed]);
    Booking::factory()->refunded()->create();

    $component = Livewire::test('booking.booking-list')
                         ->set('paymentStatuses', [PaymentStatus::Paid->value, PaymentStatus::Pending->value]);

    $bookings = $component->instance()->bookings;
    expect($bookings)->toHaveCount(2);
});

test('booking list shows no bookings when no payment statuses are selected', function () {
    Booking::factory()->paid()->create();
    Booking::factory()->create(['payment_status' => PaymentStatus::Pending]);

    $component = Livewire::test('booking.booking-list')
                         ->set('paymentStatuses', []);

    $bookings = $component->instance()->bookings;
    expect($bookings)->toHaveCount(0);
});

test('booking list resets pagination when payment status filter changes', function () {
    Booking::factory()->paid()->count(15)->create();

    Livewire::test('booking.booking-list')
            ->call('gotoPage', 2)
            ->assertSet('paginators.page', 2)
            ->set('paymentStatuses', [PaymentStatus::Paid->value])
            ->assertSet('paginators.page', 1);
});

test('booking list payment status filter is reflected in url query string', function () {
    Livewire::withQueryParams(['paymentStatuses' => [PaymentStatus::Paid->value]])
            ->test('booking.booking-list')
            ->assertSet('paymentStatuses', [PaymentStatus::Paid->value]);
});

test('booking list displays payment status filter checkboxes', function () {
    Booking::factory()->create();

    $this->get(route('admin.bookings.index'))
         ->assertSuccessful()
         ->assertSee('Apmaksāts')
         ->assertSee('Gaida apmaksu')
         ->assertSee('Neizdevās')
         ->assertSee('Atmaksāts');
});

test('booking list today only filter is off by default', function () {
    $component = Livewire::test('booking.booking-list');

    expect($component->instance()->todayOnly)->toBeFalse();
});

test('booking list today only filter shows only today bookings', function () {
    $todayBooking    = Booking::factory()->create(['booking_date' => today()]);
    $tomorrowBooking = Booking::factory()->create(['booking_date' => now()->addDay()]);
    $pastBooking     = Booking::factory()->past()->create();

    $component = Livewire::test('booking.booking-list')
                         ->set('todayOnly', true);

    $bookings = $component->instance()->bookings;
    expect($bookings)->toHaveCount(1);
    expect($bookings->first()->id)->toBe($todayBooking->id);
});

test('booking list today only filter shows all bookings when disabled', function () {
    Booking::factory()->create(['booking_date' => today()]);
    Booking::factory()->create(['booking_date' => now()->addDay()]);

    $component = Livewire::test('booking.booking-list')
                         ->set('todayOnly', false);

    $bookings = $component->instance()->bookings;
    expect($bookings)->toHaveCount(2);
});

test('booking list today only filter resets pagination', function () {
    Booking::factory()->count(15)->create(['booking_date' => today()]);

    Livewire::test('booking.booking-list')
            ->call('gotoPage', 2)
            ->assertSet('paginators.page', 2)
            ->set('todayOnly', true)
            ->assertSet('paginators.page', 1);
});

test('booking list today only filter is reflected in url query string', function () {
    Livewire::withQueryParams(['todayOnly' => true])
            ->test('booking.booking-list')
            ->assertSet('todayOnly', true);
});

test('booking list today only filter works with payment status filter', function () {
    $todayPaid    = Booking::factory()->paid()->create(['booking_date' => today()]);
    $todayPending = Booking::factory()->create(['booking_date' => today(), 'payment_status' => PaymentStatus::Pending]);
    $tomorrowPaid = Booking::factory()->paid()->create(['booking_date' => now()->addDay()]);

    $component = Livewire::test('booking.booking-list')
                         ->set('todayOnly', true)
                         ->set('paymentStatuses', [PaymentStatus::Paid->value]);

    $bookings = $component->instance()->bookings;
    expect($bookings)->toHaveCount(1);
    expect($bookings->first()->id)->toBe($todayPaid->id);
});

test('booking list displays today only filter checkbox', function () {
    Booking::factory()->create();

    $this->get(route('admin.bookings.index'))
         ->assertSuccessful()
         ->assertSee('Tikai šodienas');
});

test('booking list past only filter is off by default', function () {
    $component = Livewire::test('booking.booking-list');

    expect($component->instance()->pastOnly)->toBeFalse();
});

test('booking list past only filter shows only past bookings when checked', function () {
    $pastBooking   = Booking::factory()->past()->create();
    $futureBooking = Booking::factory()->create(['booking_date' => now()->addDay()]);
    $todayBooking  = Booking::factory()->create(['booking_date' => today()]);

    $component = Livewire::test('booking.booking-list')
                         ->set('pastOnly', true);

    $bookings = $component->instance()->bookings;
    expect($bookings)->toHaveCount(1);
    expect($bookings->first()->id)->toBe($pastBooking->id);
});

test('booking list past only filter excludes today bookings', function () {
    $pastBooking  = Booking::factory()->past()->create();
    $todayBooking = Booking::factory()->create(['booking_date' => today()]);

    $component = Livewire::test('booking.booking-list')
                         ->set('pastOnly', true);

    $bookings = $component->instance()->bookings;
    expect($bookings)->toHaveCount(1);
    expect($bookings->first()->id)->toBe($pastBooking->id);
});

test('booking list past only filter shows today and future when unchecked', function () {
    $pastBooking   = Booking::factory()->past()->create();
    $futureBooking = Booking::factory()->create(['booking_date' => now()->addDay()]);
    $todayBooking  = Booking::factory()->create(['booking_date' => today()]);

    $component = Livewire::test('booking.booking-list')
                         ->set('pastOnly', false);

    $bookings = $component->instance()->bookings;
    expect($bookings)->toHaveCount(2);
    expect($bookings->pluck('id')->toArray())->toContain($todayBooking->id);
    expect($bookings->pluck('id')->toArray())->toContain($futureBooking->id);
    expect($bookings->pluck('id')->toArray())->not->toContain($pastBooking->id);
});

test('booking list past only filter resets pagination', function () {
    Booking::factory()->past()->count(15)->create();

    Livewire::test('booking.booking-list')
            ->set('pastOnly', true)
            ->call('gotoPage', 2)
            ->assertSet('paginators.page', 2)
            ->set('pastOnly', false)
            ->assertSet('paginators.page', 1);
});

test('booking list past only filter is reflected in url query string', function () {
    Livewire::withQueryParams(['pastOnly' => true])
            ->test('booking.booking-list')
            ->assertSet('pastOnly', true);
});

test('booking list past only filter works with payment status filter', function () {
    $pastPaid    = Booking::factory()->paid()->past()->create();
    $pastPending = Booking::factory()->past()->create(['payment_status' => PaymentStatus::Pending]);
    $futurePaid  = Booking::factory()->paid()->create(['booking_date' => now()->addDay()]);

    $component = Livewire::test('booking.booking-list')
                         ->set('pastOnly', true)
                         ->set('paymentStatuses', [PaymentStatus::Paid->value]);

    $bookings = $component->instance()->bookings;
    expect($bookings)->toHaveCount(1);
    expect($bookings->first()->id)->toBe($pastPaid->id);
});

test('booking list displays past only filter checkbox', function () {
    Booking::factory()->create();

    $this->get(route('admin.bookings.index'))
         ->assertSuccessful()
         ->assertSee('Tikai pagātnes');
});
