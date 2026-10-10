<?php

use App\Actions\CreateStripeCheckoutSession;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Coach;
use App\Models\Schedule;
use App\Models\Service;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Stripe\Exception\UnknownApiErrorException;

/**
 * Create a class on Wednesdays at 18:00. "Now" in these tests is Monday 5 October 2026, 09:00,
 * so the class can be booked for Wednesday 7 October 2026.
 *
 * @param  array<string, mixed>  $service
 * @param  array<string, mixed>  $schedule
 */
function createClassForCheckout(array $service = [], array $schedule = []): Schedule
{
    test()->travelTo(Carbon::parse('2026-10-05 09:00:00'));

    return Schedule::factory()->create(array_merge([
        'service_id' => Service::factory()->create($service)->id,
        'day_of_week' => 3,
        'start_time' => '18:00',
        'max_capacity' => 10,
    ], $schedule));
}

/**
 * Open the checkout for the class on 7 October with valid customer details filled in.
 */
function openCheckoutWithCustomer(Schedule $class): Testable
{
    return Livewire::test('booking.booking-checkout')
        ->call('open', $class->id, '2026-10-07')
        ->set('name', 'Anna')
        ->set('surname', 'Liepa')
        ->set('phone', '+371 20000000')
        ->set('email', 'anna@example.com');
}

/**
 * Replace the Stripe call with a double that expects to be asked once for the given price.
 */
function expectStripeCheckoutFor(int $priceInCents): void
{
    test()->mock(CreateStripeCheckoutSession::class)
        ->shouldReceive('execute')
        ->once()
        ->withArgs(fn (Booking $booking, int $price) => $price === $priceInCents)
        ->andReturn(['url' => 'https://checkout.stripe.com/test', 'session_id' => 'cs_test_123']);
}

test('opens with the details of the chosen class', function () {
    $coach = Coach::factory()->create(['name' => 'Elīna Nagle']);
    $class = createClassForCheckout(['name' => 'Fusion Pilates', 'coach_id' => $coach->id, 'price' => 1500, 'duration_minutes' => 45]);

    Livewire::test('booking.booking-checkout')
        ->call('open', $class->id, '2026-10-07')
        ->assertSet('scheduleId', $class->id)
        ->assertSet('date', '2026-10-07')
        ->assertSee('Fusion Pilates')
        ->assertSee('Elīna Nagle')
        ->assertSee('07.10.2026')
        ->assertSee('18:00')
        ->assertDontSee('18:45')
        ->assertSee('€15.00')
        ->assertSee('Apmaksāt ar karti');
});

test('does not offer payment for a class that can no longer be booked', function () {
    $class = createClassForCheckout(schedule: ['max_capacity' => 1]);
    Booking::factory()->paid()->create(['schedule_id' => $class->id, 'booking_date' => '2026-10-07']);

    Livewire::test('booking.booking-checkout')
        ->call('open', $class->id, '2026-10-07')
        ->assertSet('scheduleId', null)
        ->assertSee('Šis laiks vairs nav pieejams.')
        ->assertDontSee('Apmaksāt ar karti')
        ->assertDispatched('timetable-refresh');
});

test('creates an unpaid booking and sends the customer to Stripe for the class price', function () {
    Mail::fake();
    $class = createClassForCheckout(['price' => 1500]);
    expectStripeCheckoutFor(1500);

    openCheckoutWithCustomer($class)
        ->call('pay')
        ->assertHasNoErrors()
        ->assertRedirect('https://checkout.stripe.com/test');

    $booking = Booking::sole();
    expect($booking)
        ->schedule_id->toBe($class->id)
        ->name->toBe('Anna')
        ->surname->toBe('Liepa')
        ->phone->toBe('+371 20000000')
        ->email->toBe('anna@example.com')
        ->participant_count->toBe(1)
        ->payment_status->toBe(PaymentStatus::Pending);
    expect($booking->booking_date->toDateString())->toBe('2026-10-07');
    expect($booking->expires_at->toDateTimeString())->toBe('2026-10-05 09:30:00');
    Mail::assertNothingOutgoing();
});

test('requires the customer details before paying', function (string $field, string $rule, string $message) {
    $class = createClassForCheckout();

    openCheckoutWithCustomer($class)
        ->set($field, '')
        ->call('pay')
        ->assertHasErrors([$field => $rule])
        ->assertSee($message)
        ->assertNoRedirect();

    expect(Booking::count())->toBe(0);
})->with([
    'name' => ['name', 'required', 'Vārds ir obligāts.'],
    'surname' => ['surname', 'required', 'Uzvārds ir obligāts.'],
    'phone' => ['phone', 'required', 'Tālrunis ir obligāts.'],
    'email' => ['email', 'required', 'E-pasts ir obligāts.'],
]);

