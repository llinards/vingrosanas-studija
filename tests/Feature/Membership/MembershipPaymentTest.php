<?php

use App\Enums\PaymentStatus;
use App\Mail\MembershipConfirmation;
use App\Mail\MembershipRefunded;
use App\Models\Booking;
use App\Models\Membership;
use App\Models\Schedule;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
});

test('membership success page displays details for paid membership', function () {
    $membership = Membership::factory()->paid()->create([
        'stripe_checkout_session_id' => 'cs_test_membership_123',
    ]);

    $this->get(route('membership.success', ['membership' => $membership, 'session_id' => 'cs_test_membership_123']))
        ->assertSuccessful()
        ->assertSee($membership->tierLabel());
});

test('membership success page shows processing for pending membership', function () {
    $membership = Membership::factory()->create([
        'stripe_checkout_session_id' => 'cs_test_membership_456',
    ]);

    $this->get(route('membership.success', ['membership' => $membership, 'session_id' => 'cs_test_membership_456']))
        ->assertSuccessful()
        ->assertSee(__('Maksājums tiek apstrādāts'));
});

test('membership success page rejects invalid session_id', function () {
    $membership = Membership::factory()->paid()->create([
        'stripe_checkout_session_id' => 'cs_test_123',
    ]);

    $this->get(route('membership.success', ['membership' => $membership, 'session_id' => 'wrong_id']))
        ->assertNotFound();
});

test('expire pending memberships command deletes expired memberships', function () {
    $expiredMembership = Membership::factory()->create([
        'payment_status' => PaymentStatus::Pending,
        'expires_at' => now()->subMinutes(5),
    ]);

    $activeMembership = Membership::factory()->create([
        'payment_status' => PaymentStatus::Pending,
        'expires_at' => now()->addMinutes(25),
    ]);

    $paidMembership = Membership::factory()->paid()->create();

    $this->artisan('memberships:expire-pending')
        ->assertSuccessful();

    $this->assertDatabaseMissing('memberships', ['id' => $expiredMembership->id]);
    $this->assertDatabaseHas('memberships', ['id' => $activeMembership->id]);
    $this->assertDatabaseHas('memberships', ['id' => $paidMembership->id]);
});

test('expire pending memberships command cascade deletes linked bookings', function () {
    $expiredMembership = Membership::factory()->create([
        'payment_status' => PaymentStatus::Pending,
        'expires_at' => now()->subMinutes(5),
    ]);

    $schedule = Schedule::factory()->create();

    $booking = Booking::factory()->create([
        'membership_id' => $expiredMembership->id,
        'schedule_id' => $schedule->id,
        'payment_status' => PaymentStatus::Paid,
    ]);

    $this->artisan('memberships:expire-pending')
        ->assertSuccessful();

    $this->assertDatabaseMissing('memberships', ['id' => $expiredMembership->id]);
    $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
});

test('stripe webhook handles membership checkout completed event', function () {
    $membership = Membership::factory()->create([
        'payment_status' => PaymentStatus::Pending,
        'stripe_checkout_session_id' => 'cs_test_mem_123',
        'expires_at' => now()->addMinutes(30),
    ]);

    $payload = json_encode([
        'id' => 'evt_test_123',
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'id' => 'cs_test_mem_123',
                'payment_intent' => 'pi_test_mem_456',
                'metadata' => [
                    'membership_id' => $membership->id,
                ],
            ],
        ],
    ]);

    $timestamp = time();
    $secret = config('services.stripe.webhook_secret');

    if (empty($secret)) {
        $this->markTestSkipped('Stripe webhook secret not configured');
    }

    $signedPayload = "{$timestamp}.{$payload}";
    $signature = hash_hmac('sha256', $signedPayload, $secret);

    $this->postJson(route('stripe.webhook'), json_decode($payload, true), [
        'Stripe-Signature' => "t={$timestamp},v1={$signature}",
    ])->assertSuccessful();

    $membership->refresh();

    expect($membership)
        ->payment_status->toBe(PaymentStatus::Paid)
        ->payment_reference->toBe('pi_test_mem_456');

    Mail::assertQueued(MembershipConfirmation::class);
});

test('stripe webhook handles membership refund event', function () {
    $membership = Membership::factory()->paid()->create([
        'payment_reference' => 'pi_test_mem_refund',
    ]);

    $schedule = Schedule::factory()->create();

    $booking = Booking::factory()->forMembership($membership)->create([
        'schedule_id' => $schedule->id,
    ]);

    $payload = json_encode([
        'id' => 'evt_test_refund',
        'type' => 'charge.refunded',
        'data' => [
            'object' => [
                'id' => 'ch_test_123',
                'payment_intent' => 'pi_test_mem_refund',
                'refunds' => [
                    'data' => [
                        ['id' => 're_test_mem_123'],
                    ],
                ],
            ],
        ],
    ]);

    $timestamp = time();
    $secret = config('services.stripe.webhook_secret');

    if (empty($secret)) {
        $this->markTestSkipped('Stripe webhook secret not configured');
    }

    $signedPayload = "{$timestamp}.{$payload}";
    $signature = hash_hmac('sha256', $signedPayload, $secret);

    $this->postJson(route('stripe.webhook'), json_decode($payload, true), [
        'Stripe-Signature' => "t={$timestamp},v1={$signature}",
    ])->assertSuccessful();

    $membership->refresh();
    $booking->refresh();

    expect($membership->payment_status)->toBe(PaymentStatus::Refunded);
    expect($booking->payment_status)->toBe(PaymentStatus::Refunded);

    Mail::assertQueued(MembershipRefunded::class);
});
