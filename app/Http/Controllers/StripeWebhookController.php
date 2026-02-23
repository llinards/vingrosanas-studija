<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Mail\BookingConfirmation;
use App\Mail\BookingRefunded;
use App\Mail\NewBookingNotification;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');
        $webhookSecret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $signature, $webhookSecret);
        } catch (SignatureVerificationException $e) {
            Log::warning('Stripe webhook signature verification failed', [
                'error' => $e->getMessage(),
            ]);

            return response('Invalid signature', 400);
        }

        return match ($event->type) {
            'checkout.session.completed' => $this->handleCheckoutSessionCompleted($event->data->object),
            'checkout.session.expired' => $this->handleCheckoutSessionExpired($event->data->object),
            'charge.refunded' => $this->handleChargeRefunded($event->data->object),
            default => response('Webhook received', 200),
        };
    }

    /**
     * Handle successful checkout session completion.
     */
    private function handleCheckoutSessionCompleted(object $session): Response
    {
        $bookingId = $session->metadata->booking_id ?? null;

        if (! $bookingId) {
            Log::warning('Stripe checkout session completed without booking_id metadata', [
                'session_id' => $session->id,
            ]);

            return response('Missing booking_id', 400);
        }

        $booking = Booking::find($bookingId);

        if (! $booking) {
            Log::warning('Booking not found for completed checkout session', [
                'booking_id' => $bookingId,
                'session_id' => $session->id,
            ]);

            return response('Booking not found', 404);
        }

        // Mark the booking as paid
        $booking->markAsPaid($session->payment_intent);

        // Load relationships for emails
        $booking->load('schedule.service.coach');

        // Send confirmation email to customer
        Mail::to($booking->email)->send(new BookingConfirmation($booking));

        // Send notification to coach
        $coachEmail = $booking->schedule->service->coach->email ?? null;
        if ($coachEmail) {
            Mail::to($coachEmail)->send(new NewBookingNotification($booking));
        }

        Log::info('Booking payment completed', [
            'booking_id' => $booking->id,
            'payment_intent' => $session->payment_intent,
        ]);

        return response('Payment processed', 200);
    }

    /**
     * Handle expired checkout session.
     */
    private function handleCheckoutSessionExpired(object $session): Response
    {
        $bookingId = $session->metadata->booking_id ?? null;

        if (! $bookingId) {
            return response('Missing booking_id', 400);
        }

        $booking = Booking::find($bookingId);

        if ($booking && $booking->isPendingPayment()) {
            // The scheduled cleanup job will handle deletion
            // We just log here for visibility
            Log::info('Checkout session expired', [
                'booking_id' => $booking->id,
                'session_id' => $session->id,
            ]);
        }

        return response('Session expiry noted', 200);
    }

    /**
     * Handle a charge refund event (e.g. refund initiated from Stripe dashboard).
     */
    private function handleChargeRefunded(object $charge): Response
    {
        $paymentIntent = $charge->payment_intent ?? null;

        if (! $paymentIntent) {
            Log::warning('Stripe charge.refunded without payment_intent', [
                'charge_id' => $charge->id ?? null,
            ]);

            return response('Missing payment_intent', 400);
        }

        $booking = Booking::where('payment_reference', $paymentIntent)->first();

        if (! $booking) {
            Log::warning('Booking not found for refunded charge', [
                'payment_intent' => $paymentIntent,
            ]);

            return response('Booking not found', 404);
        }

        // Already marked as refunded (e.g. refund initiated from admin panel)
        if ($booking->payment_status === PaymentStatus::Refunded) {
            return response('Already refunded', 200);
        }

        $refundId = $charge->refunds->data[0]->id ?? 're_unknown';

        $booking->markAsRefunded($refundId);

        $booking->load('schedule.service.coach');
        Mail::to($booking->email)->send(new BookingRefunded($booking));

        Log::info('Booking refunded via Stripe dashboard', [
            'booking_id' => $booking->id,
            'refund_id' => $refundId,
            'payment_intent' => $paymentIntent,
        ]);

        return response('Refund processed', 200);
    }
}
