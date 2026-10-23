<?php

use App\Enums\AttendanceStatus;
use App\Enums\DayOfWeek;
use App\Enums\PaymentStatus;
use App\Enums\SlotStatus;
use App\Exceptions\SlotUnavailableException;
use App\Livewire\Concerns\ShowsWeekTimetable;
use App\Models\Booking;
use App\Models\Membership;
use App\Models\Schedule;
use App\Models\Service;
use App\Services\ScheduleAvailabilityService;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    use ShowsWeekTimetable;

    #[Locked]
    public int $membershipId;

    #[Locked]
    public string $sessionId;

    /**
     * The session being moved (null while the list of sessions is shown).
     */
    #[Locked]
    public ?int $rebookingBookingId = null;

    /**
     * The class and day chosen as the new time, waiting for the customer to confirm.
     */
    #[Locked]
    public ?int $targetScheduleId = null;

    #[Locked]
    public ?string $targetDate = null;

    /**
     * Weeks ahead of the current week shown while moving a session (0 = this week).
     */
    public int $week = 0;

    /**
     * Whether a session has just been moved.
     */
    public bool $hasRebooked = false;

    /**
     * Initialize the component and verify access.
     */
    public function mount(Membership $membership): void
    {
        $sessionId = request()->query('session_id');

        if (! $sessionId || $sessionId !== $membership->stripe_checkout_session_id) {
            abort(404);
        }

        $this->membershipId = $membership->id;
        $this->sessionId = $sessionId;
    }

    #[Computed]
    public function membership(): Membership
    {
        return Membership::with('service')->findOrFail($this->membershipId);
    }

    /**
     * The sessions of this membership that still count.
     */
    #[Computed]
    public function bookings(): Collection
    {
        return $this->membership->bookings()
            ->with(['schedule.service.coach'])
            ->whereNotIn('payment_status', [PaymentStatus::Refunded, PaymentStatus::Failed])
            ->orderBy('booking_date')
            ->get();
    }

    #[Computed]
    public function rebookingBooking(): ?Booking
    {
        return $this->rebookingBookingId === null ? null : $this->bookings->firstWhere('id', $this->rebookingBookingId);
    }

    /**
     * Start moving a session: show the week of that session.
     */
    public function startRebook(int $bookingId): void
    {
        $booking = $this->findReschedulableBooking($bookingId);

        $this->rebookingBookingId = $booking->id;
        $this->week = $this->clampWeek($this->weekOffset($booking->booking_date));
        $this->opensOnLastDay = false;
        $this->hasRebooked = false;
        $this->reset('targetScheduleId', 'targetDate');
        $this->resetErrorBag();
        unset($this->rebookingBooking, $this->days);
    }

    /**
     * Go back to the list of sessions without moving anything.
     */
    public function cancelRebook(): void
    {
        $this->reset('rebookingBookingId', 'targetScheduleId', 'targetDate');
        $this->resetErrorBag();
        unset($this->rebookingBooking);
    }

    /**
     * Ask the customer to confirm the chosen new time. The time is checked when it is confirmed.
     */
    public function chooseTarget(int $scheduleId, string $date): void
    {
        if ($this->rebookingBookingId === null || ! Carbon::canBeCreatedFromFormat($date, 'Y-m-d')) {
            return;
        }

        $this->resetErrorBag();
        $this->targetScheduleId = $scheduleId;
        $this->targetDate = $date;

        Flux::modal('confirm-rebook')->show();
    }

    /**
     * Move the session to the chosen time.
     *
     * The new time is checked here against every booking rule, because the browser only limits what is shown.
     */
    public function confirmRebook(): void
    {
        $booking = $this->findReschedulableBooking($this->rebookingBookingId);

        if ($this->targetScheduleId === null || $this->targetDate === null) {
            return;
        }

        $date = Carbon::createFromFormat('Y-m-d', $this->targetDate)->startOfDay();

        if ($date->lt(today()) || $date->gt($this->membership->period_end)) {
            $this->refuseTarget(__('Datumam jābūt abonementa periodā.'));

            return;
        }

        if ($this->hasSessionAt($this->targetScheduleId, $this->targetDate, exceptBookingId: $booking->id)) {
            $this->refuseTarget(__('Šajā laikā jums jau ir rezervēta nodarbība.'));

            return;
        }

        try {
            DB::transaction(function () use ($booking, $date) {
                $schedule = Schedule::with('service.coach')->lockForUpdate()->find($this->targetScheduleId);

                if ($schedule === null
                    || ! $this->isCoveredByMembership($schedule->service)
                    || app(ScheduleAvailabilityService::class)->slotStatus($schedule, $date, excludeBookingId: $booking->id) !== SlotStatus::Available) {
                    throw new SlotUnavailableException;
                }

                $booking->update([
                    'schedule_id' => $schedule->id,
                    'booking_date' => $date->toDateString(),
                ]);
            });
        } catch (SlotUnavailableException) {
            $this->refuseTarget(__('Šis laiks vairs nav pieejams. Lūdzu, izvēlieties citu.'));

            return;
        } catch (\Exception $exception) {
            Log::error($exception);
            $this->refuseTarget(__('Neizdevās pārplānot nodarbību. Lūdzu, mēģiniet vēlreiz.'));

            return;
        }

        Flux::modal('confirm-rebook')->close();
        $this->cancelRebook();
        $this->hasRebooked = true;
        unset($this->bookings);
    }

    /**
     * Label of the session being moved, e.g. "Trešdiena, 07.10. 18:00 Fusion Pilates".
     */
    #[Computed]
    public function rebookingLabel(): string
    {
        $booking = $this->rebookingBooking;

        return $booking === null ? '' : $this->sessionLabel($booking->schedule, $booking->booking_date);
    }

    /**
     * Label of the chosen new time.
     */
    #[Computed]
    public function targetLabel(): string
    {
        $schedule = $this->targetScheduleId === null ? null : Schedule::with('service')->find($this->targetScheduleId);

        return $schedule === null || $this->targetDate === null ? '' : $this->sessionLabel($schedule, Carbon::parse($this->targetDate));
    }

    /**
     * What a class card shows while a session is being moved, and what its button does.
     *
     * @param  array<string, mixed>  $entry
     * @return array{key: string, tone: string, lines: list<?string>, button: ?array{label: string, action: string, variant: string}, status: ?string}
     */
    public function cardFor(array $entry): array
    {
        $booking = $this->rebookingBooking;
        $isCurrent = $booking !== null
            && (int) $booking->schedule_id === $entry['schedule_id']
            && $booking->booking_date->toDateString() === $entry['date'];

        $state = match (true) {
            $isCurrent => 'current',
            $this->hasSessionAt($entry['schedule_id'], $entry['date'], exceptBookingId: $booking?->id) => 'own',
            $entry['date'] < today()->toDateString() || $entry['date'] > $this->membership->period_end->toDateString() => 'outside-period',
            $entry['status'] !== SlotStatus::Available => 'unavailable',
            default => 'available',
        };

        return [
            'key' => 'move-'.$entry['schedule_id'].'-'.$entry['date'],
            'tone' => match ($state) {
                'current' => 'picked',
                'available' => 'normal',
                default => 'muted',
            },
            'lines' => [
                $entry['coach_name'],
                match (true) {
                    ! in_array($state, ['current', 'available'], true) => null,
                    $entry['is_exclusive'] => __('Individuāli'),
                    default => $this->placesLabel($entry['remaining']),
                },
            ],
            'button' => $state === 'available' ? [
                'label' => __('Pārcelt šeit'),
                'action' => 'chooseTarget('.$entry['schedule_id'].', \''.$entry['date'].'\')',
                'variant' => 'primary',
            ] : null,
            'status' => match ($state) {
                'current' => __('Pašreizējā'),
                'own' => __('Jau rezervēta'),
                'outside-period' => __('Ārpus perioda'),
                'unavailable' => $this->statusLabel($entry['status']),
                default => null,
            },
        ];
    }

    /**
     * Check if a booking can be rebooked (is in the future and not attended).
     */
    public function canRebook(Booking $booking): bool
    {
        return $booking->attendance_status !== AttendanceStatus::Attended
            && $booking->getServiceDateTime()->isAfter(now());
    }

    protected function firstWeek(): int
    {
        return 0;
    }

    /**
     * The week in which the membership period ends.
     */
    protected function lastWeek(): int
    {
        return max(0, $this->weekOffset($this->membership->period_end));
    }

    /**
     * Only classes of the membership's category that a membership can be used for.
     */
    protected function classesForWeek(Carbon $weekStart): array
    {
        return app(ScheduleAvailabilityService::class)->weekTimetable(
            $weekStart,
            membershipEligibleOnly: true,
            serviceTypeId: $this->membership->service?->service_type_id,
        );
    }

    protected function focusDate(): ?string
    {
        return $this->rebookingBooking?->booking_date->toDateString();
    }

    /**
     * Number of weeks from the current week to the week of the given date.
     */
    private function weekOffset(Carbon $date): int
    {
        return (int) round(today()->startOfWeek(Carbon::MONDAY)->diffInWeeks($date->copy()->startOfWeek(Carbon::MONDAY)));
    }

    /**
     * Whether this membership already has a session in that class on that day.
     */
    private function hasSessionAt(int $scheduleId, string $date, ?int $exceptBookingId = null): bool
    {
        return $this->bookings->contains(
            fn (Booking $booking) => $booking->id !== $exceptBookingId
                && (int) $booking->schedule_id === $scheduleId
                && $booking->booking_date->toDateString() === $date,
        );
    }

    /**
     * Close the confirmation, explain why the time cannot be used and show fresh availability.
     */
    private function refuseTarget(string $message): void
    {
        Flux::modal('confirm-rebook')->close();
        $this->reset('targetScheduleId', 'targetDate');
        $this->addError('rebook', $message);
        unset($this->days);
    }

    /**
     * Label of a class on a day, e.g. "Trešdiena, 07.10. 18:00 Fusion Pilates".
     */
    private function sessionLabel(Schedule $schedule, Carbon $date): string
    {
        return DayOfWeek::from($date->dayOfWeekIso)->label().', '.$date->format('d.m.').' '
            .substr((string) $schedule->start_time, 0, 5).' '.$schedule->service->name;
    }

    /**
     * Whether a class can take a session of this membership. A membership whose product
     * was deleted has no category, so any class open to memberships is allowed.
     */
    private function isCoveredByMembership(Service $service): bool
    {
        $membershipService = $this->membership->service;

        return $membershipService === null
            ? $service->is_membership_eligible
            : $service->isCoveredBy($membershipService);
    }

    /**
     * Find a session of this membership that the customer may still move, or respond with 404.
     */
    private function findReschedulableBooking(?int $bookingId): Booking
    {
        $booking = $this->membership->bookings()
            ->whereNotIn('payment_status', [PaymentStatus::Refunded, PaymentStatus::Failed])
            ->find($bookingId);

        if (! $booking || ! $this->canRebook($booking)) {
            abort(404);
        }

        return $booking;
    }

    /**
     * Render the component view with the page title.
     */
    public function render(): \Illuminate\View\View
    {
        return $this->view()
            ->title(__('Pārvaldīt abonementu'))
            ->layout('layouts.main');
    }
};
?>

