<?php

use App\Mail\ContactFormSubmission;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;
use Spatie\Honeypot\Http\Livewire\Concerns\HoneypotData;
use Spatie\Honeypot\Http\Livewire\Concerns\UsesSpamProtection;

new class extends Component
{
    use UsesSpamProtection;

    public HoneypotData $extraFields;

    public string $name = '';

    public string $surname = '';

    public string $email = '';

    public string $message = '';

    public bool $terms = false;

    public function mount(): void
    {
        $this->extraFields = new HoneypotData();
    }

    public function submit(): void
    {
        $this->protectAgainstSpam();

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'surname' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'terms' => ['accepted'],
        ], [
            'name.required' => __('Vārds ir obligāts.'),
            'name.max' => __('Vārds nedrīkst pārsniegt 255 rakstzīmes.'),
            'surname.required' => __('Uzvārds ir obligāts.'),
            'surname.max' => __('Uzvārds nedrīkst pārsniegt 255 rakstzīmes.'),
            'email.required' => __('E-pasts ir obligāts.'),
            'email.email' => __('E-pastam jābūt derīgai e-pasta adresei.'),
            'email.max' => __('E-pasts nedrīkst pārsniegt 255 rakstzīmes.'),
            'message.required' => __('Jautājums ir obligāts.'),
            'message.max' => __('Jautājums nedrīkst pārsniegt 5000 rakstzīmes.'),
            'terms.accepted' => __('Jums jāpiekrīt personas datu apstrādei.'),
        ]);

        Mail::to(SiteSetting::getValue('contact', 'email') ?? config('mail.contact_email'))->send(
            new ContactFormSubmission(
                name: $this->name,
                surname: $this->surname,
                email: $this->email,
                contactMessage: $this->message,
            )
        );

        $this->reset();

        Flux::modal('confirm')->show();
    }
};
?>

<div>
    <form wire:submit="submit" class="flex flex-col items-center justify-center w-full">
        <div
            class="w-full border-6 border-blue rounded-4xl bg-beige shadow-2xl p-8 lg:p-12 space-y-6 mb-6 md:mb-12">
            <x-honeypot livewire-model="extraFields" />
            <flux:input wire:model="name" type="text" :label="__('Vārds*')" />
            <flux:input wire:model="surname" type="text" :label="__('Uzvārds*')" />
            <flux:input wire:model="email" type="email" :label="__('E-pasts*')" />
            <flux:textarea wire:model="message" rows="4" :label="__('Jautājums*')" resize="none" />
            <flux:field variant="inline">
                <flux:checkbox wire:model="terms" />
                <flux:label>{{ __('Es piekrītu manu personas datu apstrādei saziņas nolūkos saskaņā ar Privātuma
                    politiku.*') }}</flux:label>
                <flux:error name="terms" />
            </flux:field>
        </div>
        <flux:button type="submit" class="button large primary self-center">{{ __('Nosūtīt') }}</flux:button>
    </form>

    <flux:modal id="confirmModal"
        class="p-6 md:p-12 lg:p-24 flex flex-col items-center justify-center text-center space-y-6" name="confirm">
        <flux:icon.check class="check mt-12 md:mt-0" />
        <flux:heading level="2">{{ __('Tava ziņa ir veiksmīgi nosūtīta!') }}</flux:heading>
        <flux:text>{{ __('Mēs drīz ar tevi sazināsimies — paldies, ka esi ceļā uz kustību kopā ar mums!') }}
        </flux:text>
    </flux:modal>
</div>
