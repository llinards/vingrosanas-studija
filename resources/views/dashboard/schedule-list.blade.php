<x-layouts.app :title="__('Grafiks')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <div class="relative w-full">
            <flux:heading class="mb-4" level="1" size="xl">{{ __('Grafiks') }}</flux:heading>
            <flux:button href="{{ route('schedule-create') }}" wire:navigate class="mb-4">{{ __('Pievienot jaunu grafiku') }}
            </flux:button>
            <flux:separator/>
        </div>
        <livewire:schedule.schedule-list/>
    </div>
</x-layouts.app>
