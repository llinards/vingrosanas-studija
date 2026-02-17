<?php

use App\Livewire\Concerns\HasServiceForm;
use App\Models\Service;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    use HasServiceForm;

    #[Locked]
    public int $serviceId;

    public function mount(Service $service): void
    {
        $this->serviceId = $service->id;
        $this->name = $service->name;
        $this->service_type_id = $service->service_type_id;
        $this->coach_id = $service->coach_id;
        $this->price = (string) ($service->price / 100);
        $this->is_active = $service->is_active;
        $this->is_exclusive = $service->is_exclusive;

        // Load existing price tiers (excluding the base 1-participant tier)
        $this->priceTiers = $service->priceTiers()
            ->where('participant_count', '>', 1)
            ->get()
            ->map(fn ($tier) => [
                'participant_count' => (string) $tier->participant_count,
                'price' => (string) ($tier->price / 100),
            ])
            ->toArray();
    }

    public function save(): void
    {
        $this->validate();

        try {
            DB::transaction(function () {
                $service = Service::findOrFail($this->serviceId);

                $service->update([
                    'name' => $this->name,
                    'service_type_id' => $this->service_type_id,
                    'coach_id' => $this->coach_id,
                    'price' => (int) round($this->price * 100),
                    'is_active' => $this->is_active,
                    'is_exclusive' => $this->is_exclusive,
                ]);

                // Update the base 1-participant tier
                $service->priceTiers()->updateOrCreate(
                    ['participant_count' => 1],
                    ['price' => (int) round($this->price * 100)],
                );

                // Collect participant counts to keep
                $keepCounts = [1];

                foreach ($this->priceTiers as $tier) {
                    if (! empty($tier['participant_count']) && ! empty($tier['price'])) {
                        $participantCount = (int) $tier['participant_count'];
                        $keepCounts[] = $participantCount;

                        $service->priceTiers()->updateOrCreate(
                            ['participant_count' => $participantCount],
                            ['price' => (int) round((float) $tier['price'] * 100)],
                        );
                    }
                }

                // Delete removed tiers
                $service->priceTiers()
                    ->whereNotIn('participant_count', $keepCounts)
                    ->delete();
            });

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


<x-service.service-form :heading="__('Rediģēt pakalpojumu')"/>
