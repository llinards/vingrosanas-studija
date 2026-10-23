<?php

use App\Actions\CreateMembershipCheckoutSession;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Membership;
use App\Models\Schedule;
use App\Models\Service;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Stripe\Exception\UnknownApiErrorException;

/**
 * Create a membership-eligible class on Wednesdays. "Now" in these tests is
 * Monday 5 October 2026, 08:00, so its next occurrence is Wednesday 7 October 2026.
 *
 * @param  array<string, mixed>  $schedule
 */
function createMembershipEligibleClass(array $schedule = []): Schedule
{
    test()->travelTo(Carbon::parse('2026-10-05 08:00:00'));

    return Schedule::factory()->create(array_merge([
        'service_id' => Service::factory()->membershipEligible()->create()->id,
        'day_of_week' => 3,
        'start_time' => '18:00',
        'max_capacity' => 10,
    ], $schedule));
}

/**
 * Pick the given class for the given date in the membership session builder.
 */
function addMembershipSession(Testable $modal, Schedule $class, string $date): Testable
{
    return $modal
        ->set('session_service_type_id', $class->service->service_type_id)
        ->set('session_service_id', $class->service_id)
        ->set('session_date', $date)
        ->set('session_schedule_id', $class->id)
        ->call('addSession');
}

/**
 * Open the modal with a two-session membership chosen, both sessions picked and customer details filled in.
 */
function membershipReadyToPay(Service $membershipService, Schedule $class): Testable
{
    $modal = Livewire::test('booking-modal')
        ->set('selectedMembershipServiceId', $membershipService->id)
        ->set('step', 2);

    addMembershipSession($modal, $class, '2026-10-07');
    addMembershipSession($modal, $class, '2026-10-14');

    return $modal
        ->set('name', 'Anna')
        ->set('surname', 'Liepa')
        ->set('phone', '+371 20000000')
        ->set('email', 'anna@example.com');
}

test('offers the active membership products first', function () {
    createMembershipEligibleClass();
    Service::factory()->membership(4)->create(['name' => 'Mēneša abonements (4x)', 'price' => 4500]);
    Service::factory()->membership(9)->create(['name' => 'Vecais abonements', 'is_active' => false]);

    Livewire::test('booking-modal')
        ->assertSet('step', 1)
        ->assertSee('Mēneša abonements (4x)')
        ->assertSee('€45.00')
        ->assertDontSee('Vecais abonements');
});

test('requires a membership product before the sessions can be picked', function () {
    createMembershipEligibleClass();

    Livewire::test('booking-modal')
        ->call('nextStep')
        ->assertHasErrors('selectedMembershipServiceId')
        ->assertSet('step', 1);
});

test('walks from the membership product through the sessions and customer details to the confirmation', function () {
    $class = createMembershipEligibleClass();
    $membershipService = Service::factory()->membership(2)->inCategoryOf($class->service)->create(['name' => 'Mēneša abonements (2x)']);

    $modal = Livewire::test('booking-modal')
        ->set('selectedMembershipServiceId', $membershipService->id)
        ->call('nextStep')
        ->assertSet('step', 2);
    addMembershipSession($modal, $class, '2026-10-07')->assertSet('step', 2);
    addMembershipSession($modal, $class, '2026-10-14')->assertSet('step', 3);

    $modal
        ->set('name', 'Anna')
        ->set('surname', 'Liepa')
        ->set('phone', '+371 20000000')
        ->set('email', 'anna@example.com')
        ->call('nextStep')
        ->assertSet('step', 4)
        ->assertSee('Mēneša abonements (2x)')
        ->assertSee('Apmaksāt ar karti');
});

test('buying a membership creates an unpaid membership with its sessions waiting for payment and sends the customer to Stripe', function () {
    $class = createMembershipEligibleClass();
    $membershipService = Service::factory()->membership(2)->inCategoryOf($class->service)->create(['price' => 4500]);
    $this->mock(CreateMembershipCheckoutSession::class)
        ->shouldReceive('execute')
        ->once()
        ->andReturn(['url' => 'https://checkout.stripe.com/membership', 'session_id' => 'cs_test_123']);

    membershipReadyToPay($membershipService, $class)
        ->call('submitMembership')
        ->assertHasNoErrors()
        ->assertRedirect('https://checkout.stripe.com/membership');

    $membership = Membership::sole();
    expect($membership)
        ->service_id->toBe($membershipService->id)
        ->price->toBe(4500)
        ->sessions_total->toBe(2)
        ->name->toBe('Anna')
        ->surname->toBe('Liepa')
        ->phone->toBe('+371 20000000')
        ->email->toBe('anna@example.com')
        ->payment_status->toBe(PaymentStatus::Pending);
    expect($membership->expires_at->toDateTimeString())->toBe('2026-10-05 08:35:00');
    expect($membership->period_start->toDateString())->toBe('2026-10-07');
    expect($membership->period_end->toDateString())->toBe('2026-11-07');

    $sessions = Booking::orderBy('booking_date')->get();
    expect($sessions)->toHaveCount(2);
    expect($sessions->map(fn (Booking $booking) => $booking->booking_date->toDateString())->all())->toBe(['2026-10-07', '2026-10-14']);
    expect($sessions)->each(fn ($booking) => $booking
        ->membership_id->toBe($membership->id)
        ->schedule_id->toBe($class->id)
        ->participant_count->toBe(1)
        ->payment_status->toBe(PaymentStatus::Pending)
        ->email->toBe('anna@example.com')
        ->expires_at->toBeNull());
});

test('does not sell a membership when one of its sessions filled up meanwhile', function () {
    $class = createMembershipEligibleClass(['max_capacity' => 1]);
    $membershipService = Service::factory()->membership(2)->inCategoryOf($class->service)->create();
    $this->mock(CreateMembershipCheckoutSession::class)->shouldNotReceive('execute');
    $modal = membershipReadyToPay($membershipService, $class);
    Booking::factory()->paid()->create(['schedule_id' => $class->id, 'booking_date' => '2026-10-14']);

    $modal->call('submitMembership')
        ->assertHasErrors('sessions')
        ->assertSet('step', 2)
        ->assertNoRedirect();

    expect(Membership::count())->toBe(0);
    expect(Booking::count())->toBe(1);
});

test('returns to the customer details and frees the places when Stripe is unavailable', function () {
    $class = createMembershipEligibleClass();
    $membershipService = Service::factory()->membership(2)->inCategoryOf($class->service)->create();
    $this->mock(CreateMembershipCheckoutSession::class)
        ->shouldReceive('execute')
        ->once()
        ->andThrow(new UnknownApiErrorException('Stripe is down'));

    membershipReadyToPay($membershipService, $class)
        ->call('submitMembership')
        ->assertHasErrors('membership')
        ->assertSet('step', 3)
        ->assertNoRedirect();

    expect(Membership::count())->toBe(0);
    expect(Booking::count())->toBe(0);
});
