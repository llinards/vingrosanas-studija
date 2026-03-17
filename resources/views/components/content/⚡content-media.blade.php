<?php

use App\Models\SiteSetting;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public $video_thumbnail;

    public $video_file;

    public string $current_video_thumbnail = '';

    public string $current_video_file = '';

    /** @var array<int, string> */
    public array $current_gallery_images = [];

    /** @var array<int, mixed> */
    public array $new_gallery_images = [];

    public function mount(): void
    {
        $settings = SiteSetting::getGroup('media');

        $this->current_video_thumbnail = $settings->get('video_thumbnail', '');
        $this->current_video_file = $settings->get('video_file', '');

        $images = $settings->get('gallery_images', '[]');
        $this->current_gallery_images = json_decode($images, true) ?: [];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'video_thumbnail' => ['nullable', 'image', 'max:400'],
            'video_file' => ['nullable', 'file', 'mimetypes:video/mp4,video/quicktime', 'max:102400'],
            'new_gallery_images.*' => ['nullable', 'image', 'max:400'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'video_thumbnail.image' => __('Failam jābūt attēlam.'),
            'video_thumbnail.max' => __('Attēls nedrīkst pārsniegt 400KB.'),
            'video_file.mimetypes' => __('Failam jābūt MP4 vai MOV video.'),
            'video_file.max' => __('Video nedrīkst pārsniegt 100MB.'),
            'new_gallery_images.*.image' => __('Failam jābūt attēlam.'),
            'new_gallery_images.*.max' => __('Attēls nedrīkst pārsniegt 400KB.'),
        ];
    }

    public function removeVideoThumbnail(): void
    {
        $this->video_thumbnail?->delete();
        $this->video_thumbnail = null;
    }

    public function removeVideoFile(): void
    {
        $this->video_file?->delete();
        $this->video_file = null;
    }

    public function removeExistingGalleryImage(int $index): void
    {
        if (isset($this->current_gallery_images[$index])) {
            $this->deleteOldFile($this->current_gallery_images[$index]);
            unset($this->current_gallery_images[$index]);
            $this->current_gallery_images = array_values($this->current_gallery_images);
        }
    }

    public function removeNewGalleryImage(int $index): void
    {
        if (isset($this->new_gallery_images[$index])) {
            $this->new_gallery_images[$index]->delete();
            unset($this->new_gallery_images[$index]);
            $this->new_gallery_images = array_values($this->new_gallery_images);
        }
    }

    public function save(): void
    {
        $this->validate();

        $thumbnailPath = $this->current_video_thumbnail;
        $videoPath = $this->current_video_file;

        if ($this->video_thumbnail) {
            $this->deleteOldFile($this->current_video_thumbnail);
            $stored = $this->video_thumbnail->store('content/media', 'public');
            $thumbnailPath = 'storage/'.$stored;
            $this->current_video_thumbnail = $thumbnailPath;
            $this->video_thumbnail = null;
        }

        if ($this->video_file) {
            $this->deleteOldFile($this->current_video_file);
            $stored = $this->video_file->store('content/media', 'public');
            $videoPath = 'storage/'.$stored;
            $this->current_video_file = $videoPath;
            $this->video_file = null;
        }

        $galleryImages = $this->current_gallery_images;

        foreach ($this->new_gallery_images as $newImage) {
            if ($newImage) {
                $stored = $newImage->store('content/media', 'public');
                $galleryImages[] = 'storage/'.$stored;
            }
        }

        $this->current_gallery_images = $galleryImages;
        $this->new_gallery_images = [];

        SiteSetting::setGroup('media', [
            'video_thumbnail' => ['value' => $thumbnailPath, 'type' => 'image'],
            'video_file' => ['value' => $videoPath, 'type' => 'string'],
            'gallery_images' => ['value' => json_encode($galleryImages, JSON_UNESCAPED_UNICODE), 'type' => 'json'],
        ]);

        Flux::toast(
            text: __('Mediju sadaļa saglabāta!'),
            variant: 'success',
        );
    }

    private function deleteOldFile(string $path): void
    {
        $storagePath = str_replace('storage/', '', $path);

        if ($storagePath && Storage::disk('public')->exists($storagePath)) {
            Storage::disk('public')->delete($storagePath);
        }
    }

    public function render(): \Illuminate\View\View
    {
        return $this->view()
            ->title(__('Mediji'));
    }
};
?>

