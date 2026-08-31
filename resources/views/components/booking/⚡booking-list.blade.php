<?php

use App\Enums\AttendanceStatus;
use App\Enums\PaymentStatus;
use App\Mail\BookingDeleted;
use App\Models\Booking;
use App\Models\Coach;
use App\Models\Schedule;
use App\Models\Service;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    #[Url]
    public string $period = 'upcoming';

    /**
     * Start and end dates (Y-m-d) applied when the period is 'custom'.
     *
     * @var array{start: ?string, end: ?string}
     */
    #[Url]
    public array $dateRange = ['start' => null, 'end' => null];

    #[Url]
    public string $paymentStatus = '';

    #[Url]
    public string $attendanceStatus = '';

    #[Url]
    public string $coachId = '';

    #[Url]
    public string $serviceId = '';

    #[Url]
    public string $bookingType = '';

    #[Url]
    public string $search = '';

    /**
     * Reset pagination when any filter changes.
     */
    public function updated(): void
    {
        $this->resetPage();
    }

    /**
     * Discard the picked dates when leaving the custom period.
     */
    public function updatedPeriod(string $value): void
    {
        if ($value !== 'custom') {
            $this->reset('dateRange');
        }
    }

    /**
     * @return Collection<int, Coach>
     */
    #[Computed]
    public function coaches(): Collection
    {
        return Coach::orderBy('name')->get(['id', 'name']);
    }

    /**
     * @return Collection<int, Service>
     */
    #[Computed]
    public function services(): Collection
    {
        return Service::where('is_membership', false)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Get paginated bookings filtered by all active filters.
     *
     * Returns nothing without querying while the custom period has no dates picked.
     */
    #[Computed]
    public function bookings(): LengthAwarePaginator
    {
        if ($this->awaitingDateRange) {
            return new Paginator(items: [], total: 0, perPage: 10);
        }

        return Booking::with(['schedule.service.coach'])
            ->when($this->search, fn($query) => $query->search($this->search))
            ->when($this->paymentStatus, fn($query) => $query->where('payment_status', $this->paymentStatus))
            ->when($this->attendanceStatus, fn($query) => $query->where('attendance_status', $this->attendanceStatus))
            ->when($this->coachId, fn($query) => $query->whereHas('schedule.service', fn($sq) => $sq->where('coach_id', $this->coachId)))
            ->when($this->serviceId, fn($query) => $query->whereHas('schedule', fn($sq) => $sq->where('service_id', $this->serviceId)))
            ->when($this->bookingType === 'membership', fn($query) => $query->whereNotNull('membership_id'))
            ->when($this->bookingType === 'regular', fn($query) => $query->whereNull('membership_id'))
            ->when(
                true,
                fn($query) => match ($this->period) {
                    'past' => $query->whereDate('booking_date', '<', today()),
                    'today' => $query->whereDate('booking_date', today()),
                    'future' => $query->whereDate('booking_date', '>', today()),
                    'custom' => $this->applyCustomDateRange($query),
                    'all' => $query,
                    default => $query->whereDate('booking_date', '>=', today()),
                },
            )
            ->orderBy('booking_date', 'asc')
            ->orderBy(Schedule::select('start_time')->whereColumn('schedules.id', 'bookings.schedule_id'), 'asc')
            ->orderBy('bookings.id', 'asc')
            ->paginate(10);
    }

    /**
     * Check if the custom period is selected but no date has been picked yet.
     */
    #[Computed]
    public function awaitingDateRange(): bool
    {
        return $this->period === 'custom'
            && blank($this->dateRange['start'] ?? null)
            && blank($this->dateRange['end'] ?? null);
    }

    /**
     * Constrain the query to the picked date range, skipping either end when unset.
     *
     * @param  Builder<Booking>  $query
     * @return Builder<Booking>
     */
    private function applyCustomDateRange(Builder $query): Builder
    {
        return $query
            ->when($this->dateRange['start'] ?? null, fn($subQuery, $start) => $subQuery->whereDate('booking_date', '>=', $start))
            ->when($this->dateRange['end'] ?? null, fn($subQuery, $end) => $subQuery->whereDate('booking_date', '<=', $end));
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
            if ($booking->payment_status !== PaymentStatus::Refunded && filled($booking->email)) {
                $booking->loadMissing('schedule.service.coach');
                Mail::to($booking->email)->send(new BookingDeleted($booking));
            }

            $booking->delete();

            unset($this->bookings);

            Flux::toast(text: __('Rezervācija veiksmīgi dzēsta!'), variant: 'success');
        } catch (\Exception $e) {
            Log::error($e);

            Flux::toast(text: __('Neizdevās dzēst rezervāciju. Lūdzu, mēģini vēlreiz.'), heading: __('Kļūda!'), variant: 'danger');
        }
    }
};
?>

