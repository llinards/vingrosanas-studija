<?php

use App\Models\Coach;
use Flux\Flux;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $coachId;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $title = '';

    public $newImage = null;

    public ?string $existingImage = null;

    public string $bio = '';

    public bool $is_active = false;

    public function mount(Coach $coach): void
    {
        $this->coachId = $coach->id;
        $this->name = $coach->name;
        $this->email = $coach->email ?? '';
        $this->phone = $coach->phone ?? '';
        $this->title = $coach->title;
        $this->existingImage = $coach->image;
        $this->bio = $coach->bio;
        $this->is_active = $coach->is_active;
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:coaches,email,'.$this->coachId],
            'phone' => ['nullable', 'string', 'max:255', 'unique:coaches,phone,'.$this->coachId],
            'title' => ['required', 'string', 'max:255'],
            'newImage' => ['nullable', 'image', 'max:400'],
            'bio' => ['required', 'string', 'max:10000'],
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required' => __('Vārds un uzvārds ir obligāts.'),
            'name.max' => __('Vārds un uzvārds nedrīkst pārsniegt 255 rakstzīmes.'),
            'email.email' => __('Lūdzu, ievadiet derīgu e-pasta adresi.'),
            'email.unique' => __('Šis e-pasts jau ir reģistrēts.'),
            'phone.unique' => __('Šis telefona numurs jau ir reģistrēts.'),
            'title.required' => __('Amats ir obligāts.'),
            'title.max' => __('Amats nedrīkst pārsniegt 255 rakstzīmes.'),
            'newImage.image' => __('Failam jābūt attēlam.'),
            'newImage.max' => __('Attēls nedrīkst pārsniegt 400KB.'),
            'bio.required' => __('Biogrāfija ir obligāta.'),
            'bio.max' => __('Biogrāfija nedrīkst pārsniegt 10000 rakstzīmes.'),
        ];
    }

    public function removeNewImage(): void
    {
        $this->newImage?->delete();
        $this->newImage = null;
    }

    public function removeExistingImage(): void
    {
        $this->existingImage = null;
    }

    public function save(): void
    {
        $this->validate();

        if (! $this->existingImage && ! $this->newImage) {
            $this->addError('newImage', __('Attēls ir obligāts.'));

            return;
        }

        try {
            $coach = Coach::findOrFail($this->coachId);
            $imagePath = $coach->image;

            if ($this->newImage) {
                $coach->deleteImage();

                $newPath = $this->newImage->store('coaches', 'public');

                if (! $newPath) {
                    Flux::toast(
                        text: __('Neizdevās saglabāt attēlu. Lūdzu, mēģini vēlreiz.'),
                        heading: __('Kļūda!'),
                        variant: 'danger',
                    );

                    return;
                }

                $imagePath = 'storage/'.$newPath;
            } elseif (! $this->existingImage) {
                $coach->deleteImage();
                $imagePath = null;
            }

            $coach->update([
                'name' => $this->name,
                'email' => $this->email ?: null,
                'phone' => $this->phone ?: null,
                'title' => $this->title,
                'image' => $imagePath,
                'bio' => $this->bio,
                'is_active' => $this->is_active,
            ]);

            Flux::toast(
                text: __('Treneris atjaunināts!'),
                variant: 'success',
            );

            $this->redirect(route('coach-list'), navigate: true);
        } catch (\Exception $e) {
            Log::error($e);

            Flux::toast(
                text: __('Neizdevās atjaunināt treneri. Lūdzu, mēģini vēlreiz.'),
                heading: __('Kļūda!'),
                variant: 'danger',
            );
        }
    }

    public function render(): \Illuminate\View\View
    {
        return $this->view()
            ->title(__('Rediģēt treneri'));
    }
};
?>


<div class="flex min-h-full flex-col items-center justify-center p-6">
    <div class="w-full max-w-2xl">
        <flux:heading level="1" size="xl" class="mb-6">{{ __('Rediģēt treneri') }}</flux:heading>

        <form wire:submit="save" class="flex flex-col gap-6">
            <flux:input
                wire:model="name"
                :label="__('Vārds, uzvārds')"
                :placeholder="__('Ievadiet trenera vārdu un uzvārdu')"
            />

            <flux:input
                wire:model="email"
                :label="__('E-pasts')"
                type="email"
                :placeholder="__('Ievadiet e-pasta adresi')"
            />

            <flux:input
                wire:model="phone"
                :label="__('Telefons')"
                type="tel"
                :placeholder="__('Ievadiet telefona numuru')"
            />

            <flux:input
                wire:model="title"
                :label="__('Amats')"
                :placeholder="__('Ievadiet amatu (piem., Fitnesa treneris)')"
            />

            <flux:editor
                wire:model="bio"
                :label="__('Biogrāfija')"
                rows="4"
                :placeholder="__('Īss apraksts par treneri')"
            />

            @if ($existingImage && !$newImage)
                <div>
                    <flux:label>{{ __('Pašreizējais attēls') }}</flux:label>
                    <flux:file-item
                        :heading="basename($existingImage)"
                        :image="asset($existingImage)"
                        class="mt-2"
                    >
                        <x-slot name="actions">
                            <flux:file-item.remove wire:click="removeExistingImage"/>
                        </x-slot>
                    </flux:file-item>
                </div>
            @endif

            <flux:file-upload wire:model="newImage" :label="$existingImage ? __('Jauns attēls (neobligāts)') : __('Attēls')">
                <flux:file-upload.dropzone
                    :heading="__('Velciet failu šeit vai klikšķiniet, lai pārlūkotu')"
                    :text="__('JPG, PNG, GIF līdz 400KB')"
                />
            </flux:file-upload>

            @if ($newImage)
                <flux:file-item
                    :heading="$newImage->getClientOriginalName()"
                    :image="$newImage->temporaryUrl()"
                    :size="$newImage->getSize()"
                >
                    <x-slot name="actions">
                        <flux:file-item.remove wire:click="removeNewImage"/>
                    </x-slot>
                </flux:file-item>
            @endif

            <flux:error name="newImage"/>

            <flux:switch wire:model="is_active" :label="__('Aktīvs')"/>

            <div class="flex items-center justify-end gap-4">
                <flux:button href="{{ route('coach-list') }}" wire:navigate variant="ghost">
                    {{ __('Atcelt') }}
                </flux:button>
                <flux:button type="submit" variant="primary">
                    {{ __('Saglabāt') }}
                </flux:button>
            </div>
        </form>
    </div>
</div>
