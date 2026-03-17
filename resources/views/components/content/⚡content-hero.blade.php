<?php

use App\Models\SiteSetting;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public string $heading = '';

    public string $cta_text = '';

    public $background_image;

    public string $current_background_image = '';

    public function mount(): void
    {
        $settings = SiteSetting::getGroup('hero');

        $this->heading = $settings->get('heading', '');
        $this->cta_text = $settings->get('cta_text', '');
        $this->current_background_image = $settings->get('background_image', '');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'heading' => ['required', 'string', 'max:255'],
            'cta_text' => ['required', 'string', 'max:100'],
            'background_image' => ['nullable', 'image', 'max:400'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'heading.required' => __('Virsraksts ir obligāts.'),
            'cta_text.required' => __('Pogas teksts ir obligāts.'),
            'background_image.image' => __('Failam jābūt attēlam.'),
            'background_image.max' => __('Attēls nedrīkst pārsniegt 400KB.'),
        ];
    }

    public function removeImage(): void
    {
        $this->background_image?->delete();
        $this->background_image = null;
    }

    public function save(): void
    {
        $this->validate();

        $imagePath = $this->current_background_image;

        if ($this->background_image) {
            $this->deleteOldImage($this->current_background_image);
            $stored = $this->background_image->store('content/hero', 'public');
            $imagePath = 'storage/'.$stored;
            $this->current_background_image = $imagePath;
            $this->background_image = null;
        }

        SiteSetting::setGroup('hero', [
            'heading' => ['value' => $this->heading, 'type' => 'string'],
            'cta_text' => ['value' => $this->cta_text, 'type' => 'string'],
            'background_image' => ['value' => $imagePath, 'type' => 'image'],
        ]);

        Flux::toast(
            text: __('Sākumlapas saturs saglabāts!'),
            variant: 'success',
        );
    }

    private function deleteOldImage(string $path): void
    {
        $storagePath = str_replace('storage/', '', $path);

        if ($storagePath && Storage::disk('public')->exists($storagePath)) {
            Storage::disk('public')->delete($storagePath);
        }
    }

    public function render(): \Illuminate\View\View
    {
        return $this->view()
            ->title(__('Sākumlapa'));
    }
};
?>

<div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
    <flux:heading class="mb-4" level="1" size="xl">{{ __('Saturs') }}</flux:heading>
    <flux:separator />

    <x-content.layout :heading="__('Sākumlapa')" :subheading="__('Sākumlapas virsraksts un fona attēls.')">
        <form wire:submit="save" class="space-y-6">
            <flux:input wire:model="heading" :label="__('Virsraksts')" />
            <flux:input wire:model="cta_text" :label="__('Pogas teksts')" />

            <flux:separator />

            <div class="space-y-4">
                @if ($current_background_image && !$background_image)
                    <div>
                        <flux:label>{{ __('Pašreizējais attēls') }}</flux:label>
                        <flux:file-item
                            :heading="basename($current_background_image)"
                            :image="asset($current_background_image)"
                            class="mt-2"
                        />
                    </div>
                @endif

                <flux:file-upload wire:model="background_image" :label="$current_background_image ? __('Jauns fona attēls (neobligāts)') : __('Fona attēls')">
                    <flux:file-upload.dropzone
                        :heading="__('Ievelc failu šeit vai klikšķini, lai pievienotu')"
                        :text="__('JPG, PNG līdz 400KB')"
                    />
                </flux:file-upload>

                @if ($background_image)
                    <flux:file-item
                        :heading="$background_image->getClientOriginalName()"
                        :image="$background_image->temporaryUrl()"
                        :size="$background_image->getSize()"
                    >
                        <x-slot name="actions">
                            <flux:file-item.remove wire:click="removeImage" />
                        </x-slot>
                    </flux:file-item>
                @endif
            </div>

            <flux:button type="submit" variant="primary">{{ __('Saglabāt') }}</flux:button>
        </form>
    </x-content.layout>
</div>
