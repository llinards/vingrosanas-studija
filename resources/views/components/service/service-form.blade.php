@props(['heading', 'editing' => false])

<div class="flex justify-center">
    <div class="w-full max-w-2xl">
        <flux:heading level="1" size="xl" class="mb-6">{{ $heading }}</flux:heading>

        <form wire:submit="save" class="flex flex-col gap-6">
            @if(!$editing)
                <flux:switch wire:model.live="is_membership" :label="__('Abonements')"
                             :description="__('Ja ieslēgts, šis pakalpojums ir abonements ar noteiktu nodarbību skaitu.')"/>
            @endif

            <flux:input
                wire:model="name"
                :label="__('Nosaukums')"
                :placeholder="__('Ievadi pakalpojuma nosaukumu')"
            />

            <flux:editor wire:model="description"
                         :label="__('Apraksts')"
                         toolbar="bold italic | bullet ordered"
                         :placeholder="__('Ievadi pakalpojuma aprakstu')"/>

            @if(!$this->is_membership)
                <flux:select wire:model="coach_id" :label="__('Treneris')">
                    <flux:select.option value="">{{ __('Izvēlieties treneri') }}</flux:select.option>
                    @foreach($this->coaches as $coach)
                        <flux:select.option :value="$coach->id">{{ $coach->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif
            <flux:pillbox wire:model="service_type_id" variant="combobox" :label="__('Pakalpojuma veids')"
                          :placeholder="__('Meklē vai izveido jaunu...')">
                <x-slot name="input">
                    <flux:pillbox.input wire:model="serviceTypeSearch" :placeholder="__('Meklē vai izveido jaunu...')"/>
                </x-slot>

                @foreach($this->serviceTypes as $serviceType)
                    <flux:pillbox.option :value="$serviceType->id">
                        <div class="flex items-center justify-between w-full">
                            <span>{{ $serviceType->name }}</span>
                            @if(!$serviceType->services()->exists())
                                <button type="button" wire:click.stop="deleteServiceType({{ $serviceType->id }})"
                                        class="ml-2 text-zinc-400 hover:text-red-500">
                                    <flux:icon.trash variant="micro" class="size-4"/>
                                </button>
                            @endif
                        </div>
                    </flux:pillbox.option>
                @endforeach

                <flux:pillbox.option.create wire:click="createServiceType" min-length="2">
                    {{ __('Izveidot') }} "<span wire:text="serviceTypeSearch"></span>"
                </flux:pillbox.option.create>
            </flux:pillbox>

            <flux:separator/>

            @if($this->is_membership)
                <div class="flex flex-col gap-6 sm:flex-row">
                    <div class="sm:flex-1">
                        <flux:input
                            wire:model="price"
                            :label="__('Cena (EUR)')"
                            type="number"
                            step="0.01"
                            min="0"
                            :placeholder="__('Ievadi cenu (piem., 25.00)')"
                        />
                    </div>
                    <div class="sm:flex-1">
                        <flux:input
                            wire:model="sessions_count"
                            :label="__('Nodarbību skaits')"
                            type="number"
                            min="1"
                            :placeholder="__('Piem., 4 vai 9')"
                        />
                    </div>
                </div>
            @else
                {{-- Price Tiers Section --}}
                <div class="space-y-4">
                    <flux:heading size="sm">{{ __('Cenas') }}</flux:heading>

                    <div class="flex items-end gap-4">
                        <div class="w-32">
                            <flux:input
                                :label="__('Dalībnieki')"
                                value="1"
                                disabled
                            />
                        </div>
                        <div class="flex-1">
                            <flux:input
                                wire:model="price"
                                :label="__('Cena kopā (EUR)')"
                                type="number"
                                step="0.01"
                                min="0"
                                :placeholder="__('Cena')"
                            />
                        </div>
                        {{-- Spacer to align with tier rows that have a delete button --}}
                        <div class="size-10 shrink-0"></div>
                    </div>

                    @foreach($this->priceTiers as $index => $tier)
                        <div class="flex items-end gap-4" wire:key="tier-{{ $index }}">
                            <div class="w-32">
                                <flux:input
                                    wire:model="priceTiers.{{ $index }}.participant_count"
                                    type="number"
                                    min="2"
                                    :placeholder="__('Skaits')"
                                />
                            </div>
                            <div class="flex-1">
                                <flux:input
                                    wire:model="priceTiers.{{ $index }}.price"
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

                    <flux:button wire:click.prevent="addPriceTier" variant="ghost" size="sm" icon="plus">
                        {{ __('Pievienot cenu vairākiem dalībniekiem') }}
                    </flux:button>
                </div>

                <flux:separator/>

                <flux:switch wire:model="is_exclusive" :label="__('Individuāls (viena rezervācija aizņem visu laiku)')"
                             :description="__('Ja ieslēgts, pēc vienas rezervācijas laiks vairs nav pieejams citiem.')"/>

                <flux:switch wire:model="is_membership_eligible" :label="__('Pieejams abonementam')"
                             :description="__('Ja ieslēgts, šis pakalpojums var tikt iekļauts abonementā.')"/>

                <flux:switch wire:model="notify_coach_on_cancellation" :label="__('Paziņot trenerim par atcelšanu')"
                             :description="__('Ja ieslēgts, treneris saņem e-pastu, kad klients atceļ rezervāciju un saņem atmaksu.')"/>
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
