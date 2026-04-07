<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Schedule;
use App\Models\UnavailableDate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class ScheduleAvailabilityService
{
    /**
     * Get unavailable dates as a comma-separated string for a calendar component.
     *
     * @param  array<int, array{schedule_id: int, date: string}>  $extraBookings  Additional bookings to account for (e.g. wizard selections)
     */
    public function unavailableDates(
        Collection $schedules,
        Carbon $startDate,
        Carbon $endDate,
        bool $isExclusive = false,
        ?int $excludeBookingId = null,
        array $extraBookings = [],
    ): string {
        if ($schedules->isEmpty()) {
            return '';
        }

        $unavailable = [];
        $now = now();

        $blocks = UnavailableDate::whereDate('start_date', '<=', $endDate->toDateString())
            ->whereDate('end_date', '>=', $startDate->toDateString())
            ->get();

        $studioWideBlocks = $blocks->whereNull('coach_id');
        $coachBlocks = $blocks->whereNotNull('coach_id');

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dateString = $date->toDateString();

            $isStudioClosed = $studioWideBlocks->contains(
                fn (UnavailableDate $block) => $block->start_date->toDateString() <= $dateString && $block->end_date->toDateString() >= $dateString,
            );

            if ($isStudioClosed) {
                $unavailable[] = $dateString;

                continue;
            }

            $blockedCoachIds = $coachBlocks
                ->filter(fn (UnavailableDate $block) => $block->start_date->toDateString() <= $dateString && $block->end_date->toDateString() >= $dateString)
                ->pluck('coach_id')
                ->unique()
                ->all();

            $hasAvailableSlot = false;
            $isToday = $date->isToday();

            foreach ($schedules as $schedule) {
                if (in_array($schedule->service->coach_id, $blockedCoachIds, true)) {
                    continue;
                }

                if (! $this->matchesDay($schedule, $date)) {
                    continue;
                }

                if ($isToday && ! $this->isAfterNow($schedule, $now)) {
                    continue;
                }

                $remaining = $this->remainingCapacity($schedule, $dateString, $isExclusive, $excludeBookingId, $extraBookings);

                if ($remaining >= 1) {
                    $hasAvailableSlot = true;
                    break;
                }
            }

            if (! $hasAvailableSlot) {
                $unavailable[] = $dateString;
            }
        }

        return implode(',', $unavailable);
    }

    /**
     * Get available time slots for a specific date.
     *
     * @param  array<int, array{schedule_id: int, date: string}>  $extraBookings  Additional bookings to account for
     * @return array<int, array{schedule_id: int, start_time: string, coach_name: string, remaining: int, max_capacity: int}>
     */
    public function availableTimeSlots(
        Collection $schedules,
        Carbon $date,
        bool $isExclusive = false,
        ?int $excludeBookingId = null,
        array $extraBookings = [],
    ): array {
        $blocks = UnavailableDate::forDate($date)->get();

        if ($blocks->contains(fn (UnavailableDate $block) => $block->coach_id === null)) {
            return [];
        }

        $blockedCoachIds = $blocks->pluck('coach_id')->filter()->unique()->all();

        $slots = [];
        $isToday = $date->isToday();
        $now = now();
        $dateString = $date->toDateString();

        foreach ($schedules as $schedule) {
            if (in_array($schedule->service->coach_id, $blockedCoachIds, true)) {
                continue;
            }

            if (! $this->matchesDay($schedule, $date)) {
                continue;
            }

            if ($isToday && ! $this->isAfterNow($schedule, $now)) {
                continue;
            }

            $remaining = $this->remainingCapacity($schedule, $dateString, $isExclusive, $excludeBookingId, $extraBookings);

            if ($remaining >= 1) {
                $slots[] = [
                    'schedule_id' => $schedule->id,
                    'start_time' => substr((string) $schedule->start_time, 0, 5),
                    'coach_name' => $schedule->service->coach?->name ?? '',
                    'remaining' => $remaining,
                    'max_capacity' => $schedule->max_capacity,
                ];
            }
        }

        usort($slots, static fn ($a, $b) => strcmp($a['start_time'], $b['start_time']));

        return $slots;
    }

    /**
     * Check if a schedule matches the given date (by day of week or specific date).
     */
    private function matchesDay(Schedule $schedule, Carbon $date): bool
    {
        if ($schedule->day_of_week !== null && $schedule->day_of_week->value === $date->dayOfWeekIso) {
            return true;
        }

        return $schedule->date !== null && $schedule->date->isSameDay($date);
    }

    /**
     * Check if the schedule's start time is after the current time.
     */
    private function isAfterNow(Schedule $schedule, Carbon $now): bool
    {
        return Carbon::parse($schedule->start_time)->setDateFrom($now)->isAfter($now);
    }

    /**
     * Calculate remaining capacity for a schedule on a given date.
     *
     * @param  array<int, array{schedule_id: int, date: string}>  $extraBookings
     */
    public function remainingCapacity(
        Schedule $schedule,
        string $dateString,
        bool $isExclusive = false,
        ?int $excludeBookingId = null,
        array $extraBookings = [],
    ): int {
        $query = Booking::active()
            ->where('schedule_id', $schedule->id)
            ->whereDate('booking_date', $dateString);

        if ($excludeBookingId) {
            $query->where('id', '!=', $excludeBookingId);
        }

        if ($isExclusive) {
            return $query->exists() ? 0 : $schedule->max_capacity;
        }

        $bookedParticipants = $query->sum('participant_count');

        // Account for extra bookings (e.g. already selected sessions in wizard)
        $extraCount = 0;
        foreach ($extraBookings as $extra) {
            if ($extra['schedule_id'] === $schedule->id && $extra['date'] === $dateString) {
                $extraCount++;
            }
        }

        return $schedule->max_capacity - $bookedParticipants - $extraCount;
    }
}
