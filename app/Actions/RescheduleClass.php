<?php

namespace App\Actions;

use App\Enums\PaymentStatus;
use App\Mail\BookingRescheduled;
use App\Models\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class RescheduleClass
{
    /**
     * Save changes to a class and take its upcoming bookings along.
     *
     * A booking stores its date but reads its time from the class, so a new
     * time needs no change to the booking, while a new weekday or date moves
     * each upcoming booking by the same number of days as the class. Customers
     * with a paid booking that ends up at another time or date are emailed.
     *
     * @param  array{service_id: int, day_of_week: int|null, date: string|null, start_time: string, max_capacity: int|string, is_active: bool}  $attributes
     */
    public function execute(Schedule $schedule, array $attributes): void
    {
        $originalTime = (string) $schedule->start_time;
        $timeChanged = substr($originalTime, 0, 5) !== substr($attributes['start_time'], 0, 5);
        $daysToShift = $this->daysToShift($schedule, $attributes);

        $movedBookings = DB::transaction(function () use ($schedule, $attributes, $timeChanged, $daysToShift) {
            $moved = [];

            if ($timeChanged || $daysToShift !== 0) {
                foreach ($schedule->bookings()->upcoming()->get() as $booking) {
                    $originalDate = $booking->booking_date->copy();

                    if ($daysToShift !== 0) {
                        $newDate = $originalDate->copy()->addDays($daysToShift);

                        if ($attributes['day_of_week'] !== null && $newDate->lt(today())) {
                            $newDate->addWeek();
                        }

                        $booking->update(['booking_date' => $newDate->toDateString()]);
                    }

                    $moved[] = [$booking, $originalDate];
                }
            }

            $schedule->update($attributes);

            return $moved;
        });

        foreach ($movedBookings as [$booking, $originalDate]) {
            if ($booking->payment_status !== PaymentStatus::Paid || blank($booking->email)) {
                continue;
            }

            $booking->load('schedule.service.coach');

            Mail::to($booking->email)->send(new BookingRescheduled($booking, $originalDate, $originalTime));
        }
    }

    /**
     * Work out by how many days the class moves: the difference between the old
     * and new weekday of a weekly class, or between the old and new date of a one-off class.
     *
     * @param  array{day_of_week: int|null, date: string|null}  $attributes
     */
    private function daysToShift(Schedule $schedule, array $attributes): int
    {
        if ($schedule->day_of_week !== null && $attributes['day_of_week'] !== null) {
            return (int) $attributes['day_of_week'] - $schedule->day_of_week->value;
        }

        if ($schedule->date !== null && $attributes['date'] !== null) {
            return (int) $schedule->date->copy()->startOfDay()->diffInDays(Carbon::parse($attributes['date'])->startOfDay(), false);
        }

        return 0;
    }
}
