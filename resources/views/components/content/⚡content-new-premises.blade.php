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

    public string $description = '';

    /** @var array<int, string> */
    public array $bullet_points = [];

    /** @var array<int, string> */
    public array $current_images = [];

    /** @var array<int, mixed> */
    public array $new_images = [];

    public function mount(): void
    {
        $settings = SiteSetting::getGroup('new_premises');

        $this->heading = $settings->get('heading', '');
        $this->description = $settings->get('description', '');

        $bulletPoints = $settings->get('bullet_points', '[]');
        $this->bullet_points = json_decode($bulletPoints, true) ?: [];

        $images = $settings->get('images', '[]');
        $this->current_images = json_decode($images, true) ?: [];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'heading' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:1000'],
            'bullet_points' => ['required', 'array', 'min:1'],
            'bullet_points.*' => ['required', 'string', 'max:255'],
            'new_images.*' => ['nullable', 'image', 'max:400'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'heading.required' => __('Virsraksts ir obligāts.'),
            'description.required' => __('Apraksts ir obligāts.'),
            'bullet_points.required' => __('Jābūt vismaz vienam punktam.'),
            'bullet_points.*.required' => __('Punkts nedrīkst būt tukšs.'),
            'new_images.*.image' => __('Failam jābūt attēlam.'),
            'new_images.*.max' => __('Attēls nedrīkst pārsniegt 400KB.'),
        ];
    }

    public function addBulletPoint(): void
    {
        $this->bullet_points[] = '';
    }

    public function removeBulletPoint(int $index): void
    {
        unset($this->bullet_points[$index]);
        $this->bullet_points = array_values($this->bullet_points);
    }

    public function removeExistingImage(int $index): void
    {
        if (isset($this->current_images[$index])) {
            $this->deleteOldImage($this->current_images[$index]);
            unset($this->current_images[$index]);
            $this->current_images = array_values($this->current_images);
        }
    }

    public function removeNewImage(int $index): void
    {
        if (isset($this->new_images[$index])) {
            $this->new_images[$index]->delete();
            unset($this->new_images[$index]);
            $this->new_images = array_values($this->new_images);
        }
    }

    public function save(): void
    {
        $this->validate();

        $images = $this->current_images;

        foreach ($this->new_images as $newImage) {
            if ($newImage) {
                $stored = $newImage->store('content/new-premises', 'public');
                $images[] = 'storage/'.$stored;
            }
        }

        $this->current_images = $images;
        $this->new_images = [];

        SiteSetting::setGroup('new_premises', [
            'heading' => ['value' => $this->heading, 'type' => 'string'],
            'description' => ['value' => $this->description, 'type' => 'text'],
            'bullet_points' => ['value' => json_encode($this->bullet_points, JSON_UNESCAPED_UNICODE), 'type' => 'json'],
            'images' => ['value' => json_encode($images, JSON_UNESCAPED_UNICODE), 'type' => 'json'],
        ]);

        Flux::toast(
            text: __('Jaunās telpas sadaļa saglabāta!'),
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
            ->title(__('Jaunās telpas'));
    }
};
?>

<div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
    <flux:heading class="mb-4" level="1" size="xl">{{ __('Saturs') }}</flux:heading>
    <flux:separator />

    <x-content.layout :heading="__('Jaunās telpas')" :subheading="__('Sadaļa par jaunajām telpām un iespējām.')">
        <form wire:submit="save" class="space-y-6">
            <flux:input wire:model="heading" :label="__('Virsraksts')" />
            <flux:textarea wire:model="description" :label="__('Apraksts')" rows="3" />

            <flux:field>
                <flux:label>{{ __('Punkti') }}</flux:label>
                <div class="space-y-2">
                    @foreach ($bullet_points as $index => $point)
                        <div class="flex items-center gap-2">
                            <flux:input wire:model="bullet_points.{{ $index }}" class="flex-1" />
                            <flux:button variant="ghost" size="sm" wire:click.prevent="removeBulletPoint({{ $index }})">
                                <flux:icon.x-mark variant="mini" />
                            </flux:button>
                        </div>
                    @endforeach
                </div>
                <flux:button variant="ghost" size="sm" wire:click.prevent="addBulletPoint" class="mt-2">
                    + {{ __('Pievienot punktu') }}
                </flux:button>
            </flux:field>

            <flux:separator />

            <div class="space-y-4">
                @if (count($current_images))
                    <div>
                        <flux:label>{{ __('Pašreizējie attēli') }}</flux:label>
                        <div class="mt-2 space-y-2">
                            @foreach ($current_images as $index => $image)
                                <flux:file-item
                                    :heading="basename($image)"
                                    :image="asset($image)"
                                >
                                    <x-slot name="actions">
                                        <flux:file-item.remove wire:click="removeExistingImage({{ $index }})" />
                                    </x-slot>
                                </flux:file-item>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div>
                    <flux:file-upload wire:model="new_images" :label="__('Pievienot attēlus')" multiple>
                        <flux:file-upload.dropzone
                            :heading="__('Ievelc failus šeit vai klikšķini, lai pievienotu')"
                            :text="__('JPG, PNG līdz 400KB')"
                        />
                    </flux:file-upload>

                    @if (count($new_images))
                        <div class="mt-2 space-y-2">
                            @foreach ($new_images as $index => $newImage)
                                <flux:file-item
                                    :heading="$newImage->getClientOriginalName()"
                                    :image="$newImage->temporaryUrl()"
                                    :size="$newImage->getSize()"
                                >
                                    <x-slot name="actions">
                                        <flux:file-item.remove wire:click="removeNewImage({{ $index }})" />
                                    </x-slot>
                                </flux:file-item>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <flux:button type="submit" variant="primary">{{ __('Saglabāt') }}</flux:button>
        </form>
    </x-content.layout>
</div>
