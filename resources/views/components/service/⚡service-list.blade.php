<?php

use App\Models\Service;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    #[Computed]
    public function services(): Collection
    {
        return Service::with(['serviceType', 'coach'])->get();
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
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Nosaukums') }}</flux:table.column>
                <flux:table.column>{{ __('Veids') }}</flux:table.column>
                <flux:table.column>{{ __('Treneris') }}</flux:table.column>
                <flux:table.column>{{ __('Cena') }}</flux:table.column>
                <flux:table.column>{{ __('Statuss') }}</flux:table.column>
                <flux:table.column>{{ __('Darbības') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach($this->services as $service)
                    <flux:table.row wire:key="service-{{ $service->id }}">
                        <flux:table.cell>{{ $service->name }}</flux:table.cell>
                        <flux:table.cell>{{ $service->serviceType->name }}</flux:table.cell>
                        <flux:table.cell>{{ $service->coach->name }}</flux:table.cell>
                        <flux:table.cell>{{ Number::currency($service->price / 100, 'EUR') }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="$service->is_active ? 'green' : 'red'" size="sm">
                                {{ $service->is_active ? __('Aktīvs') : __('Neaktīvs') }}
                            </flux:badge>
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
    @endif
</div>