<div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
    <flux:heading class="mb-4" level="1" size="xl">{{ __('Saturs') }}</flux:heading>
    <flux:separator />

    <x-content.layout :heading="__('Mediji')" :subheading="__('Video un galerijas attēli.')">
        <form wire:submit="save" class="space-y-6">
            <flux:heading level="4">{{ __('Video') }}</flux:heading>

            @if ($current_video_thumbnail && !$video_thumbnail)
                <div>
                    <flux:label>{{ __('Pašreizējais sīktēls') }}</flux:label>
                    <flux:file-item
                        :heading="basename($current_video_thumbnail)"
                        :image="asset($current_video_thumbnail)"
                        class="mt-2"
                    />
                </div>
            @endif

            <flux:file-upload wire:model="video_thumbnail" :label="$current_video_thumbnail ? __('Jauns video sīktēls (neobligāts)') : __('Video sīktēls')">
                <flux:file-upload.dropzone
                    :heading="__('Ievelc failu šeit vai klikšķini, lai pievienotu')"
                    :text="__('JPG, PNG līdz 400KB')"
                />
            </flux:file-upload>

            @if ($video_thumbnail)
                <flux:file-item
                    :heading="$video_thumbnail->getClientOriginalName()"
                    :image="$video_thumbnail->temporaryUrl()"
                    :size="$video_thumbnail->getSize()"
                >
                    <x-slot name="actions">
                        <flux:file-item.remove wire:click="removeVideoThumbnail" />
                    </x-slot>
                </flux:file-item>
            @endif

            @if ($current_video_file && !$video_file)
                <div>
                    <flux:label>{{ __('Pašreizējais video') }}</flux:label>
                    <flux:file-item
                        :heading="basename($current_video_file)"
                        class="mt-2"
                    />
                </div>
            @endif

            <flux:file-upload wire:model="video_file" :label="$current_video_file ? __('Jauns video fails (neobligāts)') : __('Video fails')">
                <flux:file-upload.dropzone
                    :heading="__('Ievelc failu šeit vai klikšķini, lai pievienotu')"
                    :text="__('MP4, MOV līdz 100MB')"
                />
            </flux:file-upload>

            @if ($video_file)
                <flux:file-item
                    :heading="$video_file->getClientOriginalName()"
                    :size="$video_file->getSize()"
                >
                    <x-slot name="actions">
                        <flux:file-item.remove wire:click="removeVideoFile" />
                    </x-slot>
                </flux:file-item>
            @endif

            <flux:separator />

            <div class="space-y-4">
                <flux:heading level="4">{{ __('Galerija') }}</flux:heading>

                @if (count($current_gallery_images))
                    <div>
                        <flux:label>{{ __('Pašreizējie attēli') }}</flux:label>
                        <div class="mt-2 space-y-2">
                            @foreach ($current_gallery_images as $index => $image)
                                <flux:file-item
                                    :heading="basename($image)"
                                    :image="asset($image)"
                                >
                                    <x-slot name="actions">
                                        <flux:file-item.remove wire:click="removeExistingGalleryImage({{ $index }})" />
                                    </x-slot>
                                </flux:file-item>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div>
                    <flux:file-upload wire:model="new_gallery_images" :label="__('Pievienot attēlus')" multiple>
                        <flux:file-upload.dropzone
                            :heading="__('Ievelc failus šeit vai klikšķini, lai pievienotu')"
                            :text="__('JPG, PNG līdz 400KB')"
                        />
                    </flux:file-upload>

                    @if (count($new_gallery_images))
                        <div class="mt-2 space-y-2">
                            @foreach ($new_gallery_images as $index => $newImage)
                                <flux:file-item
                                    :heading="$newImage->getClientOriginalName()"
                                    :image="$newImage->temporaryUrl()"
                                    :size="$newImage->getSize()"
                                >
                                    <x-slot name="actions">
                                        <flux:file-item.remove wire:click="removeNewGalleryImage({{ $index }})" />
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
