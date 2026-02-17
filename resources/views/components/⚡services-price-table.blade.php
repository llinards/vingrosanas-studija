<?php

use App\Models\ServiceType;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    #[Computed]
    public function serviceTypes(): Collection
    {
        return ServiceType::with(['services' => fn ($query) => $query
            ->where('is_active', true)
            ->with('priceTiers')
            ->orderBy('position')])
            ->whereHas('services', fn ($query) => $query->where('is_active', true))
            ->orderBy('position')
            ->get();
    }
};
?>

<div>
    <div id="services" class="mx-auto max-w-7xl px-4 py-12">
        <flux:heading level="2" class="">{{ __('Pakalpojumi un cenas') }}</flux:heading>
        <flux:accordion transition>
            @forelse ($this->serviceTypes as $serviceType)
                <flux:accordion.item>
                    <flux:accordion.heading>
                        <flux:heading level="3">{{ $serviceType->name }}</flux:heading>
                    </flux:accordion.heading>

                    <flux:accordion.content>
                        <flux:table class="w-full table-fixed">
                            <flux:table.rows>
                                @foreach ($serviceType->services as $service)
                                    @if($service->priceTiers->count() > 1)
                                        {{-- Service with multiple price tiers - show each tier as separate row --}}
                                        @foreach($service->priceTiers as $tier)
                                            <flux:table.row>
                                                <flux:table.cell class="whitespace-normal">
                                                    {{ $service->name }} ({{ $tier->participant_count }} {{ $tier->participant_count === 1 ? __('persona') : __('personas') }})
                                                </flux:table.cell>
                                                <flux:table.cell class="w-28" align="end">
                                                    {{ Number::currency($tier->price / 100, 'EUR') }}
                                                </flux:table.cell>
                                            </flux:table.row>
                                        @endforeach
                                    @else
                                        {{-- Service with single price --}}
                                        <flux:table.row>
                                            <flux:table.cell class="whitespace-normal">
                                                {{ $service->name }}
                                            </flux:table.cell>
                                            <flux:table.cell class="w-28" align="end">
                                                {{ Number::currency($service->price / 100, 'EUR') }}
                                            </flux:table.cell>
                                        </flux:table.row>
                                    @endif
                                @endforeach
                            </flux:table.rows>
                        </flux:table>
                    </flux:accordion.content>
                </flux:accordion.item>
            @empty
                <flux:text>{{ __('Pakalpojumu informācija šobrīd nav pieejama.') }}</flux:text>
            @endforelse
        </flux:accordion>
    </div>
</div>
