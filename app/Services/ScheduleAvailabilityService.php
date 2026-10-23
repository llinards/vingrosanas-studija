<?php

namespace App\Services;

use App\Enums\SlotStatus;
use App\Models\Booking;
use App\Models\Schedule;
use App\Models\UnavailableDate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class ScheduleAvailabilityService
{
    public const BOOKING_HORIZON_WEEKS = 12;

    /**
     * Build the public class timetable for the seven days starting on $weekStart.
     *
     * Group classes are always listed together with their status. Individual
     * sessions are listed only while they can still be booked. Classes of an
     * inactive coach are left out. With $membershipEligibleOnly, only the classes
     * a membership can be used for are listed; $serviceTypeId narrows them to one category.
     *
     * @return array<string, list<array{schedule_id: int, date: string, start_time: string, service_name: string, coach_name: string, is_exclusive: bool, price_from: int, has_price_range: bool, remaining: int, status: SlotStatus}>>
     */
    public function weekTimetable(Carbon $weekStart, bool $membershipEligibleOnly = false, ?int $serviceTypeId = null): array
    {
        $weekStart = $weekStart->copy()->startOfDay();
        $weekEnd = $weekStart->copy()->addDays(6);
        $now = now();

        $schedules = Schedule::query()
            ->with(['service.coach', 'service.priceTiers'])
            ->where('is_active', true)
            ->whereHas('service', fn (Builder $query) => $query
                ->where('is_active', true)
                ->where('is_membership', false)
                ->when($membershipEligibleOnly, fn (Builder $query) => $query->where('is_membership_eligible', true))
                ->when($serviceTypeId !== null, fn (Builder $query) => $query->where('service_type_id', $serviceTypeId))
                ->whereDoesntHave('coach', fn (Builder $query) => $query->where('is_active', false)))
            ->where(fn (Builder $query) => $query
                ->whereNotNull('day_of_week')
                ->orWhereBetween('date', $this->dateRangeBounds($weekStart, $weekEnd)))
            ->get();

        $blocks = UnavailableDate::whereDate('start_date', '<=', $weekEnd->toDateString())
            ->whereDate('end_date', '>=', $weekStart->toDateString())
            ->get();

        $occupancy = $this->occupancyByScheduleAndDate($schedules, $weekStart, $weekEnd);

        $week = [];

        for ($date = $weekStart->copy(); $date->lte($weekEnd); $date = $date->copy()->addDay()) {
            $dateString = $date->toDateString();

            $blocksForDate = $blocks->filter(
                fn (UnavailableDate $block) => $block->start_date->toDateString() <= $dateString && $block->end_date->toDateString() >= $dateString,
            );

            $entries = [];

            foreach ($schedules as $schedule) {
                $service = $schedule->service;
                $occupied = $occupancy[$schedule->id.'|'.$dateString] ?? ['participants' => 0, 'bookings' => 0];

                $remaining = $service->is_exclusive
                    ? ($occupied['bookings'] > 0 ? 0 : $schedule->max_capacity)
                    : $schedule->max_capacity - $occupied['participants'];

                $status = $this->calendarStatus($schedule, $date, $now)
                    ?? ($this->isSlotBlocked($schedule, $blocksForDate) ? SlotStatus::Cancelled : null)
                    ?? ($remaining >= 1 ? SlotStatus::Available : SlotStatus::Full);

                if ($status === SlotStatus::Unavailable) {
                    continue;
                }

                if ($service->is_exclusive && $status !== SlotStatus::Available) {
                    continue;
                }

                $fittingTiers = $service->is_exclusive
                    ? $service->priceTiers->where('participant_count', '<=', $schedule->max_capacity)
                    : collect();

                $entries[] = [
                    'schedule_id' => $schedule->id,
                    'date' => $dateString,
                    'start_time' => substr((string) $schedule->start_time, 0, 5),
                    'service_name' => $service->name,
                    'coach_name' => $service->coach?->name ?? '',
                    'is_exclusive' => $service->is_exclusive,
                    'price_from' => $fittingTiers->min('price') ?? $service->price,
                    'has_price_range' => $fittingTiers->count() > 1,
                    'remaining' => max(0, $remaining),
                    'status' => $status,
                ];
            }

            usort($entries, static fn (array $a, array $b) => [$a['start_time'], $a['service_name']] <=> [$b['start_time'], $b['service_name']]);

            $week[$dateString] = $entries;
        }

        return $week;
    }

    /**
     * Decide whether a customer can book the given schedule on the given date.
     *
     * This is the authoritative check behind the timetable and the checkout.
     * Pass $excludeBookingId when moving an existing booking, so it does not count against itself.
     */
    public function slotStatus(Schedule $schedule, Carbon $date, ?int $excludeBookingId = null): SlotStatus
    {
        $status = $this->calendarStatus($schedule, $date, now());

        if ($status !== null) {
            return $status;
        }

        if ($this->isSlotBlocked($schedule, UnavailableDate::forDate($date)->get())) {
            return SlotStatus::Cancelled;
        }

        $remaining = $this->remainingCapacity($schedule, $date->toDateString(), $schedule->service->is_exclusive, $excludeBookingId);

        return $remaining >= 1 ? SlotStatus::Available : SlotStatus::Full;
    }

    /**
     * Whether the class takes place on the given date: its weekday for a weekly class, its date for a one-off.
     */
    public function runsOn(Schedule $schedule, Carbon $date): bool
    {
        return $this->matchesDay($schedule, $date);
    }

    /**
     * Whether a studio closure or an absence of the class's coach covers the class on the given date.
     */
    public function isClosedOn(Schedule $schedule, Carbon $date): bool
    {
        return $this->isSlotBlocked($schedule, UnavailableDate::forDate($date)->get());
    }

    /**
     * Get the last date customers may book.
     */
    public function bookingHorizonEnd(): Carbon
    {
        return today()->addWeeks(self::BOOKING_HORIZON_WEEKS);
    }

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

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dateString = $date->toDateString();

            $blocksForDate = $blocks->filter(
                fn (UnavailableDate $block) => $block->start_date->toDateString() <= $dateString && $block->end_date->toDateString() >= $dateString,
            );

            $hasAvailableSlot = false;
            $isToday = $date->isToday();

            foreach ($schedules as $schedule) {
                if (! $this->matchesDay($schedule, $date)) {
                    continue;
                }

                if ($isToday && ! $this->isAfterNow($schedule, $now)) {
                    continue;
                }

                if ($this->isSlotBlocked($schedule, $blocksForDate)) {
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

        $slots = [];
        $isToday = $date->isToday();
        $now = now();
        $dateString = $date->toDateString();

        foreach ($schedules as $schedule) {
            if (! $this->matchesDay($schedule, $date)) {
                continue;
            }

            if ($isToday && ! $this->isAfterNow($schedule, $now)) {
                continue;
            }

            if ($this->isSlotBlocked($schedule, $blocks)) {
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
     * Resolve the statuses that depend only on the schedule and the calendar.
     *
     * Returns null when the slot is offered on that date and has not started yet.
     */
    private function calendarStatus(Schedule $schedule, Carbon $date, Carbon $now): ?SlotStatus
    {
        $service = $schedule->service;

        if (! $schedule->is_active || ! $service->is_active || $service->is_membership) {
            return SlotStatus::Unavailable;
        }

        if ($service->coach?->is_active === false) {
            return SlotStatus::Unavailable;
        }

        if (! $this->matchesDay($schedule, $date) || $date->copy()->startOfDay()->gt($this->bookingHorizonEnd())) {
            return SlotStatus::Unavailable;
        }

        if ($date->copy()->startOfDay()->lt($now->copy()->startOfDay())) {
            return SlotStatus::Past;
        }

        if ($date->isSameDay($now) && ! $this->isAfterNow($schedule, $now)) {
            return SlotStatus::Past;
        }

        return null;
    }

    /**
     * Sum the places taken per schedule and date with a single query.
     *
     * @param  Collection<int, Schedule>  $schedules
     * @return array<string, array{participants: int, bookings: int}> keyed by "{schedule_id}|{Y-m-d}"
     */
    private function occupancyByScheduleAndDate(Collection $schedules, Carbon $startDate, Carbon $endDate): array
    {
        $rows = Booking::active()
            ->whereIn('schedule_id', $schedules->modelKeys())
            ->whereBetween('booking_date', $this->dateRangeBounds($startDate, $endDate))
            ->toBase()
            ->selectRaw('schedule_id, booking_date, SUM(participant_count) as participants, COUNT(*) as bookings')
            ->groupBy('schedule_id', 'booking_date')
            ->get();

        $occupancy = [];

        foreach ($rows as $row) {
            $occupancy[$row->schedule_id.'|'.substr((string) $row->booking_date, 0, 10)] = [
                'participants' => (int) $row->participants,
                'bookings' => (int) $row->bookings,
            ];
        }

        return $occupancy;
    }

    /**
     * Inclusive bounds for a date column that match both DATE and datetime-string storage.
     *
     * @return array{0: string, 1: string}
     */
    private function dateRangeBounds(Carbon $startDate, Carbon $endDate): array
    {
        return [$startDate->toDateString(), $endDate->toDateString().' 23:59:59'];
    }

    /**
     * Whether any block applicable to this schedule's coach covers its start time.
     *
     * @param  iterable<UnavailableDate>  $blocksForDate
     */
    private function isSlotBlocked(Schedule $schedule, iterable $blocksForDate): bool
    {
        $time = (string) $schedule->start_time;
        $coachId = $schedule->service->coach_id;

        foreach ($blocksForDate as $block) {
            if ($block->coach_id !== null && $block->coach_id !== $coachId) {
                continue;
            }

            if ($block->coversTime($time)) {
                return true;
            }
        }

        return false;
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
