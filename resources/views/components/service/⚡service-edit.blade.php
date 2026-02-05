<?php

use App\Models\Coach;
use App\Models\Service;
use App\Models\ServiceType;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public int $serviceId;

    public string $name = '';

    public ?int $service_type_id = null;

    public ?int $coach_id = null;

    public string $price = '';

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

    public function mount(Service $service): void
    {
        $this->serviceId = $service->id;
        $this->name = $service->name;
        $this->service_type_id = $service->service_type_id;
        $this->coach_id = $service->coach_id;
        $this->price = (string) ($service->price / 100);
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'service_type_id' => ['required', 'exists:service_types,id'],
            'coach_id' => ['required', 'exists:coaches,id'],
            'price' => ['required', 'numeric', 'min:0'],
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required' => __('Nosaukums ir obligāts.'),
            'name.max' => __('Nosaukums nedrīkst pārsniegt 255 rakstzīmes.'),
            'service_type_id.required' => __('Pakalpojuma veids ir obligāts.'),
            'service_type_id.exists' => __('Izvēlētais pakalpojuma veids neeksistē.'),
            'coach_id.required' => __('Treneris ir obligāts.'),
            'coach_id.exists' => __('Izvēlētais treneris neeksistē.'),
            'price.required' => __('Cena ir obligāta.'),
            'price.numeric' => __('Cenai jābūt skaitlim.'),
            'price.min' => __('Cena nedrīkst būt negatīva.'),
        ];
    }

    public function save(): void
    {
        $this->validate();

        try {
            $service = Service::findOrFail($this->serviceId);

            $service->update([
                'name' => $this->name,
                'service_type_id' => $this->service_type_id,
                'coach_id' => $this->coach_id,
                'price' => (int) round($this->price * 100),
            ]);

            Flux::toast(
                text: __('Pakalpojums atjaunināts!'),
                variant: 'success',
            );

            $this->redirect(route('service-list'), navigate: true);
        } catch (\Exception $e) {
            Log::error($e);

            Flux::toast(
                text: __('Neizdevās atjaunināt pakalpojumu. Lūdzu, mēģini vēlreiz.'),
                heading: __('Kļūda!'),
                variant: 'danger',
            );
        }
    }

    public function render(): \Illuminate\View\View
    {
        return $this->view()
            ->title(__('Rediģēt pakalpojumu'));
    }
};
?>


<div class="flex min-h-full flex-col items-center justify-center p-6">
    <div class="w-full max-w-2xl">
        <flux:heading level="1" size="xl" class="mb-6">{{ __('Rediģēt pakalpojumu') }}</flux:heading>

        <form wire:submit="save" class="flex flex-col gap-6">
            <flux:input
                wire:model="name"
                :label="__('Nosaukums')"
                :placeholder="__('Ievadiet pakalpojuma nosaukumu')"
            />

            <flux:select wire:model="service_type_id" :label="__('Pakalpojuma veids')">
                <flux:select.option value="">{{ __('Izvēlieties veidu') }}</flux:select.option>
                @foreach($this->serviceTypes as $serviceType)
                    <flux:select.option :value="$serviceType->id">{{ $serviceType->name }}</flux:select.option>
                @endforeach
            </flux:select>

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