test('rejects an invalid email address', function () {
    $class = createClassForCheckout();

    openCheckoutWithCustomer($class)
        ->set('email', 'not-an-email')
        ->call('pay')
        ->assertHasErrors(['email' => 'email'])
        ->assertSee('E-pastam jābūt derīgai e-pasta adresei.');

    expect(Booking::count())->toBe(0);
});

test('offers the group sizes of an individual session that fit it', function () {
    $class = createClassForCheckout(['is_exclusive' => true, 'price' => 3500], ['max_capacity' => 2]);
    $class->service->priceTiers()->createMany([
        ['participant_count' => 1, 'price' => 3500],
        ['participant_count' => 2, 'price' => 5000],
        ['participant_count' => 3, 'price' => 6000],
    ]);

    Livewire::test('booking.booking-checkout')
        ->call('open', $class->id, '2026-10-07')
        ->assertSee('1 persona')
        ->assertSee('2 personas')
        ->assertDontSee('3 personas');
});

test('does not ask for a group size in a group class', function () {
    $class = createClassForCheckout();

    Livewire::test('booking.booking-checkout')
        ->call('open', $class->id, '2026-10-07')
        ->assertDontSee('Dalībnieku skaits');
});

test('charges the price of the chosen group size for an individual session', function () {
    $class = createClassForCheckout(['is_exclusive' => true, 'price' => 3500], ['max_capacity' => 2]);
    $class->service->priceTiers()->createMany([
        ['participant_count' => 1, 'price' => 3500],
        ['participant_count' => 2, 'price' => 5000],
    ]);
    expectStripeCheckoutFor(5000);

    openCheckoutWithCustomer($class)
        ->set('participantCount', 2)
        ->assertSee('€50.00')
        ->call('pay')
        ->assertRedirect('https://checkout.stripe.com/test');

    expect(Booking::sole()->participant_count)->toBe(2);
});

test('rejects a group size that is not offered', function () {
    $class = createClassForCheckout(['price' => 1500]);

    openCheckoutWithCustomer($class)
        ->set('participantCount', 4)
        ->call('pay')
        ->assertHasErrors('participantCount')
        ->assertNoRedirect();

    expect(Booking::count())->toBe(0);
});

test('tells the customer when the class filled up before they paid', function () {
    $class = createClassForCheckout(schedule: ['max_capacity' => 1]);
    $checkout = openCheckoutWithCustomer($class);
    Booking::factory()->paid()->create(['schedule_id' => $class->id, 'booking_date' => '2026-10-07']);

    $checkout->call('pay')
        ->assertHasErrors('booking')
        ->assertSee('Šis laiks vairs nav pieejams.')
        ->assertNoRedirect()
        ->assertDispatched('timetable-refresh');

    expect(Booking::count())->toBe(1);
});

test('releases the place again when Stripe is unavailable', function () {
    $class = createClassForCheckout();
    $this->mock(CreateStripeCheckoutSession::class)
        ->shouldReceive('execute')
        ->once()
        ->andThrow(new UnknownApiErrorException('Stripe is down'));

    openCheckoutWithCustomer($class)
        ->call('pay')
        ->assertHasErrors('booking')
        ->assertSee('Maksājuma sistēma pašlaik nav pieejama. Lūdzu, mēģiniet vēlreiz.')
        ->assertNoRedirect();

    expect(Booking::count())->toBe(0);
});

test('lets the customer try again after Stripe was unavailable', function () {
    $class = createClassForCheckout(['is_exclusive' => true, 'price' => 3500]);
    $stripe = $this->mock(CreateStripeCheckoutSession::class);
    $stripe->shouldReceive('execute')->once()->andThrow(new UnknownApiErrorException('Stripe is down'));
    $stripe->shouldReceive('execute')->once()->andReturn(['url' => 'https://checkout.stripe.com/test', 'session_id' => 'cs_test_123']);

    openCheckoutWithCustomer($class)
        ->call('pay')
        ->call('pay')
        ->assertRedirect('https://checkout.stripe.com/test');

    expect(Booking::count())->toBe(1);
});

test('creates only one booking when payment is submitted twice', function () {
    $class = createClassForCheckout(['price' => 1500]);
    expectStripeCheckoutFor(1500);

    openCheckoutWithCustomer($class)
        ->call('pay')
        ->call('pay');

    expect(Booking::count())->toBe(1);
});

test('does not create a booking when no class was opened', function () {
    createClassForCheckout();

    Livewire::test('booking.booking-checkout')
        ->set('name', 'Anna')
        ->set('surname', 'Liepa')
        ->set('phone', '+371 20000000')
        ->set('email', 'anna@example.com')
        ->call('pay')
        ->assertNoRedirect();

    expect(Booking::count())->toBe(0);
});

test('starts again with the smallest group size when another class is opened', function () {
    $class = createClassForCheckout(['is_exclusive' => true, 'price' => 3500], ['max_capacity' => 2]);
    $class->service->priceTiers()->createMany([
        ['participant_count' => 1, 'price' => 3500],
        ['participant_count' => 2, 'price' => 5000],
    ]);

    Livewire::test('booking.booking-checkout')
        ->call('open', $class->id, '2026-10-07')
        ->set('participantCount', 2)
        ->call('open', $class->id, '2026-10-14')
        ->assertSet('participantCount', 1)
        ->assertSet('date', '2026-10-14');
});

