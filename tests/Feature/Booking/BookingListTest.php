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
    $this->get(route('booking-list'))
        ->assertSuccessful();
});

test('booking list page requires authentication', function () {
    auth()->logout();

    $this->get(route('booking-list'))
        ->assertRedirect(route('login'));
});

test('booking list displays all bookings', function () {
    $bookings = Booking::factory()->count(3)->create();

    $this->get(route('booking-list'))
        ->assertSuccessful()
        ->assertSee($bookings[0]->name)
        ->assertSee($bookings[1]->name)
        ->assertSee($bookings[2]->name);
});

test('booking list shows empty state when no bookings exist', function () {
    $this->get(route('booking-list'))
        ->assertSuccessful()
        ->assertSee('Šobrīd nav nevienas rezervācijas!');
});

test('booking list displays customer and service names', function () {
    $booking = Booking::factory()->create();

    $this->get(route('booking-list'))
        ->assertSuccessful()
        ->assertSee($booking->name)
        ->assertSee($booking->schedule->service->name);
});

test('booking list shows payment status badge', function () {
    Booking::factory()->create(['payment_status' => PaymentStatus::Pending]);

    $this->get(route('booking-list'))
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

test('booking list displays bookings with newest first', function () {
    $oldBooking = Booking::factory()->create(['created_at' => now()->subDays(2)]);
    $newBooking = Booking::factory()->create(['created_at' => now()]);

    $component = Livewire::test('booking.booking-list');

    $bookings = $component->instance()->bookings;
    expect($bookings->first()->id)->toBe($newBooking->id);
    expect($bookings->last()->id)->toBe($oldBooking->id);
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
