<?php

namespace App\Actions;

use App\Models\Membership;
use Stripe\Checkout\Session;
use Stripe\Stripe;

class CreateMembershipCheckoutSession
{
    /**
     * Create a Stripe Checkout Session for a membership.
     *
     * @return array{url: string, session_id: string}
     */
    public function execute(Membership $membership): array
    {
        Stripe::setApiKey(config('cashier.secret'));

        $membership->loadMissing(['bookings.schedule.service', 'service']);

        $sessionDetails = $membership->bookings
            ->map(fn ($booking) => $booking->booking_date->format('d.m.Y')
                .' '.substr($booking->schedule->start_time, 0, 5)
                .' - '.$booking->schedule->service->name)
            ->join(', ');

        $session = Session::create([
            'mode' => 'payment',
            'line_items' => [[
                'price_data' => [
                    'currency' => config('cashier.currency', 'eur'),
                    'product_data' => [
                        'name' => 'Abonements - '.$membership->tierLabel(),
                        'description' => $sessionDetails,
                    ],
                    'unit_amount' => $membership->price,
                ],
                'quantity' => 1,
            ]],
            'customer_email' => $membership->email,
            'success_url' => route('membership.success', ['membership' => $membership->id]).'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('home').'?membership_cancelled=1',
            'metadata' => [
                'membership_id' => $membership->id,
            ],
            'expires_at' => now()->addMinutes(30)->timestamp,
        ]);

        $membership->update([
            'stripe_checkout_session_id' => $session->id,
        ]);

        return [
            'url' => $session->url,
            'session_id' => $session->id,
        ];
    }
}
