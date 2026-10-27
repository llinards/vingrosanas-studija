<?php

use App\Enums\SlotStatus;
use App\Livewire\Concerns\ShowsWeekTimetable;
use App\Models\Schedule;
use App\Models\Service;
use App\Services\ScheduleAvailabilityService;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component
{
    use ShowsWeekTimetable;

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
        return Service::with('serviceType')
            ->where('is_membership', true)
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

    public function updatedMode(): void
    {
        $this->mode = $this->normalizeMode($this->mode);
    }

    /**
     * Accept only a product on sale. Picks from another category are dropped, and the earliest
     * picks are kept when the new product has fewer sessions.
     */
    public function updatedMembershipServiceId(): void
    {
        $this->resetErrorBag('picks');
        $this->pickNotice = null;
        unset($this->days);

        if ($this->membershipService === null) {
            $this->membershipServiceId = null;

            return;
        }

        $notices = [];
        $coveredPicks = $this->picksCoveredBy($this->membershipService);

        if (count($coveredPicks) !== count($this->picks)) {
            $this->picks = $coveredPicks;
            unset($this->pickedSessions);

            $notices[] = __('Šis abonements ir derīgs tikai kategorijas „:category” nodarbībām, tāpēc pārējās noņemtas no izvēles.', [
                'category' => $this->membershipService->serviceType?->name,
            ]);
        }

        $sessionsCount = $this->membershipService->sessions_count;

        if (count($this->picks) > $sessionsCount) {
            $this->picks = collect($this->pickedSessions)
                ->take($sessionsCount)
                ->map(fn (array $session) => ['schedule_id' => $session['schedule_id'], 'date' => $session['date']])
                ->all();
            unset($this->pickedSessions);

            $notices[] = __('Izvēlētajā abonementā ir :count nodarbības, tāpēc atstātas pirmās :count.', ['count' => $sessionsCount]);
        }

        $this->pickNotice = $notices === [] ? null : implode(' ', $notices);
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
            unset($this->days);

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
        unset($this->days, $this->pickedSessions);

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
     * What a class card shows and what its button does.
     *
     * @param  array<string, mixed>  $entry
     * @return array{key: string, tone: string, lines: list<?string>, button: ?array{label: string, action: string, variant: string, icon?: string, disabled?: bool}, status: ?string}
     */
    public function cardFor(array $entry): array
    {
        $slot = $entry['schedule_id'].', \''.$entry['date'].'\'';

        if (! $this->isMembershipMode) {
            $isBookable = $entry['status'] === SlotStatus::Available;

            return [
                'key' => 'slot-'.$entry['schedule_id'].'-'.$entry['date'],
                'tone' => $isBookable ? 'normal' : 'muted',
                'lines' => [
                    $entry['coach_name'],
                    $entry['has_price_range'] ? __('no').' '.Number::currency($entry['price_from'] / 100, 'EUR') : price_label($entry['price_from']),
                    match (true) {
                        ! $isBookable => null,
                        $entry['is_exclusive'] => __('Individuāli'),
                        default => $this->placesLabel($entry['remaining']),
                    },
                ],
                'button' => $isBookable ? [
                    'label' => __('Pieteikties'),
                    'action' => '$dispatch(\'open-booking-checkout\', { scheduleId: '.$entry['schedule_id'].', date: \''.$entry['date'].'\' })',
                    'variant' => 'primary',
                ] : null,
                'status' => $isBookable ? null : $this->statusLabel($entry['status']),
            ];
        }

        $state = $this->membershipCardState($entry);

        return [
            'key' => 'pick-'.$entry['schedule_id'].'-'.$entry['date'],
            'tone' => match ($state) {
                'picked' => 'picked',
                'unavailable', 'outside-period' => 'muted',
                default => 'normal',
            },
            'lines' => [
                $entry['coach_name'],
                match (true) {
                    in_array($state, ['unavailable', 'outside-period'], true) => null,
                    $entry['is_exclusive'] => __('Individuāli'),
                    $state === 'picked' => $this->placesLabel(max(0, $entry['remaining'] - 1)),
                    default => $this->placesLabel($entry['remaining']),
                },
            ],
            'button' => match ($state) {
                'picked' => ['label' => __('Noņemt'), 'action' => "removeSession({$slot})", 'variant' => 'filled', 'icon' => 'check'],
                'available', 'locked' => ['label' => __('Pievienot'), 'action' => "addSession({$slot})", 'variant' => 'primary', 'disabled' => $state === 'locked'],
                default => null,
            },
            'status' => match ($state) {
                'outside-period' => __('Ārpus perioda'),
                'unavailable' => $this->statusLabel($entry['status']),
                default => null,
            },
        ];
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
     * The picks whose class belongs to the given membership's category.
     *
     * @return list<array{schedule_id: int, date: string}>
     */
    private function picksCoveredBy(Service $membership): array
    {
        $schedules = Schedule::with('service')
            ->whereIn('id', array_column($this->picks, 'schedule_id'))
            ->get()
            ->keyBy('id');

        return array_values(array_filter(
            $this->picks,
            fn (array $pick) => $schedules->get($pick['schedule_id'])?->service->isCoveredBy($membership) ?? false,
        ));
    }

    /**
     * Whether a class can be used for the chosen membership on that date right now.
     */
    private function isPickable(int $scheduleId, string $date): bool
    {
        $schedule = Schedule::with('service.coach')->find($scheduleId);

        if ($schedule === null || $this->membershipService === null || ! $schedule->service->isCoveredBy($this->membershipService)) {
            return false;
        }

        return app(ScheduleAvailabilityService::class)->slotStatus($schedule, Carbon::parse($date)) === SlotStatus::Available;
    }

    private function normalizeMode(string $mode): string
    {
        return $mode === self::MEMBERSHIP_MODE ? self::MEMBERSHIP_MODE : self::SINGLE_MODE;
    }

    protected function firstWeek(): int
    {
        return 0;
    }

    protected function lastWeek(): int
    {
        return ScheduleAvailabilityService::BOOKING_HORIZON_WEEKS;
    }

    /**
     * Membership mode lists only the classes a membership can be used for, of the chosen membership's category.
     */
    protected function classesForWeek(Carbon $weekStart): array
    {
        return app(ScheduleAvailabilityService::class)->weekTimetable(
            $weekStart,
            membershipEligibleOnly: $this->isMembershipMode,
            serviceTypeId: $this->isMembershipMode ? $this->membershipService?->service_type_id : null,
        );
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

    {{-- WEEK OF CLASSES --}}
    <x-timetable.week :days="$this->days" :week="$week" :week-start="$this->weekStart"
                      :initial-day="$this->initialDay" :is-first-week="$this->isFirstWeek"
                      :is-last-week="$this->isLastWeek" :card-for="$this->cardFor(...)"/>

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
