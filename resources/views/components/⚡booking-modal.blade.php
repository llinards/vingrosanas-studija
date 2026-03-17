<?php

use App\Actions\CreateMembershipCheckoutSession;
use App\Actions\CreateStripeCheckoutSession;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Membership;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\ServicePriceTier;
use App\Models\ServiceType;
use App\Services\ScheduleAvailabilityService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public int $step = 1;

    public string $mode = 'booking';

    // --- Booking flow properties ---

    public ?int $service_type_id = null;

    public ?int $service_id = null;

    public int $participant_count = 1;

    public ?string $selectedDate = null;

    public ?int $schedule_id = null;

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

    public bool $bookingComplete = false;

    /**
     * Handle the set-booking-mode event dispatched from outside the component.
     */
    #[On('set-booking-mode')]
    public function setMode(string $mode): void
    {
        $this->mode = $mode;
    }

    // ========================================
    // BOOKING FLOW - Computed Properties
    // ========================================

    /**
     * Check if the selected service is exclusive (one booking per slot).
     */
    #[Computed]
    public function isExclusiveService(): bool
    {
        return $this->selectedService?->is_exclusive ?? false;
    }

    #[Computed]
    public function serviceTypes(): Collection
    {
        return ServiceType::whereHas('services', function ($query) {
            $query->where('is_active', true)
                ->where('is_membership', false)
                ->whereHas('schedules', fn ($q) => $q->where('is_active', true));
        })->get();
    }

    #[Computed]
    public function filteredServices(): Collection
    {
        if (! $this->service_type_id) {
            return new Collection;
        }

        return Service::with(['coach', 'priceTiers'])
            ->where('is_active', true)
            ->where('is_membership', false)
            ->where('service_type_id', $this->service_type_id)
            ->whereHas('schedules', fn ($query) => $query->where('is_active', true))
            ->get();
    }

    #[Computed]
    public function selectedService(): ?Service
    {
        if (! $this->service_id) {
            return null;
        }

        return Service::with('priceTiers')->find($this->service_id);
    }

    /**
     * Get available price tiers filtered by the selected schedule's max_capacity.
     *
     * @return \Illuminate\Support\Collection<int, ServicePriceTier>
     */
    #[Computed]
    public function availablePriceTiers(): \Illuminate\Support\Collection
    {
        $service = $this->selectedService;
        $schedule = $this->selectedSchedule;

        if (! $service) {
            return collect();
        }

        $query = $service->priceTiers()->orderBy('participant_count');

        // Filter by schedule's max_capacity if schedule is selected
        if ($schedule) {
            $query->where('participant_count', '<=', $schedule->max_capacity);
        }

        return $query->get();
    }

    #[Computed]
    public function activeSchedules(): Collection
    {
        if (! $this->service_id) {
            return new Collection;
        }

        return Schedule::with('service.coach')
            ->where('service_id', $this->service_id)
            ->where('is_active', true)
            ->get();
    }

    #[Computed]
    public function unavailableDates(): string
    {
        return app(ScheduleAvailabilityService::class)->unavailableDates(
            schedules: $this->activeSchedules,
            startDate: Carbon::today(),
            endDate: Carbon::today()->addWeeks(4),
            isExclusive: $this->isExclusiveService,
        );
    }

    /**
     * @return array<int, array{schedule_id: int, start_time: string, coach_name: string, remaining: int, max_capacity: int}>
     */
    #[Computed]
    public function availableTimeSlots(): array
    {
        if (! $this->selectedDate || ! $this->service_id) {
            return [];
        }

        return app(ScheduleAvailabilityService::class)->availableTimeSlots(
            schedules: $this->activeSchedules,
            date: Carbon::parse($this->selectedDate),
            isExclusive: $this->isExclusiveService,
        );
    }

    #[Computed]
    public function selectedSchedule(): ?Schedule
    {
        if (! $this->schedule_id) {
            return null;
        }

        return Schedule::with('service.coach')->find($this->schedule_id);
    }

    /**
     * Get the price for the currently selected participant count.
     */
    #[Computed]
    public function selectedPrice(): int
    {
        $service = $this->selectedService;

        if (! $service) {
            return 0;
        }

        $tier = $service->priceTiers->firstWhere('participant_count', $this->participant_count);

        return $tier?->price ?? $service->price;
    }

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
                ->whereHas('schedules', fn ($q) => $q->where('is_active', true));
        })->get();
    }

    /**
     * Get membership-eligible services filtered by service type.
     */
    #[Computed]
    public function sessionFilteredServices(): Collection
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
     * Get active schedules for the selected session service.
     */
    #[Computed]
    public function sessionActiveSchedules(): Collection
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
     * Get unavailable dates for the membership session builder calendar.
     */
    #[Computed]
    public function sessionUnavailableDates(): string
    {
        return app(ScheduleAvailabilityService::class)->unavailableDates(
            schedules: $this->sessionActiveSchedules,
            startDate: Carbon::today(),
            endDate: Carbon::today()->addDays(30),
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
        if (! $this->session_date || ! $this->session_service_id) {
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

    // Booking flow watchers

    public function updatedServiceTypeId(): void
    {
        $this->service_id = null;
        unset($this->filteredServices, $this->isExclusiveService);
    }

    public function updatedServiceId(): void
    {
        $this->participant_count = 1;
        $this->selectedDate = null;
        $this->schedule_id = null;
        unset(
            $this->activeSchedules,
            $this->unavailableDates,
            $this->selectedService,
            $this->availablePriceTiers,
            $this->isExclusiveService
        );
    }

    public function updatedSelectedDate(): void
    {
        $this->schedule_id = null;
        $this->participant_count = 1;
        unset($this->availableTimeSlots, $this->availablePriceTiers);
    }

    public function updatedScheduleId(): void
    {
        $this->participant_count = 1;
        unset($this->availablePriceTiers, $this->selectedSchedule);
    }

    // Membership flow watchers

    public function updatedSessionServiceTypeId(): void
    {
        $this->session_service_id = null;
        $this->session_date = null;
        $this->session_schedule_id = null;
        unset($this->sessionFilteredServices, $this->sessionActiveSchedules, $this->sessionUnavailableDates, $this->sessionAvailableTimeSlots);
    }

    public function updatedSessionServiceId(): void
    {
        $this->session_date = null;
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
        $this->step = $step;
    }

    public function nextStep(): void
    {
        if ($this->step === 1) {
            if ($this->mode === 'membership') {
                $this->validate([
                    'selectedMembershipServiceId' => ['required', 'integer', 'exists:services,id'],
                ], [
                    'selectedMembershipServiceId.required' => __('Jums ir jāizvēlās abonements.'),
                ]);
            } else {
                $this->validate([
                    'service_type_id' => ['required', 'exists:service_types,id'],
                    'service_id' => ['required', 'exists:services,id'],
                ], [
                    'service_type_id.required' => __('Jums ir jāizvēlās nodarbība.'),
                    'service_id.required' => __('Jums ir jāizvēlās treniņš.'),
                ]);
            }
        }

        if ($this->step === 2) {
            if ($this->mode === 'membership') {
                if (count($this->sessions) !== $this->membershipService?->sessions_count ?? 0) {
                    $this->addError('sessions', __('Jums ir jāizvēlās :count nodarbības.', ['count' => $this->membershipService?->sessions_count ?? 0]));

                    return;
                }
            } else {
                $rules = [
                    'selectedDate' => ['required', 'date'],
                    'schedule_id' => ['required', 'exists:schedules,id'],
                ];

                $messages = [
                    'selectedDate.required' => __('Datums ir obligāts.'),
                    'schedule_id.required' => __('Laika slots ir obligāts.'),
                ];

                // For exclusive services, validate participant count
                if ($this->isExclusiveService && count($this->availablePriceTiers) > 1) {
                    $rules['participant_count'] = ['required', 'integer', 'min:1'];
                    $messages['participant_count.required'] = __('Dalībnieku skaits ir obligāts.');
                }

                $this->validate($rules, $messages);
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
                'name.max' => __('Vārds nedrīkst pārsniegt 255 rakstzīmes.'),
                'surname.required' => __('Uzvārds ir obligāts.'),
                'surname.max' => __('Uzvārds nedrīkst pārsniegt 255 rakstzīmes.'),
                'phone.required' => __('Tālrunis ir obligāts.'),
                'phone.max' => __('Tālrunis nedrīkst pārsniegt 50 rakstzīmes.'),
                'email.required' => __('E-pasts ir obligāts.'),
                'email.email' => __('E-pastam jābūt derīgai e-pasta adresei.'),
                'email.max' => __('E-pasts nedrīkst pārsniegt 255 rakstzīmes.'),
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

        // Auto-advance to customer info when all sessions are selected
        if (count($this->sessions) === $this->membershipService?->sessions_count) {
            $this->step = 3;

            return;
        }

        // Reset session builder for next selection
        $this->session_service_type_id = null;
        $this->session_service_id = null;
        $this->session_date = null;
        $this->session_schedule_id = null;
        unset($this->sessionFilteredServices, $this->sessionActiveSchedules, $this->sessionUnavailableDates, $this->sessionAvailableTimeSlots);
    }

    /**
     * Remove a session from the selected sessions.
     */
    public function removeSession(int $index): void
    {
        unset($this->sessions[$index]);
        $this->sessions = array_values($this->sessions);
    }

    // ========================================
    // SUBMIT ACTIONS
    // ========================================

    public function submitBooking(): void
    {
        $isExclusive = $this->isExclusiveService;
        $price = $this->selectedPrice;

        $booking = DB::transaction(function () use ($isExclusive) {
            $schedule = Schedule::findOrFail($this->schedule_id);

            if ($isExclusive) {
                // For exclusive services: fail if ANY active booking exists
                $hasBooking = Booking::active()
                    ->where('schedule_id', $this->schedule_id)
                    ->whereDate('booking_date', $this->selectedDate)
                    ->lockForUpdate()
                    ->exists();

                if ($hasBooking) {
                    return null;
                }

                // Also validate participant count against schedule's max_capacity
                if ($this->participant_count > $schedule->max_capacity) {
                    return null;
                }
            } else {
                // For regular services: check remaining capacity (excluding inactive bookings)
                $bookedParticipants = Booking::active()
                    ->where('schedule_id', $this->schedule_id)
                    ->whereDate('booking_date', $this->selectedDate)
                    ->lockForUpdate()
                    ->sum('participant_count');

                $remaining = $schedule->max_capacity - $bookedParticipants;

                if ($remaining < $this->participant_count) {
                    return null;
                }
            }

            return Booking::create([
                'schedule_id' => $this->schedule_id,
                'booking_date' => $this->selectedDate,
                'name' => $this->name,
                'surname' => $this->surname,
                'phone' => $this->phone,
                'email' => $this->email,
                'participant_count' => $this->participant_count,
                'payment_status' => PaymentStatus::Pending,
                'expires_at' => now()->addMinutes(30),
            ]);
        });

        if (! $booking) {
            $this->addError('schedule_id', __('Šis laiks vairs nav pieejams. Lūdzu, izvēlieties citu.'));
            $this->step = 2;

            return;
        }

        // Create Stripe Checkout Session and redirect
        $checkout = app(CreateStripeCheckoutSession::class)->execute($booking, $price);

        $this->redirect($checkout['url'], navigate: false);
    }

    public function submitMembership(): void
    {
        $membershipService = $this->membershipService;
        $price = $this->membershipService?->price ?? 0;

        if (! $membershipService || count($this->sessions) !== $membershipService->sessions_count) {
            return;
        }

        // Period: 30 days from purchase date
        $periodStart = today();
        $periodEnd = today()->addDays(30);

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

    // ========================================
    // RESET
    // ========================================

    public function resetModal(): void
    {
        $this->reset();
        unset(
            // Booking flow
            $this->serviceTypes,
            $this->filteredServices,
            $this->activeSchedules,
            $this->unavailableDates,
            $this->availableTimeSlots,
            $this->selectedSchedule,
            $this->selectedService,
            $this->availablePriceTiers,
            $this->isExclusiveService,
            $this->selectedPrice,
            // Membership flow
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
    <flux:modal name="booking-modal" :dismissible="false" class="w-[calc(100vw-2rem)] max-w-lg" @close="$wire.resetModal()">
        <div class="space-y-6 p-6 md:p-8">

            @if($bookingComplete)
                {{-- SUCCESS STATE --}}
                <div class="text-center space-y-4">
                    <flux:icon.check-circle class="mx-auto size-16 text-green-500"/>
                    <flux:heading size="lg">{{ __('Rezervācija veiksmīga!') }}</flux:heading>
                    <flux:text>{{ __('Apstiprinājums nosūtīts uz Jūsu e-pastu.') }}</flux:text>
                    <flux:modal.close>
                        <flux:button class="button small primary">{{ __('Aizvērt') }}</flux:button>
                    </flux:modal.close>
                </div>
            @else
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

                {{-- STEP 1: SERVICE SELECTION --}}
                @if($step === 1)
                    <div class="space-y-6">
                        {{-- Mode toggle --}}
                        <flux:radio.group wire:model.live="mode" variant="segmented">
                            <flux:radio value="booking" :label="__('Treniņš')"/>
                            <flux:radio value="membership" :label="__('Abonements')"/>
                        </flux:radio.group>

                        @if($mode === 'booking')
                            {{-- Booking: service type + service selection --}}
                            <flux:select wire:model.live="service_type_id" :label="__('Nodarbības')">
                                <flux:select.option value="">{{ __('Izvēlieties nodarbību') }}</flux:select.option>
                                @foreach($this->serviceTypes as $type)
                                    <flux:select.option :value="$type->id">{{ $type->name }}</flux:select.option>
                                @endforeach
                            </flux:select>

                            @if($this->service_type_id)
                                <flux:select wire:model.live="service_id" :label="__('Treniņš')">
                                    <flux:select.option value="">{{ __('Izvēlieties treniņu') }}</flux:select.option>
                                    @foreach($this->filteredServices as $service)
                                        <flux:select.option :value="$service->id">
                                            {{ $service->name }} ({{ $service->coach->name }})
                                        </flux:select.option>
                                    @endforeach
                                </flux:select>
                            @endif
                        @else
                            {{-- Membership: tier selection --}}
                            <flux:radio.group wire:model.live="selectedMembershipServiceId" variant="cards">
                                @foreach($this->membershipServices as $membershipOption)
                                    <flux:radio
                                        :value="$membershipOption->id"
                                        :label="$membershipOption->name"
                                        :description="Number::currency($membershipOption->price / 100, 'EUR') . ' / ' . __('mēnesī')"
                                    />
                                @endforeach
                            </flux:radio.group>

                            @error('selectedMembershipServiceId')
                                <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text>
                            @enderror
                        @endif

                        <div class="flex justify-end">
                            <flux:button wire:click="nextStep"
                                         class="button small primary">{{ __('Tālāk') }}</flux:button>
                        </div>
                    </div>
                @endif

                {{-- STEP 2: DATE & TIME / SESSION SELECTION --}}
                @if($step === 2)
                    @if($mode === 'booking')
                        {{-- BOOKING: Date & time selection --}}
                        <div class="space-y-6">
                            <flux:heading size="lg">{{ __('Izvēlieties datumu un laiku') }}</flux:heading>

                            <div class="flex justify-center">
                                <flux:calendar wire:model.live="selectedDate" min="today"
                                               :max="now()->addWeeks(4)->toDateString()"
                                               :unavailable="$this->unavailableDates" locale="lv" start-day="1"/>
                            </div>

                            @if($this->selectedDate && count($this->availableTimeSlots) > 0)
                                <flux:radio.group wire:model.live="schedule_id" :label="__('Pieejamie laiki')"
                                                  variant="cards">
                                    @foreach($this->availableTimeSlots as $slot)
                                        <flux:radio :value="$slot['schedule_id']"
                                                    :label="$slot['start_time'] . ' — ' . $slot['coach_name']"
                                                    :description="$this->isExclusiveService ? null : $slot['remaining'] . ' ' . ($slot['remaining'] === 1 ? __('vieta') : __('vietas'))"/>
                                    @endforeach
                                </flux:radio.group>
                            @elseif($this->selectedDate)
                                <flux:text class="text-center">{{ __('Šajā datumā nav pieejamu laiku.') }}</flux:text>
                            @endif

                            {{-- Show participant count selection AFTER schedule is selected (for exclusive services) --}}
                            @if($this->schedule_id && $this->isExclusiveService && count($this->availablePriceTiers) > 1)
                                <flux:radio.group wire:model.live="participant_count" :label="__('Dalībnieku skaits')"
                                                  variant="cards" class="grid grid-cols-3 gap-2">
                                    @foreach($this->availablePriceTiers as $tier)
                                        <flux:radio :value="$tier->participant_count"
                                                    :label="$tier->participant_count . ' ' . ($tier->participant_count === 1 ? __('persona') : __('personas'))"
                                                    :description="Number::currency($tier->price / 100, 'EUR')"/>
                                    @endforeach
                                </flux:radio.group>
                            @endif

                            <div class="flex justify-between">
                                <flux:button wire:click="previousStep"
                                             class="button small tertiary">{{ __('Atpakaļ') }}</flux:button>
                                <flux:button wire:click="nextStep"
                                             class="button small primary">{{ __('Tālāk') }}</flux:button>
                            </div>
                        </div>
                    @else
                        {{-- MEMBERSHIP: Session selection --}}
                        <div class="space-y-6">
                            <flux:heading size="lg">
                                {{ __('Izvēlieties nodarbības') }}
                                <flux:badge size="sm" color="{{ count($sessions) === $this->membershipService?->sessions_count ?? 0 ? 'green' : 'zinc' }}">
                                    {{ count($sessions) }}/{{ $this->membershipService?->sessions_count ?? 0 }}
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
                                        <flux:select.option value="">{{ __('Izvēlieties treniņu') }}</flux:select.option>
                                        @foreach($this->sessionFilteredServices as $service)
                                            <flux:select.option :value="$service->id">
                                                {{ $service->name }} ({{ $service->coach->name }})
                                            </flux:select.option>
                                        @endforeach
                                    </flux:select>
                                @endif

                                @if($this->session_service_id)
                                    <div class="flex justify-center">
                                        <flux:calendar wire:model.live="session_date" min="today"
                                                       :max="now()->addDays(30)->toDateString()"
                                                       :unavailable="$this->sessionUnavailableDates" locale="lv" start-day="1"/>
                                    </div>
                                @endif

                                @if($this->session_date && count($this->sessionAvailableTimeSlots) > 0)
                                    <flux:radio.group wire:model.live="session_schedule_id" :label="__('Pieejamie laiki')" variant="cards">
                                        @foreach($this->sessionAvailableTimeSlots as $slot)
                                            <flux:radio :value="$slot['schedule_id']"
                                                        :label="$slot['start_time'] . ' — ' . $slot['coach_name']"
                                                        :description="$slot['remaining'] . ' ' . ($slot['remaining'] === 1 ? __('vieta') : __('vietas'))"/>
                                        @endforeach
                                    </flux:radio.group>
                                @elseif($this->session_date)
                                    <flux:text class="text-center">{{ __('Šajā datumā nav pieejamu laiku.') }}</flux:text>
                                @endif

                            @endif

                            @error('sessions')
                                <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text>
                            @enderror

                            <div class="flex justify-between">
                                <flux:button wire:click="previousStep" class="button small tertiary">{{ __('Atpakaļ') }}</flux:button>
                                @if(count($sessions) === ($this->membershipService?->sessions_count ?? 0))
                                    <flux:button wire:click="nextStep" class="button small primary">{{ __('Tālāk') }}</flux:button>
                                @elseif($this->session_schedule_id)
                                    <flux:button wire:click="addSession" class="button small primary">
                                        {{ __('Pievienot nodarbību') }} ({{ count($sessions) + 1 }}/{{ $this->membershipService?->sessions_count ?? 0 }})
                                    </flux:button>
                                @endif
                            </div>
                        </div>
                    @endif
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

                        <div class="flex justify-between">
                            <flux:button wire:click="previousStep"
                                         class="button small tertiary">{{ __('Atpakaļ') }}</flux:button>
                            <flux:button wire:click="nextStep"
                                         class="button small primary">{{ __('Tālāk') }}</flux:button>
                        </div>
                    </div>
                @endif

                {{-- STEP 4: CONFIRMATION --}}
                @if($step === 4)
                    <div class="space-y-6">
                        <flux:heading size="lg">{{ __('Apstiprinājums') }}</flux:heading>

                        @if($mode === 'booking')
                            {{-- BOOKING CONFIRMATION --}}
                            @if($this->selectedSchedule)
                                <div class=" p-4 space-y-2">
                                    <div class="flex justify-between">
                                        <flux:text class="font-medium">{{ __('Treniņš') }}</flux:text>
                                        <flux:text>{{ $this->selectedSchedule->service->name }}</flux:text>
                                    </div>
                                    <flux:separator/>
                                    <div class="flex justify-between">
                                        <flux:text class="font-medium">{{ __('Treneris') }}</flux:text>
                                        <flux:text>{{ $this->selectedSchedule->service->coach->name }}</flux:text>
                                    </div>
                                    <flux:separator/>
                                    <div class="flex justify-between">
                                        <flux:text class="font-medium">{{ __('Datums') }}</flux:text>
                                        <flux:text>{{ Carbon::parse($this->selectedDate)->format('d.m.Y') }}</flux:text>
                                    </div>
                                    <flux:separator/>
                                    <div class="flex justify-between">
                                        <flux:text class="font-medium">{{ __('Laiks') }}</flux:text>
                                        <flux:text>{{ substr($this->selectedSchedule->start_time, 0, 5) }}</flux:text>
                                    </div>
                                    <flux:separator/>
                                    <div class="flex justify-between">
                                        <flux:text class="font-medium">{{ __('Dalībnieki') }}</flux:text>
                                        <flux:text>{{ $this->participant_count }} {{ $this->participant_count === 1 ? __('persona') : __('personas') }}</flux:text>
                                    </div>
                                    <flux:separator/>
                                    <div class="flex justify-between">
                                        <flux:text class="font-medium">{{ __('Cena') }}</flux:text>
                                        <flux:text
                                            class="font-semibold">{{ Number::currency($this->selectedPrice / 100, 'EUR') }}</flux:text>
                                    </div>
                                </div>
                            @endif
                        @else
                            {{-- MEMBERSHIP CONFIRMATION --}}
                            <div class="p-4 space-y-2">
                                <div class="flex justify-between">
                                    <flux:text class="font-medium">{{ __('Abonements') }}</flux:text>
                                    <flux:text>{{ $this->membershipService->name }}</flux:text>
                                </div>
                                <flux:separator/>
                                <div class="flex justify-between">
                                    <flux:text class="font-medium">{{ __('Cena') }}</flux:text>
                                    <flux:text class="font-semibold">{{ Number::currency(($this->membershipService?->price ?? 0) / 100, 'EUR') }}</flux:text>
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
                        @endif

                        <div class="p-4 space-y-1">
                            <flux:text class="font-medium">{{ $this->name }} {{ $this->surname }}</flux:text>
                            <flux:text>{{ $this->phone }}</flux:text>
                            <flux:text>{{ $this->email }}</flux:text>
                        </div>

                        <div class="flex justify-between">
                            <flux:button wire:click="previousStep"
                                         class="button small tertiary">{{ __('Atpakaļ') }}</flux:button>
                            <flux:button wire:click="{{ $mode === 'booking' ? 'submitBooking' : 'submitMembership' }}"
                                         class="button small primary">{{ __('Apmaksāt ar karti') }}</flux:button>
                        </div>
                    </div>
                @endif
            @endif
        </div>
    </flux:modal>
</div>
