<?php

namespace App\Console\Commands;

use App\Actions\ConfirmBookingPayment;
use App\Actions\RetrieveStripeCheckoutSessionState;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use Illuminate\Console\Command;

class ExpirePendingBookings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bookings:expire-pending';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete pending bookings that have expired without payment';

    /**
     * Execute the console command.
     *
     * Before an overdue booking is deleted, Stripe is asked what became of its
     * payment page: a payment whose confirmation never reached us still
     * confirms the booking instead of losing it.
     */
    public function handle(RetrieveStripeCheckoutSessionState $retrieveSessionState, ConfirmBookingPayment $confirmPayment): int
    {
        $bookings = Booking::query()
            ->where('payment_status', PaymentStatus::Pending)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        $deleted = 0;
        $confirmed = 0;

        foreach ($bookings as $booking) {
            if (! $booking->stripe_checkout_session_id) {
                $booking->delete();
                $deleted++;

                continue;
            }

            $state = $retrieveSessionState->execute($booking->stripe_checkout_session_id);

            if ($state === null) {
                continue;
            }

            if ($state['payment_status'] === 'paid' && $state['payment_intent']) {
                $confirmPayment->execute($booking, $state['payment_intent']);
                $confirmed++;

                continue;
            }

            if ($state['status'] === 'expired') {
                $booking->delete();
                $deleted++;
            }
        }

        if ($deleted === 0 && $confirmed === 0) {
            $this->info('No expired pending bookings found.');

            return self::SUCCESS;
        }

        $this->info("Deleted {$deleted} expired pending booking(s), confirmed {$confirmed} paid booking(s).");

        return self::SUCCESS;
    }
}
