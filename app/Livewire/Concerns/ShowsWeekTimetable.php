<?php

namespace App\Livewire\Concerns;

use App\Enums\DayOfWeek;
use App\Enums\SlotStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;

/**
 * A week of classes shown with <x-timetable.week>: which week is shown, moving between
 * weeks, and the days with their classes.
 *
 * The component keeps the shown week in a public int $week (weeks after the current one),
 * says which weeks may be shown and which classes are listed, and builds each class card.
 */
trait ShowsWeekTimetable
{
    /**
     * Whether small screens open the shown week on Sunday, after swiping back from a Monday.
     */
    public bool $opensOnLastDay = false;

    /**
     * The first week that may be shown (0 = this week).
     */
    abstract protected function firstWeek(): int;

    /**
     * The last week that may be shown.
     */
    abstract protected function lastWeek(): int;

    /**
     * The classes of the seven days from $weekStart, as ScheduleAvailabilityService::weekTimetable() returns them.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    abstract protected function classesForWeek(Carbon $weekStart): array;

    /**
     * A date to open first on small screens when it falls in the shown week.
     */
    protected function focusDate(): ?string
    {
        return null;
    }

    /**
     * Monday of the week being shown.
     */
    #[Computed]
    public function weekStart(): Carbon
    {
        return today()->startOfWeek(Carbon::MONDAY)->addWeeks($this->week);
    }

    /**
     * The seven days of the shown week with their classes.
     *
     * @return list<array{date: string, weekday: string, weekday_short: string, day_of_month: string, short_date: string, entries: list<array<string, mixed>>}>
     */
    #[Computed]
    public function days(): array
    {
        $days = [];

        foreach ($this->classesForWeek($this->weekStart) as $date => $entries) {
            $day = Carbon::parse($date);
            $weekday = DayOfWeek::from($day->dayOfWeekIso)->label();

            $days[] = [
                'date' => $date,
                'weekday' => $weekday,
                'weekday_short' => Str::substr($weekday, 0, 3),
                'day_of_month' => $day->format('j'),
                'short_date' => $day->format('d.m.'),
                'entries' => $entries,
            ];
        }

        return $days;
    }

    /**
     * The day opened first on small screens: Sunday after swiping back into the week,
     * otherwise the focus date or today when they fall in the week, else Monday.
     */
    #[Computed]
    public function initialDay(): string
    {
        $weekEnd = $this->weekStart->copy()->addDays(6);

        if ($this->opensOnLastDay) {
            return $weekEnd->toDateString();
        }

        foreach ([$this->focusDate(), today()->toDateString()] as $date) {
            if ($date !== null && $date >= $this->weekStart->toDateString() && $date <= $weekEnd->toDateString()) {
                return $date;
            }
        }

        return $this->weekStart->toDateString();
    }

    #[Computed]
    public function isFirstWeek(): bool
    {
        return $this->week <= $this->firstWeek();
    }

    #[Computed]
    public function isLastWeek(): bool
    {
        return $this->week >= $this->lastWeek();
    }

    /**
     * Show the previous week, opening it on Sunday when the customer swiped back from a Monday.
     */
    public function previousWeek(bool $openOnLastDay = false): void
    {
        $this->week = $this->clampWeek($this->week - 1);
        $this->opensOnLastDay = $openOnLastDay;
    }

    public function nextWeek(): void
    {
        $this->week = $this->clampWeek($this->week + 1);
        $this->opensOnLastDay = false;
    }

    public function updatedWeek(): void
    {
        $this->week = $this->clampWeek($this->week);
    }

    /**
     * Label for the remaining places of a group class, e.g. "1 vieta", "12 vietas".
     */
    public function placesLabel(int $places): string
    {
        $isSingular = $places % 10 === 1 && $places % 100 !== 11;

        return $places.' '.($isSingular ? __('vieta') : __('vietas'));
    }

    /**
     * Label for a class customers cannot book.
     */
    public function statusLabel(SlotStatus $status): string
    {
        return match ($status) {
            SlotStatus::Full => __('Pilns'),
            SlotStatus::Past => __('Notikusi'),
            SlotStatus::Cancelled => __('Nenotiek'),
            default => '',
        };
    }

    protected function clampWeek(int $week): int
    {
        return max($this->firstWeek(), min($this->lastWeek(), $week));
    }
}
