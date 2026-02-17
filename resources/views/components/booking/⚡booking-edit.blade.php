<?php

use App\Livewire\Concerns\HasBookingForm;
use App\Models\Booking;
use Flux\Flux;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    use HasBookingForm;

    #[Locked]
    public int $bookingId;

    public function mount(Booking $booking): void
    {
        $booking->load('schedule.service');

        $this->bookingId = $booking->id;
        $this->service_type_id = $booking->schedule->service->service_type_id;
        $this->service_id = $booking->schedule->service_id;
        $this->schedule_id = $booking->schedule_id;
        $this->booking_date = $booking->booking_date->format('Y-m-d');
        $this->participant_count = $booking->participant_count;
        $this->name = $booking->name;
        $this->surname = $booking->surname;
        $this->phone = $booking->phone;
        $this->email = $booking->email;
        $this->payment_status = $booking->payment_status->value;
    }

    public function updatedServiceTypeId(): void
    {
        $this->service_id = null;
        $this->schedule_id = null;
        $this->participant_count = 1;
        unset(
            $this->services,
            $this->schedules,
            $this->selectedService,
            $this->isExclusiveService,
            $this->availablePriceTiers
        );
    }

    public function updatedServiceId(): void
    {
        $this->schedule_id = null;
        $this->participant_count = 1;
        unset(
            $this->schedules,
            $this->selectedService,
            $this->selectedSchedule,
            $this->isExclusiveService,
            $this->availablePriceTiers
        );
    }

    public function updatedScheduleId(): void
    {
        $this->participant_count = 1;
        unset($this->selectedSchedule, $this->availablePriceTiers, $this->remainingCapacity, $this->isSlotAlreadyBooked);
    }

    public function updatedBookingDate(): void
    {
        unset($this->remainingCapacity, $this->isSlotAlreadyBooked);
    }

    /**
     * Override isSlotAlreadyBooked to exclude the current booking.
     */
    public function getIsSlotAlreadyBookedProperty(): bool
    {
        if (! $this->schedule_id || ! $this->booking_date) {
            return false;
        }

        return Booking::where('schedule_id', $this->schedule_id)
            ->whereDate('booking_date', $this->booking_date)
            ->where('id', '!=', $this->bookingId)
            ->exists();
    }

    /**
     * Override remainingCapacity to exclude the current booking's participants.
     */
    public function getRemainingCapacityProperty(): int
    {
        if (! $this->schedule_id || ! $this->booking_date) {
            return 0;
        }

        $schedule = $this->selectedSchedule;
        if (! $schedule) {
            return 0;
        }

        $bookedParticipants = Booking::where('schedule_id', $this->schedule_id)
            ->whereDate('booking_date', $this->booking_date)
            ->where('id', '!=', $this->bookingId)
            ->sum('participant_count');

        return max(0, $schedule->max_capacity - $bookedParticipants);
    }

    public function save(): void
    {
        $this->validate();

        try {
            $booking = Booking::findOrFail($this->bookingId);

            $booking->update([
                'schedule_id' => $this->schedule_id,
                'booking_date' => $this->booking_date,
                'participant_count' => $this->participant_count,
                'name' => $this->name,
                'surname' => $this->surname,
                'phone' => $this->phone,
                'email' => $this->email,
                'payment_status' => $this->payment_status,
            ]);

            Flux::toast(
                text: __('Rezervācija atjaunināta!'),
                variant: 'success',
            );

            $this->redirect(route('booking-list'), navigate: true);
        } catch (\Exception $e) {
            Log::error($e);

            Flux::toast(
                text: __('Neizdevās atjaunināt rezervāciju. Lūdzu, mēģini vēlreiz.'),
                heading: __('Kļūda!'),
                variant: 'danger',
            );
        }
    }

    public function render(): \Illuminate\View\View
    {
        return $this->view()
            ->title(__('Rediģēt rezervāciju'));
    }
};
?>


<x-booking.booking-form :heading="__('Rediģēt rezervāciju')"/>
