<?php

use App\Livewire\Concerns\HasServiceForm;
use App\Models\Service;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

new class extends Component
{
    use HasServiceForm;

    /**
     * Validate and create a new service with its price tiers.
     *
     * Creates the base price tier and any additional participant count tiers.
     */
    public function save(): void
    {
        $this->validate();

        try {
            DB::transaction(function () {
                $service = Service::create([
                    'name' => $this->name,
                    'description' => $this->description ?: null,
                    'service_type_id' => $this->service_type_id,
                    'coach_id' => $this->is_membership ? null : $this->coach_id,
                    'price' => (int) round($this->price * 100),
                    'is_active' => $this->is_active,
                    'is_exclusive' => $this->is_membership ? false : $this->is_exclusive,
                    'is_membership_eligible' => $this->is_membership ? false : $this->is_membership_eligible,
                    'is_membership' => $this->is_membership,
                    'sessions_count' => $this->is_membership ? (int) $this->sessions_count : null,
                ]);

                // Create price tier for 1 participant using the base price
                $service->priceTiers()->create([
                    'participant_count' => 1,
                    'price' => (int) round($this->price * 100),
                ]);

                // Create additional price tiers
                foreach ($this->priceTiers as $tier) {
                    if (! empty($tier['participant_count']) && ! empty($tier['price'])) {
                        $service->priceTiers()->updateOrCreate(
                            ['participant_count' => (int) $tier['participant_count']],
                            ['price' => (int) round((float) $tier['price'] * 100)],
                        );
                    }
                }
            });

            Flux::toast(
                text: __('Pakalpojums izveidots!'),
                variant: 'success',
            );

            $this->redirect(route('admin.services.index'), navigate: true);
        } catch (\Exception $e) {
            Log::error($e);

            Flux::toast(
                text: __('Neizdevās izveidot pakalpojumu. Lūdzu, mēģini vēlreiz.'),
                heading: __('Kļūda!'),
                variant: 'danger',
            );
        }
    }

    /**
     * Render the component view with the page title.
     */
    public function render(): \Illuminate\View\View
    {
        return $this->view()
            ->title('Pievienot jaunu pakalpojumu');
    }
};
?>


<x-service.service-form :heading="__('Pievienot jaunu pakalpojumu')"/>
