<?php

use App\Models\Coach;
use Livewire\Component;
use Livewire\WithFileUploads;
use Flux\Flux;
use Illuminate\Support\Facades\Log;

new class extends Component {
    use WithFileUploads;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $title = '';

    public $image;

    public string $bio = '';

    public bool $is_active = false;

    protected function rules(): array
    {
        return [
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:coaches,email'],
            'phone' => ['nullable', 'string', 'max:255', 'unique:coaches,phone'],
            'title' => ['required', 'string', 'max:255'],
            'image' => ['required', 'image', 'max:400'],
            'bio'   => ['required', 'string', 'max:10000'],
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required'  => __('Vārds un uzvārds ir obligāts.'),
            'name.max'       => __('Vārds un uzvārds nedrīkst pārsniegt 255 rakstzīmes.'),
            'email.email'    => __('Lūdzu, ievadiet derīgu e-pasta adresi.'),
            'email.unique'   => __('Šis e-pasts jau ir reģistrēts.'),
            'phone.unique'   => __('Šis telefona numurs jau ir reģistrēts.'),
            'title.required' => __('Amats ir obligāts.'),
            'title.max'      => __('Amats nedrīkst pārsniegt 255 rakstzīmes.'),
            'image.required' => __('Attēls ir obligāts.'),
            'image.image'    => __('Failam jābūt attēlam.'),
            'image.max'      => __('Attēls nedrīkst pārsniegt 400KB.'),
            'bio.required'   => __('Biogrāfija ir obligāta.'),
            'bio.max'        => __('Biogrāfija nedrīkst pārsniegt 1000 rakstzīmes.'),
        ];
    }

    public function removeImage(): void
    {
        $this->image?->delete();
        $this->image = null;
    }

    public function save(): void
    {
        $this->validate();

        try {
            $imagePath = $this->image->store('coaches', 'public');

            if ( ! $imagePath) {
                Flux::toast(
                    text: __('Neizdevās izveidot treneri. Lūdzu, mēģini vēlreiz.'),
                    heading: __('Kļūda!'),
                    variant: 'danger',
                );

                return;
            }

            Coach::create([
                'name'      => $this->name,
                'email'     => $this->email ?: null,
                'phone'     => $this->phone ?: null,
                'title'     => $this->title,
                'image'     => 'storage/'.$imagePath,
                'bio'       => $this->bio,
                'is_active' => $this->is_active,
            ]);

            Flux::toast(
                text: __('Treneris izveidots!'),
                variant: 'success',
            );

            $this->redirect(route('coach-list'), navigate: true);
        } catch (\Exception $e) {
            Log::error($e);

            Flux::toast(
                text: __('Neizdevās izveidot treneri. Lūdzu, mēģini vēlreiz.'),
                heading: __('Kļūda!'),
                variant: 'danger',
            );
        }
    }

    public function render(): \Illuminate\View\View
    {
        return $this->view()
                    ->title('Pievienot jaunu treneri');
    }
};
?>


<x-coach.coach-form :heading="__('Pievienot jaunu treneri')">
    <flux:file-upload wire:model="image" :label="__('Attēls')">
        <flux:file-upload.dropzone
            :heading="__('Velciet failu šeit vai klikšķiniet, lai pārlūkotu')"
            :text="__('JPG, PNG, GIF līdz 400KB')"
        />
    </flux:file-upload>

    @if ($image)
        <flux:file-item
            :heading="$image->getClientOriginalName()"
            :image="$image->temporaryUrl()"
            :size="$image->getSize()"
        >
            <x-slot name="actions">
                <flux:file-item.remove wire:click="removeImage"/>
            </x-slot>
        </flux:file-item>
    @endif
</x-coach.coach-form>
