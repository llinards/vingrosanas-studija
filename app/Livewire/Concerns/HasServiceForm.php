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

    public bool $is_active = false;

    public string $newServiceTypeName = '';

    /**
     * @var array<int, array{participant_count: string, price: string}>
     */
    public array $priceTiers = [];

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
            'priceTiers' => ['array'],
            'priceTiers.*.participant_count' => ['required', 'integer', 'min:1'],
            'priceTiers.*.price' => ['required', 'numeric', 'min:0'],
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
            'priceTiers.*.participant_count.required' => __('Dalībnieku skaits ir obligāts.'),
            'priceTiers.*.participant_count.integer' => __('Dalībnieku skaitam jābūt veselam skaitlim.'),
            'priceTiers.*.participant_count.min' => __('Dalībnieku skaitam jābūt vismaz 1.'),
            'priceTiers.*.price.required' => __('Cena ir obligāta.'),
            'priceTiers.*.price.numeric' => __('Cenai jābūt skaitlim.'),
            'priceTiers.*.price.min' => __('Cena nedrīkst būt negatīva.'),
        ];
    }

    public function addPriceTier(): void
    {
        // Start at 2 since 1 is the base price
        $nextCount = 2;

        if (count($this->priceTiers) > 0) {
            $maxCount = max(array_column($this->priceTiers, 'participant_count'));
            $nextCount = (int) $maxCount + 1;
        }

        $this->priceTiers[] = [
            'participant_count' => (string) $nextCount,
            'price' => '',
        ];
    }

    public function removePriceTier(int $index): void
    {
        unset($this->priceTiers[$index]);
        $this->priceTiers = array_values($this->priceTiers);
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
