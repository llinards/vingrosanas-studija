<?php

use App\Enums\AttendanceStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Membership;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;

new class extends Component {
    public string $email = '';

    public bool $checkedIn = false;

    public ?string $checkedInServiceName = null;

    public ?string $checkedInCoachName = null;

    public ?string $checkedInDate = null;

    public ?string $checkedInTime = null;

    public bool $hasMembership = false;

    public int $membershipSessionsUsed = 0;

    public int $membershipSessionsTotal = 0;

    /** @var Collection<int, Booking>|null */
    public ?Collection $matchingBookings = null;

    public function checkIn(): void
    {
        $this->validate([
            'email' => ['required', 'email'],
        ], [
            'email.required' => __('E-pasts ir obligāts.'),
            'email.email'    => __('E-pastam jābūt derīgai e-pasta adresei.'),
        ]);

        $bookings = Booking::query()
                           ->with('schedule.service.coach')
                           ->where('email', $this->email)
                           ->where('booking_date', today())
                           ->where('payment_status', PaymentStatus::Paid)
                           ->whereIn('attendance_status', [AttendanceStatus::Pending, AttendanceStatus::Attended])
                           ->get();

        $pending = $bookings->where('attendance_status', AttendanceStatus::Pending);

        if ($pending->isEmpty()) {
            if ($bookings->where('attendance_status', AttendanceStatus::Attended)->isNotEmpty()) {
                $this->addError('email', __('Jūs jau esat reģistrējies šodienas treniņam.'));
            } else {
                $this->addError('email', __('Nav atrasta neviena rezervācija ar šo e-pastu šodienai.'));
            }

            return;
        }

        if ($pending->count() === 1) {
            $this->markAttended($pending->first());

            return;
        }

        $this->matchingBookings = $pending->values();
    }

    public function selectBooking(int $bookingId): void
    {
        $booking = $this->matchingBookings?->firstWhere('id', $bookingId);

        if ( ! $booking) {
            return;
        }

        $this->markAttended($booking);
    }

    public function resetForm(): void
    {
        $this->reset();
        $this->resetValidation();
    }

    private function markAttended(Booking $booking): void
    {
        $booking->update(['attendance_status' => AttendanceStatus::Attended]);

        $this->checkedIn            = true;
        $this->checkedInServiceName = $booking->schedule->service->name;
        $this->checkedInCoachName   = $booking->schedule->service->coach->name;
        $this->checkedInDate        = $booking->booking_date->format('d.m.Y');
        $this->checkedInTime        = substr($booking->schedule->start_time, 0, 5);
        $this->matchingBookings     = null;

        // Check for membership info
        if ($booking->membership_id) {
            $membership = Membership::find($booking->membership_id);

            if ($membership && $membership->isActive()) {
                $this->hasMembership           = true;
                $this->membershipSessionsUsed  = $membership->bookings()
                                                            ->where('attendance_status', AttendanceStatus::Attended)
                                                            ->count();
                $this->membershipSessionsTotal = $membership->sessions_total;
            }
        }
    }
};
?>

<div>
    @if($checkedIn)
        <div class="py-8 px-6 md:px-8 border-8 md:border-12 border-blue rounded-4xl space-y-4">
            <flux:icon.check class="size-12 text-blue mx-auto mb-4"/>
            <flux:heading level="3"
                          class="text-center">{{ __('Reģistrācija veiksmīga!') }}</flux:heading>

            <div class="space-y-4">
                <div class="flex justify-between items-center py-2 border-b border-gray-200">
                    <flux:text>{{ __('Treniņš') }}</flux:text>
                    <flux:text>{{ $checkedInServiceName }}</flux:text>
                </div>

                <div class="flex justify-between items-center py-2 border-b border-gray-200">
                    <flux:text>{{ __('Treneris') }}</flux:text>
                    <flux:text>{{ $checkedInCoachName }}</flux:text>
                </div>

                <div class="flex justify-between items-center py-2 border-b border-gray-200">
                    <flux:text>{{ __('Datums') }}</flux:text>
                    <flux:text>{{ $checkedInDate }}</flux:text>
                </div>

                <div class="flex justify-between items-center py-2 border-b border-gray-200">
                    <flux:text>{{ __('Laiks') }}</flux:text>
                    <flux:text>{{ $checkedInTime }}</flux:text>
                </div>
            </div>

            @if($hasMembership)
                <flux:separator/>
                <div class="flex justify-between items-center py-2">
                    <flux:text class="font-medium">{{ __('Abonements') }}</flux:text>
                    <flux:badge size="sm">{{ $membershipSessionsUsed }}
                        /{{ $membershipSessionsTotal }} {{ __('apmeklētas') }}</flux:badge>
                </div>
            @endif
        </div>

        <div class="mt-8 text-center">
            <flux:button wire:click="resetForm" class="button large primary">
                {{ __('Jauna reģistrācija') }}
            </flux:button>
        </div>
    @elseif($matchingBookings && $matchingBookings->count() > 1)
        <div class="py-8 px-6 md:px-8 border-8 md:border-12 border-blue rounded-4xl space-y-4">
            <flux:heading level="3"
                          class="text-center">{{ __('Izvēlieties savu treniņu') }}</flux:heading>
            <flux:text class="text-center pb-2">
                {{ __('Jums šodien ir vairākas rezervācijas. Lūdzu, izvēlieties vienu.') }}
            </flux:text>

            <div class="space-y-3">
                @foreach($matchingBookings as $matchingBooking)
                    <button wire:click="selectBooking({{ $matchingBooking->id }})"
                            class="w-full text-left p-4 border border-gray-200 rounded-xl hover:bg-gray-50 transition cursor-pointer">
                        <div class="flex justify-between items-center">
                            <div>
                                <flux:text
                                    class="font-semibold">{{ $matchingBooking->schedule->service->name }}</flux:text>
                                <flux:text
                                    class="text-sm text-gray-500">{{ $matchingBooking->schedule->service->coach->name }}</flux:text>
                            </div>
                            <flux:text
                                class="font-semibold">{{ substr($matchingBooking->schedule->start_time, 0, 5) }}</flux:text>
                        </div>
                    </button>
                @endforeach
            </div>
        </div>

        <div class="mt-8 text-center">
            <flux:button wire:click="resetForm" class="button large primary">
                {{ __('Atpakaļ') }}
            </flux:button>
        </div>
    @else
        <div class="py-8 px-6 md:px-8 border-8 md:border-12 border-blue rounded-4xl space-y-4">
            <flux:heading level="3"
                          class="text-center">{{ __('Reģistrācija treniņam') }}</flux:heading>
            <flux:text class="text-center pb-2">
                {{ __('Ievadiet savu e-pastu, lai reģistrētos treniņam.') }}
            </flux:text>

            <form wire:submit="checkIn" class="space-y-6">
                <flux:input wire:model="email" type="email" :label="__('E-pasts')"/>
                <div class="text-center">
                    <flux:button type="submit" class="button large primary">
                        {{ __('Reģistrēties') }}
                    </flux:button>
                </div>
            </form>
        </div>
    @endif
</div>
