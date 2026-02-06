<?php

use App\Livewire\Concerns\HasBookingForm;
use App\Models\Booking;
use Flux\Flux;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

new class extends Component {
    use HasBookingForm;

    public function updatedServiceTypeId(): void
    {
        $this->service_id = null;
        $this->schedule_id = null;
        unset($this->services, $this->schedules);
    }

    public function updatedServiceId(): void
    {
        $this->schedule_id = null;
        unset($this->schedules);
    }

    public function save(): void
    {
        $this->validate();

        try {
            Booking::create([
                'schedule_id' => $this->schedule_id,
                'booking_date' => $this->booking_date,
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
