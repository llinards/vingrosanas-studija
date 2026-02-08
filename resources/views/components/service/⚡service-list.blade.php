<?php

use App\Models\Service;
use App\Models\ServiceType;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    #[Computed]
    public function services(): Collection
    {
        return Service::with(['serviceType', 'coach'])
            ->join('service_types', 'services.service_type_id', '=', 'service_types.id')
            ->orderBy('service_types.position')
            ->orderBy('services.position')
            ->select('services.*')
            ->get();
    }

    public function updateServiceTypePosition(int $id, int $position): void
    {
        $serviceType = ServiceType::findOrFail($id);
        $serviceTypes = ServiceType::query()->orderBy('position')->get();
        $serviceTypes = $serviceTypes->reject(fn ($st) => $st->id === $id);
        $serviceTypes->splice($position, 0, [$serviceType]);
        $serviceTypes->each(function ($st, $index) {
            $st->update(['position' => $index]);
        });

        unset($this->services);

        Flux::toast(
            text: __('Pakalpojumu veidu secība veiksmīgi atjaunināta!'),
            variant: 'success',
        );
    }

    public function updateServicePosition(int $id, int $position): void
    {
        $service = Service::findOrFail($id);
        $services = Service::query()
            ->where('service_type_id', $service->service_type_id)
            ->orderBy('position')
            ->get();
        $services = $services->reject(fn ($s) => $s->id === $id);
        $services->splice($position, 0, [$service]);
        $services->each(function ($s, $index) {
            $s->update(['position' => $index]);
        });

        unset($this->services);

        Flux::toast(
            text: __('Pakalpojumu secība veiksmīgi atjaunināta!'),
            variant: 'success',
        );
    }

    public function toggleActive(Service $service): void
    {
        $service->update(['is_active' => ! $service->is_active]);

        Flux::toast(
            text: $service->is_active ? __('Pakalpojums aktivizēts!') : __('Pakalpojums deaktivizēts.'),
            variant: 'success',
        );

        unset($this->services);
    }

    public function destroy(Service $service): void
    {
        try {
            $service->delete();

            unset($this->services);

            Flux::toast(
                text: __('Pakalpojums veiksmīgi dzēsts!'),
                variant: 'success',
            );
        } catch (\Exception $e) {
            Log::error($e);

            Flux::toast(
                text: __('Neizdevās dzēst pakalpojumu. Lūdzu, mēģini vēlreiz.'),
                heading: __('Kļūda!'),
                variant: 'danger',
            );
        }
    }
};
?>

<div>
    @if($this->services->isEmpty())
        <div class="flex flex-col items-center">
            <flux:heading class="mb-2" level="2" size="xl">Šobrīd nav neviena pakalpojuma!</flux:heading>
            <flux:button href="{{ route('service-create') }}" wire:navigate class="mb-4">Pievienot jaunu pakalpojumu
            </flux:button>
        </div>
    @else
        <div class="flex flex-col gap-6" wire:sort="updateServiceTypePosition">
            @foreach($this->services->groupBy(fn ($service) => $service->serviceType->id) as $serviceTypeId => $typeServices)
                <div wire:key="service-type-{{ $serviceTypeId }}" wire:sort:item="{{ $serviceTypeId }}">
                    <div class="mb-3 flex items-center gap-2">
                        <div wire:sort:handle class="cursor-move">
                            <flux:icon.bars-3 class="size-5 text-zinc-400 hover:text-zinc-600"/>
                        </div>
                        <flux:heading level="2" size="lg">{{ $typeServices->first()->serviceType->name }}</flux:heading>
                    </div>
                    <div>
                        <flux:table>
                            <flux:table.columns>
                                <flux:table.column class="w-10"></flux:table.column>
                                <flux:table.column>{{ __('Nosaukums') }}</flux:table.column>
                                <flux:table.column>{{ __('Treneris') }}</flux:table.column>
                                <flux:table.column>{{ __('Cena') }}</flux:table.column>
                                <flux:table.column>{{ __('Statuss') }}</flux:table.column>
                                <flux:table.column>{{ __('Darbības') }}</flux:table.column>
                            </flux:table.columns>
                            <flux:table.rows wire:sort="updateServicePosition">
                                @foreach($typeServices as $service)
                                    <flux:table.row wire:key="service-{{ $service->id }}" wire:sort:item="{{ $service->id }}">
                                        <flux:table.cell>
                                            <div wire:sort:handle class="cursor-move">
                                                <flux:icon.bars-3 class="size-5 text-zinc-400 hover:text-zinc-600"/>
                                            </div>
                                        </flux:table.cell>
                                        <flux:table.cell>{{ $service->name }}</flux:table.cell>
                                        <flux:table.cell>{{ $service->coach->name }}</flux:table.cell>
                                        <flux:table.cell>{{ Number::currency($service->price / 100, 'EUR') }}</flux:table.cell>
                                        <flux:table.cell>
                                            <flux:switch wire:click="toggleActive({{ $service->id }})" :checked="$service->is_active"/>
                                        </flux:table.cell>
                                        <flux:table.cell>
                                            <div class="flex gap-2">
                                                <flux:button href="{{ route('service.edit', $service) }}" variant="primary" size="sm">
                                                    {{ __('Rediģēt') }}
                                                </flux:button>
                                                <flux:button wire:confirm="{{ __('Vai tiešām vēlies dzēst pakalpojumu?') }}"
                                                             variant="danger"
                                                             size="sm"
                                                             wire:click="destroy({{ $service->id }})">
                                                    {{ __('Dzēst') }}
                                                </flux:button>
                                            </div>
                                        </flux:table.cell>
                                    </flux:table.row>
                                @endforeach
                            </flux:table.rows>
                        </flux:table>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
