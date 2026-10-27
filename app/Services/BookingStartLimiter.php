<?php

namespace App\Services;

use Illuminate\Support\Facades\RateLimiter;

/**
 * Limits how many single bookings and memberships one visitor may start.
 *
 * Both checkouts share one counter, so a visitor cannot hold more places by
 * switching between them.
 */
class BookingStartLimiter
{
    public const MAX_STARTS = 5;

    public const WINDOW_SECONDS = 600;

    public function tooManyStarts(): bool
    {
        return RateLimiter::tooManyAttempts($this->key(), self::MAX_STARTS);
    }

    /**
     * Count a start that holds a place.
     */
    public function hit(): void
    {
        RateLimiter::hit($this->key(), self::WINDOW_SECONDS);
    }

    /**
     * Message telling the visitor how long to wait before trying again.
     */
    public function tooManyStartsMessage(): string
    {
        $minutes = max(1, (int) ceil(RateLimiter::availableIn($this->key()) / 60));

        return $minutes === 1
            ? __('Pārāk daudz mēģinājumu. Lūdzu, mēģiniet vēlreiz pēc 1 minūtes.')
            : __('Pārāk daudz mēģinājumu. Lūdzu, mēģiniet vēlreiz pēc :minutes minūtēm.', ['minutes' => $minutes]);
    }

    /**
     * Key under which the starts of the current visitor are counted.
     */
    private function key(): string
    {
        return 'booking-starts:'.request()->ip();
    }
}
