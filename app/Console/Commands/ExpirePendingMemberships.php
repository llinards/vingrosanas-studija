<?php

namespace App\Console\Commands;

use App\Actions\ConfirmMembershipPayment;
use App\Actions\RetrieveStripeCheckoutSessionState;
use App\Enums\PaymentStatus;
use App\Models\Membership;
use Illuminate\Console\Command;

class ExpirePendingMemberships extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'memberships:expire-pending';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete pending memberships that have expired without payment';

    /**
     * Execute the console command.
     *
     * Before an overdue membership is deleted (its sessions go with it), Stripe
     * is asked what became of its payment page: a payment whose confirmation
     * never reached us still confirms the membership instead of losing it.
     */
    public function handle(RetrieveStripeCheckoutSessionState $retrieveSessionState, ConfirmMembershipPayment $confirmPayment): int
    {
        $memberships = Membership::query()
            ->where('payment_status', PaymentStatus::Pending)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        $deleted = 0;
        $confirmed = 0;

        foreach ($memberships as $membership) {
            if (! $membership->stripe_checkout_session_id) {
                $membership->delete();
                $deleted++;

                continue;
            }

            $state = $retrieveSessionState->execute($membership->stripe_checkout_session_id);

            if ($state === null) {
                continue;
            }

            if ($state['payment_status'] === 'paid' && $state['payment_intent']) {
                $confirmPayment->execute($membership, $state['payment_intent']);
                $confirmed++;

                continue;
            }

            if ($state['status'] === 'expired') {
                $membership->delete();
                $deleted++;
            }
        }

        if ($deleted === 0 && $confirmed === 0) {
            $this->info('No expired pending memberships found.');

            return self::SUCCESS;
        }

        $this->info("Deleted {$deleted} expired pending membership(s), confirmed {$confirmed} paid membership(s).");

        return self::SUCCESS;
    }
}
