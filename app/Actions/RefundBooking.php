<?php

namespace App\Actions;

use App\Exceptions\RefundNotAllowedException;
use App\Mail\BookingRefunded;
use App\Mail\BookingRefundedCoach;
use App\Models\Booking;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Stripe\Exception\ApiErrorException;
use Stripe\Refund;
use Stripe\Stripe;

class RefundBooking
{
    /**
     * Process a full refund for the given booking via Stripe.
     *
     * @throws RefundNotAllowedException
     * @throws ApiErrorException
     */
    public function execute(Booking $booking, bool $notifyCoach = false): void
    {
        $booking->loadMissing('schedule.service.coach');

        if (! $booking->isRefundable()) {
            throw new RefundNotAllowedException($booking);
        }

        Stripe::setApiKey(config('cashier.secret'));

        try {
            $refund = Refund::create([
                'payment_intent' => $booking->payment_reference,
            ]);
        } catch (ApiErrorException $e) {
            Log::error('Stripe refund failed', [
                'booking_id' => $booking->id,
                'payment_intent' => $booking->payment_reference,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        $booking->markAsRefunded($refund->id);

        Mail::to($booking->email)->send(new BookingRefunded($booking));

        if ($notifyCoach) {
            $service = $booking->schedule->service;
            $coachEmail = $service->coach->email ?? null;

            if ($service->notify_coach_on_cancellation && $coachEmail) {
                Mail::to($coachEmail)->send(new BookingRefundedCoach($booking));
            }
        }

        Log::info('Booking refunded', [
            'booking_id' => $booking->id,
            'refund_id' => $refund->id,
            'payment_intent' => $booking->payment_reference,
        ]);
    }
}
