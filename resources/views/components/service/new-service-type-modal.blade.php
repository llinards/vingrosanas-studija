<flux:modal name="create-service-type" class="p-5 md:w-96">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Jauns pakalpojuma veids') }}</flux:heading>
        </div>

        <flux:input wire:model="newServiceTypeName" :label="__('Nosaukums')"
                    :placeholder="__('Ievadi nosaukumu')"/>

        <div class="flex">
            <flux:spacer/>
            <flux:button wire:click="saveServiceType" variant="primary">{{ __('Saglabāt') }}</flux:button>
        </div>
    </div>
</flux:modal>
