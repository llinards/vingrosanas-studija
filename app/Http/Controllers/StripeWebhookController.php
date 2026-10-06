<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Mail\BookingConfirmation;
use App\Mail\BookingRefunded;
use App\Mail\ExpiredBookingPaymentRefunded;
use App\Mail\ExpiredBookingPaymentRefundedNotification;
use App\Mail\MembershipConfirmation;
use App\Mail\MembershipRefunded;
use App\Mail\NewBookingNotification;
use App\Mail\NewMembershipNotification;
use App\Models\Booking;
use App\Models\Membership;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Refund;
use Stripe\Stripe;
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
        $membershipId = $session->metadata->membership_id ?? null;

        if ($membershipId) {
            return $this->handleMembershipCheckoutCompleted($session, $membershipId);
        }

        $bookingId = $session->metadata->booking_id ?? null;

        if (! $bookingId) {
            Log::warning('Stripe checkout session completed without booking_id or membership_id metadata', [
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

            return $this->refundPaymentForMissingBooking($session);
        }

        // Skip if already processed (idempotency guard for webhook retries)
        if ($booking->payment_status === PaymentStatus::Paid) {
            return response('Already processed', 200);
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
     * Handle successful membership checkout completion.
     */
    private function handleMembershipCheckoutCompleted(object $session, string $membershipId): Response
    {
        $membership = Membership::find($membershipId);

        if (! $membership) {
            Log::warning('Membership not found for completed checkout session', [
                'membership_id' => $membershipId,
                'session_id' => $session->id,
            ]);

            return response('Membership not found', 404);
        }

        // Skip if already processed (idempotency guard for webhook retries)
        if ($membership->payment_status === PaymentStatus::Paid) {
            return response('Already processed', 200);
        }

        $membership->markAsPaid($session->payment_intent);

        $membership->load('bookings.schedule.service.coach');

        Mail::to($membership->email)->send(new MembershipConfirmation($membership));

        // Send notification to each unique coach
        $coachEmails = $membership->bookings
            ->map(fn ($booking) => $booking->schedule->service->coach->email ?? null)
            ->filter()
            ->unique();

        foreach ($coachEmails as $coachEmail) {
            Mail::to($coachEmail)->send(new NewMembershipNotification($membership));
        }

        Log::info('Membership payment completed', [
            'membership_id' => $membership->id,
            'payment_intent' => $session->payment_intent,
        ]);

        return response('Membership payment processed', 200);
    }

    /**
     * Handle expired checkout session.
     */
    private function handleCheckoutSessionExpired(object $session): Response
    {
        $membershipId = $session->metadata->membership_id ?? null;

        if ($membershipId) {
            $membership = Membership::find($membershipId);

            if ($membership && $membership->isPendingPayment()) {
                Log::info('Membership checkout session expired', [
                    'membership_id' => $membership->id,
                    'session_id' => $session->id,
                ]);
            }

            return response('Session expiry noted', 200);
        }

        $bookingId = $session->metadata->booking_id ?? null;

        if (! $bookingId) {
            return response('Missing booking_id', 400);
        }

        $booking = Booking::find($bookingId);

        // The payment page can no longer be paid, so the place is free again.
        if ($booking
            && $booking->payment_status === PaymentStatus::Pending
            && $booking->stripe_checkout_session_id === $session->id) {
            $booking->delete();

            Log::info('Unpaid booking released after checkout session expired', [
                'booking_id' => $bookingId,
                'session_id' => $session->id,
            ]);
        }

        return response('Session expiry noted', 200);
    }

    /**
     * Refund a payment whose booking no longer exists and tell the customer and the studio.
     *
     * Without the booking the place cannot be confirmed. A failed refund is
     * answered with an error so that Stripe delivers the event again.
     */
    private function refundPaymentForMissingBooking(object $session): Response
    {
        $paymentIntent = $session->payment_intent ?? null;

        if (! $paymentIntent) {
            return response('Booking not found', 404);
        }

        $refundMarker = $this->expiredBookingRefundMarker($paymentIntent);

        if (Cache::has($refundMarker)) {
            return response('Already refunded', 200);
        }

        Stripe::setApiKey(config('cashier.secret'));

        try {
            Refund::create(
                ['payment_intent' => $paymentIntent],
                ['idempotency_key' => 'expired-booking-refund-'.$session->id],
            );
        } catch (ApiErrorException $e) {
            Log::error('Refund failed for a payment without a booking', [
                'session_id' => $session->id,
                'payment_intent' => $paymentIntent,
                'error' => $e->getMessage(),
            ]);

            return response('Refund failed', 500);
        }

        if (! Cache::add($refundMarker, true, now()->addDays(7))) {
            return response('Already refunded', 200);
        }

        $customerEmail = $session->customer_details->email ?? $session->customer_email ?? null;
        $amountInCents = (int) ($session->amount_total ?? 0);

        if ($customerEmail) {
            Mail::to($customerEmail)->send(new ExpiredBookingPaymentRefunded($amountInCents));
        }

        Mail::to(SiteSetting::getValue('contact', 'email') ?? config('mail.contact_email'))->send(
            new ExpiredBookingPaymentRefundedNotification($customerEmail, $amountInCents, $paymentIntent),
        );

        Log::warning('Payment refunded because its booking no longer exists', [
            'session_id' => $session->id,
            'payment_intent' => $paymentIntent,
            'customer_email' => $customerEmail,
        ]);

        return response('Payment refunded: booking no longer exists', 200);
    }

    /**
     * Cache key remembering that a payment without a booking was refunded automatically.
     */
    private function expiredBookingRefundMarker(string $paymentIntent): string
    {
        return 'expired-booking-refund:'.$paymentIntent;
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

        // Check if this is a membership refund
        $membership = Membership::where('payment_reference', $paymentIntent)->first();

        if ($membership) {
            return $this->handleMembershipRefund($charge, $membership, $paymentIntent);
        }

        // Otherwise, check for a booking refund
        $booking = Booking::where('payment_reference', $paymentIntent)->first();

        if (! $booking) {
            if (Cache::has($this->expiredBookingRefundMarker($paymentIntent))) {
                return response('Refund of a payment without a booking noted', 200);
            }

            Log::warning('Booking or membership not found for refunded charge', [
                'payment_intent' => $paymentIntent,
            ]);

            return response('Record not found', 404);
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

    /**
     * Handle a membership refund.
     */
    private function handleMembershipRefund(object $charge, Membership $membership, string $paymentIntent): Response
    {
        if ($membership->payment_status === PaymentStatus::Refunded) {
            return response('Already refunded', 200);
        }

        $refundId = $charge->refunds->data[0]->id ?? 're_unknown';

        DB::transaction(function () use ($membership, $refundId) {
            $membership->markAsRefunded($refundId);

            // Mark all linked bookings as refunded
            $membership->bookings()->update([
                'payment_status' => PaymentStatus::Refunded,
                'refund_reference' => $refundId,
                'refunded_at' => now(),
            ]);
        });

        Mail::to($membership->email)->send(new MembershipRefunded($membership));

        Log::info('Membership refunded via Stripe dashboard', [
            'membership_id' => $membership->id,
            'refund_id' => $refundId,
            'payment_intent' => $paymentIntent,
        ]);

        return response('Membership refund processed', 200);
    }
}
