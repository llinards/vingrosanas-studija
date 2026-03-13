<?php

use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Membership;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\ServiceType;
use App\Services\ScheduleAvailabilityService;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public int $membershipId;

    #[Locked]
    public string $sessionId;

    /**
     * The booking ID being rebooked (null if not rebooking).
     */
    public ?int $rebookingBookingId = null;

    /**
     * Rebook form fields.
     */
    public ?int $rebook_service_type_id = null;

    public ?int $rebook_service_id = null;

    public ?string $rebook_date = null;

    public ?int $rebook_schedule_id = null;

    /**
     * Initialize the component and verify access.
     */
    public function mount(Membership $membership): void
    {
        $sessionId = request()->query('session_id');

        if ( ! $sessionId || $sessionId !== $membership->stripe_checkout_session_id) {
            abort(404);
        }

        $this->membershipId = $membership->id;
        $this->sessionId    = $sessionId;
    }

    /**
     * Get the membership.
     */
    #[Computed]
    public function membership(): Membership
    {
        return Membership::with('service')->findOrFail($this->membershipId);
    }

    /**
     * Get the bookings for this membership.
     */
    #[Computed]
    public function bookings(): Collection
    {
        return $this->membership->bookings()
                                ->with(['schedule.service.coach'])
                                ->whereNotIn('payment_status', [PaymentStatus::Refunded, PaymentStatus::Failed])
                                ->orderBy('booking_date')
                                ->get();
    }

    /**
     * Get service types for the rebook form.
     */
    #[Computed]
    public function serviceTypes(): Collection
    {
        return ServiceType::whereHas('services', function ($query) {
            $query->where('is_active', true)
                  ->where('is_membership_eligible', true)
                  ->whereHas('schedules', fn($q) => $q->where('is_active', true));
        })->get();
    }

    /**
     * Get filtered services for the rebook form.
     */
    #[Computed]
    public function rebookServices(): Collection
    {
        if ( ! $this->rebook_service_type_id) {
            return new Collection;
        }

        return Service::with('coach')
                      ->where('is_active', true)
                      ->where('is_membership_eligible', true)
                      ->where('service_type_id', $this->rebook_service_type_id)
                      ->whereHas('schedules', fn($query) => $query->where('is_active', true))
                      ->get();
    }

    /**
     * Get active schedules for the rebook service.
     */
    #[Computed]
    public function rebookSchedules(): Collection
    {
        if ( ! $this->rebook_service_id) {
            return new Collection;
        }

        return Schedule::with('service.coach')
                       ->where('service_id', $this->rebook_service_id)
                       ->where('is_active', true)
                       ->get();
    }

    /**
     * Get unavailable dates for the rebook calendar.
     */
    #[Computed]
    public function rebookUnavailableDates(): string
    {
        return app(ScheduleAvailabilityService::class)->unavailableDates(
            schedules: $this->rebookSchedules,
            startDate: Carbon::today(),
            endDate: $this->membership->period_end,
            excludeBookingId: $this->rebookingBookingId,
        );
    }

    /**
     * Get available time slots for the rebook date.
     *
     * @return array<int, array{schedule_id: int, start_time: string, coach_name: string, remaining: int, max_capacity: int}>
     */
    #[Computed]
    public function rebookTimeSlots(): array
    {
        if ( ! $this->rebook_date || ! $this->rebook_service_id) {
            return [];
        }

        return app(ScheduleAvailabilityService::class)->availableTimeSlots(
            schedules: $this->rebookSchedules,
            date: Carbon::parse($this->rebook_date),
            excludeBookingId: $this->rebookingBookingId,
        );
    }

    /**
     * Start rebooking a specific booking.
     */
    public function startRebook(int $bookingId): void
    {
        $this->rebookingBookingId     = $bookingId;
        $this->rebook_service_type_id = null;
        $this->rebook_service_id      = null;
        $this->rebook_date            = null;
        $this->rebook_schedule_id     = null;
        unset($this->rebookServices, $this->rebookSchedules, $this->rebookUnavailableDates, $this->rebookTimeSlots);
    }

    /**
     * Cancel the rebooking process.
     */
    public function cancelRebook(): void
    {
        $this->rebookingBookingId     = null;
        $this->rebook_service_type_id = null;
        $this->rebook_service_id      = null;
        $this->rebook_date            = null;
        $this->rebook_schedule_id     = null;
        unset($this->rebookServices, $this->rebookSchedules, $this->rebookUnavailableDates, $this->rebookTimeSlots);
    }

    public function updatedRebookServiceTypeId(): void
    {
        $this->rebook_service_id  = null;
        $this->rebook_date        = null;
        $this->rebook_schedule_id = null;
        unset($this->rebookServices, $this->rebookSchedules, $this->rebookUnavailableDates, $this->rebookTimeSlots);
    }

    public function updatedRebookServiceId(): void
    {
        $this->rebook_date        = null;
        $this->rebook_schedule_id = null;
        unset($this->rebookSchedules, $this->rebookUnavailableDates, $this->rebookTimeSlots);
    }

    public function updatedRebookDate(): void
    {
        $this->rebook_schedule_id = null;
        unset($this->rebookTimeSlots);
    }

    /**
     * Confirm the rebook and update the booking.
     */
    public function confirmRebook(): void
    {
        $this->validate([
            'rebook_service_id'  => ['required', 'exists:services,id'],
            'rebook_date'        => ['required', 'date'],
            'rebook_schedule_id' => ['required', 'exists:schedules,id'],
        ], [
            'rebook_service_id.required'  => __('Jums ir jāizvēlās pakalpojums.'),
            'rebook_date.required'        => __('Datums ir obligāts.'),
            'rebook_schedule_id.required' => __('Laika slots ir obligāts.'),
        ]);

        try {
            DB::transaction(function () {
                $booking  = Booking::findOrFail($this->rebookingBookingId);
                $schedule = Schedule::lockForUpdate()->findOrFail($this->rebook_schedule_id);

                // Verify capacity (excluding the booking being rebooked)
                $bookedParticipants = Booking::active()
                                             ->where('schedule_id', $this->rebook_schedule_id)
                                             ->whereDate('booking_date', $this->rebook_date)
                                             ->where('id', '!=', $this->rebookingBookingId)
                                             ->sum('participant_count');

                $remaining = $schedule->max_capacity - $bookedParticipants;

                if ($remaining < 1) {
                    throw new \RuntimeException('No capacity available');
                }

                $booking->update([
                    'schedule_id'  => $this->rebook_schedule_id,
                    'booking_date' => $this->rebook_date,
                ]);
            });

            $this->cancelRebook();
            unset($this->bookings);

            Flux::toast(
                text: __('Nodarbība veiksmīgi pārplānota!'),
                variant: 'success',
            );
        } catch (\RuntimeException $e) {
            Flux::toast(
                text: __('Šis laiks vairs nav pieejams. Lūdzu, izvēlieties citu.'),
                heading: __('Kļūda!'),
                variant: 'danger',
            );
        } catch (\Exception $e) {
            Log::error($e);

            Flux::toast(
                text: __('Neizdevās pārplānot nodarbību. Lūdzu, mēģiniet vēlreiz.'),
                heading: __('Kļūda!'),
                variant: 'danger',
            );
        }
    }

    /**
     * Check if a booking can be rebooked (is in the future).
     */
    public function canRebook(Booking $booking): bool
    {
        return $booking->getServiceDateTime()->isAfter(now());
    }

    /**
     * Render the component view with the page title.
     */
    public function render(): \Illuminate\View\View
    {
        return $this->view()
                    ->title(__('Pārvaldīt abonementu'))
                    ->layout('layouts.main');
    }
};
?>

