<?php

namespace App\Livewire\Concerns;

use App\Enums\DayOfWeek;
use App\Models\Service;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;

trait HasScheduleForm
{
    public ?int $service_id = null;

    public string $schedule_type = 'recurring';

    public ?int $day_of_week = null;

    public ?string $date = null;

    public string $start_time = '';

    public string $max_capacity = '';

    public bool $is_active = false;

    /**
     * Get all services with their coaches for the dropdown.
     */
    #[Computed]
    public function services(): Collection
    {
        return Service::with('coach')->get();
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    #[Computed]
    public function dayOptions(): array
    {
        return collect(DayOfWeek::cases())
            ->map(fn (DayOfWeek $day) => [
                'value' => $day->value,
                'label' => $day->label(),
            ])
            ->all();
    }

    /**
     * Get the validation rules for the schedule form.
     *
     * Rules vary based on schedule type: recurring schedules require day_of_week,
     * while one-time schedules require a specific date.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        $rules = [
            'service_id' => ['required', 'exists:services,id'],
            'start_time' => ['required', 'date_format:H:i'],
            'max_capacity' => ['required', 'integer', 'min:1'],
        ];

        if ($this->schedule_type === 'recurring') {
            $rules['day_of_week'] = ['required', 'integer', 'between:1,7'];
        } else {
            $rules['date'] = ['required', 'date'];
        }

        return $rules;
    }

    /**
     * Get the custom validation messages for the schedule form.
     *
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'service_id.required' => __('Pakalpojums ir obligāts.'),
            'service_id.exists' => __('Izvēlētais pakalpojums neeksistē.'),
            'day_of_week.required' => __('Nedēļas diena ir obligāta.'),
            'day_of_week.between' => __('Nedēļas dienai jābūt no 1 līdz 7.'),
            'date.required' => __('Datums ir obligāts.'),
            'date.date' => __('Datumam jābūt derīgam datumam.'),
            'start_time.required' => __('Sākuma laiks ir obligāts.'),
            'start_time.date_format' => __('Sākuma laikam jābūt formātā HH:MM.'),
            'max_capacity.required' => __('Maksimālais dalībnieku skaits ir obligāts.'),
            'max_capacity.integer' => __('Dalībnieku skaitam jābūt veselam skaitlim.'),
            'max_capacity.min' => __('Dalībnieku skaitam jābūt vismaz 1.'),
        ];
    }
}
