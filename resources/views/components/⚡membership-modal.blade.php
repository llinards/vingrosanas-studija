<?php

use App\Actions\CreateMembershipCheckoutSession;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Membership;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\ServiceType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public int $step = 1;

    /** @var int|null Selected membership service ID */
    public ?int $selectedServiceId = null;

    /**
     * Currently selected session builder fields.
     */
    public ?int $session_service_type_id = null;

    public ?int $session_service_id = null;

    public ?string $session_date = null;

    public ?int $session_schedule_id = null;

    /**
     * Array of selected sessions.
     *
     * @var array<int, array{service_id: int, service_name: string, coach_name: string, schedule_id: int, date: string, time: string}>
     */
    public array $sessions = [];

    /**
     * Customer info.
     */
    public string $name = '';

    public string $surname = '';

    public string $phone = '';

    public string $email = '';

    /**
     * Get the selected membership service.
     */
    #[Computed]
    public function membershipService(): ?Service
    {
        return $this->selectedServiceId ? Service::find($this->selectedServiceId) : null;
    }

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
     * Get the total sessions required for the selected membership.
     */
    #[Computed]
    public function requiredSessions(): int
    {
        return $this->membershipService?->sessions_count ?? 0;
    }

    /**
     * Get the price for the selected membership service.
     */
    #[Computed]
    public function tierPrice(): int
    {
        return $this->membershipService?->price ?? 0;
    }

    /**
     * Get service types that have membership-eligible services.
     */
    #[Computed]
    public function serviceTypes(): Collection
    {
        return ServiceType::whereHas('services', function ($query) {
            $query->where('is_active', true)
                ->where('is_membership_eligible', true)
                ->whereHas('schedules', fn ($q) => $q->where('is_active', true));
        })->get();
    }

    /**
     * Get membership-eligible services filtered by service type.
     */
    #[Computed]
    public function filteredServices(): Collection
    {
        if (! $this->session_service_type_id) {
            return new Collection;
        }

        return Service::with('coach')
            ->where('is_active', true)
            ->where('is_membership_eligible', true)
            ->where('service_type_id', $this->session_service_type_id)
            ->whereHas('schedules', fn ($query) => $query->where('is_active', true))
            ->get();
    }

    /**
     * Get active schedules for the selected service.
     */
    #[Computed]
    public function activeSchedules(): Collection
    {
        if (! $this->session_service_id) {
            return new Collection;
        }

        return Schedule::with('service.coach')
            ->where('service_id', $this->session_service_id)
            ->where('is_active', true)
            ->get();
    }

    /**
     * Get available time slots for the selected date.
     *
     * @return array<int, array{schedule_id: int, start_time: string, coach_name: string, remaining: int}>
     */
    #[Computed]
    public function availableTimeSlots(): array
    {
        if (! $this->session_date || ! $this->session_service_id) {
            return [];
        }

        $date = Carbon::parse($this->session_date);
        $schedules = $this->activeSchedules;
        $slots = [];
        $isToday = $date->isToday();
        $now = now();

        // Count already selected sessions for same schedule+date
        $selectedForDate = collect($this->sessions)
            ->where('date', $this->session_date)
            ->pluck('schedule_id')
            ->countBy()
            ->all();

        foreach ($schedules as $schedule) {
            $matchesDay = false;

            if ($schedule->day_of_week !== null && $schedule->day_of_week->value === $date->dayOfWeekIso) {
                $matchesDay = true;
            } elseif ($schedule->date !== null && $schedule->date->isSameDay($date)) {
                $matchesDay = true;
            }

            if ($matchesDay) {
                if ($isToday) {
                    $slotTime = Carbon::parse($schedule->start_time);
                    if ($slotTime->format('H:i') <= $now->format('H:i')) {
                        continue;
                    }
                }

                $bookedParticipants = Booking::active()
                    ->where('schedule_id', $schedule->id)
                    ->whereDate('booking_date', $date->toDateString())
                    ->sum('participant_count');

                // Account for already selected sessions in the current wizard
                $alreadySelected = $selectedForDate[$schedule->id] ?? 0;
                $remaining = $schedule->max_capacity - $bookedParticipants - $alreadySelected;

                if ($remaining >= 1) {
                    $slots[] = [
                        'schedule_id' => $schedule->id,
                        'start_time' => substr((string) $schedule->start_time, 0, 5),
                        'coach_name' => $schedule->service->coach->name,
                        'remaining' => $remaining,
                    ];
                }
            }
        }

        usort($slots, fn ($a, $b) => strcmp($a['start_time'], $b['start_time']));

        return $slots;
    }

    /**
     * Get unavailable dates string for the calendar.
     */
    #[Computed]
    public function unavailableDates(): string
    {
        $schedules = $this->activeSchedules;

        if ($schedules->isEmpty()) {
            return '';
        }

        $today = Carbon::today();
        $endDate = $today->copy()->endOfMonth();

        // If we're past the 25th, also include next month
        if ($today->day > 25) {
            $endDate = $today->copy()->addMonth()->endOfMonth();
        }

        $unavailable = [];
        $now = now();

        for ($date = $today->copy(); $date->lte($endDate); $date->addDay()) {
            $hasAvailableSlot = false;
            $isToday = $date->isToday();

            foreach ($schedules as $schedule) {
                $matchesDay = false;

                if ($schedule->day_of_week !== null && $schedule->day_of_week->value === $date->dayOfWeekIso) {
                    $matchesDay = true;
                } elseif ($schedule->date !== null && $schedule->date->isSameDay($date)) {
                    $matchesDay = true;
                }

                if ($matchesDay) {
                    if ($isToday) {
                        $slotTime = Carbon::parse($schedule->start_time);
                        if ($slotTime->format('H:i') <= $now->format('H:i')) {
                            continue;
                        }
                    }

                    $bookedParticipants = Booking::active()
                        ->where('schedule_id', $schedule->id)
                        ->whereDate('booking_date', $date->toDateString())
                        ->sum('participant_count');

                    $remaining = $schedule->max_capacity - $bookedParticipants;

                    if ($remaining >= 1) {
                        $hasAvailableSlot = true;
                        break;
                    }
                }
            }

            if (! $hasAvailableSlot) {
                $unavailable[] = $date->toDateString();
            }
        }

        return implode(',', $unavailable);
    }

    public function updatedSessionServiceTypeId(): void
    {
        $this->session_service_id = null;
        $this->session_date = null;
        $this->session_schedule_id = null;
        unset($this->filteredServices, $this->activeSchedules, $this->unavailableDates, $this->availableTimeSlots);
    }

    public function updatedSessionServiceId(): void
    {
        $this->session_date = null;
        $this->session_schedule_id = null;
        unset($this->activeSchedules, $this->unavailableDates, $this->availableTimeSlots);
    }

    public function updatedSessionDate(): void
    {
        $this->session_schedule_id = null;
        unset($this->availableTimeSlots);
    }

    /**
     * Add the currently selected session to the sessions array.
     */
    public function addSession(): void
    {
        $this->validate([
            'session_service_id' => ['required', 'exists:services,id'],
            'session_date' => ['required', 'date'],
            'session_schedule_id' => ['required', 'exists:schedules,id'],
        ], [
            'session_service_id.required' => __('Jums ir jāizvēlās pakalpojums.'),
            'session_date.required' => __('Datums ir obligāts.'),
            'session_schedule_id.required' => __('Laika slots ir obligāts.'),
        ]);

        $schedule = Schedule::with('service.coach')->findOrFail($this->session_schedule_id);

        $this->sessions[] = [
            'service_id' => $schedule->service_id,
            'service_name' => $schedule->service->name,
            'coach_name' => $schedule->service->coach->name,
            'schedule_id' => $schedule->id,
            'date' => $this->session_date,
            'time' => substr((string) $schedule->start_time, 0, 5),
        ];

        // Reset session builder
        $this->session_service_type_id = null;
        $this->session_service_id = null;
        $this->session_date = null;
        $this->session_schedule_id = null;
        unset($this->filteredServices, $this->activeSchedules, $this->unavailableDates, $this->availableTimeSlots);
    }

    /**
     * Remove a session from the selected sessions.
     */
    public function removeSession(int $index): void
    {
        unset($this->sessions[$index]);
        $this->sessions = array_values($this->sessions);
    }

    public function goToStep(int $step): void
    {
        $this->step = $step;
    }

    public function nextStep(): void
    {
        if ($this->step === 1) {
            $this->validate([
                'selectedServiceId' => ['required', 'integer', 'exists:services,id'],
            ], [
                'selectedServiceId.required' => __('Jums ir jāizvēlās abonements.'),
            ]);
        }

        if ($this->step === 2) {
            if (count($this->sessions) !== $this->requiredSessions) {
                $this->addError('sessions', __('Jums ir jāizvēlās :count nodarbības.', ['count' => $this->requiredSessions]));

                return;
            }
        }

        if ($this->step === 3) {
            $this->validate([
                'name' => ['required', 'string', 'max:255'],
                'surname' => ['required', 'string', 'max:255'],
                'phone' => ['required', 'string', 'max:50'],
                'email' => ['required', 'email', 'max:255'],
            ], [
                'name.required' => __('Vārds ir obligāts.'),
                'surname.required' => __('Uzvārds ir obligāts.'),
                'phone.required' => __('Tālrunis ir obligāts.'),
                'email.required' => __('E-pasts ir obligāts.'),
                'email.email' => __('E-pastam jābūt derīgai e-pasta adresei.'),
            ]);
        }

        $this->step++;
    }

    public function previousStep(): void
    {
        $this->step--;
    }

    public function submitMembership(): void
    {
        $membershipService = $this->membershipService;
        $price = $this->tierPrice;

        if (! $membershipService || count($this->sessions) !== $membershipService->sessions_count) {
            return;
        }

        // Determine period: all sessions should be within a calendar month
        $dates = collect($this->sessions)->pluck('date')->map(fn ($d) => Carbon::parse($d));
        $periodStart = $dates->min()->startOfMonth();
        $periodEnd = $dates->min()->endOfMonth();

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
                'email' => $this->email,
                'name' => $this->name,
                'surname' => $this->surname,
                'phone' => $this->phone,
                'service_id' => $membershipService->id,
                'price' => $price,
                'sessions_total' => $membershipService->sessions_count,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'payment_status' => PaymentStatus::Pending,
                'expires_at' => now()->addMinutes(30),
            ]);

            foreach ($this->sessions as $session) {
                Booking::create([
                    'membership_id' => $membership->id,
                    'schedule_id' => $session['schedule_id'],
                    'booking_date' => $session['date'],
                    'name' => $this->name,
                    'surname' => $this->surname,
                    'phone' => $this->phone,
                    'email' => $this->email,
                    'participant_count' => 1,
                    'payment_status' => PaymentStatus::Paid,
                ]);
            }

            return $membership;
        });

        if (! $result) {
            $this->addError('sessions', __('Kāds no izvēlētajiem laikiem vairs nav pieejams. Lūdzu, pārbaudiet un mēģiniet vēlreiz.'));
            $this->step = 2;

            return;
        }

        $checkout = app(CreateMembershipCheckoutSession::class)->execute($result);

        $this->redirect($checkout['url'], navigate: false);
    }

    public function resetModal(): void
    {
        $this->reset();
        unset(
            $this->membershipService,
            $this->membershipServices,
            $this->requiredSessions,
            $this->tierPrice,
            $this->serviceTypes,
            $this->filteredServices,
            $this->activeSchedules,
            $this->unavailableDates,
            $this->availableTimeSlots,
        );
    }
};
?>