<div class="container mx-auto pt-36 pb-12 px-4">
    <div class="md:max-w-xl mx-auto">
        <div class="py-8 px-6 md:px-8 border-8 md:border-12 border-blue rounded-4xl space-y-6">
            <flux:heading level="3" class="text-center">{{ __('Mans abonements') }}</flux:heading>

            <div class="space-y-2">
                <div class="flex justify-between items-center py-2 border-b border-gray-200">
                    <flux:text>{{ __('Abonements') }}</flux:text>
                    <flux:text>{{ $this->membership->tierLabel() }}</flux:text>
                </div>
                <div class="flex justify-between items-center py-2 border-b border-gray-200">
                    <flux:text>{{ __('Periods') }}</flux:text>
                    <flux:text>{{ $this->membership->period_start->format('d.m.Y') }}
                        — {{ $this->membership->period_end->format('d.m.Y') }}</flux:text>
                </div>
                <div class="flex justify-between items-center py-2 border-b border-gray-200">
                    <flux:text>{{ __('Nodarbības') }}</flux:text>
                    <flux:text class="font-semibold">{{ $this->bookings->count() }}
                        /{{ $this->membership->sessions_total }}</flux:text>
                </div>
            </div>

            <flux:separator/>

            <flux:heading level="4">{{ __('Rezervētās nodarbības') }}</flux:heading>

            <div class="space-y-3">
                @foreach($this->bookings as $booking)
                    <div class="rounded-lg border p-4 space-y-2" wire:key="manage-booking-{{ $booking->id }}">
                        <div class="flex items-center justify-between">
                            <div>
                                <flux:text class="font-medium">{{ $booking->schedule->service->name }}</flux:text>
                                <flux:text class="text-sm text-zinc-500">
                                    {{ $booking->booking_date->format('d.m.Y') }}
                                    {{ substr($booking->schedule->start_time, 0, 5) }}
                                    — {{ $booking->schedule->service->coach->name }}
                                </flux:text>
                            </div>
                            <div>
                                @if($booking->attendance_status->value === 'attended')
                                    <flux:badge size="sm"
                                                color="zinc">{{ $booking->attendance_status->label() }}</flux:badge>
                                @elseif($this->canRebook($booking))
                                    @if($this->rebookingBookingId !== $booking->id)
                                        <flux:button wire:click="startRebook({{ $booking->id }})"
                                                     class="button primary">
                                            {{ __('Pārplānot') }}
                                        </flux:button>
                                    @endif
                                @else
                                    <flux:badge size="sm" color="zinc">{{ __('Pagātnē') }}</flux:badge>
                                @endif
                            </div>
                        </div>

                        {{-- Rebook form for this booking --}}
                        @if($this->rebookingBookingId === $booking->id)
                            <flux:separator/>
                            <div class="space-y-4 pt-2">
                                <flux:select wire:model.live="rebook_service_type_id" :label="__('Nodarbība')">
                                    <flux:select.option value="">{{ __('Izvēlieties nodarbību') }}</flux:select.option>
                                    @foreach($this->serviceTypes as $type)
                                        <flux:select.option :value="$type->id">{{ $type->name }}</flux:select.option>
                                    @endforeach
                                </flux:select>

                                @if($this->rebook_service_type_id)
                                    <flux:select wire:model.live="rebook_service_id" :label="__('Treniņš')">
                                        <flux:select.option
                                            value="">{{ __('Izvēlieties treniņu') }}</flux:select.option>
                                        @foreach($this->rebookServices as $service)
                                            <flux:select.option :value="$service->id">
                                                {{ $service->name }} ({{ $service->coach->name }})
                                            </flux:select.option>
                                        @endforeach
                                    </flux:select>
                                @endif

                                @if($this->rebook_service_id)
                                    <div class="flex justify-center">
                                        <flux:calendar wire:model.live="rebook_date" min="today"
                                                       :max="$this->membership->period_end->toDateString()"
                                                       :unavailable="$this->rebookUnavailableDates"
                                                       locale="lv" start-day="1"/>
                                    </div>
                                @endif

                                @if($this->rebook_date && count($this->rebookTimeSlots) > 0)
                                    <flux:radio.group wire:model.live="rebook_schedule_id"
                                                      :label="__('Pieejamie laiki')" variant="cards">
                                        @foreach($this->rebookTimeSlots as $slot)
                                            <flux:radio :value="$slot['schedule_id']"
                                                        :label="$slot['start_time'] . ' — ' . $slot['coach_name']"
                                                        :description="$slot['remaining'] . ' ' . ($slot['remaining'] === 1 ? __('vieta') : __('vietas'))"/>
                                        @endforeach
                                    </flux:radio.group>
                                @elseif($this->rebook_date)
                                    <flux:text
                                        class="text-center">{{ __('Šajā datumā nav pieejamu laiku.') }}</flux:text>
                                @endif

                                <div class="flex justify-between">
                                    <flux:link as="button" wire:click="cancelRebook">{{ __('Atcelt') }}</flux:link>
                                    @if($this->rebook_schedule_id)
                                        <flux:button wire:click="confirmRebook"
                                                     class="button primary">{{ __('Apstiprināt') }}</flux:button>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        <div class="mt-8 text-center">
            <flux:link href="{{ route('home') }}"
                       icon="arrow-uturn-left">
                {{ __('Atgriezties uz sākumu') }}
            </flux:link>
        </div>
    </div>
</div>
