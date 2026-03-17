<?php

use App\Models\SiteSetting;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public string $text = '';

    public string $attribution_name = '';

    public string $attribution_title = '';

    public $image_left;

    public $image_right;

    public string $current_image_left = '';

    public string $current_image_right = '';

    public function mount(): void
    {
        $settings = SiteSetting::getGroup('quote');

        $this->text = $settings->get('text', '');
        $this->attribution_name = $settings->get('attribution_name', '');
        $this->attribution_title = $settings->get('attribution_title', '');
        $this->current_image_left = $settings->get('image_left', '');
        $this->current_image_right = $settings->get('image_right', '');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'text' => ['required', 'string', 'max:500'],
            'attribution_name' => ['required', 'string', 'max:255'],
            'attribution_title' => ['required', 'string', 'max:255'],
            'image_left' => ['nullable', 'image', 'max:400'],
            'image_right' => ['nullable', 'image', 'max:400'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'text.required' => __('Citāta teksts ir obligāts.'),
            'attribution_name.required' => __('Autora vārds ir obligāts.'),
            'attribution_title.required' => __('Autora amats ir obligāts.'),
            'image_left.image' => __('Failam jābūt attēlam.'),
            'image_left.max' => __('Attēls nedrīkst pārsniegt 400KB.'),
            'image_right.image' => __('Failam jābūt attēlam.'),
            'image_right.max' => __('Attēls nedrīkst pārsniegt 400KB.'),
        ];
    }

    public function removeImageLeft(): void
    {
        $this->image_left?->delete();
        $this->image_left = null;
    }

    public function removeImageRight(): void
    {
        $this->image_right?->delete();
        $this->image_right = null;
    }

    public function save(): void
    {
        $this->validate();

        $leftPath = $this->current_image_left;
        $rightPath = $this->current_image_right;

        if ($this->image_left) {
            $this->deleteOldImage($this->current_image_left);
            $stored = $this->image_left->store('content/quote', 'public');
            $leftPath = 'storage/'.$stored;
            $this->current_image_left = $leftPath;
            $this->image_left = null;
        }

        if ($this->image_right) {
            $this->deleteOldImage($this->current_image_right);
            $stored = $this->image_right->store('content/quote', 'public');
            $rightPath = 'storage/'.$stored;
            $this->current_image_right = $rightPath;
            $this->image_right = null;
        }

        SiteSetting::setGroup('quote', [
            'text' => ['value' => $this->text, 'type' => 'text'],
            'attribution_name' => ['value' => $this->attribution_name, 'type' => 'string'],
            'attribution_title' => ['value' => $this->attribution_title, 'type' => 'string'],
            'image_left' => ['value' => $leftPath, 'type' => 'image'],
            'image_right' => ['value' => $rightPath, 'type' => 'image'],
        ]);

        Flux::toast(
            text: __('Citāta sadaļa saglabāta!'),
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
            ->title(__('Citāts'));
    }
};
?>

<div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
    <flux:heading class="mb-4" level="1" size="xl">{{ __('Saturs') }}</flux:heading>
    <flux:separator />

    <x-content.layout :heading="__('Citāts')" :subheading="__('Citāta sadaļa ar attēliem.')">
        <form wire:submit="save" class="space-y-6">
            <flux:textarea wire:model="text" :label="__('Citāta teksts')" rows="3" />
            <flux:input wire:model="attribution_name" :label="__('Autora vārds')" />
            <flux:input wire:model="attribution_title" :label="__('Autora amats')" />

            <flux:separator />

            <div class="grid gap-6 sm:grid-cols-2">
                @foreach (['left' => __('Kreisais attēls'), 'right' => __('Labais attēls')] as $side => $label)
                    <div class="space-y-4">
                        @if (${'current_image_'.$side} && !${'image_'.$side})
                            <div>
                                <flux:label>{{ __('Pašreizējais') }} — {{ $label }}</flux:label>
                                <flux:file-item
                                    :heading="basename(${'current_image_'.$side})"
                                    :image="asset(${'current_image_'.$side})"
                                    class="mt-2"
                                />
                            </div>
                        @endif

                        <flux:file-upload wire:model="image_{{ $side }}" :label="${'current_image_'.$side} ? __('Jauns') . ' — ' . $label . ' ' . __('(neobligāts)') : $label">
                            <flux:file-upload.dropzone
                                :heading="__('Ievelc failu šeit vai klikšķini, lai pievienotu')"
                                :text="__('JPG, PNG līdz 400KB')"
                            />
                        </flux:file-upload>

                        @if (${'image_'.$side})
                            <flux:file-item
                                :heading="${'image_'.$side}->getClientOriginalName()"
                                :image="${'image_'.$side}->temporaryUrl()"
                                :size="${'image_'.$side}->getSize()"
                            >
                                <x-slot name="actions">
                                    <flux:file-item.remove wire:click="removeImage{{ ucfirst($side) }}" />
                                </x-slot>
                            </flux:file-item>
                        @endif
                    </div>
                @endforeach
            </div>

            <flux:button type="submit" variant="primary">{{ __('Saglabāt') }}</flux:button>
        </form>
    </x-content.layout>
</div>
