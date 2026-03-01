<?php

use App\Enums\PaymentStatus;
use App\Models\Booking;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    /**
     * Selected payment statuses for filtering.
     *
     * @var array<int, string>
     */
    #[Url]
    public array $paymentStatuses = [];

    /**
     * Whether to show only today's bookings.
     */
    #[Url]
    public bool $todayOnly = false;

    /**
     * Whether to show only past bookings.
     */
    #[Url]
    public bool $pastOnly = false;

    /**
     * Search query for filtering bookings.
     */
    #[Url]
    public string $search = '';

    /**
     * Initialize the component with all payment statuses selected.
     */
    public function mount(): void
    {
        if (empty($this->paymentStatuses)) {
            $this->paymentStatuses = collect(PaymentStatus::cases())
                ->map(fn(PaymentStatus $status) => $status->value)
                ->all();
        }
    }

    /**
     * Reset pagination when filters change.
     */
    public function updatedPaymentStatuses(): void
    {
        $this->resetPage();
    }

    /**
     * Reset pagination when the today-only filter changes.
     */
    public function updatedTodayOnly(): void
    {
        $this->resetPage();
    }

    /**
     * Reset pagination when the past-only filter changes.
     */
    public function updatedPastOnly(): void
    {
        $this->resetPage();
    }

    /**
     * Reset pagination when the search filter changes.
     */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Get paginated bookings ordered by date ascending, filtered by payment status and optionally by today's date.
     */
    #[Computed]
    public function bookings(): LengthAwarePaginator
    {
        return Booking::with(['schedule.service.coach'])
                      ->when($this->search, fn($query) => $query->where(function ($subQuery) {
                          $subQuery->where('name', 'like', '%'.$this->search.'%')
                                   ->orWhere('surname', 'like', '%'.$this->search.'%')
                                   ->orWhere('phone', 'like', '%'.$this->search.'%')
                                   ->orWhere('email', 'like', '%'.$this->search.'%');
                      }))
                      ->whereIn('payment_status', $this->paymentStatuses)
                      ->when($this->todayOnly, fn($query) => $query->whereDate('booking_date', today()))
                      ->when($this->pastOnly, fn($query) => $query->whereDate('booking_date', '<', today()))
                      ->when(! $this->pastOnly && ! $this->todayOnly,
                          fn($query) => $query->whereDate('booking_date', '>=', today()))
                      ->orderBy('booking_date', 'asc')
                      ->paginate(10);
    }

    /**
     * Check if any bookings exist in the database.
     *
     * Used to determine whether to show the empty state or the list.
     */
    #[Computed]
    public function hasAnyBookings(): bool
    {
        return Booking::exists();
    }

    /**
     * Delete a booking from the database.
     */
    public function destroy(Booking $booking): void
    {
        try {
            $booking->delete();

            unset($this->bookings);

            Flux::toast(
                text: __('Rezervācija veiksmīgi dzēsta!'),
                variant: 'success',
            );
        } catch (\Exception $e) {
            Log::error($e);

            Flux::toast(
                text: __('Neizdevās dzēst rezervāciju. Lūdzu, mēģini vēlreiz.'),
                heading: __('Kļūda!'),
                variant: 'danger',
            );
        }
    }
};
?>

<div>
    @if(!$this->hasAnyBookings)
        <div class="flex flex-col items-center">
            <flux:text class="text-center py-8">{{ __('Šobrīd nav nevienas rezervācijas!') }}</flux:text>
            <flux:button href="{{ route('admin.bookings.create') }}" wire:navigate
                         class="mb-4">{{ __('Pievienot jaunu rezervāciju') }}
            </flux:button>
        </div>
    @else
        <div class="mb-6 flex flex-col gap-4">
            <flux:input prefix-icon="magnifying-glass" type="search"
                        wire:model.live="search" placeholder="{{ __('Meklēt rezervācijas') }}"/>

            <div class="flex flex-wrap items-end gap-8">
                <flux:checkbox.group wire:model.live="paymentStatuses">
                    @foreach(\App\Enums\PaymentStatus::cases() as $status)
                        <flux:checkbox label="{{ $status->label() }}" value="{{ $status->value }}"/>
                    @endforeach
                </flux:checkbox.group>

                <flux:checkbox.group>
                    <flux:checkbox label="{{ __('Tikai šodienas') }}" wire:model.live="todayOnly"/>
                    <flux:checkbox label="{{ __('Tikai pagātnes') }}" wire:model.live="pastOnly"/>
                </flux:checkbox.group>
            </div>
        </div>

        @if($this->bookings->isEmpty())
            <flux:text class="text-center py-8">{{ __('Nav nevienas rezervācijas.') }}</flux:text>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Klients') }}</flux:table.column>
                    <flux:table.column>{{ __('Pakalpojums') }}</flux:table.column>
                    <flux:table.column>{{ __('Treneris') }}</flux:table.column>
                    <flux:table.column>{{ __('Pieteicies uz') }}</flux:table.column>
                    <flux:table.column>{{ __('Dalībnieki') }}</flux:table.column>
                    <flux:table.column colspan="2">{{ __('Statuss') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach($this->bookings as $booking)
                        <flux:table.row wire:key="booking-{{ $booking->id }}">
                            <flux:table.cell>{{ $booking->name }} {{ $booking->surname }}</flux:table.cell>
                            <flux:table.cell>{{ $booking->schedule->service->name }}</flux:table.cell>
                            <flux:table.cell>{{ $booking->schedule->service->coach->name }}</flux:table.cell>
                            <flux:table.cell>{{ $booking->booking_date->format('d.m.Y') }}
                                / {{ substr($booking->schedule->start_time, 0, 5) }}</flux:table.cell>
                            <flux:table.cell>{{ $booking->participant_count }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="match($booking->payment_status->value) {
                                'paid' => 'green',
                                'pending' => 'yellow',
                                'failed' => 'red',
                                'refunded' => 'zinc',
                            }">{{ $booking->payment_status->label() }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex gap-2">
                                    <flux:button href="{{ route('admin.bookings.edit', $booking) }}" variant="primary"
                                                 size="sm"
                                                 icon="pencil">
                                    </flux:button>
                                    <flux:button wire:confirm="{{ __('Vai tiešām vēlies dzēst rezervāciju?') }}"
                                                 variant="danger"
                                                 size="sm"
                                                 icon="trash"
                                                 wire:click="destroy({{ $booking->id }})">
                                    </flux:button>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            <div class="mt-4">
                {{ $this->bookings->links() }}
            </div>
        @endif
    @endif
</div>
