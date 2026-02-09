<?php

use App\Enums\DayOfWeek;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Schedule;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    #[Computed]
    public function todaysBookingsCount(): int
    {
        return Booking::whereDate('booking_date', today())->count();
    }

    #[Computed]
    public function todaysRevenue(): int
    {
        return Booking::query()
            ->whereDate('booking_date', today())
            ->where('payment_status', PaymentStatus::Paid)
            ->join('schedules', 'bookings.schedule_id', '=', 'schedules.id')
            ->join('services', 'schedules.service_id', '=', 'services.id')
            ->sum('services.price');
    }

    #[Computed]
    public function pendingPaymentsCount(): int
    {
        return Booking::where('payment_status', PaymentStatus::Pending)->count();
    }

    #[Computed]
    public function thisWeekBookings(): int
    {
        return Booking::whereBetween('booking_date', [
            now()->startOfWeek(),
            now()->endOfWeek(),
        ])->count();
    }

    #[Computed]
    public function lastWeekBookings(): int
    {
        return Booking::whereBetween('booking_date', [
            now()->subWeek()->startOfWeek(),
            now()->subWeek()->endOfWeek(),
        ])->count();
    }

    /**
     * @return array{change: int, direction: string}
     */
    #[Computed]
    public function weeklyTrend(): array
    {
        $lastWeek = $this->lastWeekBookings;
        $thisWeek = $this->thisWeekBookings;

        if ($lastWeek === 0) {
            return [
                'change' => $thisWeek > 0 ? 100 : 0,
                'direction' => $thisWeek > 0 ? 'up' : 'neutral',
            ];
        }

        $change = (int) round((($thisWeek - $lastWeek) / $lastWeek) * 100);

        return [
            'change' => abs($change),
            'direction' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'neutral'),
        ];
    }

    #[Computed]
    public function mostPopularDay(): ?string
    {
        $result = Booking::query()
            ->join('schedules', 'bookings.schedule_id', '=', 'schedules.id')
            ->selectRaw('schedules.day_of_week, COUNT(*) as count')
            ->groupBy('schedules.day_of_week')
            ->orderByDesc('count')
            ->first();

        if (! $result) {
            return null;
        }

        $dayOfWeek = DayOfWeek::tryFrom($result->day_of_week);

        return $dayOfWeek?->label();
    }

    #[Computed]
    public function upcomingBookingsCount(): int
    {
        return Booking::whereBetween('booking_date', [
            today(),
            today()->addDays(7),
        ])->count();
    }

    #[Computed]
    public function averageCapacityUtilization(): int
    {
        $schedules = Schedule::query()
            ->where('is_active', true)
            ->withCount('bookings')
            ->get();

        if ($schedules->isEmpty()) {
            return 0;
        }

        $totalBookings = $schedules->sum('bookings_count');
        $totalCapacity = $schedules->sum('max_capacity');

        if ($totalCapacity === 0) {
            return 0;
        }

        return (int) round(($totalBookings / $totalCapacity) * 100);
    }

    #[Computed]
    public function fullClassesCount(): int
    {
        return Schedule::query()
            ->where('is_active', true)
            ->withCount('bookings')
            ->get()
            ->filter(fn($schedule) => $schedule->bookings_count >= $schedule->max_capacity)
            ->count();
    }

    #[Computed]
    public function availableSpotsToday(): int
    {
        $todayDayOfWeek = today()->dayOfWeekIso;

        $schedules = Schedule::query()
            ->where('is_active', true)
            ->where('day_of_week', $todayDayOfWeek)
            ->withCount(['bookings' => function ($query) {
                $query->whereDate('booking_date', today());
            }])
            ->get();

        return $schedules->sum(fn($schedule) => max(0, $schedule->max_capacity - $schedule->bookings_count));
    }
};
?>

<div class="flex flex-col gap-6">
    {{-- Summary Cards --}}
    <div class="grid gap-4 md:grid-cols-3">
        <flux:card>
            <flux:text>{{ __('Šodienas rezervācijas') }}</flux:text>
            <flux:heading size="xl" class="mt-2 tabular-nums">{{ $this->todaysBookingsCount }}</flux:heading>
        </flux:card>

        <flux:card>
            <flux:text>{{ __('Šodienas ieņēmumi') }}</flux:text>
            <flux:heading size="xl" class="mt-2 tabular-nums">€{{ number_format($this->todaysRevenue / 100, 2) }}</flux:heading>
        </flux:card>

        <flux:card>
            <flux:text>{{ __('Gaida apmaksu') }}</flux:text>
            <flux:heading size="xl" class="mt-2 tabular-nums">{{ $this->pendingPaymentsCount }}</flux:heading>
        </flux:card>
    </div>

    {{-- Trends and Capacity --}}
    <div class="grid gap-4 md:grid-cols-2">
        {{-- Booking Trends --}}
        <flux:card>
            <flux:heading size="lg">{{ __('Rezervāciju tendences') }}</flux:heading>

            <div class="mt-4 space-y-4">
                <div class="flex items-center justify-between">
                    <flux:text>{{ __('Šonedēļ') }}</flux:text>
                    <div class="flex items-center gap-2">
                        <flux:text class="font-semibold tabular-nums">{{ $this->thisWeekBookings }}</flux:text>
                        @if($this->weeklyTrend['direction'] !== 'neutral')
                            <flux:badge size="sm" :color="$this->weeklyTrend['direction'] === 'up' ? 'green' : 'red'">
                                {{ $this->weeklyTrend['direction'] === 'up' ? '+' : '-' }}{{ $this->weeklyTrend['change'] }}%
                            </flux:badge>
                        @endif
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <flux:text>{{ __('Pagājušonedēļ') }}</flux:text>
                    <flux:text class="font-semibold tabular-nums">{{ $this->lastWeekBookings }}</flux:text>
                </div>

                <div class="flex items-center justify-between">
                    <flux:text>{{ __('Populārākā diena') }}</flux:text>
                    <flux:text class="font-semibold">{{ $this->mostPopularDay ?? __('Nav datu') }}</flux:text>
                </div>

                <div class="flex items-center justify-between">
                    <flux:text>{{ __('Nākamās 7 dienas') }}</flux:text>
                    <flux:text class="font-semibold tabular-nums">{{ $this->upcomingBookingsCount }}</flux:text>
                </div>
            </div>
        </flux:card>

        {{-- Capacity Utilization --}}
        <flux:card>
            <flux:heading size="lg">{{ __('Kapacitātes izmantojums') }}</flux:heading>

            <div class="mt-4 space-y-4">
                <div class="flex items-center justify-between">
                    <flux:text>{{ __('Vidējā noslodze') }}</flux:text>
                    <flux:text class="font-semibold tabular-nums">{{ $this->averageCapacityUtilization }}%</flux:text>
                </div>

                <div class="flex items-center justify-between">
                    <flux:text>{{ __('Pilnas nodarbības') }}</flux:text>
                    <flux:text class="font-semibold tabular-nums">{{ $this->fullClassesCount }}</flux:text>
                </div>

                <div class="flex items-center justify-between">
                    <flux:text>{{ __('Brīvas vietas šodien') }}</flux:text>
                    <flux:text class="font-semibold tabular-nums">{{ $this->availableSpotsToday }}</flux:text>
                </div>
            </div>
        </flux:card>
    </div>
</div>
