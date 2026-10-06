<?php

use App\Actions\CreateMembershipCheckoutSession;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Membership;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\ServiceType;
use App\Services\ScheduleAvailabilityService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Stripe\Exception\ApiErrorException;

new class extends Component {
    public int $step = 1;

    // --- Membership flow properties ---

    public ?int $selectedMembershipServiceId = null;

    public ?int $session_service_type_id = null;

    public ?int $session_service_id = null;

    public ?string $session_date = null;

    public ?int $session_schedule_id = null;

    /**
     * @var array<int, array{service_id: int, service_name: string, coach_name: string, schedule_id: int, date: string, time: string}>
     */
    public array $sessions = [];

    // --- Shared properties ---

    public string $name = '';

    public string $surname = '';

    public string $phone = '';

    public string $email = '';

    public bool $isProcessing = false;

    // ========================================
    // MEMBERSHIP FLOW - Computed Properties
    // ========================================

    /**
     * Get all active membership services for the tier selection step.
     */
    #[Computed]
    public function membershipServices(): Collection
    {
        return Service::where('is_membership', true)
                      ->where('is_active', true)
                      ->orderBy('sessions_count')
                      ->get();
    }

    /**
     * Get the selected membership service.
     */
    #[Computed]
    public function membershipService(): ?Service
    {
        return $this->selectedMembershipServiceId ? Service::find($this->selectedMembershipServiceId) : null;
    }

    /**
     * Get service types that have membership-eligible services.
     */
    #[Computed]
    public function sessionServiceTypes(): Collection
    {
        return ServiceType::whereHas('services', function ($query) {
            $query->where('is_active', true)
                  ->where('is_membership_eligible', true)
                  ->whereHas('schedules', fn($q) => $q->where('is_active', true));
        })->get();
    }

    /**
     * Get membership-eligible services filtered by service type.
     */
    #[Computed]
    public function sessionFilteredServices(): Collection
    {
        if ( ! $this->session_service_type_id) {
            return new Collection;
        }

        return Service::with('coach')
                      ->where('is_active', true)
                      ->where('is_membership_eligible', true)
                      ->where('service_type_id', $this->session_service_type_id)
                      ->whereHas('schedules', fn($query) => $query->where('is_active', true))
                      ->get();
    }

    /**
     * Get active schedules for the selected session service.
     */
    #[Computed]
    public function sessionActiveSchedules(): Collection
    {
        if ( ! $this->session_service_id) {
            return new Collection;
        }

        return Schedule::with('service.coach')
                       ->where('service_id', $this->session_service_id)
                       ->where('is_active', true)
                       ->get();
    }

    /**
     * The first selected session date anchors the membership period; null until one is added.
     */
    #[Computed]
    public function membershipAnchorDate(): ?Carbon
    {
        if (count($this->sessions) === 0) {
            return null;
        }

        return collect($this->sessions)
            ->map(fn (array $session) => Carbon::parse($session['date']))
            ->min();
    }

    /**
     * Earliest date selectable in the session calendar: today until the first session anchors it.
     */
    #[Computed]
    public function sessionWindowStart(): Carbon
    {
        return $this->membershipAnchorDate() ?? Carbon::today();
    }

    /**
     * Latest date selectable: three months ahead for the first session, then one month from the anchor.
     */
    #[Computed]
    public function sessionWindowEnd(): Carbon
    {
        return $this->membershipAnchorDate()?->copy()->addMonth() ?? Carbon::today()->addMonths(3);
    }

    /**
     * Get unavailable dates for the membership session builder calendar.
     */
    #[Computed]
    public function sessionUnavailableDates(): string
    {
        return app(ScheduleAvailabilityService::class)->unavailableDates(
            schedules: $this->sessionActiveSchedules,
            startDate: $this->sessionWindowStart(),
            endDate: $this->sessionWindowEnd(),
            extraBookings: $this->sessions,
        );
    }

    /**
     * Get available time slots for the membership session builder.
     *
     * @return array<int, array{schedule_id: int, start_time: string, coach_name: string, remaining: int, max_capacity: int}>
     */
    #[Computed]
    public function sessionAvailableTimeSlots(): array
    {
        if ( ! $this->session_date || ! $this->session_service_id) {
            return [];
        }

        return app(ScheduleAvailabilityService::class)->availableTimeSlots(
            schedules: $this->sessionActiveSchedules,
            date: Carbon::parse($this->session_date),
            extraBookings: $this->sessions,
        );
    }

    // ========================================
    // WATCHERS
    // ========================================

    public function updatedSessionServiceTypeId(): void
    {
        $this->session_service_id  = null;
        $this->session_date        = null;
        $this->session_schedule_id = null;
        unset($this->sessionFilteredServices, $this->sessionActiveSchedules, $this->sessionUnavailableDates, $this->sessionAvailableTimeSlots);
    }

    public function updatedSessionServiceId(): void
    {
        $this->session_date        = null;
        $this->session_schedule_id = null;
        unset($this->sessionActiveSchedules, $this->sessionUnavailableDates, $this->sessionAvailableTimeSlots);
    }

    public function updatedSessionDate(): void
    {
        $this->session_schedule_id = null;
        unset($this->sessionAvailableTimeSlots);
    }

    // ========================================
    // NAVIGATION
    // ========================================

    public function goToStep(int $step): void
    {
        if ($step < $this->step) {
            $this->step = $step;
        }
    }

    public function nextStep(): void
    {
        if ($this->step === 1) {
            $this->validate([
                'selectedMembershipServiceId' => ['required', 'integer', 'exists:services,id'],
            ], [
                'selectedMembershipServiceId.required' => __('Jums ir jāizvēlās abonements.'),
            ]);
        }

        if ($this->step === 2) {
            if (count($this->sessions) !== $this->membershipService?->sessions_count ?? 0) {
                $this->addError('sessions', __('Jums ir jāizvēlās :count nodarbības.',
                    ['count' => $this->membershipService?->sessions_count ?? 0]));

                return;
            }
        }

        if ($this->step === 3) {
            $this->validate([
                'name'    => ['required', 'string', 'max:255'],
                'surname' => ['required', 'string', 'max:255'],
                'phone'   => ['required', 'string', 'max:50'],
                'email'   => ['required', 'email', 'max:255'],
            ], [
                'name.required'    => __('Vārds ir obligāts.'),
                'name.max'         => __('Vārds nedrīkst pārsniegt 255 rakstzīmes.'),
                'surname.required' => __('Uzvārds ir obligāts.'),
                'surname.max'      => __('Uzvārds nedrīkst pārsniegt 255 rakstzīmes.'),
                'phone.required'   => __('Tālrunis ir obligāts.'),
                'phone.max'        => __('Tālrunis nedrīkst pārsniegt 50 rakstzīmes.'),
                'email.required'   => __('E-pasts ir obligāts.'),
                'email.email'      => __('E-pastam jābūt derīgai e-pasta adresei.'),
                'email.max'        => __('E-pasts nedrīkst pārsniegt 255 rakstzīmes.'),
            ]);
        }

        $this->step++;
    }

    public function previousStep(): void
    {
        $this->step--;
    }

    // ========================================
    // MEMBERSHIP SESSION BUILDER
    // ========================================

    /**
     * Add the currently selected session to the sessions array.
     */
    public function addSession(): void
    {
        $this->validate([
            'session_service_id'  => ['required', 'exists:services,id'],
            'session_date'        => [
                'required', 'date',
                'after_or_equal:'.$this->sessionWindowStart()->toDateString(),
                'before_or_equal:'.$this->sessionWindowEnd()->toDateString(),
            ],
            'session_schedule_id' => ['required', 'exists:schedules,id'],
        ], [
            'session_service_id.required'  => __('Jums ir jāizvēlās pakalpojums.'),
            'session_date.required'        => __('Datums ir obligāts.'),
            'session_date.after_or_equal'  => __('Datumam jābūt abonementa periodā.'),
            'session_date.before_or_equal' => __('Datumam jābūt abonementa periodā.'),
            'session_schedule_id.required' => __('Laika slots ir obligāts.'),
        ]);

        // Prevent duplicate sessions (same schedule on the same date)
        $isDuplicate = collect($this->sessions)->contains(
            fn(array $session) => $session['schedule_id'] === $this->session_schedule_id
                                  && $session['date'] === $this->session_date
        );

        if ($isDuplicate) {
            $this->addError('session_schedule_id', __('Šī nodarbība jau ir pievienota.'));

            return;
        }

        $schedule = Schedule::with('service.coach')->findOrFail($this->session_schedule_id);

        $this->sessions[] = [
            'service_id'   => $schedule->service_id,
            'service_name' => $schedule->service->name,
            'coach_name'   => $schedule->service->coach?->name ?? '',
            'schedule_id'  => $schedule->id,
            'date'         => $this->session_date,
            'time'         => substr((string) $schedule->start_time, 0, 5),
        ];

        // The first session anchors the one-month window, so recompute it.
        unset($this->membershipAnchorDate, $this->sessionWindowStart, $this->sessionWindowEnd);

        // Auto-advance to customer info when all sessions are selected
        if (count($this->sessions) === $this->membershipService?->sessions_count) {
            $this->step = 3;

            return;
        }

        // Reset session builder for next selection
        $this->session_service_type_id = null;
        $this->session_service_id      = null;
        $this->session_date            = null;
        $this->session_schedule_id     = null;
        unset($this->sessionFilteredServices, $this->sessionActiveSchedules, $this->sessionUnavailableDates, $this->sessionAvailableTimeSlots);
    }

    /**
     * Remove a session from the selected sessions.
     */
    public function removeSession(int $index): void
    {
        unset($this->sessions[$index]);
        $this->sessions = array_values($this->sessions);

        // Removing a session may move the anchor, so recompute the window.
        unset($this->membershipAnchorDate, $this->sessionWindowStart, $this->sessionWindowEnd, $this->sessionUnavailableDates);
    }

    // ========================================
    // SUBMIT ACTIONS
    // ========================================

    public function submitMembership(): void
    {
        if ($this->isProcessing) {
            return;
        }
        $this->isProcessing = true;

        $membershipService = $this->membershipService;
        $price             = $this->membershipService?->price ?? 0;

        if ( ! $membershipService || count($this->sessions) !== $membershipService->sessions_count) {
            $this->isProcessing = false;

            return;
        }

        // Period: one month from the earliest selected session (sessions are bookable up to 3 months ahead).
        $periodStart = $this->membershipAnchorDate()?->copy()->startOfDay() ?? today();
        $periodEnd   = $periodStart->copy()->addMonth();

        $latestSession = collect($this->sessions)
            ->map(fn (array $session) => Carbon::parse($session['date']))
            ->max();

        if ($periodStart->lt(today())
            || $periodStart->gt(today()->addMonths(3))
            || ($latestSession && $latestSession->gt($periodEnd))) {
            $this->isProcessing = false;
            $this->addError('sessions', __('Abonementa nodarbības nav derīgā periodā.'));
            $this->step = 2;

            return;
        }

        $result = DB::transaction(function () use ($membershipService, $price, $periodStart, $periodEnd) {
            // Verify capacity for all sessions
            foreach ($this->sessions as $session) {
                $schedule = Schedule::lockForUpdate()->findOrFail($session['schedule_id']);

                $bookedParticipants = Booking::active()
                                             ->where('schedule_id', $session['schedule_id'])
                                             ->whereDate('booking_date', $session['date'])
                                             ->sum('participant_count');

                $remaining = $schedule->max_capacity - $bookedParticipants;

                if ($remaining < 1) {
                    return null;
                }
            }

            $membership = Membership::create([
                'email'          => $this->email,
                'name'           => $this->name,
                'surname'        => $this->surname,
                'phone'          => $this->phone,
                'service_id'     => $membershipService->id,
                'price'          => $price,
                'sessions_total' => $membershipService->sessions_count,
                'period_start'   => $periodStart,
                'period_end'     => $periodEnd,
                'payment_status' => PaymentStatus::Pending,
                'expires_at'     => now()->addMinutes(30),
            ]);

            foreach ($this->sessions as $session) {
                Booking::create([
                    'membership_id'     => $membership->id,
                    'schedule_id'       => $session['schedule_id'],
                    'booking_date'      => $session['date'],
                    'name'              => $this->name,
                    'surname'           => $this->surname,
                    'phone'             => $this->phone,
                    'email'             => $this->email,
                    'participant_count' => 1,
                    'payment_status'    => PaymentStatus::Paid,
                ]);
            }

            return $membership;
        });

        if ( ! $result) {
            $this->isProcessing = false;
            $this->addError('sessions',
                __('Kāds no izvēlētajiem laikiem vairs nav pieejams. Lūdzu, pārbaudiet un mēģiniet vēlreiz.'));
            $this->step = 2;

            return;
        }

        try {
            $checkout = app(CreateMembershipCheckoutSession::class)->execute($result);
        } catch (ApiErrorException $e) {
            $this->isProcessing = false;
            $this->addError('membership', __('Maksājuma sistēma pašlaik nav pieejama. Lūdzu, mēģiniet vēlreiz.'));
            $this->step = 3;

            return;
        }

        $this->redirect($checkout['url'], navigate: false);
    }

    // ========================================
    // RESET
    // ========================================

    public function resetModal(): void
    {
        $this->reset();
        unset(
            $this->membershipServices,
            $this->membershipService,
            $this->sessionServiceTypes,
            $this->sessionFilteredServices,
            $this->sessionActiveSchedules,
            $this->sessionUnavailableDates,
            $this->sessionAvailableTimeSlots,
        );
    }
};
?>

