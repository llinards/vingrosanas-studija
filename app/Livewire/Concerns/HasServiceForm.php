<?php

namespace App\Livewire\Concerns;

use App\Models\Coach;
use App\Models\ServiceType;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;

trait HasServiceForm
{
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

    public function saveServiceType(): void
    {
        $this->validate([
            'newServiceTypeName' => ['required', 'string', 'max:255', 'unique:service_types,name'],
        ], [
            'newServiceTypeName.required' => __('Nosaukums ir obligāts.'),
            'newServiceTypeName.max' => __('Nosaukums nedrīkst pārsniegt 255 rakstzīmes.'),
            'newServiceTypeName.unique' => __('Šāds pakalpojuma veids jau eksistē.'),
        ]);

        $serviceType = ServiceType::create(['name' => $this->newServiceTypeName]);

        $this->service_type_id = $serviceType->id;
        $this->newServiceTypeName = '';

        unset($this->serviceTypes);

        Flux::toast(
            text: __('Pakalpojuma veids izveidots!'),
            variant: 'success',
        );

        $this->modal('create-service-type')->close();
    }
}
