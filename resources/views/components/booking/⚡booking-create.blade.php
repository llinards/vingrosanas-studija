<?php

use App\Livewire\Concerns\HasBookingForm;
use App\Models\Booking;
use Flux\Flux;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

new class extends Component
{
    use HasBookingForm;

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

    public function save(): void
    {
        $this->validate();

        try {
            Booking::create([
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
                text: __('Rezervācija izveidota!'),
                variant: 'success',
            );

            $this->redirect(route('booking-list'), navigate: true);
        } catch (\Exception $e) {
            Log::error($e);

            Flux::toast(
                text: __('Neizdevās izveidot rezervāciju. Lūdzu, mēģini vēlreiz.'),
                heading: __('Kļūda!'),
                variant: 'danger',
            );
        }
    }

    public function render(): \Illuminate\View\View
    {
        return $this->view()
            ->title(__('Pievienot jaunu rezervāciju'));
    }
};
?>


<x-booking.booking-form :heading="__('Pievienot jaunu rezervāciju')"/>
