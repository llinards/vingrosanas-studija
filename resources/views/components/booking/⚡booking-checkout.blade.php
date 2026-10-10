<?php

use App\Actions\CreatePendingBooking;
use App\Actions\CreateStripeCheckoutSession;
use App\Enums\SlotStatus;
use App\Exceptions\SlotUnavailableException;
use App\Models\Schedule;
use App\Services\ScheduleAvailabilityService;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Stripe\Exception\ApiErrorException;

new class extends Component
{
    /**
     * How many bookings one visitor may start within the window below.
     */
    private const MAX_BOOKING_STARTS = 5;

    private const BOOKING_STARTS_WINDOW_SECONDS = 600;

    #[Locked]
    public ?int $scheduleId = null;

    #[Locked]
    public ?string $date = null;

    public int $participantCount = 1;

    public string $name = '';

    public string $surname = '';

    public string $phone = '';

    public string $email = '';

    public bool $isProcessing = false;

    /**
     * Open the checkout for a class chosen in the timetable.
     *
     * The slot comes from the browser, so it is checked again before it is accepted.
     */
    #[On('open-booking-checkout')]
    public function open(int $scheduleId, string $date): void
    {
        $this->resetErrorBag();
        $this->reset('scheduleId', 'date', 'participantCount', 'isProcessing');
        unset($this->schedule, $this->participantOptions);

        if ($this->isBookable($scheduleId, $date)) {
            $this->scheduleId = $scheduleId;
            $this->date = $date;
            $this->participantCount = $this->participantOptions[0];
        } else {
            $this->dispatch('timetable-refresh');
        }

        Flux::modal('booking-checkout')->show();
    }

    /**
     * The class being booked, or null when none is open.
     */
    #[Computed]
    public function schedule(): ?Schedule
    {
        if ($this->scheduleId === null) {
            return null;
        }

        return Schedule::with(['service.coach', 'service.priceTiers'])->find($this->scheduleId);
    }

    /**
     * Group sizes the customer may choose for the open class.
     *
     * @return list<int>
     */
    #[Computed]
    public function participantOptions(): array
    {
        if ($this->schedule === null) {
            return [1];
        }

        return app(CreatePendingBooking::class)->allowedParticipantCounts($this->schedule);
    }

    /**
     * Total price in cents for the chosen group size.
     */
    #[Computed]
    public function price(): int
    {
        return $this->schedule?->service->priceFor($this->participantCount) ?? 0;
    }

    /**
     * Reserve the place and send the customer to Stripe to pay.
     */
    public function pay(): void
    {
        if ($this->isProcessing || $this->schedule === null) {
            return;
        }

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'surname' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
            'participantCount' => ['required', 'integer', Rule::in($this->participantOptions)],
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
            'participantCount.*' => __('Izvēlētais dalībnieku skaits nav pieejams.'),
        ]);

        if (RateLimiter::tooManyAttempts($this->bookingStartsKey(), self::MAX_BOOKING_STARTS)) {
            $this->addError('booking', $this->tooManyBookingStartsMessage());

            return;
        }

        $this->isProcessing = true;

        try {
            $booking = app(CreatePendingBooking::class)->execute($this->scheduleId, $this->date, $this->participantCount, [
                'name' => $this->name,
                'surname' => $this->surname,
                'phone' => $this->phone,
                'email' => $this->email,
            ]);
        } catch (SlotUnavailableException) {
            $this->isProcessing = false;
            $this->addError('booking', __('Šis laiks vairs nav pieejams. Lūdzu, izvēlieties citu.'));
            $this->dispatch('timetable-refresh');

            return;
        }

        try {
            $checkout = app(CreateStripeCheckoutSession::class)->execute($booking, $booking->price);
        } catch (ApiErrorException) {
            // No checkout session exists, so this booking could never be paid: release the place.
            $booking->delete();

            $this->isProcessing = false;
            $this->addError('booking', __('Maksājuma sistēma pašlaik nav pieejama. Lūdzu, mēģiniet vēlreiz.'));

            return;
        }

        // Only a start that holds a place and reaches Stripe counts towards the limit.
        RateLimiter::hit($this->bookingStartsKey(), self::BOOKING_STARTS_WINDOW_SECONDS);

        $this->redirect($checkout['url'], navigate: false);
    }

    /**
     * Key under which the booking starts of the current visitor are counted.
     */
    private function bookingStartsKey(): string
    {
        return 'booking-starts:'.request()->ip();
    }

    private function tooManyBookingStartsMessage(): string
    {
        $minutes = max(1, (int) ceil(RateLimiter::availableIn($this->bookingStartsKey()) / 60));

        return $minutes === 1
            ? __('Pārāk daudz mēģinājumu. Lūdzu, mēģiniet vēlreiz pēc 1 minūtes.')
            : __('Pārāk daudz mēģinājumu. Lūdzu, mēģiniet vēlreiz pēc :minutes minūtēm.', ['minutes' => $minutes]);
    }

    private function isBookable(int $scheduleId, string $date): bool
    {
        if (! Carbon::canBeCreatedFromFormat($date, 'Y-m-d')) {
            return false;
        }

        $schedule = Schedule::with('service')->find($scheduleId);

        if ($schedule === null) {
            return false;
        }

        return app(ScheduleAvailabilityService::class)->slotStatus($schedule, Carbon::parse($date)) === SlotStatus::Available;
    }
};
?>

