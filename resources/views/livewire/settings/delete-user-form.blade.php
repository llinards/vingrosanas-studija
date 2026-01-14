<section class="mt-10 space-y-6">
    <div class="relative mb-5">
        <flux:heading>{{ __('Dzēst kontu') }}</flux:heading>
        <flux:subheading>{{ __('Dzēst savu kontu un visus tā resursus') }}</flux:subheading>
    </div>

    <flux:modal.trigger name="confirm-user-deletion">
        <flux:button variant="danger" x-data="" x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')">
            {{ __('Dzēst kontu') }}
        </flux:button>
    </flux:modal.trigger>

    <flux:modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable class="max-w-lg">
        <form method="POST" wire:submit="deleteUser" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Vai tiešām vēlaties dzēst savu kontu?') }}</flux:heading>

                <flux:subheading>
                    {{ __('Pēc konta dzēšanas visi tā resursi un dati tiks neatgriezeniski dzēsti. Lūdzu, ievadiet savu paroli, lai apstiprinātu, ka vēlaties neatgriezeniski dzēst savu kontu.') }}
                </flux:subheading>
            </div>

            <flux:input wire:model="password" :label="__('Parole')" type="password" />

            <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Atcelt') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" type="submit">{{ __('Dzēst kontu') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
