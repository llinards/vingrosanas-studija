@props(['heading'])

<div class="flex min-h-full flex-col items-center justify-center p-6">
    <div class="w-full max-w-2xl">
        <flux:heading level="1" size="xl" class="mb-6">{{ $heading }}</flux:heading>

        <form wire:submit="save" class="flex flex-col gap-6">
            <flux:select wire:model.live="service_type_id" :label="__('Pakalpojuma veids')">
                <flux:select.option value="">{{ __('Izvēlieties veidu') }}</flux:select.option>
                @foreach($this->serviceTypes as $type)
                    <flux:select.option :value="$type->id">{{ $type->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="service_id" :label="__('Pakalpojums')">
                <flux:select.option value="">{{ __('Izvēlieties pakalpojumu') }}</flux:select.option>
                @foreach($this->services as $service)
                    <flux:select.option :value="$service->id">{{ $service->name }} ({{ $service->coach->name }})</flux:select.option>
                @endforeach
            </flux:select>

            @if($this->service_id)
                <flux:select wire:model="schedule_id" :label="__('Grafiks')">
                    <flux:select.option value="">{{ __('Izvēlieties grafiku') }}</flux:select.option>
                    @foreach($this->schedules as $schedule)
                        <flux:select.option :value="$schedule->id">
                            @if($schedule->day_of_week)
                                {{ $schedule->day_of_week->label() }}
                            @else
                                {{ $schedule->date->format('d.m.Y') }}
                            @endif
                            — {{ substr($schedule->start_time, 0, 5) }} ({{ __('maks.') }} {{ $schedule->max_capacity }})
                        </flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <flux:input
                wire:model="booking_date"
                :label="__('Rezervācijas datums')"
                type="date"
            />

            <flux:separator />

            <flux:input wire:model="name" :label="__('Vārds')" :placeholder="__('Ievadiet vārdu')" />
            <flux:input wire:model="surname" :label="__('Uzvārds')" :placeholder="__('Ievadiet uzvārdu')" />
            <flux:input wire:model="phone" :label="__('Tālrunis')" :placeholder="__('Ievadiet tālruni')" />
            <flux:input wire:model="email" type="email" :label="__('E-pasts')" :placeholder="__('Ievadiet e-pastu')" />

            <flux:separator />

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
