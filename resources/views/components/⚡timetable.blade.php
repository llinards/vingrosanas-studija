<?php

use App\Enums\DayOfWeek;
use App\Enums\SlotStatus;
use App\Models\Schedule;
use App\Models\Service;
use App\Services\ScheduleAvailabilityService;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component
{
    public const SINGLE_MODE = 'nodarbiba';

    public const MEMBERSHIP_MODE = 'abonements';

    /**
     * Weeks ahead of the current week (0 = this week).
     */
    #[Url]
    public int $week = 0;

    /**
     * Whether the customer books one class or picks the sessions of a membership.
     */
    #[Url(as: 'veids')]
    public string $mode = self::SINGLE_MODE;

    /**
     * The membership product being bought.
     */
    public ?int $membershipServiceId = null;

    /**
     * Sessions picked for the membership. Only a wish list: every rule is checked again at checkout.
     *
     * @var list<array{schedule_id: int, date: string}>
     */
    #[Locked]
    public array $picks = [];

    /**
     * Short note about a change to the picks the customer did not make themselves.
     */
    #[Locked]
    public ?string $pickNotice = null;

    /**
     * Whether small screens open the shown week on Sunday, after swiping back from a Monday.
     */
    public bool $opensOnLastDay = false;

    /**
     * Whether the customer has just come back from an unfinished Stripe payment for a class.
     */
    public bool $paymentCancelled = false;

    /**
     * Whether the customer has just come back from an unfinished Stripe payment for a membership.
     */
    public bool $membershipPaymentCancelled = false;

    /**
     * Bring values taken from the address bar into the allowed range.
     */
    public function mount(): void
    {
        $this->paymentCancelled = request()->boolean('booking_cancelled');
        $this->membershipPaymentCancelled = request()->boolean('membership_cancelled');
        $this->week = $this->clampWeek($this->week);
        $this->mode = $this->normalizeMode($this->mode);
    }

    #[Computed]
    public function isMembershipMode(): bool
    {
        return $this->mode === self::MEMBERSHIP_MODE;
    }

    /**
     * Membership products on sale.
     *
     * @return Collection<int, Service>
     */
    #[Computed]
    public function membershipServices(): Collection
    {
        return Service::where('is_membership', true)
            ->where('is_active', true)
            ->orderBy('sessions_count')
            ->orderBy('price')
            ->get();
    }

    #[Computed]
    public function membershipService(): ?Service
    {
        return $this->membershipServices->firstWhere('id', $this->membershipServiceId);
    }

    /**
     * Whether every session of the chosen membership is picked.
     */
    #[Computed]
    public function hasPickedAllSessions(): bool
    {
        return $this->membershipService !== null && count($this->picks) === $this->membershipService->sessions_count;
    }

    /**
     * Whether the bar with the membership progress is shown: from the first pick, or while it has a message.
     */
    #[Computed]
    public function showsPicksBar(): bool
    {
        return $this->isMembershipMode
            && $this->membershipService !== null
            && ($this->picks !== [] || $this->pickNotice !== null || $this->getErrorBag()->has('picks'));
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
        $timetable = app(ScheduleAvailabilityService::class)->weekTimetable($this->weekStart, $this->isMembershipMode);

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
     * The day opened first on small screens: Sunday after swiping back into the week,
     * otherwise today for this week and Monday for other weeks.
     */
    #[Computed]
    public function initialDay(): string
    {
        if ($this->opensOnLastDay) {
            return $this->weekStart->copy()->addDays(6)->toDateString();
        }

        return $this->week === 0 ? today()->toDateString() : $this->weekStart->toDateString();
    }

    /**
     * The picked sessions in date order, with what the customer needs to recognise them.
     *
     * @return list<array{schedule_id: int, date: string, label: string}>
     */
    #[Computed]
    public function pickedSessions(): array
    {
        $schedules = Schedule::with('service')
            ->whereIn('id', array_column($this->picks, 'schedule_id'))
            ->get()
            ->keyBy('id');

        return collect($this->picks)
            ->filter(fn (array $pick) => $schedules->has($pick['schedule_id']))
            ->map(function (array $pick) use ($schedules) {
                $schedule = $schedules->get($pick['schedule_id']);
                $startTime = substr((string) $schedule->start_time, 0, 5);

                return [
                    'schedule_id' => $pick['schedule_id'],
                    'date' => $pick['date'],
                    'start_time' => $startTime,
                    'label' => Carbon::parse($pick['date'])->format('d.m.').' '.$startTime.' '.$schedule->service->name,
                ];
            })
            ->sortBy([['date', 'asc'], ['start_time', 'asc']])
            ->map(fn (array $session) => Arr::except($session, 'start_time'))
            ->values()
            ->all();
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

    public function updatedMode(): void
    {
        $this->mode = $this->normalizeMode($this->mode);
    }

    /**
     * Accept only a product on sale, and keep the earliest picks when the new one has fewer sessions.
     */
    public function updatedMembershipServiceId(): void
    {
        $this->resetErrorBag('picks');
        $this->pickNotice = null;

        if ($this->membershipService === null) {
            $this->membershipServiceId = null;

            return;
        }

        $sessionsCount = $this->membershipService->sessions_count;

        if (count($this->picks) > $sessionsCount) {
            $this->picks = collect($this->pickedSessions)
                ->take($sessionsCount)
                ->map(fn (array $session) => ['schedule_id' => $session['schedule_id'], 'date' => $session['date']])
                ->all();
            unset($this->pickedSessions);

            $this->pickNotice = __('Izvēlētajā abonementā ir :count nodarbības, tāpēc atstātas pirmās :count.', ['count' => $sessionsCount]);
        }
    }

    /**
     * Add a class to the membership picks.
     */
    public function addSession(int $scheduleId, string $date): void
    {
        $this->resetErrorBag('picks');
        $this->pickNotice = null;

        if ($this->membershipService === null) {
            $this->addError('picks', __('Vispirms izvēlieties abonementu.'));

            return;
        }

        if (count($this->picks) >= $this->membershipService->sessions_count) {
            $this->addError('picks', __('Visas abonementa nodarbības jau ir izvēlētas.'));

            return;
        }

        if (! Carbon::canBeCreatedFromFormat($date, 'Y-m-d') || $this->isPicked($scheduleId, $date)) {
            return;
        }

        if (! $this->fitsMembershipPeriod($date)) {
            $this->addError('picks', __('Visām nodarbībām jābūt viena mēneša laikā no pirmās nodarbības.'));

            return;
        }

        if (! $this->isPickable($scheduleId, $date)) {
            $this->addError('picks', __('Šis laiks vairs nav pieejams. Lūdzu, izvēlieties citu.'));
            unset($this->days, $this->hasClasses);

            return;
        }

        $this->picks[] = ['schedule_id' => $scheduleId, 'date' => $date];
        unset($this->pickedSessions);
    }

    /**
     * Remove a class from the membership picks.
     */
    public function removeSession(int $scheduleId, string $date): void
    {
        $this->resetErrorBag('picks');
        $this->pickNotice = null;

        $this->picks = array_values(array_filter(
            $this->picks,
            fn (array $pick) => ! ($pick['schedule_id'] === $scheduleId && $pick['date'] === $date),
        ));
        unset($this->pickedSessions);
    }

    /**
     * Open the checkout once every session of the membership is picked.
     */
    public function continueToCheckout(): void
    {
        if (! $this->hasPickedAllSessions) {
            return;
        }

        $this->dispatch('open-membership-checkout', membershipServiceId: $this->membershipService->id, sessions: $this->picks);
    }

    /**
     * Re-render with fresh availability after a booking attempt, dropping picks that can no longer be booked.
     */
    #[On('timetable-refresh')]
    public function refreshTimetable(): void
    {
        unset($this->days, $this->hasClasses, $this->pickedSessions);

        $stillPickable = array_values(array_filter(
            $this->picks,
            fn (array $pick) => $this->isPickable($pick['schedule_id'], $pick['date']),
        ));

        if (count($stillPickable) !== count($this->picks)) {
            $this->picks = $stillPickable;
            $this->pickNotice = __('Kāds no izvēlētajiem laikiem vairs nav pieejams, tāpēc tas noņemts no izvēles. Lūdzu, izvēlieties citu.');
        }
    }

    /**
     * How a class card looks and what its button does in membership mode.
     *
     * @param  array<string, mixed>  $entry
     */
    public function membershipCardState(array $entry): string
    {
        return match (true) {
            $this->isPicked($entry['schedule_id'], $entry['date']) => 'picked',
            $entry['status'] !== SlotStatus::Available => 'unavailable',
            ! $this->fitsMembershipPeriod($entry['date']) => 'outside-period',
            $this->membershipService === null
                || count($this->picks) >= $this->membershipService->sessions_count => 'locked',
            default => 'available',
        };
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

    private function isPicked(int $scheduleId, string $date): bool
    {
        return collect($this->picks)->contains(
            fn (array $pick) => $pick['schedule_id'] === $scheduleId && $pick['date'] === $date,
        );
    }

    /**
     * Whether a date keeps every pick within one month of the earliest one, as the membership period requires.
     */
    private function fitsMembershipPeriod(string $date): bool
    {
        $dates = collect($this->picks)->pluck('date')->push($date);

        return Carbon::parse($dates->max())->lte(Carbon::parse($dates->min())->addMonth());
    }

    /**
     * Whether a class can be used for a membership on that date right now.
     */
    private function isPickable(int $scheduleId, string $date): bool
    {
        $schedule = Schedule::with('service.coach')->find($scheduleId);

        if ($schedule === null || ! $schedule->service->is_membership_eligible || $schedule->service->is_membership) {
            return false;
        }

        return app(ScheduleAvailabilityService::class)->slotStatus($schedule, Carbon::parse($date)) === SlotStatus::Available;
    }

    private function normalizeMode(string $mode): string
    {
        return $mode === self::MEMBERSHIP_MODE ? self::MEMBERSHIP_MODE : self::SINGLE_MODE;
    }

    private function clampWeek(int $week): int
    {
        return max(0, min(ScheduleAvailabilityService::BOOKING_HORIZON_WEEKS, $week));
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

<div @class([
    'container mx-auto pt-36 px-4 space-y-8',
    'pb-12' => ! $this->showsPicksBar,
    'pb-36' => $this->showsPicksBar,
])>
    <flux:heading level="1">{{ __('Nodarbību saraksts') }}</flux:heading>

    {{-- BOOKING TYPE SWITCH --}}
    <flux:radio.group wire:model.live="mode" variant="segmented" class="max-w-md">
        <flux:radio value="{{ $this::SINGLE_MODE }}" :label="__('Vienreizēja nodarbība')"/>
        <flux:radio value="{{ $this::MEMBERSHIP_MODE }}" :label="__('Abonements')"/>
    </flux:radio.group>

    @if($paymentCancelled && ! $this->isMembershipMode)
        <flux:callout icon="information-circle">
            <flux:callout.heading>{{ __('Maksājums netika pabeigts.') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Jūsu vieta tiek turēta 30 minūtes — atgādinājumu ar apmaksas saiti nosūtīsim uz e-pastu.') }}</flux:callout.text>
        </flux:callout>
    @endif

    @if($membershipPaymentCancelled && $this->isMembershipMode)
        <flux:callout icon="information-circle">
            <flux:callout.heading>{{ __('Maksājums netika pabeigts.') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Jūsu izvēlētās nodarbības tiek turētas 30 minūtes — atgādinājumu ar apmaksas saiti nosūtīsim uz e-pastu.') }}</flux:callout.text>
        </flux:callout>
    @endif

    {{-- MEMBERSHIP PRODUCTS --}}
    @if($this->isMembershipMode)
        @if($this->membershipServices->isEmpty())
            <flux:text>{{ __('Abonementi pašlaik nav pieejami.') }}</flux:text>
        @else
            <div class="space-y-3">
                <flux:radio.group wire:model.live="membershipServiceId" variant="cards"
                                  class="grid! grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach($this->membershipServices as $membershipProduct)
                        <flux:radio wire:key="membership-product-{{ $membershipProduct->id }}"
                                    :value="$membershipProduct->id"
                                    :label="$membershipProduct->name"
                                    :description="$membershipProduct->sessions_count . ' ' . __('nodarbības') . ' · ' . price_label($membershipProduct->price)"/>
                    @endforeach
                </flux:radio.group>
                @if($this->membershipService)
                    <flux:text size="sm">
                        {{ __('Pievienojiet :count nodarbības no saraksta. Visām jābūt viena mēneša laikā no pirmās nodarbības.', ['count' => $this->membershipService->sessions_count]) }}
                    </flux:text>
                @endif

                {{-- PICKED SESSIONS --}}
                @if($this->pickedSessions !== [])
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach($this->pickedSessions as $session)
                            <span wire:key="picked-{{ $session['schedule_id'] }}-{{ $session['date'] }}"
                                  class="flex w-full items-center justify-between gap-2 rounded-xl border border-blue px-3 py-1 text-sm">
                                <span>{{ $session['label'] }}</span>
                                <button type="button" class="shrink-0 text-zinc-500 hover:text-black"
                                        wire:click="removeSession({{ $session['schedule_id'] }}, '{{ $session['date'] }}')"
                                        aria-label="{{ __('Noņemt') }}">
                                    <flux:icon.x-mark variant="micro"/>
                                </button>
                            </span>
                        @endforeach
                    </div>
                @endif

            </div>
        @endif
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
        <div wire:key="week-{{ $week }}" class="space-y-4"
             x-data="{
                 day: '{{ $this->initialDay }}',
                 dates: @js(array_column($this->days, 'date')),
                 isFirstWeek: @js($week === 0),
                 isLastWeek: @js($week === \App\Services\ScheduleAvailabilityService::BOOKING_HORIZON_WEEKS),
                 touchStart: null,
                 startSwipe(event) {
                     const touch = event.changedTouches[0];
                     {{-- Swipes from the screen edge belong to the phone's own back / forward gesture. --}}
                     const isFromScreenEdge = touch.clientX < 24 || touch.clientX > window.innerWidth - 24;
                     this.touchStart = isFromScreenEdge ? null : { x: touch.clientX, y: touch.clientY };
                 },
                 endSwipe(event) {
                     if (this.touchStart === null || window.matchMedia('(min-width: 1024px)').matches) {
                         return;
                     }
                     const touch = event.changedTouches[0];
                     const distanceX = touch.clientX - this.touchStart.x;
                     const distanceY = touch.clientY - this.touchStart.y;
                     this.touchStart = null;
                     {{-- Only a clear sideways swipe counts, so scrolling the page never changes the day. --}}
                     if (Math.abs(distanceX) < 50 || Math.abs(distanceX) < Math.abs(distanceY) * 1.5) {
                         return;
                     }
                     this.moveDay(distanceX < 0 ? 1 : -1);
                 },
                 moveDay(direction) {
                     const index = this.dates.indexOf(this.day) + direction;
                     if (index >= 0 && index < this.dates.length) {
                         this.day = this.dates[index];
                     } else if (direction > 0 && ! this.isLastWeek) {
                         $wire.nextWeek();
                     } else if (direction < 0 && ! this.isFirstWeek) {
                         $wire.previousWeek(true);
                     }
                 },
             }">
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

            {{-- DAYS (swipe sideways on small screens to change the day) --}}
            <div class="grid gap-3 lg:grid-cols-7" @touchstart.passive="startSwipe($event)" @touchend.passive="endSwipe($event)">
                @foreach($this->days as $day)
                    <section wire:key="day-{{ $day['date'] }}" class="space-y-3"
                             :class="day === '{{ $day['date'] }}' ? '' : 'max-lg:hidden'">
                        <header class="border-b border-zinc-200 pb-2">
                            <div class="font-heading text-lg font-medium uppercase">{{ $day['weekday'] }}</div>
                            <flux:text size="sm">{{ $day['short_date'] }}</flux:text>
                        </header>

                        @forelse($day['entries'] as $entry)
                            @if($this->isMembershipMode)
                                @php($cardState = $this->membershipCardState($entry))
                                <x-timetable.class-card wire:key="pick-{{ $entry['schedule_id'] }}-{{ $entry['date'] }}"
                                                        :entry="$entry"
                                                        :tone="match ($cardState) { 'picked' => 'picked', 'unavailable', 'outside-period' => 'muted', default => 'normal' }"
                                                        :lines="[
                                                            $entry['coach_name'],
                                                            match (true) {
                                                                in_array($cardState, ['unavailable', 'outside-period'], true) => null,
                                                                $entry['is_exclusive'] => __('Individuāli'),
                                                                $cardState === 'picked' => $this->placesLabel(max(0, $entry['remaining'] - 1)),
                                                                default => $this->placesLabel($entry['remaining']),
                                                            },
                                                        ]">
                                    <x-slot:action>
                                        @if($cardState === 'picked')
                                            <flux:button variant="filled" size="sm" class="lg:w-full" icon="check"
                                                         wire:click="removeSession({{ $entry['schedule_id'] }}, '{{ $entry['date'] }}')">
                                                {{ __('Noņemt') }}
                                            </flux:button>
                                        @elseif(in_array($cardState, ['available', 'locked'], true))
                                            <flux:button variant="primary" size="sm" class="lg:w-full"
                                                         :disabled="$cardState === 'locked'"
                                                         wire:click="addSession({{ $entry['schedule_id'] }}, '{{ $entry['date'] }}')">
                                                {{ __('Pievienot') }}
                                            </flux:button>
                                        @else
                                            <span class="text-sm font-medium uppercase">
                                                {{ $cardState === 'outside-period' ? __('Ārpus perioda') : $this->statusLabel($entry['status']) }}
                                            </span>
                                        @endif
                                    </x-slot:action>
                                </x-timetable.class-card>
                            @else
                                @php($isBookable = $entry['status'] === \App\Enums\SlotStatus::Available)
                                <x-timetable.class-card wire:key="slot-{{ $entry['schedule_id'] }}-{{ $entry['date'] }}"
                                                        :entry="$entry"
                                                        :tone="$isBookable ? 'normal' : 'muted'"
                                                        :lines="[
                                                            $entry['coach_name'],
                                                            $entry['has_price_range'] ? __('no').' '.Number::currency($entry['price_from'] / 100, 'EUR') : price_label($entry['price_from']),
                                                            match (true) {
                                                                ! $isBookable => null,
                                                                $entry['is_exclusive'] => __('Individuāli'),
                                                                default => $this->placesLabel($entry['remaining']),
                                                            },
                                                        ]">
                                    <x-slot:action>
                                        @if($isBookable)
                                            <flux:button variant="primary" size="sm" class="lg:w-full"
                                                         wire:click="$dispatch('open-booking-checkout', { scheduleId: {{ $entry['schedule_id'] }}, date: '{{ $entry['date'] }}' })">
                                                {{ __('Pieteikties') }}
                                            </flux:button>
                                        @else
                                            <span class="text-sm font-medium uppercase">{{ $this->statusLabel($entry['status']) }}</span>
                                        @endif
                                    </x-slot:action>
                                </x-timetable.class-card>
                            @endif
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

    {{-- MEMBERSHIP PROGRESS BAR --}}
    @if($this->showsPicksBar)
        <div class="fixed inset-x-0 bottom-0 z-40 border-t border-zinc-200 bg-white shadow-lg">
            <div class="container mx-auto space-y-1 px-4 py-3">
                <div class="flex flex-col items-center gap-2 sm:flex-row sm:justify-between">
                    @if($this->hasPickedAllSessions)
                        <div class="flex items-center gap-2 font-medium">
                            <flux:icon.check-circle variant="mini" class="text-blue"/>
                            {{ $this->membershipService->sessions_count === 1
                                ? __('Nodarbība izvēlēta')
                                : __('Visas :count nodarbības izvēlētas', ['count' => $this->membershipService->sessions_count]) }}
                            · {{ price_label($this->membershipService->price) }}
                        </div>
                        <flux:button variant="primary" size="sm" wire:click="continueToCheckout">
                            {{ __('Turpināt') }}
                        </flux:button>
                    @else
                        <div class="font-medium">
                            {{ __('Izvēlētas :picked no :total', ['picked' => count($picks), 'total' => $this->membershipService->sessions_count]) }}
                        </div>
                    @endif
                </div>

                @error('picks')
                <flux:text class="text-center text-sm text-red-500 sm:text-left">{{ $message }}</flux:text>
                @enderror
                @if($pickNotice)
                    <flux:text class="text-center text-sm sm:text-left">{{ $pickNotice }}</flux:text>
                @endif
            </div>
        </div>
    @endif

    <livewire:booking.booking-checkout wire:key="booking-checkout" />
    <livewire:membership.membership-checkout wire:key="membership-checkout" />
</div>
