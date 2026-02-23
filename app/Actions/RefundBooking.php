<?php

namespace App\Actions;

use App\Exceptions\RefundNotAllowedException;
use App\Mail\BookingRefunded;
use App\Models\Booking;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Stripe\Stripe;

class RefundBooking
{
    /**
     * Process a full refund for the given booking via Stripe.
     *
     * @throws RefundNotAllowedException
     */
    public function execute(Booking $booking): void
    {
        $booking->loadMissing('schedule.service.coach');

        if (! $booking->isRefundable()) {
            throw new RefundNotAllowedException($booking);
        }

        Stripe::setApiKey(config('cashier.secret'));

        $refund = \Stripe\Refund::create([
            'payment_intent' => $booking->payment_reference,
        ]);

        $booking->markAsRefunded($refund->id);

        Mail::to($booking->email)->send(new BookingRefunded($booking));

        Log::info('Booking refunded', [
            'booking_id' => $booking->id,
            'refund_id' => $refund->id,
            'payment_intent' => $booking->payment_reference,
        ]);
    }
}
