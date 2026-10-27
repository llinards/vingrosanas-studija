<?php

use App\Actions\RescheduleClass;
use App\Livewire\Concerns\HasScheduleForm;
use App\Models\Schedule;
use Flux\Flux;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    use HasScheduleForm;

    #[Locked]
    public int $scheduleId;

    /**
     * Initialize the component with existing schedule data.
     *
     * Determines schedule type (recurring vs specific) based on day_of_week presence.
     */
    public function mount(Schedule $schedule): void
    {
        $schedule->load('service');

        $this->scheduleId = $schedule->id;
        $this->coach_id = $schedule->service->coach_id;
        $this->service_id = $schedule->service_id;
        $this->schedule_type = $schedule->day_of_week !== null ? 'recurring' : 'specific';
        $this->day_of_week = $schedule->day_of_week?->value;
        $this->date = $schedule->date?->format('Y-m-d');
        $this->start_time = substr((string) $schedule->start_time, 0, 5);
        $this->max_capacity = (string) $schedule->max_capacity;
        $this->is_active = $schedule->is_active;
    }

    /**
     * Forget a pending confirmation as soon as the form is changed again.
     */
    public function updated(string $property): void
    {
        if ($property !== 'bookingsToMove') {
            $this->bookingsToMove = 0;
        }
    }

    /**
     * Validate and save the updated schedule data.
     *
     * A change of day or time that would move upcoming bookings is not saved
     * until it has been confirmed with saveAndMoveBookings().
     */
    public function save(): void
    {
        $this->persist(moveConfirmed: false);
    }

    /**
     * Save a change of day or time and move the upcoming bookings along with the class.
     */
    public function saveAndMoveBookings(): void
    {
        $this->persist(moveConfirmed: true);
    }

    private function persist(bool $moveConfirmed): void
    {
        $this->validate();

        $schedule = Schedule::findOrFail($this->scheduleId);
        $isRecurring = $this->schedule_type === 'recurring';
        $upcomingBookings = $schedule->bookings()->upcoming()->count();

        if ($upcomingBookings > 0) {
            if ($schedule->service_id !== (int) $this->service_id) {
                $this->addError('service_id', __('Pakalpojumu nevar mainīt, kamēr šai nodarbībai ir nākotnes rezervācijas (:count).', ['count' => $upcomingBookings]));

                return;
            }

            if (($schedule->day_of_week !== null) !== $isRecurring) {
                $this->addError('schedule_type', __('Grafika veidu nevar mainīt, kamēr šai nodarbībai ir nākotnes rezervācijas (:count).', ['count' => $upcomingBookings]));

                return;
            }

            $timeChanged = substr((string) $schedule->start_time, 0, 5) !== $this->start_time;
            $dayChanged = $isRecurring
                ? $schedule->day_of_week->value !== (int) $this->day_of_week
                : $schedule->date->format('Y-m-d') !== $this->date;

            if (($timeChanged || $dayChanged) && ! $moveConfirmed) {
                $this->bookingsToMove = $upcomingBookings;

                return;
            }
        }

        try {
            app(RescheduleClass::class)->execute($schedule, [
                'service_id' => (int) $this->service_id,
                'day_of_week' => $isRecurring ? (int) $this->day_of_week : null,
                'date' => $isRecurring ? null : $this->date,
                'start_time' => $this->start_time,
                'max_capacity' => $this->max_capacity,
                'is_active' => $this->is_active,
            ]);

            Flux::toast(
                text: __('Grafiks atjaunināts!'),
                variant: 'success',
            );

            $this->redirect(route('admin.schedules.index'), navigate: true);
        } catch (\Exception $e) {
            Log::error($e);

            Flux::toast(
                text: __('Neizdevās atjaunināt grafiku. Lūdzu, mēģini vēlreiz.'),
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
            ->title(__('Rediģēt grafiku'));
    }
};
?>


<x-schedule.schedule-form :heading="__('Rediģēt grafiku')"/>
