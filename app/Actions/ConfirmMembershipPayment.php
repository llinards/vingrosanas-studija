<?php

namespace App\Actions;

use App\Mail\MembershipConfirmation;
use App\Mail\NewMembershipNotification;
use App\Models\Membership;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ConfirmMembershipPayment
{
    /**
     * Mark a membership and its waiting sessions as paid, then tell the customer and each coach.
     */
    public function execute(Membership $membership, string $paymentReference): void
    {
        DB::transaction(function () use ($membership, $paymentReference) {
            $membership->markAsPaid($paymentReference);
            $membership->markSessionsAsPaid();
        });

        $membership->load('bookings.schedule.service.coach');

        Mail::to($membership->email)->send(new MembershipConfirmation($membership));

        $coachEmails = $membership->bookings
            ->map(fn ($booking) => $booking->schedule->service->coach->email ?? null)
            ->filter()
            ->unique();

        foreach ($coachEmails as $coachEmail) {
            Mail::to($coachEmail)->send(new NewMembershipNotification($membership));
        }

        Log::info('Membership payment completed', [
            'membership_id' => $membership->id,
            'payment_intent' => $paymentReference,
        ]);
    }
}
