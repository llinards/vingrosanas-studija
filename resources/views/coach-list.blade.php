<x-layouts.app :title="__('Treneri')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <div class="relative w-full">
            <flux:heading class="mb-4" level="1" size="xl">Treneri</flux:heading>
            <flux:button class="mb-4">Pievienot jaunu treneri</flux:button>
            <flux:separator/>
        </div>
        <livewire:coach-list/>
    </div>
</x-layouts.app>

