<?php

use App\Enums\DayOfWeek;
use App\Enums\SlotStatus;
use App\Models\ServiceType;
use App\Services\ScheduleAvailabilityService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component
{
    /**
     * Weeks ahead of the current week (0 = this week).
     */
    #[Url(as: 'nedela')]
    public int $week = 0;

    #[Url(as: 'veids')]
    public ?int $serviceTypeId = null;

    /**
     * Whether the customer has just come back from an unfinished Stripe payment.
     */
    public bool $paymentCancelled = false;

    /**
     * Bring values taken from the address bar into the bookable range.
     */
    public function mount(): void
    {
        $this->paymentCancelled = request()->boolean('booking_cancelled');
        $this->week = $this->clampWeek($this->week);
        $this->serviceTypeId = $this->knownServiceTypeId($this->serviceTypeId);
    }

    /**
     * Service types that have at least one class customers can book.
     */
    #[Computed]
    public function serviceTypes(): Collection
    {
        return ServiceType::whereHas('services', function ($query) {
            $query->where('is_active', true)
                ->where('is_membership', false)
                ->whereHas('schedules', fn ($query) => $query->where('is_active', true));
        })->orderBy('position')->get();
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
        $timetable = app(ScheduleAvailabilityService::class)->weekTimetable($this->weekStart, $this->serviceTypeId);

        $days = [];

        foreach ($timetable as $date => $entries) {
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
     * Whether the shown week has any class at all.
     */
    #[Computed]
    public function hasClasses(): bool
    {
        return collect($this->days)->contains(fn (array $day) => $day['entries'] !== []);
    }

    /**
     * The day opened first on small screens: today, or Monday for other weeks.
     */
    #[Computed]
    public function initialDay(): string
    {
        return $this->week === 0 ? today()->toDateString() : $this->weekStart->toDateString();
    }

    public function previousWeek(): void
    {
        $this->week = $this->clampWeek($this->week - 1);
    }

    public function nextWeek(): void
    {
        $this->week = $this->clampWeek($this->week + 1);
    }

    public function updatedWeek(): void
    {
        $this->week = $this->clampWeek($this->week);
    }

    /**
     * Filter the timetable by service type; null shows every type.
     */
    public function showServiceType(?int $serviceTypeId): void
    {
        $this->serviceTypeId = $this->knownServiceTypeId($serviceTypeId);
    }

    /**
     * Re-render with fresh availability after a booking attempt.
     */
    #[On('timetable-refresh')]
    public function refreshTimetable(): void
    {
        unset($this->days, $this->hasClasses);
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

    private function clampWeek(int $week): int
    {
        return max(0, min(ScheduleAvailabilityService::BOOKING_HORIZON_WEEKS, $week));
    }

    private function knownServiceTypeId(?int $serviceTypeId): ?int
    {
        return $this->serviceTypes->contains('id', $serviceTypeId) ? $serviceTypeId : null;
    }

    /**
     * Render the component view with the page title.
     */
    public function render(): \Illuminate\View\View
    {
        return $this->view()
            ->title(__('Nodarbību saraksts'))
            ->layout('layouts.main');
    }
};
?>

<div class="container mx-auto pt-36 pb-12 px-4 space-y-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <flux:heading level="1">{{ __('Nodarbību saraksts') }}</flux:heading>
        <flux:modal.trigger name="booking-modal">
            <flux:button class="btn btn-sm btn-tertiary">{{ __('Abonementi') }}</flux:button>
        </flux:modal.trigger>
    </div>

    @if($paymentCancelled)
        <flux:callout icon="information-circle">
            <flux:callout.heading>{{ __('Maksājums netika pabeigts.') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Jūsu vieta tiek turēta 30 minūtes — atgādinājumu ar apmaksas saiti nosūtīsim uz e-pastu.') }}</flux:callout.text>
        </flux:callout>
    @endif

    {{-- SERVICE TYPE FILTER --}}
    @if($this->serviceTypes->count() > 1)
        <div class="flex flex-wrap gap-2">
            <flux:button size="sm" :variant="$serviceTypeId === null ? 'primary' : 'filled'"
                         wire:click="showServiceType(null)">{{ __('Visi') }}</flux:button>
            @foreach($this->serviceTypes as $serviceType)
                <flux:button wire:key="type-{{ $serviceType->id }}" size="sm"
                             :variant="$serviceTypeId === $serviceType->id ? 'primary' : 'filled'"
                             wire:click="showServiceType({{ $serviceType->id }})">{{ $serviceType->name }}</flux:button>
            @endforeach
        </div>
    @endif

    {{-- WEEK NAVIGATION --}}
    <div class="flex items-center justify-between gap-4 border-y border-black py-3">
        <flux:button variant="ghost" icon="chevron-left" wire:click="previousWeek" :disabled="$week === 0"
                     :aria-label="__('Iepriekšējā nedēļa')"/>
        <flux:heading level="3">
            {{ $this->weekStart->format('d.m.') }} – {{ $this->weekStart->copy()->addDays(6)->format('d.m.Y') }}
        </flux:heading>
        <flux:button variant="ghost" icon="chevron-right" wire:click="nextWeek"
                     :disabled="$week === \App\Services\ScheduleAvailabilityService::BOOKING_HORIZON_WEEKS"
                     :aria-label="__('Nākamā nedēļa')"/>
    </div>

    @if($this->hasClasses)
        <div wire:key="week-{{ $week }}-{{ $serviceTypeId }}" x-data="{ day: '{{ $this->initialDay }}' }" class="space-y-4">
            {{-- DAY PICKER (SMALL SCREENS) --}}
            <div class="grid grid-cols-7 gap-1 lg:hidden">
                @foreach($this->days as $day)
                    <button type="button" wire:key="tab-{{ $day['date'] }}" @click="day = '{{ $day['date'] }}'"
                            :class="day === '{{ $day['date'] }}' ? 'bg-blue border-blue text-white' : 'border-zinc-200'"
                            class="rounded-lg border py-2 text-center transition-colors">
                        <span class="block text-xs uppercase">{{ $day['weekday_short'] }}</span>
                        <span class="block font-semibold">{{ $day['day_of_month'] }}</span>
                    </button>
                @endforeach
            </div>

            {{-- DAYS --}}
            <div class="grid gap-3 lg:grid-cols-7">
                @foreach($this->days as $day)
                    <section wire:key="day-{{ $day['date'] }}" class="space-y-3"
                             :class="day === '{{ $day['date'] }}' ? '' : 'max-lg:hidden'">
                        <header class="border-b border-zinc-200 pb-2">
                            <div class="font-heading text-lg font-medium uppercase">{{ $day['weekday'] }}</div>
                            <flux:text size="sm">{{ $day['short_date'] }}</flux:text>
                        </header>

                        @forelse($day['entries'] as $entry)
                            @php($isBookable = $entry['status'] === \App\Enums\SlotStatus::Available)
                            <article wire:key="slot-{{ $entry['schedule_id'] }}-{{ $entry['date'] }}"
                                     @class([
                                         'flex flex-col gap-2 rounded-xl border p-3',
                                         'border-zinc-200 bg-surface' => $isBookable,
                                         'border-zinc-100 bg-zinc-50 text-zinc-400' => ! $isBookable,
                                     ])>
                                <div class="font-heading text-lg font-medium">{{ $entry['start_time'] }}–{{ $entry['end_time'] }}</div>
                                <div class="font-medium leading-snug">{{ $entry['service_name'] }}</div>
                                @if($entry['coach_name'] !== '')
                                    <div class="text-sm">{{ $entry['coach_name'] }}</div>
                                @endif

                                <div class="flex flex-wrap items-center justify-between gap-x-2 gap-y-1 text-sm">
                                    <span>{{ $entry['has_price_range'] ? __('no').' ' : '' }}{{ Number::currency($entry['price_from'] / 100, 'EUR') }}</span>
                                    @if(! $isBookable)
                                        <span class="font-medium uppercase">{{ $this->statusLabel($entry['status']) }}</span>
                                    @elseif($entry['is_exclusive'])
                                        <span>{{ __('Individuāli') }}</span>
                                    @else
                                        <span>{{ $this->placesLabel($entry['remaining']) }}</span>
                                    @endif
                                </div>

                                @if($isBookable)
                                    <flux:button variant="primary" size="sm" class="w-full"
                                                 wire:click="$dispatch('open-booking-checkout', { scheduleId: {{ $entry['schedule_id'] }}, date: '{{ $entry['date'] }}' })">
                                        {{ __('Pieteikties') }}
                                    </flux:button>
                                @endif
                            </article>
                        @empty
                            <flux:text size="sm">{{ __('Nodarbību nav') }}</flux:text>
                        @endforelse
                    </section>
                @endforeach
            </div>
        </div>
    @else
        <flux:text>{{ __('Šajā nedēļā nodarbību nav.') }}</flux:text>
    @endif

    <livewire:booking.booking-checkout wire:key="booking-checkout" />
</div>
