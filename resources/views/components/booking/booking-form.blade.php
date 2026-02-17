@props(['heading'])

<div class="flex min-h-full flex-col items-center justify-center p-6">
    <div class="w-full max-w-2xl">
        <flux:heading level="1" size="xl" class="mb-6">{{ $heading }}</flux:heading>

        <form wire:submit="save" class="flex flex-col gap-6">
            <flux:select wire:model.live="service_type_id" :label="__('Pakalpojuma veids')">
                <flux:select.option value="">{{ __('Izvēlies veidu') }}</flux:select.option>
                @foreach($this->serviceTypes as $type)
                    <flux:select.option :value="$type->id">{{ $type->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="service_id" :label="__('Pakalpojums')">
                <flux:select.option value="">{{ __('Izvēlies pakalpojumu') }}</flux:select.option>
                @foreach($this->services as $service)
                    <flux:select.option :value="$service->id">{{ $service->name }} ({{ $service->coach->name }})
                    </flux:select.option>
                @endforeach
            </flux:select>

            @if($this->service_id)
                <flux:select wire:model.live="schedule_id" :label="__('Grafiks')">
                    <flux:select.option value="">{{ __('Izvēlies grafiku') }}</flux:select.option>
                    @foreach($this->schedules as $schedule)
                        <flux:select.option :value="$schedule->id">
                            @if($schedule->day_of_week)
                                {{ $schedule->day_of_week->label() }}
                            @else
                                {{ $schedule->date->format('d.m.Y') }}
                            @endif
                            — {{ substr($schedule->start_time, 0, 5) }} ({{ __('maks.') }} {{ $schedule->max_capacity }}
                            )
                        </flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <flux:date-picker min="today" placeholder="Rezervācijas datums" :label="__('Rezervācijas datums')"
                              wire:model.live="booking_date"/>

            {{-- Warning for exclusive services if slot is already booked --}}
            @if($this->schedule_id && $this->booking_date && $this->isExclusiveService && $this->isSlotAlreadyBooked)
                <flux:callout variant="warning" icon="exclamation-triangle">
                    <flux:callout.heading>{{ __('Uzmanību!') }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ __('Šis laiks jau ir rezervēts. Izveidojot jaunu rezervāciju, tiks pievienota papildus rezervācija šim laikam.') }}
                    </flux:callout.text>
                </flux:callout>
            @endif

            {{-- Show remaining capacity for regular services --}}
            @if($this->schedule_id && $this->booking_date && !$this->isExclusiveService)
                <flux:text class="text-sm text-zinc-500">
                    {{ __('Atlikušās vietas') }}: {{ $this->remainingCapacity }}
                </flux:text>
            @endif

            {{-- Participant count selection --}}
            @if($this->schedule_id && $this->isExclusiveService && count($this->availablePriceTiers) > 0)
                <flux:radio.group wire:model.live="participant_count" :label="__('Dalībnieku skaits')" variant="cards">
                    @foreach($this->availablePriceTiers as $tier)
                        <flux:radio :value="$tier->participant_count"
                                    :label="$tier->participant_count . ' ' . ($tier->participant_count === 1 ? __('persona') : __('personas'))"
                                    :description="Number::currency($tier->price / 100, 'EUR')"/>
                    @endforeach
                </flux:radio.group>
            @elseif($this->schedule_id && !$this->isExclusiveService)
                <flux:input type="number" wire:model="participant_count" :label="__('Dalībnieku skaits')"
                            min="1" :max="$this->remainingCapacity ?: 1"/>
            @endif

            <flux:separator/>

            <flux:input wire:model="name" :label="__('Vārds')" :placeholder="__('Ievadi vārdu')"/>
            <flux:input wire:model="surname" :label="__('Uzvārds')" :placeholder="__('Ievadi uzvārdu')"/>
            <flux:input wire:model="phone" :label="__('Tālrunis')" :placeholder="__('Ievadi tālruni')"/>
            <flux:input wire:model="email" type="email" :label="__('E-pasts')" :placeholder="__('Ievadi e-pastu')"/>

            <flux:separator/>

            <flux:select wire:model="payment_status" :label="__('Maksājuma statuss')">
                @foreach($this->paymentStatusOptions as $option)
                    <flux:select.option :value="$option['value']">{{ $option['label'] }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="flex items-center justify-end gap-4">
                <flux:button href="{{ route('booking-list') }}" wire:navigate variant="ghost">
                    {{ __('Atcelt') }}
                </flux:button>
                <flux:button type="submit" variant="primary">
                    {{ __('Saglabāt') }}
                </flux:button>
            </div>
        </form>
    </div>
</div>