<div id="membershipModal">
    <flux:modal name="membership-modal" class="w-[calc(100vw-2rem)] max-w-lg" @close="$wire.resetModal()">
        <div class="space-y-6 p-6 md:p-8">
            {{-- STEP INDICATOR --}}
            <div class="flex items-center justify-center gap-2 mt-12 md:mt-0">
                @for($i = 1; $i <= 4; $i++)
                    <div class="flex items-center gap-2">
                        <div @class([
                            'flex size-8 items-center justify-center rounded-full text-sm font-medium',
                            'bg-blue text-white' => $step === $i,
                            'bg-green-500 text-white' => $step > $i,
                            'bg-zinc-200 text-zinc-500' => $step < $i,
                        ])>
                            @if($step > $i)
                                <flux:icon.check class="size-4"/>
                            @else
                                {{ $i }}
                            @endif
                        </div>
                        @if($i < 4)
                            <div @class([
                                'h-px w-8',
                                'bg-green-500' => $step > $i,
                                'bg-zinc-200' => $step <= $i,
                            ])>
                            </div>
                        @endif
                    </div>
                @endfor
            </div>

            {{-- STEP 1: CHOOSE TIER --}}
            @if($step === 1)
                <div class="space-y-6">
                    <flux:heading size="lg">{{ __('Izvēlieties abonementu') }}</flux:heading>

                    <flux:radio.group wire:model.live="selectedServiceId" variant="cards">
                        @foreach($this->membershipServices as $membershipOption)
                            <flux:radio
                                :value="$membershipOption->id"
                                :label="$membershipOption->name"
                                :description="Number::currency($membershipOption->price / 100, 'EUR') . ' / ' . __('mēnesī')"
                            />
                        @endforeach
                    </flux:radio.group>

                    @error('selectedServiceId')
                        <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text>
                    @enderror

                    <div class="flex justify-end">
                        <flux:button wire:click="nextStep" class="button small primary">{{ __('Tālāk') }}</flux:button>
                    </div>
                </div>
            @endif

            {{-- STEP 2: SELECT SESSIONS --}}
            @if($step === 2)
                <div class="space-y-6">
                    <flux:heading size="lg">
                        {{ __('Izvēlieties nodarbības') }}
                        <flux:badge size="sm" color="{{ count($sessions) === $this->requiredSessions ? 'green' : 'zinc' }}">
                            {{ count($sessions) }}/{{ $this->requiredSessions }}
                        </flux:badge>
                    </flux:heading>

                    {{-- Selected sessions list --}}
                    @if(count($sessions) > 0)
                        <div class="space-y-2">
                            @foreach($sessions as $index => $session)
                                <div class="flex items-center justify-between rounded-lg border p-3" wire:key="session-{{ $index }}">
                                    <div>
                                        <flux:text class="font-medium">{{ $session['service_name'] }}</flux:text>
                                        <flux:text class="text-sm text-zinc-500">
                                            {{ Carbon::parse($session['date'])->format('d.m.Y') }} {{ $session['time'] }}
                                            — {{ $session['coach_name'] }}
                                        </flux:text>
                                    </div>
                                    <flux:button wire:click="removeSession({{ $index }})" variant="ghost" size="sm" icon="x-mark"/>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Session builder (only if more sessions needed) --}}
                    @if(count($sessions) < $this->requiredSessions)
                        <flux:separator/>

                        <flux:select wire:model.live="session_service_type_id" :label="__('Nodarbība')">
                            <flux:select.option value="">{{ __('Izvēlieties nodarbību') }}</flux:select.option>
                            @foreach($this->serviceTypes as $type)
                                <flux:select.option :value="$type->id">{{ $type->name }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        @if($this->session_service_type_id)
                            <flux:select wire:model.live="session_service_id" :label="__('Treniņš')">
                                <flux:select.option value="">{{ __('Izvēlieties treniņu') }}</flux:select.option>
                                @foreach($this->filteredServices as $service)
                                    <flux:select.option :value="$service->id">
                                        {{ $service->name }} ({{ $service->coach->name }})
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                        @endif

                        @if($this->session_service_id)
                            <div class="flex justify-center">
                                <flux:calendar wire:model.live="session_date" min="today"
                                               :max="now()->endOfMonth()->toDateString()"
                                               :unavailable="$this->unavailableDates" locale="lv" start-day="1"/>
                            </div>
                        @endif

                        @if($this->session_date && count($this->availableTimeSlots) > 0)
                            <flux:radio.group wire:model.live="session_schedule_id" :label="__('Pieejamie laiki')" variant="cards">
                                @foreach($this->availableTimeSlots as $slot)
                                    <flux:radio :value="$slot['schedule_id']"
                                                :label="$slot['start_time'] . ' — ' . $slot['coach_name']"
                                                :description="$slot['remaining'] . ' ' . ($slot['remaining'] === 1 ? __('vieta') : __('vietas'))"/>
                                @endforeach
                            </flux:radio.group>
                        @elseif($this->session_date)
                            <flux:text class="text-center">{{ __('Šajā datumā nav pieejamu laiku.') }}</flux:text>
                        @endif

                        @if($this->session_schedule_id)
                            <div class="flex justify-end">
                                <flux:button wire:click="addSession" variant="outline" icon="plus">
                                    {{ __('Pievienot nodarbību') }}
                                </flux:button>
                            </div>
                        @endif
                    @endif

                    @error('sessions')
                        <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text>
                    @enderror

                    <div class="flex justify-between">
                        <flux:button wire:click="previousStep" class="button small tertiary">{{ __('Atpakaļ') }}</flux:button>
                        <flux:button wire:click="nextStep" class="button small primary">{{ __('Tālāk') }}</flux:button>
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
                    <flux:input wire:model="email" type="email" :label="__('E-pasts')" :placeholder="__('Ievadiet e-pastu')"/>

                    <div class="flex justify-between">
                        <flux:button wire:click="previousStep" class="button small tertiary">{{ __('Atpakaļ') }}</flux:button>
                        <flux:button wire:click="nextStep" class="button small primary">{{ __('Tālāk') }}</flux:button>
                    </div>
                </div>
            @endif

            {{-- STEP 4: CONFIRMATION --}}
            @if($step === 4)
                <div class="space-y-6">
                    <flux:heading size="lg">{{ __('Apstiprinājums') }}</flux:heading>

                    <div class="p-4 space-y-2">
                        <div class="flex justify-between">
                            <flux:text class="font-medium">{{ __('Abonements') }}</flux:text>
                            <flux:text>{{ $this->membershipService->name }}</flux:text>
                        </div>
                        <flux:separator/>
                        <div class="flex justify-between">
                            <flux:text class="font-medium">{{ __('Cena') }}</flux:text>
                            <flux:text class="font-semibold">{{ Number::currency($this->tierPrice / 100, 'EUR') }}</flux:text>
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
                        <flux:button wire:click="previousStep" class="button small tertiary">{{ __('Atpakaļ') }}</flux:button>
                        <flux:button wire:click="submitMembership" class="button small primary">{{ __('Apmaksāt ar karti') }}</flux:button>
                    </div>
                </div>
            @endif
        </div>
    </flux:modal>
</div>
