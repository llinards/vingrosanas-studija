<?php

use App\Livewire\Concerns\HasServiceForm;
use App\Models\Service;
use Flux\Flux;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    use HasServiceForm;

    #[Locked]
    public int $serviceId;

    public function mount(Service $service): void
    {
        $this->serviceId       = $service->id;
        $this->name            = $service->name;
        $this->service_type_id = $service->service_type_id;
        $this->coach_id        = $service->coach_id;
        $this->price           = (string) ($service->price / 100);
    }

    public function save(): void
    {
        $this->validate();

        try {
            $service = Service::findOrFail($this->serviceId);

            $service->update([
                'name'            => $this->name,
                'service_type_id' => $this->service_type_id,
                'coach_id'        => $this->coach_id,
                'price'           => (int) round($this->price * 100),
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


<x-service.service-form :heading="__('Rediģēt pakalpojumu')"/>
