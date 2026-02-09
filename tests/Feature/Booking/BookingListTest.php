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

test('booking list displays bookings with newest booking date first by default', function () {
    $oldBooking = Booking::factory()->create(['booking_date' => now()->subDays(2)]);
    $newBooking = Booking::factory()->create(['booking_date' => now()]);

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

test('booking list can filter to show only paid bookings', function () {
    Booking::factory()->create(['payment_status' => PaymentStatus::Paid]);
    Booking::factory()->create(['payment_status' => PaymentStatus::Pending]);
    Booking::factory()->create(['payment_status' => PaymentStatus::Failed]);

    $component = Livewire::test('booking.booking-list')
        ->set('paidOnly', true);

    $bookings = $component->instance()->bookings;
    expect($bookings)->toHaveCount(1);
    expect($bookings->first()->payment_status)->toBe(PaymentStatus::Paid);
});

test('booking list shows all bookings when filter is disabled', function () {
    Booking::factory()->create(['payment_status' => PaymentStatus::Paid]);
    Booking::factory()->create(['payment_status' => PaymentStatus::Pending]);

    $component = Livewire::test('booking.booking-list')
        ->set('paidOnly', false);

    $bookings = $component->instance()->bookings;
    expect($bookings)->toHaveCount(2);
});

test('booking list resets page when filter changes', function () {
    Booking::factory()->count(15)->create(['payment_status' => PaymentStatus::Paid]);

    Livewire::test('booking.booking-list')
        ->call('gotoPage', 2)
        ->assertSet('paginators.page', 2)
        ->set('paidOnly', true)
        ->assertSet('paginators.page', 1);
});

test('booking list shows empty message when filter returns no results', function () {
    Booking::factory()->create(['payment_status' => PaymentStatus::Pending]);

    Livewire::test('booking.booking-list')
        ->set('paidOnly', true)
        ->assertSee('Nav nevienas apmaksātas rezervācijas.');
});

test('booking list can sort by name ascending', function () {
    $bookingA = Booking::factory()->create(['name' => 'Anna']);
    $bookingZ = Booking::factory()->create(['name' => 'Zane']);

    $component = Livewire::test('booking.booking-list')
        ->call('sort', 'name');

    $bookings = $component->instance()->bookings;
    expect($bookings->first()->id)->toBe($bookingA->id);
    expect($bookings->last()->id)->toBe($bookingZ->id);
});

test('booking list can sort by name descending', function () {
    $bookingA = Booking::factory()->create(['name' => 'Anna']);
    $bookingZ = Booking::factory()->create(['name' => 'Zane']);

    $component = Livewire::test('booking.booking-list')
        ->call('sort', 'name')
        ->call('sort', 'name');

    $bookings = $component->instance()->bookings;
    expect($bookings->first()->id)->toBe($bookingZ->id);
    expect($bookings->last()->id)->toBe($bookingA->id);
});

test('booking list can sort by booking date ascending', function () {
    $oldBooking = Booking::factory()->create(['booking_date' => now()->subDays(2)]);
    $newBooking = Booking::factory()->create(['booking_date' => now()]);

    $component = Livewire::test('booking.booking-list')
        ->call('sort', 'booking_date');

    $bookings = $component->instance()->bookings;
    expect($bookings->first()->id)->toBe($oldBooking->id);
    expect($bookings->last()->id)->toBe($newBooking->id);
});

test('booking list can sort by payment status', function () {
    $paidBooking = Booking::factory()->create(['payment_status' => PaymentStatus::Paid]);
    $pendingBooking = Booking::factory()->create(['payment_status' => PaymentStatus::Pending]);

    $component = Livewire::test('booking.booking-list')
        ->call('sort', 'payment_status');

    $bookings = $component->instance()->bookings;
    expect($bookings)->toHaveCount(2);
    expect($component->get('sortBy'))->toBe('payment_status');
    expect($component->get('sortDirection'))->toBe('asc');
});

test('clicking same column toggles sort direction', function () {
    Livewire::test('booking.booking-list')
        ->assertSet('sortBy', 'booking_date')
        ->assertSet('sortDirection', 'desc')
        ->call('sort', 'booking_date')
        ->assertSet('sortDirection', 'asc')
        ->call('sort', 'booking_date')
        ->assertSet('sortDirection', 'desc');
});

test('clicking different column resets sort direction to ascending', function () {
    Livewire::test('booking.booking-list')
        ->assertSet('sortBy', 'booking_date')
        ->assertSet('sortDirection', 'desc')
        ->call('sort', 'name')
        ->assertSet('sortBy', 'name')
        ->assertSet('sortDirection', 'asc');
});

test('sorting resets pagination to first page', function () {
    Booking::factory()->count(15)->create();

    Livewire::test('booking.booking-list')
        ->call('gotoPage', 2)
        ->assertSet('paginators.page', 2)
        ->call('sort', 'name')
        ->assertSet('paginators.page', 1);
});