<div>
    <flux:modal name="booking-checkout" :dismissible="false" class="w-[calc(100vw-2rem)] max-w-lg">
        @if($this->schedule)
            <form wire:submit="pay" class="space-y-6 p-6 md:p-8">
                <flux:heading size="lg">{{ __('Pieteikšanās nodarbībai') }}</flux:heading>

                {{-- CLASS SUMMARY --}}
                <div class="rounded-lg border border-zinc-200 p-3 space-y-1">
                    <div class="flex justify-between gap-2 text-sm">
                        <flux:text class="text-zinc-500 shrink-0">{{ __('Nodarbība') }}</flux:text>
                        <flux:text class="font-medium text-right">{{ $this->schedule->service->name }}</flux:text>
                    </div>
                    @if($this->schedule->service->coach)
                        <div class="flex justify-between gap-2 text-sm">
                            <flux:text class="text-zinc-500 shrink-0">{{ __('Treneris') }}</flux:text>
                            <flux:text class="font-medium text-right">{{ $this->schedule->service->coach->name }}</flux:text>
                        </div>
                    @endif
                    <div class="flex justify-between gap-2 text-sm">
                        <flux:text class="text-zinc-500 shrink-0">{{ __('Datums') }}</flux:text>
                        <flux:text class="font-medium">{{ Carbon::parse($date)->format('d.m.Y') }}</flux:text>
                    </div>
                    <div class="flex justify-between gap-2 text-sm">
                        <flux:text class="text-zinc-500 shrink-0">{{ __('Laiks') }}</flux:text>
                        <flux:text class="font-medium">{{ substr($this->schedule->start_time, 0, 5) }}</flux:text>
                    </div>
                    <div class="flex justify-between gap-2 text-sm">
                        <flux:text class="text-zinc-500 shrink-0">{{ __('Cena') }}</flux:text>
                        <flux:text class="font-semibold">{{ Number::currency($this->price / 100, 'EUR') }}</flux:text>
                    </div>
                </div>

                {{-- GROUP SIZE (individual sessions with several price tiers) --}}
                @if(count($this->participantOptions) > 1)
                    <flux:radio.group wire:model.live="participantCount" :label="__('Dalībnieku skaits')"
                                      variant="cards" class="grid grid-cols-3 gap-2">
                        @foreach($this->participantOptions as $option)
                            <flux:radio wire:key="participants-{{ $option }}" :value="$option"
                                        :label="$option . ' ' . ($option === 1 ? __('persona') : __('personas'))"
                                        :description="Number::currency($this->schedule->service->priceFor($option) / 100, 'EUR')"/>
                        @endforeach
                    </flux:radio.group>
                @endif

                {{-- CUSTOMER DETAILS --}}
                <flux:input wire:model="name" :label="__('Vārds')" :placeholder="__('Ievadiet vārdu')"/>
                <flux:input wire:model="surname" :label="__('Uzvārds')" :placeholder="__('Ievadiet uzvārdu')"/>
                <flux:input wire:model="phone" :label="__('Tālrunis')" :placeholder="__('Ievadiet tālruni')"/>
                <flux:input wire:model="email" type="email" :label="__('E-pasts')"
                            :placeholder="__('Ievadiet e-pastu')"/>

                @error('participantCount')
                <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text>
                @enderror
                @error('booking')
                <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text>
                @enderror

                <div class="flex justify-between">
                    <flux:modal.close>
                        <flux:button class="btn btn-sm btn-tertiary">{{ __('Atcelt') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" class="btn btn-sm btn-primary"
                                 :disabled="$isProcessing">{{ __('Apmaksāt ar karti') }}</flux:button>
                </div>
            </form>
        @else
            <div class="space-y-6 p-6 md:p-8">
                <flux:heading size="lg">{{ __('Šis laiks vairs nav pieejams.') }}</flux:heading>
                <flux:text>{{ __('Lūdzu, izvēlieties citu nodarbību sarakstā.') }}</flux:text>
                <flux:modal.close>
                    <flux:button class="btn btn-sm btn-primary">{{ __('Aizvērt') }}</flux:button>
                </flux:modal.close>
            </div>
        @endif
    </flux:modal>
</div>
