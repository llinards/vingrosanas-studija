<?php

namespace App\Console\Commands;

use App\Actions\RetrieveStripeCheckoutUrl;
use App\Enums\PaymentStatus;
use App\Mail\BookingPaymentReminder;
use App\Mail\MembershipPaymentReminder;
use App\Models\Booking;
use App\Models\Membership;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendPaymentReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send payment reminder emails for pending bookings and memberships nearing expiry';

    /**
     * Execute the console command.
     */
    public function handle(RetrieveStripeCheckoutUrl $retrieveCheckoutUrl): int
    {
        $bookingCount = $this->sendBookingReminders($retrieveCheckoutUrl);
        $membershipCount = $this->sendMembershipReminders($retrieveCheckoutUrl);

        if ($bookingCount === 0 && $membershipCount === 0) {
            $this->info('No payment reminders to send.');

            return self::SUCCESS;
        }

        $this->info("Sent {$bookingCount} booking and {$membershipCount} membership payment reminder(s).");

        return self::SUCCESS;
    }

    private function sendBookingReminders(RetrieveStripeCheckoutUrl $retrieveCheckoutUrl): int
    {
        $bookings = Booking::query()
            ->where('payment_status', PaymentStatus::Pending)
            ->whereNotNull('expires_at')
            ->whereNull('payment_reminder_sent_at')
            ->whereNull('membership_id')
            ->where('expires_at', '>', now())
            ->where('expires_at', '<=', now()->addMinutes(10))
            ->get();

        $sent = 0;

        foreach ($bookings as $booking) {
            if (! $booking->stripe_checkout_session_id) {
                continue;
            }

            $checkoutUrl = $retrieveCheckoutUrl->execute($booking->stripe_checkout_session_id);

            if (! $checkoutUrl) {
                continue;
            }

            $booking->loadMissing('schedule.service.coach');

            Mail::to($booking->email)->send(new BookingPaymentReminder($booking, $checkoutUrl));

            $booking->update(['payment_reminder_sent_at' => now()]);

            Log::info('Payment reminder sent for booking', ['booking_id' => $booking->id]);

            $sent++;
        }

        return $sent;
    }

    private function sendMembershipReminders(RetrieveStripeCheckoutUrl $retrieveCheckoutUrl): int
    {
        $memberships = Membership::query()
            ->where('payment_status', PaymentStatus::Pending)
            ->whereNotNull('expires_at')
            ->whereNull('payment_reminder_sent_at')
            ->where('expires_at', '>', now())
            ->where('expires_at', '<=', now()->addMinutes(10))
            ->get();

        $sent = 0;

        foreach ($memberships as $membership) {
            if (! $membership->stripe_checkout_session_id) {
                continue;
            }

            $checkoutUrl = $retrieveCheckoutUrl->execute($membership->stripe_checkout_session_id);

            if (! $checkoutUrl) {
                continue;
            }

            $membership->loadMissing('bookings.schedule.service');

            Mail::to($membership->email)->send(new MembershipPaymentReminder($membership, $checkoutUrl));

            $membership->update(['payment_reminder_sent_at' => now()]);

            Log::info('Payment reminder sent for membership', ['membership_id' => $membership->id]);

            $sent++;
        }

        return $sent;
    }
}
