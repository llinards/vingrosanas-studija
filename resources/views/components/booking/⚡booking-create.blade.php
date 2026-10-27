<?php

use App\Livewire\Concerns\HasBookingForm;
use App\Models\Booking;
use Flux\Flux;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

new class extends Component
{
    use HasBookingForm;

    /**
     * Validate and create a new booking.
     */
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
                'attendance_status' => $this->attendance_status,
            ]);

            Flux::toast(
                text: __('Rezervācija izveidota!'),
                variant: 'success',
            );

            $this->redirect(route('admin.bookings.index'), navigate: true);
        } catch (\Exception $e) {
            Log::error($e);

            Flux::toast(
                text: __('Neizdevās izveidot rezervāciju. Lūdzu, mēģini vēlreiz.'),
                heading: __('Kļūda!'),
                variant: 'danger',
            );
        }
    }

    /**
     * Render the component view with the page title.
     */
    public function render(): \Illuminate\View\View
    {
        return $this->view()
            ->title(__('Pievienot jaunu rezervāciju'));
    }
};
?>


<x-booking.booking-form :heading="__('Pievienot jaunu rezervāciju')"/>
