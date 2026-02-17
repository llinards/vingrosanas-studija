<?php

use App\Livewire\Concerns\HasScheduleForm;
use App\Models\Schedule;
use Flux\Flux;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

new class extends Component
{
    use HasScheduleForm;

    /**
     * Validate and create a new schedule.
     *
     * Creates either a recurring (day_of_week) or specific date schedule.
     */
    public function save(): void
    {
        $this->validate();

        try {
            Schedule::create([
                'service_id' => $this->service_id,
                'day_of_week' => $this->schedule_type === 'recurring' ? $this->day_of_week : null,
                'date' => $this->schedule_type === 'specific' ? $this->date : null,
                'start_time' => $this->start_time,
                'max_capacity' => $this->max_capacity,
                'is_active' => $this->is_active,
            ]);

            Flux::toast(
                text: __('Grafiks izveidots!'),
                variant: 'success',
            );

            $this->redirect(route('admin.schedules.index'), navigate: true);
        } catch (\Exception $e) {
            Log::error($e);

            Flux::toast(
                text: __('Neizdevās izveidot grafiku. Lūdzu, mēģini vēlreiz.'),
                heading: __('Kļūda!'),
                variant: 'danger',
            );
        }
    }

    /**
     * Render the component view with the page title.
     */
    public function render(): \Illuminate\View\View
    {
        return $this->view()
            ->title(__('Pievienot jaunu grafiku'));
    }
};
?>


<x-schedule.schedule-form :heading="__('Pievienot jaunu grafiku')"/>
