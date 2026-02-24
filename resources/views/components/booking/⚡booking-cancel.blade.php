<?php

use App\Actions\RefundBooking;
use App\Exceptions\RefundNotAllowedException;
use App\Models\Booking;
use Flux\Flux;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public int $bookingId;

    #[Locked]
    public ?string $sessionId = null;

    public bool $isRefundable = false;

    /**
     * Initialize the component with the booking data.
     */
    public function mount(Booking $booking): void
    {
        $booking->loadMissing('schedule');

        $this->bookingId = $booking->id;
        $this->sessionId = request()->query('session_id');
        $this->isRefundable = $booking->isRefundable();
    }

    /**
     * Process a refund and cancel the booking.
     */
    public function cancel(): void
    {
        $booking = Booking::findOrFail($this->bookingId);

        try {
            app(RefundBooking::class)->execute($booking);

            $url = $this->sessionId
                ? route('booking.success', ['booking' => $booking, 'session_id' => $this->sessionId])
                : route('booking.success', $booking);

            $this->redirect($url, navigate: false);
        } catch (RefundNotAllowedException $e) {
            Flux::toast(
                text: __('Atcelšana nav iespējama. Līdz nodarbībai jābūt vairāk nekā 24 stundām.'),
                heading: __('Kļūda!'),
                variant: 'danger',
            );
        } catch (\Exception $e) {
            Log::error($e);

            Flux::toast(
                text: __('Neizdevās atcelt rezervāciju. Lūdzu, mēģiniet vēlreiz.'),
                heading: __('Kļūda!'),
                variant: 'danger',
            );
        }
    }
};
?>

<div>
    @if($isRefundable)
        <flux:separator class="my-6" />

        <flux:text class="text-center mb-2">
            {{ __('Vēlaties atcelt rezervāciju?') }}
        </flux:text>
        <flux:text class="text-center mb-4">
            {{ __('Atcelšana un atmaksa ir iespējama, ja līdz nodarbības sākumam ir vairāk nekā 24h.') }}
        </flux:text>

        <div class="text-center">
            <flux:link as="button" wire:click="cancel"
                         wire:confirm="{{ __('Vai tiešām vēlaties atcelt rezervāciju un saņemt atmaksu?') }}"
                       icon="arrow-uturn-left">
                {{ __('Atcelt rezervāciju') }}
            </flux:link>
        </div>
    @endif
</div>
