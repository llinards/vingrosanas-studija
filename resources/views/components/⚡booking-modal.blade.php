<?php

use App\Enums\PaymentStatus;
use App\Mail\BookingConfirmation;
use App\Mail\NewBookingNotification;
use App\Models\Booking;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\ServicePriceTier;
use App\Models\ServiceType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public int $step = 1;

    public ?int $service_type_id = null;

    public ?int $service_id = null;

    public int $participant_count = 1;

    public ?string $selectedDate = null;

    public ?int $schedule_id = null;

    public string $name = '';

    public string $surname = '';

    public string $phone = '';

    public string $email = '';

    public bool $bookingComplete = false;

    #[Computed]
    public function serviceTypes(): Collection
    {
        return ServiceType::whereHas('services', function ($query) {
            $query->where('is_active', true)
                  ->whereHas('schedules', fn($q) => $q->where('is_active', true));
        })->get();
    }

    #[Computed]
    public function filteredServices(): Collection
    {
        if ( ! $this->service_type_id) {
            return new Collection;
        }

        return Service::with(['coach', 'priceTiers'])
                      ->where('is_active', true)
                      ->where('service_type_id', $this->service_type_id)
                      ->whereHas('schedules', fn($query) => $query->where('is_active', true))
                      ->get();
    }

    #[Computed]
    public function selectedService(): ?Service
    {
        if ( ! $this->service_id) {
            return null;
        }

        return Service::with('priceTiers')->find($this->service_id);
    }

    /**
     * @return \Illuminate\Support\Collection<int, ServicePriceTier>
     */
    #[Computed]
    public function availablePriceTiers(): \Illuminate\Support\Collection
    {
        $service = $this->selectedService;

        if ( ! $service) {
            return collect();
        }

        return $service->priceTiers()->orderBy('participant_count')->get();
    }

    #[Computed]
    public function activeSchedules(): Collection
    {
        if ( ! $this->service_id) {
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
        $schedules = $this->activeSchedules;

        if ($schedules->isEmpty()) {
            return '';
        }

        $today       = Carbon::today();
        $endDate     = $today->copy()->addWeeks(4);
        $unavailable = [];
        $now         = now();

        for ($date = $today->copy(); $date->lte($endDate); $date->addDay()) {
            $hasAvailableSlot = false;
            $isToday          = $date->isToday();

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

                    $bookedParticipants = Booking::where('schedule_id', $schedule->id)
                                                 ->whereDate('booking_date', $date->toDateString())
                                                 ->sum('participant_count');

                    $remaining = $schedule->max_capacity - $bookedParticipants;

                    // Check if there's room for at least 1 participant
                    if ($remaining >= 1) {
                        $hasAvailableSlot = true;
                        break;
                    }
                }
            }

            if ( ! $hasAvailableSlot) {
                $unavailable[] = $date->toDateString();
            }
        }

        return implode(',', $unavailable);
    }

    /**
     * @return array<int, array{schedule_id: int, start_time: string, coach_name: string, remaining: int}>
     */
    #[Computed]
    public function availableTimeSlots(): array
    {
        if ( ! $this->selectedDate || ! $this->service_id) {
            return [];
        }

        $date      = Carbon::parse($this->selectedDate);
        $schedules = $this->activeSchedules;
        $slots     = [];
        $isToday   = $date->isToday();
        $now       = now();

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

                $bookedParticipants = Booking::where('schedule_id', $schedule->id)
                                             ->whereDate('booking_date', $date->toDateString())
                                             ->sum('participant_count');

                $remaining = $schedule->max_capacity - $bookedParticipants;

                if ($remaining >= $this->participant_count) {
                    $slots[] = [
                        'schedule_id' => $schedule->id,
                        'start_time'  => substr((string) $schedule->start_time, 0, 5),
                        'coach_name'  => $schedule->service->coach->name,
                        'remaining'   => $remaining,
                    ];
                }
            }
        }

        usort($slots, fn($a, $b) => strcmp($a['start_time'], $b['start_time']));

        return $slots;
    }

    #[Computed]
    public function selectedSchedule(): ?Schedule
    {
        if ( ! $this->schedule_id) {
            return null;
        }

        return Schedule::with('service.coach')->find($this->schedule_id);
    }

    public function updatedServiceTypeId(): void
    {
        $this->service_id = null;
        unset($this->filteredServices);
    }

    public function updatedServiceId(): void
    {
        $this->participant_count = 1;
        $this->selectedDate      = null;
        $this->schedule_id       = null;
        unset($this->activeSchedules, $this->unavailableDates, $this->selectedService, $this->availablePriceTiers);
    }

    public function updatedParticipantCount(): void
    {
        $this->selectedDate = null;
        $this->schedule_id  = null;
        unset($this->unavailableDates, $this->availableTimeSlots);
    }

    public function updatedSelectedDate(): void
    {
        $this->schedule_id = null;
        unset($this->availableTimeSlots);
    }

    public function goToStep(int $step): void
    {
        $this->step = $step;
    }

    public function nextStep(): void
    {
        if ($this->step === 1) {
            $this->validate([
                'service_type_id' => ['required', 'exists:service_types,id'],
                'service_id'      => ['required', 'exists:services,id'],
            ], [
                'service_type_id.required' => __('Jums ir jāizvēlās nodarbība.'),
                'service_id.required'      => __('Jums ir jāizvēlās treniņš.'),
            ]);
        }

        if ($this->step === 2) {
            $this->validate([
                'selectedDate' => ['required', 'date'],
                'schedule_id'  => ['required', 'exists:schedules,id'],
            ], [
                'selectedDate.required' => __('Datums ir obligāts.'),
                'schedule_id.required'  => __('Laika slots ir obligāts.'),
            ]);
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

    public function submitBooking(): void
    {
        $booking = DB::transaction(function () {
            $bookedParticipants = Booking::where('schedule_id', $this->schedule_id)
                                         ->whereDate('booking_date', $this->selectedDate)
                                         ->lockForUpdate()
                                         ->sum('participant_count');

            $schedule = Schedule::findOrFail($this->schedule_id);

            $remaining = $schedule->max_capacity - $bookedParticipants;

            if ($remaining < $this->participant_count) {
                return null;
            }

            return Booking::create([
                'schedule_id'       => $this->schedule_id,
                'booking_date'      => $this->selectedDate,
                'name'              => $this->name,
                'surname'           => $this->surname,
                'phone'             => $this->phone,
                'email'             => $this->email,
                'participant_count' => $this->participant_count,
                'payment_status'    => PaymentStatus::Pending,
            ]);
        });

        if ( ! $booking) {
            $this->addError('schedule_id', __('Šis laiks vairs nav pieejams. Lūdzu, izvēlieties citu.'));
            $this->step = 2;

            return;
        }

        $booking->load('schedule.service.coach');

        Mail::to($this->email)->send(new BookingConfirmation($booking));

        $coachEmail = $booking->schedule->service->coach->email ?? null;
        if ($coachEmail) {
            Mail::to($coachEmail)->send(new NewBookingNotification($booking));
        }

        $this->bookingComplete = true;
    }

    public function resetModal(): void
    {
        $this->reset();
        unset(
            $this->serviceTypes,
            $this->filteredServices,
            $this->activeSchedules,
            $this->unavailableDates,
            $this->availableTimeSlots,
            $this->selectedSchedule,
            $this->selectedService,
            $this->availablePriceTiers,
        );
    }

    /**
     * Get the price for the currently selected participant count.
     */
    #[Computed]
    public function selectedPrice(): int
    {
        $service = $this->selectedService;

        if ( ! $service) {
            return 0;
        }

        $tier = $service->priceTiers()
                        ->where('participant_count', $this->participant_count)
                        ->first();

        return $tier?->price ?? $service->price;
    }
};
?>

<div id="bookingModal">
    <flux:modal name="booking-modal" class="w-[calc(100vw-2rem)] max-w-lg" @close="$wire.resetModal()">
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
                            <div @class([ 'flex size-8 items-center justify-center rounded-full text-sm font-medium'
                        , 'bg-blue text-white'=> $step === $i,
                        'bg-green-500 text-white' => $step > $i,
                        'bg-zinc-200 text-zinc-500' => $step < $i, ])>
                                @if($step > $i)
                                    <flux:icon.check class="size-4"/>
                                @else
                                    {{ $i }}
                                @endif
                            </div>
                            @if($i < 4)
                                <div @class([ 'h-px w-8' , 'bg-green-500'=> $step > $i,
                        'bg-zinc-200' => $step <= $i, ])>
                                </div>
                            @endif
                        </div>
                    @endfor
                </div>

                {{-- STEP 1: SERVICE SELECTION --}}
                @if($step === 1)
                    <div class="space-y-6">
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
                                        — {{ Number::currency($service->price / 100, 'EUR') }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                        @endif

                        @if($this->service_id && count($this->availablePriceTiers) > 1)
                            <flux:radio.group wire:model.live="participant_count" :label="__('Dalībnieku skaits')"
                                              variant="cards">
                                @foreach($this->availablePriceTiers as $tier)
                                    <flux:radio :value="$tier->participant_count"
                                                :label="$tier->participant_count . ' ' . ($tier->participant_count === 1 ? __('persona') : __('personas'))"
                                                :description="Number::currency($tier->price / 100, 'EUR')"/>
                                @endforeach
                            </flux:radio.group>
                        @endif

                        <div class="flex justify-end">
                            <flux:button wire:click="nextStep"
                                         class="button small primary">{{ __('Tālāk') }}</flux:button>
                        </div>
                    </div>
                @endif

                {{-- STEP 2: DATE & TIME SELECTION --}}
                @if($step === 2)
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
                                                :description="$slot['remaining'] . ' ' . ($slot['remaining'] === 1 ? __('vieta') : __('vietas'))"/>
                                @endforeach
                            </flux:radio.group>
                        @elseif($this->selectedDate)
                            <flux:text class="text-center">{{ __('Šajā datumā nav pieejamu laiku.') }}</flux:text>
                        @endif

                        <div class="flex justify-between">
                            <flux:button wire:click="previousStep"
                                         class="button small tertiary">{{ __('Atpakaļ') }}</flux:button>
                            <flux:button wire:click="nextStep"
                                         class="button small primary">{{ __('Tālāk') }}</flux:button>
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

                            <div class="p-4 space-y-1">
                                <flux:text class="font-medium">{{ $this->name }} {{ $this->surname }}</flux:text>
                                <flux:text>{{ $this->phone }}</flux:text>
                                <flux:text>{{ $this->email }}</flux:text>
                            </div>
                        @endif

                        <div class="flex justify-between">
                            <flux:button wire:click="previousStep"
                                         class="button small tertiary">{{ __('Atpakaļ') }}</flux:button>
                            <div class="flex flex-col gap-2">
                                <flux:button wire:click="submitBooking"
                                             class="button small primary">{{ __('Apmaksāt uz vietas') }}
                                </flux:button>
                                <flux:button wire:click="submitBooking"
                                             class="button small primary">{{ __('Apmaksāt ar karti') }}
                                </flux:button>
                            </div>
                        </div>
                    </div>
                @endif
            @endif
        </div>
    </flux:modal>
</div>
