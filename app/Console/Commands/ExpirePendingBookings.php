<?php

namespace App\Console\Commands;

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
     */
    public function handle(): int
    {
        $count = Booking::query()
            ->where('payment_status', PaymentStatus::Pending)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->delete();

        if ($count === 0) {
            $this->info('No expired pending bookings found.');

            return self::SUCCESS;
        }

        $this->info("Deleted {$count} expired pending booking(s).");

        return self::SUCCESS;
    }
}
