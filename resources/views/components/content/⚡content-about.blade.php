<?php

use App\Models\SiteSetting;
use Flux\Flux;
use Livewire\Component;

new class extends Component
{
    public string $about_content = '';

    public string $goal_content = '';

    public string $vision_content = '';

    public string $mission_content = '';

    public function mount(): void
    {
        $settings = SiteSetting::getGroup('about');

        $this->about_content = $settings->get('about_content', '');
        $this->goal_content = $settings->get('goal_content', '');
        $this->vision_content = $settings->get('vision_content', '');
        $this->mission_content = $settings->get('mission_content', '');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'about_content' => ['required', 'string', 'max:10000'],
            'goal_content' => ['required', 'string', 'max:10000'],
            'vision_content' => ['required', 'string', 'max:10000'],
            'mission_content' => ['required', 'string', 'max:10000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'about_content.required' => __('Sadaļas "Par mums" saturs ir obligāts.'),
            'goal_content.required' => __('Sadaļas "Mērķis" saturs ir obligāts.'),
            'vision_content.required' => __('Sadaļas "Vīzija" saturs ir obligāts.'),
            'mission_content.required' => __('Sadaļas "Misija" saturs ir obligāts.'),
        ];
    }

    public function save(): void
    {
        $this->validate();

        SiteSetting::setGroup('about', [
            'about_content' => ['value' => $this->about_content, 'type' => 'html'],
            'goal_content' => ['value' => $this->goal_content, 'type' => 'html'],
            'vision_content' => ['value' => $this->vision_content, 'type' => 'html'],
            'mission_content' => ['value' => $this->mission_content, 'type' => 'html'],
        ]);

        Flux::toast(
            text: __('Par mums sadaļa saglabāta!'),
            variant: 'success',
        );
    }

    public function render(): \Illuminate\View\View
    {
        return $this->view()
            ->title(__('Par mums'));
    }
};
?>

<div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <flux:heading class="mb-4" level="1" size="xl">{{ __('Saturs') }}</flux:heading>
        <flux:separator />

        <x-content.layout :heading="__('Par mums')" :subheading="__('Rediģē četras \'Par mums\' apakšsadaļas.')">
            <form wire:submit="save" class="space-y-6">
                <flux:editor wire:model="about_content" :label="__('Par mums')" />
                <flux:editor wire:model="goal_content" :label="__('Mērķis')" />
                <flux:editor wire:model="vision_content" :label="__('Vīzija')" />
                <flux:editor wire:model="mission_content" :label="__('Misija')" />

                <flux:button type="submit" variant="primary">{{ __('Saglabāt') }}</flux:button>
            </form>
        </x-content.layout>
</div>
