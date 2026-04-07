<x-layouts.app :title="__('Brīvdienas')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <div class="relative w-full">
            <flux:heading class="mb-4" level="1" size="xl">{{ __('Brīvdienas') }}</flux:heading>
            <flux:separator/>
        </div>
        <livewire:closure.closure-list/>
    </div>
</x-layouts.app>
