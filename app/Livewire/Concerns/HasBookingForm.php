<?php

namespace App\Livewire\Concerns;

use App\Enums\AttendanceStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Coach;
use App\Models\Schedule;
use App\Services\ScheduleAvailabilityService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;

trait HasBookingForm
{
    public ?int $coach_id = null;

    public ?int $schedule_id = null;

    public ?string $booking_date = null;

    public int $participant_count = 1;

    public string $name = '';

    public string $surname = '';

    public string $phone = '';

    public string $email = '';

    public string $payment_status = 'pending';

    public string $attendance_status = 'pending';

    /**
     * Get the currently selected schedule.
     */
    #[Computed]
    public function selectedSchedule(): ?Schedule
    {
        if (! $this->schedule_id) {
            return null;
        }

        return Schedule::with('service')->find($this->schedule_id);
    }

    /**
     * Check if the selected class is an individual session (one booking per slot).
     */
    #[Computed]
    public function isExclusiveService(): bool
    {
        return $this->selectedSchedule?->service->is_exclusive ?? false;
    }

    /**
     * Get the coaches who have a class that can be booked, by name.
     *
     * The coach of the booking being edited is always included.
     */
    #[Computed]
    public function coaches(): Collection
    {
        $savedCoachId = $this->savedBooking()?->schedule->service->coach_id;

        return Coach::query()
            ->whereHas('services', fn ($query) => $query
                ->where('is_active', true)
                ->where('is_membership', false)
                ->whereHas('schedules', fn ($query) => $query->where('is_active', true)))
            ->when($savedCoachId, fn ($query, int $coachId) => $query->orWhere('id', $coachId))
            ->orderBy('name')
            ->get();
    }

    /**
     * List the classes of the chosen coach with their usual day and time.
     *
     * One-off classes whose date has passed are left out. The class of the booking
     * being edited stays in the list even after it was switched off or its date passed.
     *
     * @return list<array{id: int, label: string}>
     */
    #[Computed]
    public function classesOfCoach(): array
    {
        if (! $this->coach_id) {
            return [];
        }

        $savedScheduleId = $this->savedBooking()?->schedule_id;

        return Schedule::query()
            ->with('service')
            ->whereHas('service', fn ($query) => $query->where('coach_id', $this->coach_id))
            ->where(fn ($query) => $query
                ->where(fn ($query) => $query
                    ->where('is_active', true)
                    ->whereHas('service', fn ($query) => $query->where('is_active', true)->where('is_membership', false))
                    ->where(fn ($query) => $query
                        ->whereNotNull('day_of_week')
                        ->orWhereDate('date', '>=', today()->toDateString())))
                ->when($savedScheduleId, fn ($query, int $scheduleId) => $query->orWhere('id', $scheduleId)))
            ->get()
            ->sortBy(fn (Schedule $schedule) => [
                $schedule->service->name,
                $schedule->day_of_week?->value ?? 8,
                $schedule->date?->toDateString() ?? '',
                substr((string) $schedule->start_time, 0, 5),
            ])
            ->map(fn (Schedule $schedule): array => [
                'id' => $schedule->id,
                'label' => $schedule->service->name.' — '.$this->usualDayLabel($schedule).' '.substr((string) $schedule->start_time, 0, 5),
            ])
            ->values()
            ->all();
    }

    /**
     * Describe when a class usually takes place: "trešdienās" for a weekly class, its date for a one-off.
     */
    private function usualDayLabel(Schedule $schedule): string
    {
        if ($schedule->day_of_week === null) {
            return $schedule->date->format('d.m.Y');
        }

        return Str::lower(Str::replaceLast('a', 'ās', $schedule->day_of_week->label()));
    }

    /**
     * Get the booking being edited as it is stored, or null when a new booking is being created.
     */
    protected function savedBooking(): ?Booking
    {
        return null;
    }

    /**
     * Drop the chosen class when it does not belong to the newly chosen coach.
     */
    public function updatedCoachId(): void
    {
        unset($this->classesOfCoach);

        if ($this->schedule_id && ! in_array($this->schedule_id, array_column($this->classesOfCoach, 'id'), true)) {
            $this->schedule_id = null;
            $this->participant_count = 1;
            unset($this->selectedSchedule, $this->isExclusiveService, $this->signedUpParticipants, $this->slotWarnings, $this->isSlotAlreadyBooked);
        }
    }

    /**
     * Reset the participant count when another class is chosen.
     */
    public function updatedScheduleId(): void
    {
        $this->participant_count = 1;
        unset($this->selectedSchedule, $this->isExclusiveService, $this->signedUpParticipants, $this->slotWarnings, $this->isSlotAlreadyBooked);
    }

    /**
     * Recalculate the sign-ups and warnings for the newly chosen date.
     */
    public function updatedBookingDate(): void
    {
        unset($this->signedUpParticipants, $this->slotWarnings, $this->isSlotAlreadyBooked);
    }

