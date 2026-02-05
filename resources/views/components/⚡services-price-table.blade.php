<?php

use App\Models\ServiceType;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    #[Computed]
    public function serviceTypes(): Collection
    {
        return ServiceType::with('services')->get();
    }
};
?>

<div>
    @forelse ($this->serviceTypes as $serviceType)
        <flux:accordion.item>
            <flux:accordion.heading>
                <flux:heading level="3">{{ $serviceType->name }}</flux:heading>
            </flux:accordion.heading>
            <flux:accordion.content>
                <flux:table>
                    <flux:table.rows>
                        @foreach ($serviceType->services as $service)
                            <flux:table.row>
                                <flux:table.cell>{{ $service->name }}</flux:table.cell>
                                <flux:table.cell
                                    align="center">{{ Number::currency($service->price / 100, 'EUR') }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </flux:accordion.content>
        </flux:accordion.item>
    @empty
        <flux:text>{{ __('Pakalpojumu informācija nav pieejama.') }}</flux:text>
    @endforelse
</div>
