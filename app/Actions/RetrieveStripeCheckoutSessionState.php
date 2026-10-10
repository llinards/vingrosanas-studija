<?php

namespace App\Actions;

use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;

class RetrieveStripeCheckoutSessionState
{
    /**
     * Ask Stripe what became of a checkout session.
     *
     * Returns null when Stripe cannot be reached, so the caller can try again later.
     *
     * @return array{status: string, payment_status: string, payment_intent: string|null}|null
     */
    public function execute(string $sessionId): ?array
    {
        Stripe::setApiKey(config('cashier.secret'));

        try {
            $session = Session::retrieve($sessionId);
        } catch (ApiErrorException $e) {
            Log::warning('Failed to retrieve Stripe checkout session state', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        return [
            'status' => $session->status,
            'payment_status' => $session->payment_status,
            'payment_intent' => $session->payment_intent,
        ];
    }
}
