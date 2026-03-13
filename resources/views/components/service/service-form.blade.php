@props(['heading'])

<div class="flex justify-center">
    <div class="w-full max-w-2xl">
        <flux:heading level="1" size="xl" class="mb-6">{{ $heading }}</flux:heading>

        <form wire:submit="save" class="flex flex-col gap-6">
            <flux:switch wire:model.live="is_membership" :label="__('Abonements')"
                         :description="__('Ja ieslēgts, šis pakalpojums ir abonements ar noteiktu nodarbību skaitu.')"/>

            <div class="flex flex-col gap-6 sm:flex-row ">
                <div class="sm:flex-1">

                    <flux:input
                        wire:model="name"
                        :label="__('Nosaukums')"
                        :placeholder="__('Ievadi pakalpojuma nosaukumu')"
                    />
                </div>
                <div class="sm:flex-1">
                    <flux:input
                        wire:model="price"
                        :label="__('Cena 1 personai (EUR)')"
                        type="number"
                        step="0.01"
                        min="0"
                        :placeholder="__('Ievadi cenu (piem., 25.00)')"
                    />
                </div>
            </div>

            @if(!$this->is_membership)
                <flux:select wire:model="coach_id" :label="__('Treneris')">
                    <flux:select.option value="">{{ __('Izvēlieties treneri') }}</flux:select.option>
                    @foreach($this->coaches as $coach)
                        <flux:select.option :value="$coach->id">{{ $coach->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif
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

            <flux:separator/>

            @if($this->is_membership)
                <flux:input
                    wire:model="sessions_count"
                    :label="__('Nodarbību skaits')"
                    type="number"
                    min="1"
                    :placeholder="__('Piem., 4 vai 9')"
                />
            @else
                {{-- Price Tiers Section --}}
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <flux:heading size="sm">{{ __('Cenas vairākiem dalībniekiem') }}</flux:heading>
                        <flux:button wire:click.prevent="addPriceTier" variant="ghost" size="sm" icon="plus">
                            {{ __('Pievienot') }}
                        </flux:button>
                    </div>

                    <flux:text size="sm" class="text-zinc-500">
                        {{ __('Pievieno cenas, ja vēlies atļaut rezervāciju vairākām personām vienlaikus.') }}
                    </flux:text>

                    @foreach($this->priceTiers as $index => $tier)
                        <div class="flex items-end gap-4" wire:key="tier-{{ $index }}">
                            <div class="w-32">
                                <flux:input
                                    wire:model="priceTiers.{{ $index }}.participant_count"
                                    :label="$index === 0 ? __('Dalībnieki') : null"
                                    type="number"
                                    min="2"
                                    :placeholder="__('Skaits')"
                                />
                            </div>
                            <div class="flex-1">
                                <flux:input
                                    wire:model="priceTiers.{{ $index }}.price"
                                    :label="$index === 0 ? __('Cena kopā (EUR)') : null"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    :placeholder="__('Cena')"
                                />
                            </div>
                            <flux:button wire:click.prevent="removePriceTier({{ $index }})" variant="ghost" icon="trash"
                                         class="text-red-500"/>
                        </div>
                    @endforeach

                    @if(count($this->priceTiers) === 0)
                        <flux:text size="sm" class="text-zinc-400 italic">
                            {{ __('Nav pievienotu papildu cenu. Rezervācija būs pieejama tikai 1 personai.') }}
                        </flux:text>
                    @endif
                </div>

                <flux:separator/>

                <flux:switch wire:model="is_exclusive" :label="__('Individuāls (viena rezervācija aizņem visu laiku)')"
                             :description="__('Ja ieslēgts, pēc vienas rezervācijas laiks vairs nav pieejams citiem.')"/>

                <flux:switch wire:model="is_membership_eligible" :label="__('Pieejams abonementam')"
                             :description="__('Ja ieslēgts, šis pakalpojums var tikt iekļauts abonementā.')"/>
            @endif

            <flux:separator/>

            <flux:switch wire:model="is_active" :label="__('Aktīvs')"/>

            <div class="flex items-center justify-end gap-4">
                <flux:button href="{{ route('admin.services.index') }}" wire:navigate variant="ghost">
                    {{ __('Atcelt') }}
                </flux:button>
                <flux:button type="submit" variant="primary">
                    {{ __('Saglabāt') }}
                </flux:button>
            </div>
        </form>
    </div>
</div>
