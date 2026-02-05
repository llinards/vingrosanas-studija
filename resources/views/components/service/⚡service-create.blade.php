<?php

use App\Models\Coach;
use App\Models\Service;
use App\Models\ServiceType;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public string $name = '';

    public ?int $service_type_id = null;

    public ?int $coach_id = null;

    public string $price = '';

    public string $newServiceTypeName = '';

    #[Computed]
    public function serviceTypes(): Collection
    {
        return ServiceType::all();
    }

    #[Computed]
    public function coaches(): Collection
    {
        return Coach::all();
    }

    protected function rules(): array
    {
        return [
            'name'            => ['required', 'string', 'max:255'],
            'service_type_id' => ['required', 'exists:service_types,id'],
            'coach_id'        => ['required', 'exists:coaches,id'],
            'price'           => ['required', 'numeric', 'min:0'],
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required'            => __('Nosaukums ir obligāts.'),
            'name.max'                 => __('Nosaukums nedrīkst pārsniegt 255 rakstzīmes.'),
            'service_type_id.required' => __('Pakalpojuma veids ir obligāts.'),
            'service_type_id.exists'   => __('Izvēlētais pakalpojuma veids neeksistē.'),
            'coach_id.required'        => __('Treneris ir obligāts.'),
            'coach_id.exists'          => __('Izvēlētais treneris neeksistē.'),
            'price.required'           => __('Cena ir obligāta.'),
            'price.numeric'            => __('Cenai jābūt skaitlim.'),
            'price.min'                => __('Cena nedrīkst būt negatīva.'),
        ];
    }

    public function saveServiceType(): void
    {
        $this->validate([
            'newServiceTypeName' => ['required', 'string', 'max:255', 'unique:service_types,name'],
        ], [
            'newServiceTypeName.required' => __('Nosaukums ir obligāts.'),
            'newServiceTypeName.max'      => __('Nosaukums nedrīkst pārsniegt 255 rakstzīmes.'),
            'newServiceTypeName.unique'   => __('Šāds pakalpojuma veids jau eksistē.'),
        ]);

        $serviceType = ServiceType::create(['name' => $this->newServiceTypeName]);

        $this->service_type_id    = $serviceType->id;
        $this->newServiceTypeName = '';

        unset($this->serviceTypes);

        Flux::toast(
            text: __('Pakalpojuma veids izveidots!'),
            variant: 'success',
        );

        $this->modal('create-service-type')->close();
    }

    public function save(): void
    {
        $this->validate();

        try {
            Service::create([
                'name'            => $this->name,
                'service_type_id' => $this->service_type_id,
                'coach_id'        => $this->coach_id,
                'price'           => (int) round($this->price * 100),
            ]);

            Flux::toast(
                text: __('Pakalpojums izveidots!'),
                variant: 'success',
            );

            $this->redirect(route('service-list'), navigate: true);
        } catch (\Exception $e) {
            Log::error($e);

            Flux::toast(
                text: __('Neizdevās izveidot pakalpojumu. Lūdzu, mēģini vēlreiz.'),
                heading: __('Kļūda!'),
                variant: 'danger',
            );
        }
    }

    public function render(): \Illuminate\View\View
    {
        return $this->view()
                    ->title('Pievienot jaunu pakalpojumu');
    }
};
?>


<div class="flex min-h-full flex-col items-center justify-center p-6">
    <div class="w-full max-w-2xl">
        <flux:heading level="1" size="xl" class="mb-6">Pievienot jaunu pakalpojumu</flux:heading>

        <form wire:submit="save" class="flex flex-col gap-6">
            <flux:input
                wire:model="name"
                :label="__('Nosaukums')"
                :placeholder="__('Ievadiet pakalpojuma nosaukumu')"
            />

            <div class="flex items-end gap-2">
                <div class="flex-1">
                    <flux:select wire:model="service_type_id" :label="__('Pakalpojuma veids')">
                        <flux:select.option value="">{{ __('Izvēlieties veidu') }}</flux:select.option>
                        @foreach($this->serviceTypes as $serviceType)
                            <flux:select.option :value="$serviceType->id">{{ $serviceType->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
                <flux:modal.trigger name="create-service-type">
                    <flux:button variant="outline" icon="plus"/>
                </flux:modal.trigger>
            </div>

            <flux:modal name="create-service-type" class="md:w-96">
                <div class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Jauns pakalpojuma veids') }}</flux:heading>
                    </div>

                    <flux:input wire:model="newServiceTypeName" :label="__('Nosaukums')"
                                :placeholder="__('Ievadi nosaukumu')"/>

                    <div class="flex">
                        <flux:spacer/>
                        <flux:button wire:click="saveServiceType" variant="primary">{{ __('Saglabāt') }}</flux:button>
                    </div>
                </div>
            </flux:modal>

            <flux:select wire:model="coach_id" :label="__('Treneris')">
                <flux:select.option value="">{{ __('Izvēlieties treneri') }}</flux:select.option>
                @foreach($this->coaches as $coach)
                    <flux:select.option :value="$coach->id">{{ $coach->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input
                wire:model="price"
                :label="__('Cena (EUR)')"
                type="number"
                step="0.01"
                min="0"
                :placeholder="__('Ievadiet cenu (piem., 25.00)')"
            />

            <div class="flex items-center justify-end gap-4">
                <flux:button href="{{ route('service-list') }}" wire:navigate variant="ghost">
                    {{ __('Atcelt') }}
                </flux:button>
                <flux:button type="submit" variant="primary">
                    {{ __('Saglabāt') }}
                </flux:button>
            </div>
        </form>
    </div>
</div>
