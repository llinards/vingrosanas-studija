<x-layouts.app :title="__('Pakalpojumi')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <div class="relative w-full">
            <flux:heading class="mb-4" level="1" size="xl">Pakalpojumi</flux:heading>
            <flux:button href="{{ route('admin.services.create') }}" wire:navigate class="mb-4">Pievienot jaunu pakalpojumu
            </flux:button>
            <flux:separator/>
        </div>
        <livewire:service.service-list/>
    </div>
</x-layouts.app>
