<?php

use App\Models\SiteSetting;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public string $meta_description = '';

    public $og_image;

    public string $current_og_image = '';

    public function mount(): void
    {
        $settings = SiteSetting::getGroup('seo');

        $this->meta_description = $settings->get('meta_description', '');
        $this->current_og_image = $settings->get('og_image', '');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'meta_description' => ['required', 'string', 'max:500'],
            'og_image' => ['nullable', 'image', 'max:400'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'meta_description.required' => __('Meta apraksts ir obligāts.'),
            'og_image.image' => __('Failam jābūt attēlam.'),
            'og_image.max' => __('Attēls nedrīkst pārsniegt 400KB.'),
        ];
    }

    public function removeImage(): void
    {
        $this->og_image?->delete();
        $this->og_image = null;
    }

    public function save(): void
    {
        $this->validate();

        $imagePath = $this->current_og_image;

        if ($this->og_image) {
            $this->deleteOldImage($this->current_og_image);
            $stored = $this->og_image->store('content/seo', 'public');
            $imagePath = 'storage/'.$stored;
            $this->current_og_image = $imagePath;
            $this->og_image = null;
        }

        SiteSetting::setGroup('seo', [
            'meta_description' => ['value' => $this->meta_description, 'type' => 'text'],
            'og_image' => ['value' => $imagePath, 'type' => 'image'],
        ]);

        Flux::toast(
            text: __('SEO iestatījumi saglabāti!'),
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
            ->title(__('SEO'));
    }
};
?>

<div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
    <flux:heading class="mb-4" level="1" size="xl">{{ __('Saturs') }}</flux:heading>
    <flux:separator />

    <x-content.layout :heading="__('SEO')" :subheading="__('Meklētājprogrammu optimizācijas iestatījumi.')">
        <form wire:submit="save" class="space-y-6">
            <flux:textarea wire:model="meta_description" :label="__('Meta apraksts')" rows="3" />

            <flux:separator />

            <div class="space-y-4">
                @if ($current_og_image && !$og_image)
                    <div>
                        <flux:label>{{ __('Pašreizējais OG attēls') }}</flux:label>
                        <flux:file-item
                            :heading="basename($current_og_image)"
                            :image="asset($current_og_image)"
                            class="mt-2"
                        />
                    </div>
                @endif

                <flux:file-upload wire:model="og_image" :label="$current_og_image ? __('Jauns OG attēls (neobligāts)') : __('OG attēls (sociālo tīklu priekšskatījums)')">
                    <flux:file-upload.dropzone
                        :heading="__('Ievelc failu šeit vai klikšķini, lai pievienotu')"
                        :text="__('JPG, PNG līdz 400KB, ieteicams 1200x630px')"
                    />
                </flux:file-upload>

                @if ($og_image)
                    <flux:file-item
                        :heading="$og_image->getClientOriginalName()"
                        :image="$og_image->temporaryUrl()"
                        :size="$og_image->getSize()"
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
