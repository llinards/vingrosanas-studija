<?php

use App\Models\Booking;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    /**
     * Get paginated bookings ordered by date ascending.
     */
    #[Computed]
    public function bookings(): LengthAwarePaginator
    {
        return Booking::with(['schedule.service.coach'])
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
        @if($this->bookings->isEmpty())
            <flux:text class="text-center py-8">{{ __('Nav nevienas rezervācijas.') }}</flux:text>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Klients') }}</flux:table.column>
                    <flux:table.column>{{ __('Pakalpojums') }}</flux:table.column>
                    <flux:table.column>{{ __('Treneris') }}</flux:table.column>
                    <flux:table.column>{{ __('Datums') }}</flux:table.column>
                    <flux:table.column>{{ __('Laiks') }}</flux:table.column>
                    <flux:table.column>{{ __('Dalībnieki') }}</flux:table.column>
                    <flux:table.column>{{ __('Statuss') }}</flux:table.column>
                    <flux:table.column>{{ __('Darbības') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach($this->bookings as $booking)
                        <flux:table.row wire:key="booking-{{ $booking->id }}">
                            <flux:table.cell>{{ $booking->name }} {{ $booking->surname }}</flux:table.cell>
                            <flux:table.cell>{{ $booking->schedule->service->name }}</flux:table.cell>
                            <flux:table.cell>{{ $booking->schedule->service->coach->name }}</flux:table.cell>
                            <flux:table.cell>{{ $booking->booking_date->format('d.m.Y') }}</flux:table.cell>
                            <flux:table.cell>{{ substr($booking->schedule->start_time, 0, 5) }}</flux:table.cell>
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
                                                 size="sm">
                                        {{ __('Rediģēt') }}
                                    </flux:button>
                                    <flux:button wire:confirm="{{ __('Vai tiešām vēlies dzēst rezervāciju?') }}"
                                                 variant="danger"
                                                 size="sm"
                                                 wire:click="destroy({{ $booking->id }})">
                                        {{ __('Dzēst') }}
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
