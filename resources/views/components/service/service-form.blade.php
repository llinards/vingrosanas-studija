@props(['heading'])

<div class="flex min-h-full flex-col items-center justify-center p-6">
    <div class="w-full max-w-2xl">
        <flux:heading level="1" size="xl" class="mb-6">{{ $heading }}</flux:heading>

        <form wire:submit="save" class="flex flex-col gap-6">
            <flux:input
                wire:model="name"
                :label="__('Nosaukums')"
                :placeholder="__('Ievadiet pakalpojuma nosaukumu')"
            />

            <div class="flex items-end gap-2">
                <div class="flex-1">
                    <flux:select wire:model="service_type_id" :label="__('Pakalpojuma veids')">
                        <flux:select.option value="">{{ __('Izvēlieties veidu') }}</flux:select.option>
                        @foreach($this->serviceTypes as $serviceType)
                            <flux:select.option :value="$serviceType->id">{{ $serviceType->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
                <flux:modal.trigger name="create-service-type">
                    <flux:button variant="outline" icon="plus"/>
                </flux:modal.trigger>
            </div>

            <x-service.new-service-type-modal/>

            <flux:select wire:model="coach_id" :label="__('Treneris')">
                <flux:select.option value="">{{ __('Izvēlieties treneri') }}</flux:select.option>
                @foreach($this->coaches as $coach)
                    <flux:select.option :value="$coach->id">{{ $coach->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input
                wire:model="price"
                :label="__('Cena (EUR)')"
                type="number"
                step="0.01"
                min="0"
                :placeholder="__('Ievadiet cenu (piem., 25.00)')"
            />

            <flux:switch wire:model="is_active" :label="__('Aktīvs')"/>

            <div class="flex items-center justify-end gap-4">
                <flux:button href="{{ route('service-list') }}" wire:navigate variant="ghost">
                    {{ __('Atcelt') }}
                </flux:button>
                <flux:button type="submit" variant="primary">
                    {{ __('Saglabāt') }}
                </flux:button>
            </div>
        </form>
    </div>
</div>
