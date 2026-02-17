<?php

use App\Models\Schedule;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    #[Computed]
    public function schedules(): Collection
    {
        return Schedule::with(['service.coach'])
            ->join('services', 'schedules.service_id', '=', 'services.id')
            ->join('coaches', 'services.coach_id', '=', 'coaches.id')
            ->where(function ($query) {
                $query->whereNull('schedules.date')
                    ->orWhere('schedules.date', '>=', today());
            })
            ->orderBy('coaches.name')
            ->orderBy('schedules.day_of_week')
            ->orderBy('schedules.date')
            ->orderBy('schedules.start_time')
            ->select('schedules.*')
            ->get();
    }

    public function toggleActive(Schedule $schedule): void
    {
        $schedule->update(['is_active' => ! $schedule->is_active]);

        Flux::toast(
            text: $schedule->is_active ? __('Grafiks aktivizēts!') : __('Grafiks deaktivizēts.'),
            variant: 'success',
        );

        unset($this->schedules);
    }

    public function destroy(Schedule $schedule): void
    {
        try {
            $schedule->delete();

            unset($this->schedules);

            Flux::toast(
                text: __('Grafiks veiksmīgi dzēsts!'),
                variant: 'success',
            );
        } catch (\Exception $e) {
            Log::error($e);

            Flux::toast(
                text: __('Neizdevās dzēst grafiku. Lūdzu, mēģini vēlreiz.'),
                heading: __('Kļūda!'),
                variant: 'danger',
            );
        }
    }
};
?>

<div>
    @if($this->schedules->isEmpty())
        <div class="flex flex-col items-center">
            <flux:text class="text-center py-8">{{ __('Šobrīd nav neviena grafika!') }}</flux:text>
            <flux:button href="{{ route('admin.schedules.create') }}" wire:navigate
                         class="mb-4">{{ __('Pievienot jaunu grafiku') }}
            </flux:button>
        </div>
    @else
        <div class="flex flex-col gap-6">
            @foreach($this->schedules->groupBy(fn ($schedule) => $schedule->service->coach->name) as $coachName => $coachSchedules)
                <div>
                    <flux:heading level="2" size="lg" class="mb-3">{{ $coachName }}</flux:heading>
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ __('Pakalpojums') }}</flux:table.column>
                            <flux:table.column>{{ __('Veids') }}</flux:table.column>
                            <flux:table.column>{{ __('Diena/Datums') }}</flux:table.column>
                            <flux:table.column>{{ __('Laiks') }}</flux:table.column>
                            <flux:table.column>{{ __('Maks. dalībnieki') }}</flux:table.column>
                            <flux:table.column>{{ __('Statuss') }}</flux:table.column>
                            <flux:table.column>{{ __('Darbības') }}</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach($coachSchedules as $schedule)
                                <flux:table.row wire:key="schedule-{{ $schedule->id }}">
                                    <flux:table.cell>{{ $schedule->service->name }}</flux:table.cell>
                                    <flux:table.cell>
                                        @if($schedule->day_of_week)
                                            <flux:badge size="sm">{{ __('Regulārs') }}</flux:badge>
                                        @else
                                            <flux:badge size="sm" variant="pill">{{ __('Vienreizējs') }}</flux:badge>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        @if($schedule->day_of_week)
                                            {{ $schedule->day_of_week->label() }}
                                        @else
                                            {{ $schedule->date->format('d.m.Y') }}
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell>{{ substr($schedule->start_time, 0, 5) }}</flux:table.cell>
                                    <flux:table.cell>{{ $schedule->max_capacity }}</flux:table.cell>
                                    <flux:table.cell>
                                        <flux:switch wire:click="toggleActive({{ $schedule->id }})"
                                                     :checked="$schedule->is_active"/>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <div class="flex gap-2">
                                            <flux:button href="{{ route('admin.schedules.edit', $schedule) }}"
                                                         variant="primary" size="sm">
                                                {{ __('Rediģēt') }}
                                            </flux:button>
                                            <flux:button wire:confirm="{{ __('Vai tiešām vēlies dzēst grafiku?') }}"
                                                         variant="danger"
                                                         size="sm"
                                                         wire:click="destroy({{ $schedule->id }})">
                                                {{ __('Dzēst') }}
                                            </flux:button>
                                        </div>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                </div>
            @endforeach
        </div>
    @endif
</div>
