<?php

use App\Livewire\Concerns\HasServiceForm;
use App\Models\Service;
use Flux\Flux;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

new class extends Component {
    use HasServiceForm;

    public function save(): void
    {
        $this->validate();

        try {
            Service::create([
                'name'            => $this->name,
                'service_type_id' => $this->service_type_id,
                'coach_id'        => $this->coach_id,
                'price'           => (int) round($this->price * 100),
                'is_active'       => $this->is_active,
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


<x-service.service-form :heading="__('Pievienot jaunu pakalpojumu')"/>
