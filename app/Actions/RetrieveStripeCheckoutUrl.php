<?php

namespace App\Actions;

use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;

class RetrieveStripeCheckoutUrl
{
    /**
     * Retrieve the checkout URL for a Stripe session.
     */
    public function execute(string $sessionId): ?string
    {
        Stripe::setApiKey(config('cashier.secret'));

        try {
            $session = Session::retrieve($sessionId);

            return $session->url;
        } catch (ApiErrorException $e) {
            Log::warning('Failed to retrieve Stripe checkout session for reminder', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
