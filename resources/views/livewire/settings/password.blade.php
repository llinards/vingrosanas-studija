<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Atjaunināt paroli')"
                       :subheading="__('Pārliecinieties, ka jūsu kontā tiek izmantota gara, nejauša parole, lai saglabātu drošību')">
        <form method="POST" wire:submit="updatePassword" class="mt-6 space-y-6">
            <flux:input
                wire:model="current_password"
                :label="__('Pašreizējā parole')"
                type="password"
                required
                autocomplete="current-password"
            />
            <flux:input
                wire:model="password"
                :label="__('Jaunā parole')"
                type="password"
                required
                autocomplete="new-password"
            />
            <flux:input
                wire:model="password_confirmation"
                :label="__('Apstipriniet paroli')"
                type="password"
                required
                autocomplete="new-password"
            />

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full">{{ __('Saglabāt') }}</flux:button>
                </div>
            </div>
        </form>
    </x-settings.layout>
</section>
