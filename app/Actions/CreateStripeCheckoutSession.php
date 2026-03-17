<?php

namespace App\Actions;

use App\Models\Booking;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;

class CreateStripeCheckoutSession
{
    /**
     * Create a Stripe Checkout Session for a booking.
     *
     * @return array{url: string, session_id: string}
     *
     * @throws ApiErrorException
     */
    public function execute(Booking $booking, int $priceInCents): array
    {
        Stripe::setApiKey(config('cashier.secret'));

        $booking->loadMissing('schedule.service.coach');

        $serviceName = $booking->schedule->service->name;
        $coachName = $booking->schedule->service->coach?->name;
        $participantCount = $booking->participant_count;

        if ($coachName) {
            $productName = $participantCount > 1
                ? "{$serviceName} ({$coachName}) - {$participantCount} personas"
                : "{$serviceName} ({$coachName})";
        } else {
            $productName = $participantCount > 1
                ? "{$serviceName} - {$participantCount} personas"
                : $serviceName;
        }

        try {
            $session = Session::create([
                'mode' => 'payment',
                'line_items' => [[
                    'price_data' => [
                        'currency' => config('cashier.currency', 'eur'),
                        'product_data' => [
                            'name' => $productName,
                            'description' => $booking->booking_date->format('d.m.Y').' '.$booking->schedule->start_time,
                        ],
                        'unit_amount' => $priceInCents,
                    ],
                    'quantity' => 1,
                ]],
                'customer_email' => $booking->email,
                'success_url' => route('booking.success', ['booking' => $booking->id]).'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('home').'?booking_cancelled=1',
                'metadata' => [
                    'booking_id' => $booking->id,
                ],
                'expires_at' => now()->addMinutes(30)->timestamp,
            ]);
        } catch (ApiErrorException $e) {
            Log::error('Stripe checkout session creation failed', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        $booking->update([
            'stripe_checkout_session_id' => $session->id,
        ]);

        return [
            'url' => $session->url,
            'session_id' => $session->id,
        ];
    }
}
