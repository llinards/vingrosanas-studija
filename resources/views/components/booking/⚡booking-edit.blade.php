<?php

use App\Actions\RefundBooking;
use App\Enums\PaymentStatus;
use App\Livewire\Concerns\HasBookingForm;
use App\Mail\BookingRescheduled;
use App\Models\Booking;
use Flux\Flux;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Stripe\Exception\ApiErrorException;

new class extends Component
{
    use HasBookingForm;

    #[Locked]
    public int $bookingId;

    /**
     * Initialize the component with existing booking data.
     */
    public function mount(Booking $booking): void
    {
        $booking->load('schedule.service');

        $this->bookingId = $booking->id;
        $this->coach_id = $booking->schedule->service->coach_id;
        $this->schedule_id = $booking->schedule_id;
        $this->booking_date = $booking->booking_date->format('Y-m-d');
        $this->participant_count = $booking->participant_count;
        $this->name = $booking->name;
        $this->surname = $booking->surname;
        $this->phone = $booking->phone;
        $this->email = $booking->email;
        $this->payment_status = $booking->payment_status->value;
        $this->attendance_status = $booking->attendance_status->value;
    }

    protected function editedBookingId(): ?int
    {
        return $this->bookingId;
    }

    protected function savedBooking(): ?Booking
    {
        return Booking::with('schedule.service')->find($this->bookingId);
    }

    /**
     * A booking that keeps its date may be corrected after that date has passed;
     * only a move to a different date has to land on today or later.
     *
     * @return array<int, string>
     */
    protected function bookingDateRules(): array
    {
        $savedDate = Booking::findOrFail($this->bookingId)->booking_date->format('Y-m-d');

        return $this->booking_date === $savedDate
            ? ['required', 'date']
            : ['required', 'date', 'after_or_equal:today'];
    }

    /**
     * Validate and save the updated booking data.
     */
    public function save(): void
    {
        $this->validate();

        $booking = Booking::findOrFail($this->bookingId);

        if ($booking->isRefundedThroughStripe() && $this->payment_status !== PaymentStatus::Refunded->value) {
            $this->addError('payment_status', __('Šī rezervācija ir atmaksāta caur Stripe, tāpēc maksājuma statusu vairs nevar mainīt.'));

            return;
        }

        try {
            // Returning the money comes first: if Stripe refuses, nothing about the booking is changed.
            if ($booking->isPaidThroughStripe() && $this->payment_status === PaymentStatus::Refunded->value) {
                app(RefundBooking::class)->execute($booking, enforceCancellationWindow: false);
            }

            $scheduleChanged = $booking->schedule_id !== (int) $this->schedule_id;
            $dateChanged = $booking->booking_date->format('Y-m-d') !== $this->booking_date;
            $originalDate = $booking->booking_date->copy();
            $originalTime = $booking->schedule->start_time;

            $booking->update([
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

            if ($scheduleChanged || $dateChanged) {
                $booking->load('schedule.service.coach');
                Mail::to($booking->email)->send(new BookingRescheduled($booking, $originalDate, $originalTime));
            }

            Flux::toast(
                text: __('Rezervācija atjaunināta!'),
                variant: 'success',
            );

            $this->redirect(route('admin.bookings.index'), navigate: true);
        } catch (ApiErrorException $e) {
            Flux::toast(
                text: __('Neizdevās veikt atmaksu caur Stripe. Rezervācija nav mainīta.'),
                heading: __('Kļūda!'),
                variant: 'danger',
            );
        } catch (\Exception $e) {
            Log::error($e);

            Flux::toast(
                text: __('Neizdevās atjaunināt rezervāciju. Lūdzu, mēģini vēlreiz.'),
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
            ->title(__('Rediģēt rezervāciju'));
    }
};
?>


<div>
    <x-booking.booking-form :heading="__('Rediģēt rezervāciju')"/>
</div>