<div>
    @if (!$this->hasAnyBookings)
        <div class="flex flex-col items-center">
            <flux:text class="text-center py-8">{{ __('Šobrīd nav nevienas rezervācijas!') }}</flux:text>
            <flux:button href="{{ route('admin.bookings.create') }}" wire:navigate class="mb-4">
                {{ __('Pievienot jaunu rezervāciju') }}
            </flux:button>
        </div>
    @else
        <div class="mb-6 flex flex-col gap-4">
            <flux:input prefix-icon="magnifying-glass" type="search" wire:model.live.debounce.300ms="search"
                placeholder="{{ __('Meklēt rezervācijas') }}" />

            <div class="flex flex-wrap items-end gap-4">
                <flux:select wire:model.live="period" :label="__('Periods')">
                    <flux:select.option value="past">{{ __('Pagātnes rezervācijas') }}</flux:select.option>
                    <flux:select.option value="today">{{ __('Šodienas rezervācijas') }}</flux:select.option>
                    <flux:select.option value="upcoming">{{ __('Šodienas un nākotnes rezervācijas') }}</flux:select.option>
                    <flux:select.option value="future">{{ __('Nākotnes rezervācijas') }}</flux:select.option>
                    <flux:select.option value="custom">{{ __('Izvēlēties datumus') }}</flux:select.option>
                    <flux:select.option value="all">{{ __('Visas rezervācijas') }}</flux:select.option>
                </flux:select>

                @if ($period === 'custom')
                    <flux:date-picker
                        mode="range"
                        wire:model.live="dateRange"
                        clearable
                        :label="__('Datumu diapazons')"
                        :placeholder="__('Izvēlies datumu vai periodu')"
                    />
                @endif

                <flux:select wire:model.live="paymentStatus" :label="__('Maksājuma statuss')">
                    <flux:select.option value="">{{ __('Visi') }}</flux:select.option>
                    @foreach (PaymentStatus::cases() as $status)
                        <flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="attendanceStatus" :label="__('Apmeklējuma statuss')">
                    <flux:select.option value="">{{ __('Visi') }}</flux:select.option>
                    @foreach (AttendanceStatus::cases() as $status)
                        <flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="coachId" :label="__('Treneris')">
                    <flux:select.option value="">{{ __('Visi') }}</flux:select.option>
                    @foreach ($this->coaches as $coach)
                        <flux:select.option :value="$coach->id">{{ $coach->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="serviceId" :label="__('Pakalpojums')">
                    <flux:select.option value="">{{ __('Visi') }}</flux:select.option>
                    @foreach ($this->services as $service)
                        <flux:select.option :value="$service->id">{{ $service->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="bookingType" :label="__('Rezervācijas veids')">
                    <flux:select.option value="">{{ __('Visi') }}</flux:select.option>
                    <flux:select.option value="membership">{{ __('Abonements') }}</flux:select.option>
                    <flux:select.option value="regular">{{ __('Parastā') }}</flux:select.option>
                </flux:select>
            </div>
        </div>

        @if ($this->awaitingDateRange)
            <flux:text class="text-center py-8">{{ __('Izvēlies datumu vai periodu, lai redzētu rezervācijas.') }}</flux:text>
        @elseif ($this->bookings->isEmpty())
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
                    @foreach ($this->bookings as $booking)
                        <flux:table.row wire:key="booking-{{ $booking->id }}">
                            <flux:table.cell>{{ $booking->name }} {{ $booking->surname }}</flux:table.cell>
                            <flux:table.cell>
                                <div class="flex items-center gap-2">
                                    {{ $booking->schedule->service->name }}
                                    @if ($booking->isMembershipBooking())
                                        <flux:badge size="sm" color="purple">{{ __('Abonements') }}</flux:badge>
                                    @endif
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>{{ $booking->schedule->service->coach->name }}</flux:table.cell>
                            <flux:table.cell>{{ $booking->booking_date->format('d.m.Y') }}
                                / {{ substr($booking->schedule->start_time, 0, 5) }}</flux:table.cell>
                            <flux:table.cell>{{ $booking->participant_count }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm"
                                    :color="match($booking->payment_status->value) {
                                                                                                                                                                                                                                                        'paid' => 'green',
                                                                                                                                                                                                                                                        'pending' => 'yellow',
                                                                                                                                                                                                                                                        'failed' => 'red',
                                                                                                                                                                                                                                                        'refunded' => 'zinc',
                                                                                                                                                                                                                                                    }">
                                    {{ $booking->payment_status->label() }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm"
                                    :color="match($booking->attendance_status->value) {
                                                                                                                                                                                                                                                        'attended' => 'green',
                                                                                                                                                                                                                                                        'missed' => 'red',
                                                                                                                                                                                                                                                        'pending' => 'zinc',
                                                                                                                                                                                                                                                    }">
                                    {{ $booking->attendance_status->label() }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex gap-2">
                                    <flux:button href="{{ route('admin.bookings.edit', $booking) }}" variant="primary"
                                        size="sm" icon="pencil">
                                    </flux:button>
                                    <flux:button wire:confirm="{{ __('Vai tiešām vēlies dzēst rezervāciju?') }}"
                                        variant="danger" size="sm" icon="trash"
                                        wire:click="destroy({{ $booking->id }})">
                                    </flux:button>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            <flux:pagination :paginator="$this->bookings" />
        @endif
    @endif
</div>
