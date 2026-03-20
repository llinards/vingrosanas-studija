<?php

use App\Enums\AttendanceStatus;
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
     * Selected attendance statuses for filtering.
     *
     * @var array<int, string>
     */
    #[Url]
    public array $attendanceStatuses = [];

    /**
     * Whether to show only today's bookings.
     */
    #[Url]
    public bool $todayOnly = true;

    /**
     * Whether to show only past bookings.
     */
    #[Url]
    public bool $pastOnly = false;

    /**
     * Whether to show only future bookings.
     */
    #[Url]
    public bool $futureOnly = false;

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

        if (empty($this->attendanceStatuses)) {
            $this->attendanceStatuses = collect(AttendanceStatus::cases())
                ->map(fn(AttendanceStatus $status) => $status->value)
                ->all();
        }
    }

    /**
     * Reset pagination and disable pastOnly when todayOnly is enabled.
     */
    public function updatedTodayOnly(): void
    {
        if ($this->todayOnly) {
            $this->pastOnly = false;
            $this->futureOnly = false;
        }

        $this->resetPage();
    }

    /**
     * Reset pagination and disable todayOnly and futureOnly when pastOnly is enabled.
     */
    public function updatedPastOnly(): void
    {
        if ($this->pastOnly) {
            $this->todayOnly = false;
            $this->futureOnly = false;
        }

        $this->resetPage();
    }

    /**
     * Reset pagination and disable todayOnly and pastOnly when futureOnly is enabled.
     */
    public function updatedFutureOnly(): void
    {
        if ($this->futureOnly) {
            $this->todayOnly = false;
            $this->pastOnly = false;
        }

        $this->resetPage();
    }

    /**
     * Reset pagination when any other filter changes.
     */
    public function updated(): void
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
                      ->when($this->search, fn($query) => $query->search($this->search))
                      ->whereIn('payment_status', $this->paymentStatuses)
                      ->whereIn('attendance_status', $this->attendanceStatuses)
                      ->when($this->todayOnly, fn($query) => $query->whereDate('booking_date', today()))
                      ->when($this->pastOnly, fn($query) => $query->whereDate('booking_date', '<', today()))
                      ->when($this->futureOnly, fn($query) => $query->whereDate('booking_date', '>', today()))
                      ->when(! $this->pastOnly && ! $this->todayOnly && ! $this->futureOnly,
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
                        wire:model.live.debounce.300ms="search" placeholder="{{ __('Meklēt rezervācijas') }}"/>

            <div class="flex flex-wrap items-end gap-8">
                <flux:checkbox.group wire:model.live="paymentStatuses">
                    @foreach(PaymentStatus::cases() as $status)
                        <flux:checkbox label="{{ $status->label() }}" value="{{ $status->value }}"/>
                    @endforeach
                </flux:checkbox.group>

                <flux:checkbox.group wire:model.live="attendanceStatuses">
                    @foreach(AttendanceStatus::cases() as $status)
                        <flux:checkbox label="{{ $status->label() }}" value="{{ $status->value }}"/>
                    @endforeach
                </flux:checkbox.group>

                <flux:checkbox.group>
                    <flux:checkbox label="{{ __('Tikai šodienas') }}" wire:model.live="todayOnly"/>
                    <flux:checkbox label="{{ __('Tikai pagātnes') }}" wire:model.live="pastOnly"/>
                    <flux:checkbox label="{{ __('Tikai nākotnes') }}" wire:model.live="futureOnly"/>
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
                    <flux:table.column>{{ __('Maksājums') }}</flux:table.column>
                    <flux:table.column colspan="2">{{ __('Apmeklējums') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach($this->bookings as $booking)
                        <flux:table.row wire:key="booking-{{ $booking->id }}">
                            <flux:table.cell>
                                <div>{{ $booking->name }} {{ $booking->surname }}</div>
                                @if($booking->isMembershipBooking())
                                    <flux:badge size="sm" color="purple">{{ __('Abonements') }}</flux:badge>
                                @endif
                            </flux:table.cell>
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
                                <flux:badge size="sm" :color="match($booking->attendance_status->value) {
                                'attended' => 'green',
                                'missed' => 'red',
                                'pending' => 'zinc',
                            }">{{ $booking->attendance_status->label() }}</flux:badge>
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
