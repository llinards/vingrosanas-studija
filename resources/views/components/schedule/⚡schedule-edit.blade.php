<?php

use App\Livewire\Concerns\HasScheduleForm;
use App\Models\Schedule;
use Flux\Flux;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    use HasScheduleForm;

    #[Locked]
    public int $scheduleId;

    public function mount(Schedule $schedule): void
    {
        $this->scheduleId    = $schedule->id;
        $this->service_id    = $schedule->service_id;
        $this->schedule_type = $schedule->day_of_week !== null ? 'recurring' : 'specific';
        $this->day_of_week   = $schedule->day_of_week?->value;
        $this->date          = $schedule->date?->format('Y-m-d');
        $this->start_time    = substr((string) $schedule->start_time, 0, 5);
        $this->max_capacity  = (string) $schedule->max_capacity;
        $this->is_active     = $schedule->is_active;
    }

    public function save(): void
    {
        $this->validate();

        try {
            $schedule = Schedule::findOrFail($this->scheduleId);

            $schedule->update([
                'service_id'   => $this->service_id,
                'day_of_week'  => $this->schedule_type === 'recurring' ? $this->day_of_week : null,
                'date'         => $this->schedule_type === 'specific' ? $this->date : null,
                'start_time'   => $this->start_time,
                'max_capacity' => $this->max_capacity,
                'is_active'    => $this->is_active,
            ]);

            Flux::toast(
                text: __('Grafiks atjaunināts!'),
                variant: 'success',
            );

            $this->redirect(route('schedule-list'), navigate: true);
        } catch (\Exception $e) {
            Log::error($e);

            Flux::toast(
                text: __('Neizdevās atjaunināt grafiku. Lūdzu, mēģini vēlreiz.'),
                heading: __('Kļūda!'),
                variant: 'danger',
            );
        }
    }

    public function render(): \Illuminate\View\View
    {
        return $this->view()
                    ->title(__('Rediģēt grafiku'));
    }
};
?>


<x-schedule.schedule-form :heading="__('Rediģēt grafiku')"/>
