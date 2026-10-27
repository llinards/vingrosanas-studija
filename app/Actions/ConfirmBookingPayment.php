<?php

namespace App\Actions;

use App\Mail\BookingConfirmation;
use App\Mail\NewBookingNotification;
use App\Models\Booking;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ConfirmBookingPayment
{
    /**
     * Mark a booking as paid and tell the customer and the coach.
     *
     * A free booking has no payment, so its reference is null.
     */
    public function execute(Booking $booking, ?string $paymentReference): void
    {
        $booking->markAsPaid($paymentReference);

        $booking->load('schedule.service.coach');

        Mail::to($booking->email)->send(new BookingConfirmation($booking));

        $coachEmail = $booking->schedule->service->coach->email ?? null;

        if ($coachEmail) {
            Mail::to($coachEmail)->send(new NewBookingNotification($booking));
        }

        Log::info('Booking payment completed', [
            'booking_id' => $booking->id,
            'payment_intent' => $paymentReference,
        ]);
    }
}