    /**
     * Check if the selected slot is already booked (for exclusive services).
     */
    #[Computed]
    public function isSlotAlreadyBooked(): bool
    {
        if (! $this->schedule_id || ! $this->booking_date) {
            return false;
        }

        return Booking::active()
            ->where('schedule_id', $this->schedule_id)
            ->whereDate('booking_date', $this->booking_date)
            ->exists();
    }

    /**
     * Get the id of the booking being edited, so that it is not counted against itself.
     */
    protected function editedBookingId(): ?int
    {
        return null;
    }

    /**
     * List what would stop a customer from making this booking.
     *
     * The studio may still save it; the warnings only prevent doing so by accident.
     *
     * @return list<string>
     */
    #[Computed]
    public function slotWarnings(): array
    {
        $schedule = $this->selectedSchedule;

        if (! $schedule || ! $this->booking_date) {
            return [];
        }

        $date = Carbon::parse($this->booking_date);
        $availability = app(ScheduleAvailabilityService::class);
        $warnings = [];

        $otherBookings = Booking::active()
            ->where('schedule_id', $schedule->id)
            ->whereDate('booking_date', $this->booking_date)
            ->when($this->editedBookingId(), fn ($query, int $bookingId) => $query->where('id', '!=', $bookingId));

        $isFull = $this->isExclusiveService
            ? $otherBookings->exists() || (int) $this->participant_count > $schedule->max_capacity
            : (int) $otherBookings->sum('participant_count') + (int) $this->participant_count > $schedule->max_capacity;

        if ($isFull) {
            $warnings[] = __('Nodarbība šajā datumā ir pilna.');
        }

        if (! $availability->runsOn($schedule, $date)) {
            $warnings[] = __('Šī nodarbība šajā nedēļas dienā nenotiek.');
        }

        if ($availability->isClosedOn($schedule, $date)) {
            $warnings[] = __('Šajā datumā studija ir slēgta vai treneris nav pieejams.');
        }

        return $warnings;
    }

    /**
     * Count the people currently signed up for the selected class and date.
     *
     * When a booking is being edited, its own participants are part of the count.
     */
    #[Computed]
    public function signedUpParticipants(): int
    {
        if (! $this->schedule_id || ! $this->booking_date) {
            return 0;
        }

        return (int) Booking::active()
            ->where('schedule_id', $this->schedule_id)
            ->whereDate('booking_date', $this->booking_date)
            ->sum('participant_count');
    }

    /**
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
     * @return array<int, array{value: string, label: string}>
     */
    #[Computed]
    public function attendanceStatusOptions(): array
    {
        return collect(AttendanceStatus::cases())
            ->map(fn (AttendanceStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ])
            ->all();
    }

    /**
     * Get the validation rules for the booking form.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'coach_id' => ['required', 'exists:coaches,id'],
            'schedule_id' => ['required', 'exists:schedules,id', Rule::in(array_column($this->classesOfCoach, 'id'))],
            'booking_date' => $this->bookingDateRules(),
            'participant_count' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:255'],
            'surname' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
            'payment_status' => ['required', 'string'],
            'attendance_status' => ['required', 'string'],
        ];
    }

    /**
     * Get the validation rules for the booking date: a booking is placed on today or a later day.
     *
     * @return array<int, string>
     */
    protected function bookingDateRules(): array
    {
        return ['required', 'date', 'after_or_equal:today'];
    }

    /**
     * Get the custom validation messages for the booking form.
     *
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'coach_id.required' => __('Treneris ir obligāts.'),
            'coach_id.exists' => __('Izvēlētais treneris neeksistē.'),
            'schedule_id.required' => __('Nodarbība ir obligāta.'),
            'schedule_id.exists' => __('Izvēlētā nodarbība neeksistē.'),
            'schedule_id.in' => __('Izvēlētā nodarbība nepieder šim trenerim.'),
            'booking_date.required' => __('Datums ir obligāts.'),
            'booking_date.date' => __('Datumam jābūt derīgam datumam.'),
            'booking_date.after_or_equal' => __('Datumam jābūt šodienai vai nākotnē.'),
            'participant_count.required' => __('Dalībnieku skaits ir obligāts.'),
            'participant_count.integer' => __('Dalībnieku skaitam jābūt skaitlim.'),
            'participant_count.min' => __('Dalībnieku skaitam jābūt vismaz 1.'),
            'name.required' => __('Vārds ir obligāts.'),
            'name.max' => __('Vārds nedrīkst pārsniegt 255 rakstzīmes.'),
            'surname.required' => __('Uzvārds ir obligāts.'),
            'surname.max' => __('Uzvārds nedrīkst pārsniegt 255 rakstzīmes.'),
            'phone.required' => __('Tālrunis ir obligāts.'),
            'phone.max' => __('Tālrunis nedrīkst pārsniegt 50 rakstzīmes.'),
            'email.required' => __('E-pasts ir obligāts.'),
            'email.email' => __('E-pastam jābūt derīgai e-pasta adresei.'),
            'email.max' => __('E-pasts nedrīkst pārsniegt 255 rakstzīmes.'),
            'payment_status.required' => __('Maksājuma statuss ir obligāts.'),
            'attendance_status.required' => __('Apmeklējuma statuss ir obligāts.'),
        ];
    }
}
