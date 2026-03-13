<?php

use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Membership;
use App\Models\Schedule;
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
        ->set('paymentStatuses', [PaymentStatus::Paid->value])
        ->assertSee('Paid')
        ->assertDontSee('Pending');
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
