<?php

use App\Actions\CreateMembershipCheckoutSession;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Membership;
use App\Models\Schedule;
use App\Models\Service;
use App\Services\BookingStartLimiter;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Stripe\Exception\UnknownApiErrorException;

/**
 * Create a class on Wednesdays at 18:00 that a membership can be used for. "Now" in these tests
 * is Monday 5 October 2026, 09:00, so the class falls on 7 and 14 October.
 *
 * @param  array<string, mixed>  $schedule
 */
function createMembershipCheckoutClass(array $schedule = []): Schedule
{
    test()->travelTo(Carbon::parse('2026-10-05 09:00:00'));

    return Schedule::factory()->create(array_merge([
        'service_id' => Service::factory()->membershipEligible()->create(['name' => 'Fusion Pilates'])->id,
        'day_of_week' => 3,
        'start_time' => '18:00',
        'max_capacity' => 10,
    ], $schedule));
}

/**
 * Open the membership checkout for two sessions of the class, with the customer details filled in.
 */
function openMembershipCheckout(Service $membershipService, Schedule $class): Testable
{
    return Livewire::test('membership.membership-checkout')
        ->call('open', $membershipService->id, [
            ['schedule_id' => $class->id, 'date' => '2026-10-14'],
            ['schedule_id' => $class->id, 'date' => '2026-10-07'],
        ])
        ->set('name', 'Anna')
        ->set('surname', 'Liepa')
        ->set('phone', '+371 20000000')
        ->set('email', 'anna@example.com');
}

test('shows the membership, its period, price and sessions', function () {
    $class = createMembershipCheckoutClass();
    $membershipService = Service::factory()->membership(2)->inCategoryOf($class->service)->create(['name' => 'Mēneša abonements (2x)', 'price' => 4500]);

    openMembershipCheckout($membershipService, $class)
        ->assertSee('Mēneša abonements (2x)')
        ->assertSee('07.10.2026 – 07.11.2026')
        ->assertSee('€45.00')
        ->assertSee('2 nodarbības')
        ->assertSeeInOrder(['07.10. 18:00', 'Fusion Pilates', '14.10. 18:00', 'Fusion Pilates'])
        ->assertSee('Apmaksāt ar karti');
});

test('buying reserves the sessions as an unpaid membership and sends the customer to Stripe', function () {
    $class = createMembershipCheckoutClass();
    $membershipService = Service::factory()->membership(2)->inCategoryOf($class->service)->create();
    $this->mock(CreateMembershipCheckoutSession::class)
        ->shouldReceive('execute')
        ->once()
        ->andReturn(['url' => 'https://checkout.stripe.com/membership', 'session_id' => 'cs_test_123']);

    openMembershipCheckout($membershipService, $class)
        ->call('pay')
        ->assertHasNoErrors()
        ->assertRedirect('https://checkout.stripe.com/membership');

    $membership = Membership::sole();
    expect($membership)
        ->service_id->toBe($membershipService->id)
        ->email->toBe('anna@example.com')
        ->payment_status->toBe(PaymentStatus::Pending);
    expect($membership->bookings()->pluck('booking_date')->map->toDateString()->sort()->values()->all())
        ->toBe(['2026-10-07', '2026-10-14']);
});

test('requires the customer details', function () {
    $class = createMembershipCheckoutClass();
    $this->mock(CreateMembershipCheckoutSession::class)->shouldNotReceive('execute');

    Livewire::test('membership.membership-checkout')
        ->call('open', Service::factory()->membership(1)->inCategoryOf($class->service)->create()->id, [['schedule_id' => $class->id, 'date' => '2026-10-07']])
        ->set('email', 'not-an-email')
        ->call('pay')
        ->assertHasErrors(['name', 'surname', 'phone', 'email'])
        ->assertSee('Vārds ir obligāts.')
        ->assertSee('E-pastam jābūt derīgai e-pasta adresei.');

    expect(Membership::count())->toBe(0);
});

test('tells the customer when a session was taken meanwhile and refreshes the timetable', function () {
    $class = createMembershipCheckoutClass(['max_capacity' => 1]);
    $this->mock(CreateMembershipCheckoutSession::class)->shouldNotReceive('execute');
    $checkout = openMembershipCheckout(Service::factory()->membership(2)->inCategoryOf($class->service)->create(), $class);
    Booking::factory()->paid()->create(['schedule_id' => $class->id, 'booking_date' => '2026-10-14']);

    $checkout->call('pay')
        ->assertSet('selectionChanged', true)
        ->assertDispatched('timetable-refresh')
        ->assertSee('Kāds no izvēlētajiem laikiem vairs nav pieejams.')
        ->assertNoRedirect();

    expect(Membership::count())->toBe(0);
});

test('refuses sessions that do not match the membership', function () {
    $class = createMembershipCheckoutClass();
    $this->mock(CreateMembershipCheckoutSession::class)->shouldNotReceive('execute');

    openMembershipCheckout(Service::factory()->membership(3)->inCategoryOf($class->service)->create(), $class)
        ->call('pay')
        ->assertHasErrors('membership')
        ->assertNoRedirect();

    expect(Membership::count())->toBe(0);
});

test('frees the sessions when Stripe is unavailable', function () {
    $class = createMembershipCheckoutClass();
    $this->mock(CreateMembershipCheckoutSession::class)
        ->shouldReceive('execute')
        ->once()
        ->andThrow(new UnknownApiErrorException('Stripe is down'));

    openMembershipCheckout(Service::factory()->membership(2)->inCategoryOf($class->service)->create(), $class)
        ->call('pay')
        ->assertHasErrors('membership')
        ->assertNoRedirect();

    expect(Membership::count())->toBe(0);
    expect(Booking::count())->toBe(0);
});

test('shares the start limit with single bookings', function () {
    $class = createMembershipCheckoutClass();
    $this->mock(CreateMembershipCheckoutSession::class)->shouldNotReceive('execute');
    foreach (range(1, BookingStartLimiter::MAX_STARTS) as $start) {
        app(BookingStartLimiter::class)->hit();
    }

    openMembershipCheckout(Service::factory()->membership(2)->inCategoryOf($class->service)->create(), $class)
        ->call('pay')
        ->assertHasErrors('membership')
        ->assertSee('Pārāk daudz mēģinājumu.');

    expect(Membership::count())->toBe(0);
});

test('a membership that reached Stripe counts towards the start limit', function () {
    $class = createMembershipCheckoutClass();
    $this->mock(CreateMembershipCheckoutSession::class)
        ->shouldReceive('execute')
        ->andReturn(['url' => 'https://checkout.stripe.com/membership', 'session_id' => 'cs_test_123']);
    foreach (range(1, BookingStartLimiter::MAX_STARTS - 1) as $start) {
        app(BookingStartLimiter::class)->hit();
    }

    openMembershipCheckout(Service::factory()->membership(2)->inCategoryOf($class->service)->create(), $class)
        ->call('pay')
        ->assertRedirect('https://checkout.stripe.com/membership');

    expect(app(BookingStartLimiter::class)->tooManyStarts())->toBeTrue();
});