test('charges exactly the price recorded on the booking', function () {
    $class = createClassForCheckout(['is_exclusive' => true, 'price' => 3500], ['max_capacity' => 2]);
    $class->service->priceTiers()->createMany([
        ['participant_count' => 1, 'price' => 3500],
        ['participant_count' => 2, 'price' => 5000],
    ]);
    $this->mock(CreateStripeCheckoutSession::class)
        ->shouldReceive('execute')
        ->once()
        ->withArgs(fn (Booking $booking, int $price) => $booking->price === 5000 && $price === 5000)
        ->andReturn(['url' => 'https://checkout.stripe.com/test', 'session_id' => 'cs_test_123']);

    openCheckoutWithCustomer($class)
        ->set('participantCount', 2)
        ->call('pay')
        ->assertRedirect('https://checkout.stripe.com/test');
});

/**
 * Let Stripe open a payment page for every booking start in the test.
 */
function letStripeOpenEveryCheckout(): void
{
    test()->mock(CreateStripeCheckoutSession::class)
        ->shouldReceive('execute')
        ->andReturn(['url' => 'https://checkout.stripe.com/test', 'session_id' => 'cs_test_123']);
}

test('refuses a sixth booking start by the same visitor within ten minutes', function () {
    $class = createClassForCheckout();
    letStripeOpenEveryCheckout();
    foreach (range(1, 5) as $start) {
        openCheckoutWithCustomer($class)->call('pay')->assertRedirect('https://checkout.stripe.com/test');
    }
    $this->travel(4)->minutes();

    openCheckoutWithCustomer($class)
        ->call('pay')
        ->assertHasErrors('booking')
        ->assertSee('Pārāk daudz mēģinājumu. Lūdzu, mēģiniet vēlreiz pēc 6 minūtēm.')
        ->assertNoRedirect();

    expect(Booking::count())->toBe(5);
});

test('lets the visitor start bookings again once ten minutes have passed', function () {
    $class = createClassForCheckout();
    letStripeOpenEveryCheckout();
    foreach (range(1, 5) as $start) {
        openCheckoutWithCustomer($class)->call('pay');
    }
    $this->travel(10)->minutes();
    $this->travel(1)->seconds();

    openCheckoutWithCustomer($class)
        ->call('pay')
        ->assertHasNoErrors()
        ->assertRedirect('https://checkout.stripe.com/test');
});

test('does not count an attempt with invalid customer details towards the limit', function () {
    $class = createClassForCheckout();
    letStripeOpenEveryCheckout();
    foreach (range(1, 6) as $attempt) {
        openCheckoutWithCustomer($class)->set('name', '')->call('pay')->assertHasErrors('name');
    }

    foreach (range(1, 5) as $start) {
        openCheckoutWithCustomer($class)->call('pay')->assertRedirect('https://checkout.stripe.com/test');
    }
});

test('does not count an attempt for a class that filled up towards the limit', function () {
    $full = createClassForCheckout(schedule: ['max_capacity' => 1]);
    $class = createClassForCheckout();
    letStripeOpenEveryCheckout();
    $attemptOnFullClass = openCheckoutWithCustomer($full);
    Booking::factory()->paid()->create(['schedule_id' => $full->id, 'booking_date' => '2026-10-07']);
    $attemptOnFullClass->call('pay')->assertHasErrors('booking');

    foreach (range(1, 5) as $start) {
        openCheckoutWithCustomer($class)->call('pay')->assertRedirect('https://checkout.stripe.com/test');
    }
});

test('does not count a start that Stripe could not open towards the limit', function () {
    $class = createClassForCheckout();
    $stripe = $this->mock(CreateStripeCheckoutSession::class);
    $stripe->shouldReceive('execute')->once()->andThrow(new UnknownApiErrorException('Stripe is down'));
    $stripe->shouldReceive('execute')->andReturn(['url' => 'https://checkout.stripe.com/test', 'session_id' => 'cs_test_123']);
    openCheckoutWithCustomer($class)->call('pay')->assertHasErrors('booking');

    foreach (range(1, 5) as $start) {
        openCheckoutWithCustomer($class)->call('pay')->assertRedirect('https://checkout.stripe.com/test');
    }
});

test('does not hold the booking starts of another visitor against this one', function () {
    $class = createClassForCheckout();
    letStripeOpenEveryCheckout();
    // Another visitor, at a different address, has used up their five starts.
    foreach (range(1, 5) as $start) {
        RateLimiter::hit('booking-starts:203.0.113.10', 600);
    }

    openCheckoutWithCustomer($class)
        ->call('pay')
        ->assertHasNoErrors()
        ->assertRedirect('https://checkout.stripe.com/test');
});