<div id="bookingModal">
    <flux:modal name="booking-modal" :dismissible="false" class="w-[calc(100vw-2rem)] max-w-lg"
                @close="$wire.resetModal()">
        <div class="space-y-6 p-6 md:p-8">

            {{-- STEP INDICATOR --}}
            @php
                $stepLabels = [1 => __('Abonements'), 2 => __('Nodarbības'), 3 => __('Dati'), 4 => __('Maksājums')];
            @endphp
            <div class="flex items-start justify-center gap-1 md:gap-2 mt-8 md:mt-0">
                @for($i = 1; $i <= 4; $i++)
                    <div class="flex items-start gap-1 md:gap-2">
                        <div class="flex flex-col items-center gap-1">
                            <div @class([
                                'flex size-7 md:size-8 items-center justify-center rounded-full text-xs md:text-sm font-medium transition-colors',
                                'bg-blue text-white' => $step === $i,
                                'bg-green-500 text-white cursor-pointer hover:bg-green-600' => $step > $i,
                                'bg-zinc-200 text-zinc-500' => $step < $i,
                            ])
                                 @if($step > $i) wire:click="goToStep({{ $i }})" @endif
                            >
                                @if($step > $i)
                                    <flux:icon.check class="size-3 md:size-4"/>
                                @else
                                    {{ $i }}
                                @endif
                            </div>
                            <span @class([
                                'text-[10px] md:text-xs whitespace-nowrap',
                                'text-blue font-medium' => $step === $i,
                                'text-green-600' => $step > $i,
                                'text-zinc-400' => $step < $i,
                            ])>{{ $stepLabels[$i] }}</span>
                        </div>
                        @if($i < 4)
                            <div @class([
                                'h-px w-4 md:w-8 mt-3.5 md:mt-4',
                                'bg-green-500' => $step > $i,
                                'bg-zinc-200' => $step <= $i,
                            ])>
                            </div>
                        @endif
                    </div>
                @endfor
            </div>

            {{-- SELECTION SUMMARY (shown on steps 2-3) --}}
            @if($step >= 2 && $step <= 3)
                <div class="rounded-lg border border-zinc-200 p-3 space-y-1">
                    @if($this->membershipService)
                        <div class="flex justify-between gap-2 text-sm">
                            <flux:text class="text-zinc-500 shrink-0">{{ __('Abonements') }}</flux:text>
                            <flux:text class="font-medium truncate">{{ $this->membershipService->name }}</flux:text>
                        </div>
                        <div class="flex justify-between gap-2 text-sm">
                            <flux:text class="text-zinc-500 shrink-0">{{ __('Cena') }}</flux:text>
                            <flux:text class="font-medium">{{ Number::currency(($this->membershipService->price ?? 0) / 100, 'EUR') }}</flux:text>
                        </div>
                    @endif
                </div>
            @endif

            {{-- STEP 1: SERVICE SELECTION --}}
            @if($step === 1)
                <div class="space-y-6">
                    {{-- Membership: tier selection --}}
                    <flux:radio.group wire:model.live="selectedMembershipServiceId" variant="cards" class="grid grid-cols-2 gap-2">
                        @foreach($this->membershipServices as $membershipOption)
                            <flux:radio
                                :value="$membershipOption->id"
                                :label="$membershipOption->name"
                                :description="Number::currency($membershipOption->price / 100, 'EUR') . ' / ' . __('mēnesī')"
                            />
                        @endforeach
                    </flux:radio.group>

                    <div class="flex justify-between">
                        <flux:modal.close>
                            <flux:button class="btn btn-sm btn-tertiary">{{ __('Atcelt') }}</flux:button>
                        </flux:modal.close>
                        <flux:button wire:click="nextStep"
                                     class="btn btn-sm btn-primary">{{ __('Tālāk') }}</flux:button>
                    </div>
                </div>
            @endif

            {{-- STEP 2: DATE & TIME / SESSION SELECTION --}}
            @if($step === 2)
                {{-- MEMBERSHIP: Session selection --}}
                <div class="space-y-6">
                    <flux:heading size="lg">
                        {{ __('Izvēlieties nodarbības') }}
                        <flux:badge size="sm"
                                    color="{{ count($sessions) === $this->membershipService?->sessions_count ?? 0 ? 'green' : 'zinc' }}">
                            {{ count($sessions) }}/{{ $this->membershipService?->sessions_count ?? 0 }}
                        </flux:badge>
                    </flux:heading>

                    {{-- Selected sessions list --}}
                    @if(count($sessions) > 0)
                        <div class="space-y-2">
                            @foreach($sessions as $index => $session)
                                <div class="flex items-center justify-between rounded-lg border p-3"
                                     wire:key="session-{{ $index }}">
                                    <div>
                                        <flux:text
                                            class="font-medium">{{ $session['service_name'] }}</flux:text>
                                        <flux:text class="text-sm text-zinc-500">
                                            {{ Carbon::parse($session['date'])->format('d.m.Y') }} {{ $session['time'] }}
                                            — {{ $session['coach_name'] }}
                                        </flux:text>
                                    </div>
                                    <flux:button wire:click="removeSession({{ $index }})" variant="ghost"
                                                 size="sm" icon="x-mark"/>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Session builder (only if more sessions needed) --}}
                    @if(count($sessions) < $this->membershipService?->sessions_count ?? 0)
                        <flux:separator/>

                        <flux:select wire:model.live="session_service_type_id" :label="__('Nodarbība')">
                            <flux:select.option value="">{{ __('Izvēlieties nodarbību') }}</flux:select.option>
                            @foreach($this->sessionServiceTypes as $type)
                                <flux:select.option :value="$type->id">{{ $type->name }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        @if($this->session_service_type_id)
                            <flux:select wire:model.live="session_service_id" :label="__('Treniņš')">
                                <flux:select.option
                                    value="">{{ __('Izvēlieties treniņu') }}</flux:select.option>
                                @foreach($this->sessionFilteredServices as $service)
                                    <flux:select.option :value="$service->id">
                                        {{ $service->name }} ({{ $service->coach->name }})
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                        @endif

                        @if($this->session_service_id)
                            <div class="flex justify-center">
                                <flux:calendar wire:model.live="session_date"
                                               wire:key="session-cal-{{ $this->sessionWindowStart()->toDateString() }}"
                                               :min="$this->sessionWindowStart()->toDateString()"
                                               :max="$this->sessionWindowEnd()->toDateString()"
                                               :open-to="$this->sessionWindowStart()->toDateString()"
                                               :unavailable="$this->sessionUnavailableDates" locale="lv"
                                               start-day="1"/>
                            </div>
                        @endif

                        @if($this->session_date && count($this->sessionAvailableTimeSlots) > 0)
                            <flux:radio.group wire:model.live="session_schedule_id"
                                              :label="__('Pieejamie laiki')" variant="cards">
                                @foreach($this->sessionAvailableTimeSlots as $slot)
                                    <flux:radio :value="$slot['schedule_id']"
                                                :label="$slot['start_time'] . ' — ' . $slot['coach_name']"
                                                :description="$slot['remaining'] . ' ' . ($slot['remaining'] === 1 ? __('vieta') : __('vietas'))"/>
                                @endforeach
                            </flux:radio.group>
                        @elseif($this->session_date)
                            <flux:text
                                class="text-center">{{ __('Šajā datumā nav pieejamu laiku.') }}</flux:text>
                        @endif

                    @endif

                    @error('sessions')
                    <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text>
                    @enderror
                    <div class="flex justify-between">
                        <flux:button wire:click="previousStep"
                                     class="btn btn-sm btn-tertiary">{{ __('Atpakaļ') }}</flux:button>
                        @if(count($sessions) === ($this->membershipService?->sessions_count ?? 0))
                            <flux:button wire:click="nextStep"
                                         class="btn btn-sm btn-primary">{{ __('Tālāk') }}</flux:button>
                        @elseif($this->session_schedule_id)
                            <flux:button wire:click="addSession" class="btn btn-sm btn-primary">
                                {{ __('Pievienot nodarbību') }} ({{ count($sessions) + 1 }}
                                /{{ $this->membershipService?->sessions_count ?? 0 }})
                            </flux:button>
                        @endif
                    </div>
                </div>
            @endif

            {{-- STEP 3: CUSTOMER INFO --}}
            @if($step === 3)
                <div class="space-y-6">
                    <flux:heading size="lg">{{ __('Jūsu informācija') }}</flux:heading>

                    <flux:input wire:model="name" :label="__('Vārds')" :placeholder="__('Ievadiet vārdu')"/>
                    <flux:input wire:model="surname" :label="__('Uzvārds')" :placeholder="__('Ievadiet uzvārdu')"/>
                    <flux:input wire:model="phone" :label="__('Tālrunis')" :placeholder="__('Ievadiet tālruni')"/>
                    <flux:input wire:model="email" type="email" :label="__('E-pasts')"
                                :placeholder="__('Ievadiet e-pastu')"/>

                    @error('membership')
                    <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text>
                    @enderror

                    <div class="flex justify-between">
                        <flux:button wire:click="previousStep"
                                     class="btn btn-sm btn-tertiary">{{ __('Atpakaļ') }}</flux:button>
                        <flux:button wire:click="nextStep"
                                     class="btn btn-sm btn-primary">{{ __('Tālāk') }}</flux:button>
                    </div>
                </div>
            @endif

            {{-- STEP 4: CONFIRMATION --}}
            @if($step === 4)
                <div class="space-y-6">
                    <flux:heading size="lg">{{ __('Apstiprinājums') }}</flux:heading>

                    {{-- MEMBERSHIP CONFIRMATION --}}
                    <div class="p-4 space-y-2">
                        <div class="flex justify-between">
                            <flux:text class="font-medium">{{ __('Abonements') }}</flux:text>
                            <flux:text>{{ $this->membershipService->name }}</flux:text>
                        </div>
                        <flux:separator/>
                        <div class="flex justify-between">
                            <flux:text class="font-medium">{{ __('Cena') }}</flux:text>
                            <flux:text
                                class="font-semibold">{{ Number::currency(($this->membershipService?->price ?? 0) / 100, 'EUR') }}</flux:text>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <flux:text class="font-medium">{{ __('Nodarbības:') }}</flux:text>
                        @foreach($sessions as $session)
                            <div class="flex items-center justify-between rounded-lg border p-3">
                                <div>
                                    <flux:text class="font-medium">{{ $session['service_name'] }}</flux:text>
                                    <flux:text class="text-sm text-zinc-500">
                                        {{ Carbon::parse($session['date'])->format('d.m.Y') }} {{ $session['time'] }}
                                        — {{ $session['coach_name'] }}
                                    </flux:text>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="p-4 space-y-1">
                        <flux:text class="font-medium">{{ $this->name }} {{ $this->surname }}</flux:text>
                        <flux:text>{{ $this->phone }}</flux:text>
                        <flux:text>{{ $this->email }}</flux:text>
                    </div>

                    <div class="flex justify-between">
                        <flux:button wire:click="previousStep"
                                     class="btn btn-sm btn-tertiary">{{ __('Atpakaļ') }}</flux:button>
                        <flux:button wire:click="submitMembership"
                                     class="btn btn-sm btn-primary"
                                     :disabled="$isProcessing">{{ __('Apmaksāt ar karti') }}</flux:button>
                    </div>
                </div>
            @endif
        </div>
    </flux:modal>
</div>
