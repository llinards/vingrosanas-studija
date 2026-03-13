<?php

namespace App\Console\Commands;

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
     */
    public function handle(): int
    {
        $count = Membership::query()
            ->where('payment_status', PaymentStatus::Pending)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->delete();

        if ($count === 0) {
            $this->info('No expired pending memberships found.');

            return self::SUCCESS;
        }

        $this->info("Deleted {$count} expired pending membership(s).");

        return self::SUCCESS;
    }
}
