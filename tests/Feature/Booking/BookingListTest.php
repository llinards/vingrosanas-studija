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
    $laterBooking = Booking::factory()->create(['booking_date' => now()->addDays(3)]);

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

test('booking list shows both past and future bookings', function () {
    $futureBooking = Booking::factory()->create(['booking_date' => now()->addDays(1)]);
    $pastBooking = Booking::factory()->past()->create();

    $component = Livewire::test('booking.booking-list');

    $bookings = $component->instance()->bookings;
    expect($bookings)->toHaveCount(2);
});

test('booking list displays participant count', function () {
    Booking::factory()->create(['participant_count' => 3]);

    $this->get(route('admin.bookings.index'))
        ->assertSuccessful()
        ->assertSee('Dalībnieki');
});

test('booking list includes today bookings', function () {
    $todayBooking = Booking::factory()->create(['booking_date' => today()]);
    $pastBooking = Booking::factory()->past()->create();

    $component = Livewire::test('booking.booking-list');

    $bookings = $component->instance()->bookings;
    expect($bookings)->toHaveCount(2);
    // Today's booking should come before past booking when sorted by date ascending
    expect($bookings->contains('id', $todayBooking->id))->toBeTrue();
});
