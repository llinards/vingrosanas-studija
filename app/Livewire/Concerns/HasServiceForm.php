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

    public string $description = '';

    public ?int $service_type_id = null;

    public ?int $coach_id = null;

    public string $price = '';

    public bool $is_active = false;

    public bool $is_exclusive = false;

    public bool $is_membership_eligible = false;

    public bool $is_membership = false;

    public bool $notify_coach_on_cancellation = false;

    public string $sessions_count = '';

    public string $serviceTypeSearch = '';

    /**
     * @var array<int, array{participant_count: string, price: string}>
     */
    public array $priceTiers = [];

    /**
     * Get all available service types for the dropdown.
     */
    #[Computed]
    public function serviceTypes(): Collection
    {
        return ServiceType::all();
    }

    /**
     * Get all available coaches for the dropdown.
     */
    #[Computed]
    public function coaches(): Collection
    {
        return Coach::all();
    }

    /**
     * Get the validation rules for the service form.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'service_type_id' => ['required', 'exists:service_types,id'],
            'coach_id' => $this->is_membership ? ['nullable'] : ['required', 'exists:coaches,id'],
            'price' => ['required', 'numeric', 'min:0'],
        ];

        if ($this->is_membership) {
            $rules['sessions_count'] = ['required', 'integer', 'min:1'];
        } else {
            $rules['priceTiers'] = ['array'];
            $rules['priceTiers.*.participant_count'] = ['required', 'integer', 'min:1'];
            $rules['priceTiers.*.price'] = ['required', 'numeric', 'min:0'];
        }

        return $rules;
    }

    /**
     * Get the custom validation messages for the service form.
     *
     * @return array<string, string>
     */
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
            'sessions_count.required' => __('Nodarbību skaits ir obligāts.'),
            'sessions_count.integer' => __('Nodarbību skaitam jābūt veselam skaitlim.'),
            'sessions_count.min' => __('Nodarbību skaitam jābūt vismaz 1.'),
        ];
    }

    /**
     * Add a new price tier to the service.
     *
     * Automatically increments the participant count based on existing tiers,
     * starting at 2 since 1 is the base price.
     */
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

    /**
     * Remove a price tier from the service by its index.
     *
     * Re-indexes the array after removal to maintain sequential keys.
     */
    public function removePriceTier(int $index): void
    {
        unset($this->priceTiers[$index]);
        $this->priceTiers = array_values($this->priceTiers);
    }

    /**
     * Create a new service type from the pillbox input and select it.
     */
    public function createServiceType(): void
    {
        $this->validate([
            'serviceTypeSearch' => ['required', 'string', 'max:255', 'unique:service_types,name'],
        ], [
            'serviceTypeSearch.required' => __('Nosaukums ir obligāts.'),
            'serviceTypeSearch.max' => __('Nosaukums nedrīkst pārsniegt 255 rakstzīmes.'),
            'serviceTypeSearch.unique' => __('Šāds pakalpojuma veids jau eksistē.'),
        ]);

        $serviceType = ServiceType::create(['name' => $this->serviceTypeSearch]);

        $this->service_type_id = $serviceType->id;
        $this->serviceTypeSearch = '';

        unset($this->serviceTypes);

        Flux::toast(
            text: __('Pakalpojuma veids izveidots!'),
            variant: 'success',
        );
    }

    /**
     * Delete a service type if it has no associated services.
     */
    public function deleteServiceType(int $serviceTypeId): void
    {
        $serviceType = ServiceType::findOrFail($serviceTypeId);

        if ($serviceType->services()->exists()) {
            Flux::toast(
                text: __('Nevar dzēst — šim veidam ir piesaistīti pakalpojumi.'),
                heading: __('Kļūda!'),
                variant: 'danger',
            );

            return;
        }

        $serviceType->delete();

        if ($this->service_type_id === $serviceTypeId) {
            $this->service_type_id = null;
        }

        unset($this->serviceTypes);

        Flux::toast(
            text: __('Pakalpojuma veids dzēsts!'),
            variant: 'success',
        );
    }
}
