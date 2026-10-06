<?php

namespace App\Actions;

use App\Enums\PaymentStatus;
use App\Enums\SlotStatus;
use App\Exceptions\SlotUnavailableException;
use App\Models\Booking;
use App\Models\Schedule;
use App\Services\ScheduleAvailabilityService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CreatePendingBooking
{
    public const PAYMENT_HOLD_MINUTES = 30;

    public function __construct(private ScheduleAvailabilityService $availability) {}

    /**
     * Reserve a place in a class for a customer who is about to pay.
     *
     * Every rule is re-checked here under a lock on the schedule row, so the
     * caller's own state (a timetable rendered earlier, a form) is never trusted.
     *
     * @param  array{name: string, surname: string, phone: string, email: string}  $customer
     *
     * @throws SlotUnavailableException
     */
    public function execute(int $scheduleId, string $date, int $participantCount, array $customer): Booking
    {
        if (! Carbon::canBeCreatedFromFormat($date, 'Y-m-d')) {
            throw new SlotUnavailableException;
        }

        $bookingDate = Carbon::createFromFormat('Y-m-d', $date)->startOfDay();

        return DB::transaction(function () use ($scheduleId, $bookingDate, $participantCount, $customer) {
            $schedule = Schedule::with('service.priceTiers')->lockForUpdate()->find($scheduleId);

            if ($schedule === null) {
                throw new SlotUnavailableException;
            }

            if ($this->availability->slotStatus($schedule, $bookingDate) !== SlotStatus::Available) {
                throw new SlotUnavailableException;
            }

            if (! in_array($participantCount, $this->allowedParticipantCounts($schedule), true)) {
                throw new SlotUnavailableException('The requested group size is not offered for this class.');
            }

            return Booking::create([
                'schedule_id' => $schedule->id,
                'booking_date' => $bookingDate->toDateString(),
                'name' => $customer['name'],
                'surname' => $customer['surname'],
                'phone' => $customer['phone'],
                'email' => $customer['email'],
                'participant_count' => $participantCount,
                'payment_status' => PaymentStatus::Pending,
                'expires_at' => now()->addMinutes(self::PAYMENT_HOLD_MINUTES),
            ]);
        });
    }

    /**
     * Get the group sizes a customer may book for the schedule.
     *
     * A group class is booked one place at a time. An individual session takes
     * any group size that has a price tier and fits the session.
     *
     * @return list<int>
     */
    public function allowedParticipantCounts(Schedule $schedule): array
    {
        if (! $schedule->service->is_exclusive) {
            return [1];
        }

        $counts = $schedule->service->priceTiers
            ->pluck('participant_count')
            ->filter(fn (int $count) => $count >= 1 && $count <= $schedule->max_capacity)
            ->values()
            ->all();

        return $counts === [] ? [1] : $counts;
    }
}
