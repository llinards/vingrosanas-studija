@props(['heading'])

<div class="flex min-h-full flex-col items-center justify-center p-6">
    <div class="w-full max-w-2xl">
        <flux:heading level="1" size="xl" class="mb-6">{{ $heading }}</flux:heading>

        <form wire:submit="save" class="flex flex-col gap-6">
            <div class="flex flex-col gap-6 sm:flex-row ">
                <div class="sm:flex-1">
                    <flux:input
                        wire:model="name"
                        :label="__('Vārds, uzvārds')"
                        :placeholder="__('Ievadi trenera vārdu un uzvārdu')"
                    />
                </div>

                <div class="sm:flex-1">
                    <flux:input
                        wire:model="title"
                        :label="__('Amats')"
                        :placeholder="__('Ievadi amatu (piem., Fitnesa treneris)')"
                    />
                </div>
            </div>

            <div class="flex flex-col gap-6 sm:flex-row ">
                <div class="sm:flex-1">
                    <flux:input
                        wire:model="email"
                        :label="__('E-pasts')"
                        type="email"
                        :placeholder="__('Ievadi e-pasta adresi')"
                    />
                </div>

                <div class="sm:flex-1">
                    <flux:input
                        wire:model="phone"
                        :label="__('Telefons')"
                        type="tel"
                        :placeholder="__('Ievadi telefona numuru')"
                    />
                </div>
            </div>
            
            <flux:editor
                wire:model="bio"
                :label="__('Biogrāfija')"
                rows="4"
                :placeholder="__('Īss apraksts par treneri')"
            />

            {{ $slot }}

            <flux:switch wire:model="is_active" :label="__('Aktīvs')"/>

            <div class="flex items-center justify-end gap-4">
                <flux:button href="{{ route('coach-list') }}" wire:navigate variant="ghost">
                    {{ __('Atcelt') }}
                </flux:button>
                <flux:button type="submit" variant="primary">
                    {{ __('Saglabāt') }}
                </flux:button>
            </div>
        </form>
    </div>
</div>
