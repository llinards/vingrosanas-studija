<?php

use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Membership;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('membership list page can be rendered', function () {
    $this->get(route('admin.memberships.index'))
        ->assertSuccessful()
        ->assertSeeLivewire('membership.membership-list');
});

test('membership list page requires authentication', function () {
    auth()->logout();

    $this->get(route('admin.memberships.index'))
        ->assertRedirect(route('login'));
});

test('membership list shows memberships', function () {
    $membership = Membership::factory()->paid()->create([
        'name' => 'Jānis',
        'surname' => 'Bērziņš',
    ]);

    Livewire::test('membership.membership-list')
        ->assertSee('Jānis')
        ->assertSee('Bērziņš');
});

test('membership list can search by name', function () {
    $m1 = Membership::factory()->paid()->create(['name' => 'Jānis', 'surname' => 'Bērziņš']);
    $m2 = Membership::factory()->paid()->create(['name' => 'Anna', 'surname' => 'Ozola']);

    Livewire::test('membership.membership-list')
        ->set('search', 'Jānis')
        ->assertSee('Jānis')
        ->assertDontSee('Anna');
});

test('membership list can filter by payment status', function () {
    $paid = Membership::factory()->paid()->create(['name' => 'Paid']);
    $pending = Membership::factory()->create(['name' => 'Pending']);

    Livewire::test('membership.membership-list')
        ->set('status', 'all')
        ->set('paymentStatus', PaymentStatus::Paid->value)
        ->assertSee('Paid')
        ->assertDontSee('Pending');
});

test('membership list status defaults to active', function () {
    $component = Livewire::test('membership.membership-list');

    expect($component->instance()->status)->toBe('active');
});

test('membership list active status shows current and future memberships', function () {
    $current = Membership::factory()->paid()->create(['name' => 'Current']);
    $future = Membership::factory()->create([
        'name' => 'Future',
        'period_start' => today()->addMonth()->startOfMonth(),
        'period_end' => today()->addMonth()->endOfMonth(),
    ]);
    $expired = Membership::factory()->paid()->expired()->create(['name' => 'Expired']);

    Livewire::test('membership.membership-list')
        ->assertSee('Current')
        ->assertSee('Future')
        ->assertDontSee('Expired');
});

test('membership list expired status shows only expired memberships', function () {
    $active = Membership::factory()->paid()->create(['name' => 'Active']);
    $expired = Membership::factory()->paid()->expired()->create(['name' => 'Expired']);

    Livewire::test('membership.membership-list')
        ->set('status', 'expired')
        ->assertSee('Expired')
        ->assertDontSee('Active');
});

test('membership list all status shows all memberships', function () {
    $active = Membership::factory()->paid()->create(['name' => 'Active']);
    $expired = Membership::factory()->paid()->expired()->create(['name' => 'Expired']);

    Livewire::test('membership.membership-list')
        ->set('status', 'all')
        ->assertSee('Active')
        ->assertSee('Expired');
});

test('membership list filters by membership plan', function () {
    $service4 = Service::factory()->membership(4)->create(['name' => '4 nodarbības']);
    $service9 = Service::factory()->membership(9)->create(['name' => '9 nodarbības']);

    Membership::factory()->paid()->create(['name' => 'FourSession', 'service_id' => $service4->id]);
    Membership::factory()->paid()->create(['name' => 'NineSession', 'service_id' => $service9->id]);

    Livewire::test('membership.membership-list')
        ->set('serviceId', (string) $service4->id)
        ->assertSee('FourSession')
        ->assertDontSee('NineSession');
});

test('membership list status filter is reflected in url query string', function () {
    Livewire::withQueryParams(['status' => 'expired'])
        ->test('membership.membership-list')
        ->assertSet('status', 'expired');
});

test('membership list payment status filter is reflected in url query string', function () {
    Livewire::withQueryParams(['paymentStatus' => PaymentStatus::Paid->value])
        ->test('membership.membership-list')
        ->assertSet('paymentStatus', PaymentStatus::Paid->value);
});

test('membership list service filter is reflected in url query string', function () {
    $service = Service::factory()->membership(4)->create();

    Livewire::withQueryParams(['serviceId' => (string) $service->id])
        ->test('membership.membership-list')
        ->assertSet('serviceId', (string) $service->id);
});

test('membership can be deleted from list', function () {
    $membership = Membership::factory()->paid()->create();

    Livewire::test('membership.membership-list')
        ->call('destroy', $membership->id);

    $this->assertDatabaseMissing('memberships', ['id' => $membership->id]);
});

test('membership edit page can be rendered', function () {
    $membership = Membership::factory()->paid()->create();

    $this->get(route('admin.memberships.edit', $membership))
        ->assertSuccessful()
        ->assertSeeLivewire('membership.membership-edit');
});

test('membership edit page requires authentication', function () {
    $membership = Membership::factory()->paid()->create();

    auth()->logout();

    $this->get(route('admin.memberships.edit', $membership))
        ->assertRedirect(route('login'));
});

test('membership edit form is populated with data', function () {
    $membership = Membership::factory()->paid()->create([
        'name' => 'Jānis',
        'surname' => 'Bērziņš',
        'email' => 'janis@example.com',
    ]);

    Livewire::test('membership.membership-edit', ['membership' => $membership])
        ->assertSet('name', 'Jānis')
        ->assertSet('surname', 'Bērziņš')
        ->assertSet('email', 'janis@example.com');
});

test('membership can be updated', function () {
    $membership = Membership::factory()->paid()->create();

    Livewire::test('membership.membership-edit', ['membership' => $membership])
        ->set('name', 'Updated')
        ->set('surname', 'Name')
        ->set('period_end', '2026-04-30')
        ->call('save')
        ->assertRedirect(route('admin.memberships.index'));

    expect($membership->fresh())
        ->name->toBe('Updated')
        ->surname->toBe('Name')
        ->period_end->format('Y-m-d')->toBe('2026-04-30');
});

test('membership edit shows linked bookings', function () {
    $membership = Membership::factory()->paid()->create();
    $schedule = Schedule::factory()->create();

    $booking = Booking::factory()->forMembership($membership)->create([
        'schedule_id' => $schedule->id,
    ]);

    Livewire::test('membership.membership-edit', ['membership' => $membership])
        ->assertSee($booking->schedule->service->name);
});

test('membership edit validates required fields', function () {
    $membership = Membership::factory()->paid()->create();

    Livewire::test('membership.membership-edit', ['membership' => $membership])
        ->set('name', '')
        ->set('email', '')
        ->call('save')
        ->assertHasErrors(['name', 'email']);
});
