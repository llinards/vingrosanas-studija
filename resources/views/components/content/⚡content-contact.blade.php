<?php

use App\Models\SiteSetting;
use Flux\Flux;
use Livewire\Component;

new class extends Component {
    public string $phone = '';

    public string $email = '';

    public string $address = '';

    public string $google_maps_url = '';

    public string $instagram_url = '';

    public string $facebook_url = '';

    public function mount(): void
    {
        $settings = SiteSetting::getGroup('contact');

        $this->phone = $settings->get('phone', '');
        $this->email = $settings->get('email', '');
        $this->address = $settings->get('address', '');
        $this->google_maps_url = $settings->get('google_maps_url', '');
        $this->instagram_url = $settings->get('instagram_url', '');
        $this->facebook_url = $settings->get('facebook_url', '');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'google_maps_url' => ['required', 'url', 'max:500'],
            'instagram_url' => ['required', 'url', 'max:500'],
            'facebook_url' => ['required', 'url', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'email.required' => __('E-pasts ir obligāts.'),
            'email.email' => __('Lūdzu, ievadi derīgu e-pasta adresi.'),
            'address.required' => __('Adrese ir obligāta.'),
            'google_maps_url.required' => __('Google Maps saite ir obligāta.'),
            'google_maps_url.url' => __('Lūdzu, ievadi derīgu URL.'),
            'instagram_url.required' => __('Instagram saite ir obligāta.'),
            'instagram_url.url' => __('Lūdzu, ievadi derīgu URL.'),
            'facebook_url.required' => __('Facebook saite ir obligāta.'),
            'facebook_url.url' => __('Lūdzu, ievadi derīgu URL.'),
        ];
    }

    public function save(): void
    {
        $this->validate();

        SiteSetting::setGroup('contact', [
            'phone' => ['value' => $this->phone, 'type' => 'string'],
            'email' => ['value' => $this->email, 'type' => 'string'],
            'address' => ['value' => $this->address, 'type' => 'string'],
            'google_maps_url' => ['value' => $this->google_maps_url, 'type' => 'string'],
            'instagram_url' => ['value' => $this->instagram_url, 'type' => 'string'],
            'facebook_url' => ['value' => $this->facebook_url, 'type' => 'string'],
        ]);

        Flux::toast(text: __('Kontaktinformācija saglabāta!'), variant: 'success');
    }

    public function render(): \Illuminate\View\View
    {
        return $this->view()->title(__('Kontaktinformācija'));
    }
};
?>

<div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
    <flux:heading class="mb-4" level="1" size="xl">{{ __('Saturs') }}</flux:heading>
    <flux:separator />

    <x-content.layout :heading="__('Kontaktinformācija')" :subheading="__('Kontaktinformācija, kas tiek rādīta mājas lapā.')">
        <form wire:submit="save" class="space-y-6">
            <flux:input wire:model="phone" :label="__('Telefona numurs')" />
            <flux:input wire:model="email" :label="__('E-pasts')" type="email" />
            <flux:input wire:model="address" :label="__('Adrese')" />
            <flux:input wire:model="google_maps_url" :label="__('Google Maps saite')" />
            <flux:input wire:model="instagram_url" :label="__('Instagram saite')" />
            <flux:input wire:model="facebook_url" :label="__('Facebook saite')" />

            <flux:button type="submit" variant="primary">{{ __('Saglabāt') }}</flux:button>
        </form>
    </x-content.layout>
</div>