<div class="container mx-auto pt-36 pb-12 px-4">
    @if($this->rebookingBooking)
        {{-- MOVE A SESSION --}}
        <div class="space-y-8">
            <div class="space-y-2">
                <flux:heading level="1">{{ __('Pārcelt nodarbību') }}</flux:heading>
                <flux:text>{{ __('Pašreiz') }}: {{ $this->rebookingLabel }}</flux:text>
                <flux:text size="sm">
                    {{ __('Izvēlieties jaunu laiku līdz :date.', ['date' => $this->membership->period_end->format('d.m.Y')]) }}
                </flux:text>
                <flux:link as="button" wire:click="cancelRebook" icon="arrow-uturn-left">{{ __('Atcelt') }}</flux:link>
            </div>

            <flux:error name="rebook"/>

            <x-timetable.week :days="$this->days" :week="$week" :week-start="$this->weekStart"
                              :initial-day="$this->initialDay" :is-first-week="$this->isFirstWeek"
                              :is-last-week="$this->isLastWeek" :card-for="$this->cardFor(...)"/>
        </div>
    @else
        <div class="md:max-w-xl mx-auto">
            <div class="py-8 px-6 md:px-8 border-8 md:border-12 border-blue rounded-4xl space-y-6">
                <flux:heading level="3" class="text-center">{{ __('Mans abonements') }}</flux:heading>

                <div class="space-y-2">
                    <div class="flex justify-between items-center py-2 border-b border-gray-200">
                        <flux:text>{{ __('Abonements') }}</flux:text>
                        <flux:text>{{ $this->membership->tierLabel() }}</flux:text>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-gray-200">
                        <flux:text>{{ __('Periods') }}</flux:text>
                        <flux:text>{{ $this->membership->period_start->format('d.m.Y') }}
                            — {{ $this->membership->period_end->format('d.m.Y') }}</flux:text>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-gray-200">
                        <flux:text>{{ __('Nodarbības') }}</flux:text>
                        <flux:text class="font-semibold">{{ $this->bookings->count() }}
                            /{{ $this->membership->sessions_total }}</flux:text>
                    </div>
                </div>

                <flux:separator/>

                <flux:heading level="4">{{ __('Rezervētās nodarbības') }}</flux:heading>

                @if($hasRebooked)
                    <flux:callout icon="check-circle" color="green">
                        <flux:callout.heading>{{ __('Nodarbība pārcelta.') }}</flux:callout.heading>
                    </flux:callout>
                @endif

                <div class="space-y-3">
                    @foreach($this->bookings as $booking)
                        <div class="rounded-lg border p-4" wire:key="manage-booking-{{ $booking->id }}">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <flux:text class="font-medium">{{ $booking->schedule->service->name }}</flux:text>
                                    <flux:text class="text-sm text-zinc-500">
                                        {{ $booking->booking_date->format('d.m.Y') }}
                                        {{ substr($booking->schedule->start_time, 0, 5) }}
                                        — {{ $booking->schedule->service->coach?->name }}
                                    </flux:text>
                                </div>
                                <div class="shrink-0">
                                    @if($booking->attendance_status->value === 'attended')
                                        <flux:badge size="sm"
                                                    color="zinc">{{ $booking->attendance_status->label() }}</flux:badge>
                                    @elseif($this->canRebook($booking))
                                        <flux:button wire:click="startRebook({{ $booking->id }})" class="btn btn-primary">
                                            {{ __('Pārplānot') }}
                                        </flux:button>
                                    @else
                                        <flux:badge size="sm" color="zinc">{{ __('Pagātnē') }}</flux:badge>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="mt-8 text-center">
                <flux:link href="{{ route('home') }}"
                           icon="arrow-uturn-left">
                    {{ __('Atgriezties uz sākumu') }}
                </flux:link>
            </div>
        </div>
    @endif

    {{-- CONFIRM THE NEW TIME --}}
    <flux:modal name="confirm-rebook" class="w-[calc(100vw-2rem)] max-w-md">
        <div class="space-y-6 p-6">
            <flux:heading size="lg">{{ __('Pārcelt nodarbību?') }}</flux:heading>
            <div class="space-y-1 text-sm">
                <div><span class="text-zinc-500">{{ __('No') }}:</span> {{ $this->rebookingLabel }}</div>
                <div><span class="text-zinc-500">{{ __('Uz') }}:</span> <span class="font-medium">{{ $this->targetLabel }}</span></div>
            </div>
            <div class="flex justify-between">
                <flux:modal.close>
                    <flux:button class="btn btn-sm btn-tertiary">{{ __('Atcelt') }}</flux:button>
                </flux:modal.close>
                <flux:button class="btn btn-sm btn-primary" wire:click="confirmRebook">{{ __('Apstiprināt') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
