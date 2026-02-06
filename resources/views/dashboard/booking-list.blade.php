<x-layouts.app :title="__('Rezervācijas')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <div class="relative w-full">
            <flux:heading class="mb-4" level="1" size="xl">{{ __('Rezervācijas') }}</flux:heading>
            <flux:button href="{{ route('booking-create') }}" wire:navigate class="mb-4">{{ __('Pievienot jaunu rezervāciju') }}
            </flux:button>
            <flux:separator/>
        </div>
        <livewire:booking.booking-list/>
    </div>
</x-layouts.app>
