<?php

namespace App\Actions;

use App\Enums\PaymentStatus;
use App\Enums\SlotStatus;
use App\Exceptions\InvalidMembershipSelectionException;
use App\Exceptions\SlotUnavailableException;
use App\Models\Membership;
use App\Models\Schedule;
use App\Models\Service;
use App\Services\ScheduleAvailabilityService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CreatePendingMembership
{
    public function __construct(private ScheduleAvailabilityService $availability) {}

    /**
     * Reserve every session of a membership for a customer who is about to pay.
     *
     * Every rule is re-checked here under a lock on the schedule rows, so the
     * caller's own state (a session list built in the browser) is never trusted.
     * Every session must be a class of the membership's category. The membership runs for one month from its earliest session. Its sessions
     * wait for payment with no expiry of their own: they hold their places until
     * the membership is paid or removed with it.
     *
     * @param  list<array{schedule_id: int, date: string}>  $sessions
     * @param  array{name: string, surname: string, phone: string, email: string}  $customer
     *
     * @throws InvalidMembershipSelectionException
     * @throws SlotUnavailableException
     */
    public function execute(int $membershipServiceId, array $sessions, array $customer): Membership
    {
        $membershipService = Service::where('is_membership', true)
            ->where('is_active', true)
            ->find($membershipServiceId);

        if ($membershipService === null) {
            throw new InvalidMembershipSelectionException('The membership is not on sale.');
        }

        if (count($sessions) !== $membershipService->sessions_count) {
            throw new InvalidMembershipSelectionException('The number of sessions does not match the membership.');
        }

        $sessionDates = $this->parseSessionDates($sessions);
        $periodStart = min($sessionDates);
        $periodEnd = $periodStart->copy()->addMonth();

        if (max($sessionDates)->gt($periodEnd)) {
            throw new InvalidMembershipSelectionException('A session is outside the membership period.');
        }

        return DB::transaction(function () use ($membershipService, $sessions, $sessionDates, $periodStart, $periodEnd, $customer) {
            $schedules = Schedule::with('service.coach')
                ->whereIn('id', array_column($sessions, 'schedule_id'))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($sessions as $index => $session) {
                $schedule = $schedules->get($session['schedule_id']);

                if ($schedule === null
                    || ! $schedule->service->isCoveredBy($membershipService)
                    || $this->availability->slotStatus($schedule, $sessionDates[$index]) !== SlotStatus::Available) {
                    throw new SlotUnavailableException;
                }
            }

            $membership = Membership::create([
                'email' => $customer['email'],
                'name' => $customer['name'],
                'surname' => $customer['surname'],
                'phone' => $customer['phone'],
                'service_id' => $membershipService->id,
                'price' => $membershipService->price,
                'sessions_total' => $membershipService->sessions_count,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'payment_status' => PaymentStatus::Pending,
                'expires_at' => now()->addMinutes(CreatePendingBooking::PAYMENT_WINDOW_MINUTES + CreatePendingBooking::HOLD_GRACE_MINUTES),
            ]);

            foreach ($sessions as $index => $session) {
                $membership->bookings()->create([
                    'schedule_id' => $session['schedule_id'],
                    'booking_date' => $sessionDates[$index]->toDateString(),
                    'name' => $customer['name'],
                    'surname' => $customer['surname'],
                    'phone' => $customer['phone'],
                    'email' => $customer['email'],
                    'participant_count' => 1,
                    'payment_status' => PaymentStatus::Pending,
                ]);
            }

            return $membership;
        });
    }

    /**
     * Turn the session dates into days, refusing malformed dates and the same class picked twice on one day.
     *
     * @param  list<array{schedule_id: int, date: string}>  $sessions
     * @return array<int, Carbon>
     *
     * @throws InvalidMembershipSelectionException
     */
    private function parseSessionDates(array $sessions): array
    {
        $dates = [];
        $picked = [];

        foreach ($sessions as $index => $session) {
            if (! Carbon::canBeCreatedFromFormat($session['date'], 'Y-m-d')) {
                throw new InvalidMembershipSelectionException('A session date is not valid.');
            }

            $key = $session['schedule_id'].'|'.$session['date'];

            if (isset($picked[$key])) {
                throw new InvalidMembershipSelectionException('The same session is picked twice.');
            }

            $picked[$key] = true;
            $dates[$index] = Carbon::createFromFormat('Y-m-d', $session['date'])->startOfDay();
        }

        return $dates;
    }
}
