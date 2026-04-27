@props(['heading'])

<div class="flex justify-center">
    <div class="w-full max-w-2xl">
        <flux:heading level="1" size="xl" class="mb-6">{{ $heading }}</flux:heading>

        <form wire:submit="save" class="flex flex-col gap-6">
            <div class="flex flex-col sm:flex-row gap-4">
                <div class="flex-1">
                    <flux:select wire:model.live="service_id" :label="__('Pakalpojums')">
                        <flux:select.option value="">{{ __('Izvēlies pakalpojumu') }}</flux:select.option>
                        @foreach($this->services as $service)
                            <flux:select.option :value="$service->id">{{ $service->name }} ({{ $service->coach->name }})
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <div class="flex-1">
                    <flux:select wire:model.live="schedule_id" :label="__('Grafiks')">
                        <flux:select.option value="">{{ __('Izvēlies grafiku') }}</flux:select.option>
                        @foreach($this->schedules as $schedule)
                            <flux:select.option :value="$schedule->id">
                                @if($schedule->day_of_week)
                                    {{ $schedule->day_of_week->label() }}
                                @else
                                    {{ $schedule->date->format('d.m.Y') }}
                                @endif
                                — {{ substr($schedule->start_time, 0, 5) }}
                                ({{ __('maks.') }} {{ $schedule->max_capacity }}
                                )
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-4">
                <div class="flex-1">
                    <flux:date-picker placeholder="Datums" :label="__('Datums')"
                                      wire:model.live="booking_date"/>
                </div>
                <div class="flex-1">
                    <flux:input type="number" wire:model="participant_count" :label="__('Dalībnieku skaits')"
                                min="1"/>
                    @if($this->schedule_id && $this->booking_date && !$this->isExclusiveService)
                        <flux:text class="mt-2 text-sm text-zinc-500">
                            {{ __('Atlikušās vietas') }}: {{ $this->remainingCapacity }}
                        </flux:text>
                    @endif
                </div>
            </div>

            <flux:separator/>

            <div class="flex flex-col sm:flex-row gap-4">
                <div class="flex-1">
                    <flux:input wire:model="name" :label="__('Vārds')" :placeholder="__('Ievadi vārdu')"/>
                </div>
                <div class="flex-1">
                    <flux:input wire:model="surname" :label="__('Uzvārds')" :placeholder="__('Ievadi uzvārdu')"/>
                </div>
            </div>
            <div class="flex flex-col sm:flex-row gap-4">
                <div class="flex-1">
                    <flux:input wire:model="phone" :label="__('Tālrunis')" :placeholder="__('Ievadi tālruni')"/>
                </div>
                <div class="flex-1">
                    <flux:input wire:model="email" type="email" :label="__('E-pasts')"
                                :placeholder="__('Ievadi e-pastu')"/>
                </div>
            </div>

            <flux:separator/>

            <div class="flex flex-col sm:flex-row gap-4">
                <div class="flex-1">
                    <flux:select wire:model="payment_status" :label="__('Maksājuma statuss')">
                        @foreach($this->paymentStatusOptions as $option)
                            <flux:select.option :value="$option['value']">{{ $option['label'] }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
                <div class="flex-1">
                    <flux:select wire:model="attendance_status" :label="__('Apmeklējuma statuss')">
                        @foreach($this->attendanceStatusOptions as $option)
                            <flux:select.option :value="$option['value']">{{ $option['label'] }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            </div>

            <div class="flex items-center justify-end gap-4">
                <flux:button href="{{ route('admin.bookings.index') }}" wire:navigate variant="ghost">
                    {{ __('Atcelt') }}
                </flux:button>
                <flux:button type="submit" variant="primary">
                    {{ __('Saglabāt') }}
                </flux:button>
            </div>
        </form>
    </div>
</div>
