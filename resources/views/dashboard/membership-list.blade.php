<x-layouts.app :title="__('Abonementi')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <div class="relative w-full">
            <flux:heading class="mb-4" level="1" size="xl">{{ __('Abonementi') }}</flux:heading>
            <flux:separator/>
        </div>
        <livewire:membership.membership-list/>
    </div>
</x-layouts.app>
