<?php

namespace App\Livewire\Concerns;

use App\Enums\AttendanceStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\ServicePriceTier;
use App\Models\ServiceType;
use App\Services\ScheduleAvailabilityService;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;

trait HasBookingForm
{
    public ?int $service_type_id = null;

    public ?int $service_id = null;

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
     * Get all available service types for the booking form dropdown.
     */
    #[Computed]
    public function serviceTypes(): Collection
    {
        return ServiceType::all();
    }

    /**
     * Get active services filtered by the selected service type.
     *
     * Only returns services that have active schedules available.
     */
    #[Computed]
    public function services(): Collection
    {
        return Service::with(['coach', 'priceTiers'])
            ->where('is_active', true)
            ->when($this->service_type_id, fn ($query) => $query->where('service_type_id', $this->service_type_id))
            ->whereHas('schedules', fn ($query) => $query->where('is_active', true))
            ->get();
    }

    /**
     * Get the currently selected service with its price tiers.
     */
    #[Computed]
    public function selectedService(): ?Service
    {
        if (! $this->service_id) {
            return null;
        }

        return $this->services->firstWhere('id', $this->service_id);
    }

    /**
     * Get active schedules for the selected service.
     */
    #[Computed]
    public function schedules(): Collection
    {
        if (! $this->service_id) {
            return new Collection;
        }

        return Schedule::where('service_id', $this->service_id)
            ->where('is_active', true)
            ->get();
    }

    /**
     * Get the currently selected schedule.
     */
    #[Computed]
    public function selectedSchedule(): ?Schedule
    {
        if (! $this->schedule_id) {
            return null;
        }

        return Schedule::find($this->schedule_id);
    }

    /**
     * Check if the selected service is exclusive (one booking per slot).
     */
    #[Computed]
    public function isExclusiveService(): bool
    {
        return $this->selectedService?->is_exclusive ?? false;
    }

    /**
     * Get available price tiers filtered by the selected schedule's max_capacity.
     *
     * @return \Illuminate\Support\Collection<int, ServicePriceTier>
     */
    #[Computed]
    public function availablePriceTiers(): \Illuminate\Support\Collection
    {
        $service = $this->selectedService;
        $schedule = $this->selectedSchedule;

        if (! $service) {
            return collect();
        }

        $query = $service->priceTiers()->orderBy('participant_count');

        // Filter by schedule's max_capacity if schedule is selected
        if ($schedule) {
            $query->where('participant_count', '<=', $schedule->max_capacity);
        }

        return $query->get();
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
     * Get the remaining capacity for the selected slot.
     */
    #[Computed]
    public function remainingCapacity(): int
    {
        if (! $this->schedule_id || ! $this->booking_date) {
            return 0;
        }

        $schedule = $this->selectedSchedule;
        if (! $schedule) {
            return 0;
        }

        return app(ScheduleAvailabilityService::class)
            ->remainingCapacity($schedule, $this->booking_date, $this->isExclusiveService);
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
            'service_id' => ['required', 'exists:services,id'],
            'schedule_id' => ['required', 'exists:schedules,id'],
            'booking_date' => ['required', 'date', 'after_or_equal:today'],
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
     * Get the custom validation messages for the booking form.
     *
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'service_id.required' => __('Pakalpojums ir obligāts.'),
            'service_id.exists' => __('Izvēlētais pakalpojums neeksistē.'),
            'schedule_id.required' => __('Grafiks ir obligāts.'),
            'schedule_id.exists' => __('Izvēlētais grafiks neeksistē.'),
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
