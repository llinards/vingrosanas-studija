<?php

use App\Models\Service;
use App\Models\ServicePriceTier;
use App\Models\ServiceType;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    #[Computed]
    public function serviceTypes(): Collection
    {
        return ServiceType::with([
            'services' => fn($query) => $query
                ->where('is_active', true)
                ->with('priceTiers')
                ->orderBy('position')
        ])
                          ->whereHas('services', fn($query) => $query->where('is_active', true))
                          ->orderBy('position')
                          ->get()
                          ->each(function (ServiceType $serviceType): void {
                              $unique = $serviceType->services->unique(fn(Service $service
                              ) => $this->servicePricingFingerprint($service));

                              $serviceType->setRelation('services', $unique);
                          });
    }

    private function servicePricingFingerprint(Service $service): string
    {
        if ($service->priceTiers->count() > 1) {
            $tiers = $service->priceTiers
                ->sortBy('participant_count')
                ->map(fn(ServicePriceTier $tier) => $tier->participant_count.':'.$tier->price)
                ->implode('|');

            return $service->name.'|'.$tiers;
        }

        return $service->name.'|'.$service->price;
    }
};
?>

<div>
    <div id="services" class="mx-auto max-w-7xl px-4 py-12">
        <flux:heading level="2" class="pb-6 md:pb-12">{{ __('Pakalpojumi un cenas') }}</flux:heading>
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
                                                    @if($service->description)
                                                        <flux:modal.trigger name="service-description-{{ $service->id }}">
                                                            <flux:link as="button">{{ $service->name }} ({{ $tier->participant_count }} {{ $tier->participant_count === 1 ? __('persona') : __('personas') }})</flux:link>
                                                        </flux:modal.trigger>
                                                    @else
                                                        {{ $service->name }} ({{ $tier->participant_count }} {{ $tier->participant_count === 1 ? __('persona') : __('personas') }})
                                                    @endif
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
                                                @if($service->description)
                                                    <flux:modal.trigger name="service-description-{{ $service->id }}">
                                                        <flux:link as="button">{{ $service->name }}</flux:link>
                                                    </flux:modal.trigger>
                                                @else
                                                    {{ $service->name }}
                                                @endif
                                            </flux:table.cell>
                                            <flux:table.cell class="w-28" align="end">
                                                {{ Number::currency($service->price / 100, 'EUR') }}
                                            </flux:table.cell>
                                        </flux:table.row>
                                    @endif

                                    @if($service->description)
                                        <flux:modal
                                            class="p-6 space-y-6"
                                            name="service-description-{{ $service->id }}">
                                            <flux:heading level="3" class="pt-8">{{ $service->name }}</flux:heading>
                                            <div class="rich-text-content text-left w-full">
                                                {!! $service->description !!}
                                            </div>
                                        </flux:modal>
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
