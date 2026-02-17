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
            'email' => ['required', 'email', 'max:255', 'unique:coaches,email,'.$this->coachId],
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
            'email.required' => __('E-pasts ir obligāts.'),
            'email.email' => __('Lūdzu, ievadi derīgu e-pasta adresi.'),
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

            $this->redirect(route('admin.coaches.index'), navigate: true);
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


<x-coach.coach-form :heading="__('Rediģēt treneri')">
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
            :heading="__('Ievelc failu šeit vai klikšķini, lai pievienotu')"
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
</x-coach.coach-form>
