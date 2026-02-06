<?php

namespace App\Livewire\Concerns;

use App\Enums\PaymentStatus;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\ServiceType;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;

trait HasBookingForm
{
    public ?int $service_type_id = null;

    public ?int $service_id = null;

    public ?int $schedule_id = null;

    public ?string $booking_date = null;

    public string $name = '';

    public string $surname = '';

    public string $phone = '';

    public string $email = '';

    public string $payment_status = 'pending';

    #[Computed]
    public function serviceTypes(): Collection
    {
        return ServiceType::all();
    }

    #[Computed]
    public function services(): Collection
    {
        return Service::with('coach')
            ->where('is_active', true)
            ->when($this->service_type_id, fn ($query) => $query->where('service_type_id', $this->service_type_id))
            ->whereHas('schedules', fn ($query) => $query->where('is_active', true))
            ->get();
    }

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

    protected function rules(): array
    {
        return [
            'service_id' => ['required', 'exists:services,id'],
            'schedule_id' => ['required', 'exists:schedules,id'],
            'booking_date' => ['required', 'date'],
            'name' => ['required', 'string', 'max:255'],
            'surname' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
            'payment_status' => ['required', 'string'],
        ];
    }

    protected function messages(): array
    {
        return [
            'service_id.required' => __('Pakalpojums ir obligāts.'),
            'service_id.exists' => __('Izvēlētais pakalpojums neeksistē.'),
            'schedule_id.required' => __('Grafiks ir obligāts.'),
            'schedule_id.exists' => __('Izvēlētais grafiks neeksistē.'),
            'booking_date.required' => __('Datums ir obligāts.'),
            'booking_date.date' => __('Datumam jābūt derīgam datumam.'),
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
        ];
    }
}
