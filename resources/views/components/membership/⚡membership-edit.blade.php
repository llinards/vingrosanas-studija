<?php

use App\Enums\PaymentStatus;
use App\Models\Membership;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public int $membershipId;

    public string $name = '';

    public string $surname = '';

    public string $phone = '';

    public string $email = '';

    public string $period_end = '';

    public string $payment_status = '';

    /**
     * Initialize the component with existing membership data.
     */
    public function mount(Membership $membership): void
    {
        $this->membershipId = $membership->id;
        $this->name = $membership->name;
        $this->surname = $membership->surname;
        $this->phone = $membership->phone;
        $this->email = $membership->email;
        $this->period_end = $membership->period_end->format('Y-m-d');
        $this->payment_status = $membership->payment_status->value;
    }

    /**
     * Get the membership model with bookings.
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
            ->orderBy('booking_date')
            ->get();
    }

    /**
     * Get payment status options for the dropdown.
     *
     * @return array<int, array{value: string, label: string}>
     */
    #[Computed]
    public function paymentStatusOptions(): array
    {
        return collect(PaymentStatus::cases())
            ->map(fn (PaymentStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ])
            ->all();
    }

    /**
     * Validate and save the updated membership data.
     */
    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'surname' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'period_end' => ['required', 'date'],
            'payment_status' => ['required', 'string'],
        ], [
            'name.required' => __('Vārds ir obligāts.'),
            'surname.required' => __('Uzvārds ir obligāts.'),
            'phone.required' => __('Telefons ir obligāts.'),
            'email.required' => __('E-pasts ir obligāts.'),
            'email.email' => __('E-pastam jābūt derīgai adresei.'),
            'period_end.required' => __('Beigu datums ir obligāts.'),
            'period_end.date' => __('Beigu datumam jābūt derīgam datumam.'),
        ]);

        try {
            $membership = Membership::findOrFail($this->membershipId);

            $membership->update([
                'name' => $this->name,
                'surname' => $this->surname,
                'phone' => $this->phone,
                'email' => $this->email,
                'period_end' => $this->period_end,
                'payment_status' => $this->payment_status,
            ]);

            Flux::toast(
                text: __('Abonements atjaunināts!'),
                variant: 'success',
            );

            $this->redirect(route('admin.memberships.index'), navigate: true);
        } catch (\Exception $e) {
            Log::error($e);

            Flux::toast(
                text: __('Neizdevās atjaunināt abonementu. Lūdzu, mēģini vēlreiz.'),
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
            ->title(__('Rediģēt abonementu'));
    }
};
?>

<div class="flex justify-center">
    <div class="w-full max-w-2xl">
        <flux:heading level="1" size="xl" class="mb-6">{{ __('Rediģēt abonementu') }}</flux:heading>

        <form wire:submit="save" class="flex flex-col gap-6">
            <div class="flex flex-col gap-6 sm:flex-row">
                <div class="sm:flex-1">
                    <flux:input wire:model="name" :label="__('Vārds')"/>
                </div>
                <div class="sm:flex-1">
                    <flux:input wire:model="surname" :label="__('Uzvārds')"/>
                </div>
            </div>

            <div class="flex flex-col gap-6 sm:flex-row">
                <div class="sm:flex-1">
                    <flux:input wire:model="phone" :label="__('Telefons')"/>
                </div>
                <div class="sm:flex-1">
                    <flux:input wire:model="email" type="email" :label="__('E-pasts')"/>
                </div>
            </div>

            <flux:separator/>

            <div class="flex flex-col gap-6 sm:flex-row">
                <div class="sm:flex-1">
                    <flux:input :value="$this->membership->tierLabel()" :label="__('Abonements')" disabled/>
                </div>
                <div class="sm:flex-1">
                    <flux:input :value="$this->membership->period_start->format('d.m.Y')" :label="__('Sākuma datums')" disabled/>
                </div>
            </div>

            <div class="flex flex-col gap-6 sm:flex-row">
                <div class="sm:flex-1">
                    <flux:input wire:model="period_end" type="date" :label="__('Beigu datums')"/>
                </div>
                <div class="sm:flex-1">
                    <flux:select wire:model="payment_status" :label="__('Maksājuma statuss')">
                        @foreach($this->paymentStatusOptions as $option)
                            <flux:select.option :value="$option['value']">{{ $option['label'] }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            </div>

            <div class="flex items-center justify-end gap-4">
                <flux:button href="{{ route('admin.memberships.index') }}" wire:navigate variant="ghost">
                    {{ __('Atcelt') }}
                </flux:button>
                <flux:button type="submit" variant="primary">
                    {{ __('Saglabāt') }}
                </flux:button>
            </div>
        </form>

        @if($this->bookings->isNotEmpty())
            <flux:separator class="my-6"/>

            <flux:heading level="2" size="lg" class="mb-4">{{ __('Rezervētās nodarbības') }}
                ({{ $this->bookings->count() }}/{{ $this->membership->sessions_total }})</flux:heading>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Pakalpojums') }}</flux:table.column>
                    <flux:table.column>{{ __('Treneris') }}</flux:table.column>
                    <flux:table.column>{{ __('Datums / Laiks') }}</flux:table.column>
                    <flux:table.column>{{ __('Apmeklējums') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach($this->bookings as $booking)
                        <flux:table.row wire:key="booking-{{ $booking->id }}">
                            <flux:table.cell>{{ $booking->schedule->service->name }}</flux:table.cell>
                            <flux:table.cell>{{ $booking->schedule->service->coach->name }}</flux:table.cell>
                            <flux:table.cell>{{ $booking->booking_date->format('d.m.Y') }}
                                / {{ substr($booking->schedule->start_time, 0, 5) }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="match($booking->attendance_status->value) {
                                    'attended' => 'green',
                                    'missed' => 'red',
                                    'pending' => 'zinc',
                                }">{{ $booking->attendance_status->label() }}</flux:badge>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </div>
</div>
