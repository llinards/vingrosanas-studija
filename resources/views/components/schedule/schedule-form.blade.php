@props(['heading'])

<div class="flex justify-center">
    <div class="w-full max-w-2xl">
        <flux:heading level="1" size="xl" class="mb-6">{{ $heading }}</flux:heading>

        <form wire:submit="save" class="flex flex-col gap-6">
            <div class="flex flex-col sm:flex-row gap-4">
                <div class="flex-1">
                    <flux:select wire:model.live="coach_id" :label="__('Treneris')">
                        <flux:select.option value="">{{ __('Izvēlies treneri') }}</flux:select.option>
                        @foreach($this->coaches as $coach)
                            <flux:select.option :value="$coach->id">{{ $coach->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
                <div class="flex-1">
                    <flux:select wire:model="service_id" :label="__('Pakalpojums')">
                        <flux:select.option value="">{{ __('Izvēlies pakalpojumu') }}</flux:select.option>
                        @foreach($this->services as $service)
                            <flux:select.option :value="$service->id">{{ $service->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            </div>

            <flux:input
                wire:model="max_capacity"
                :label="__('Maks. dalībnieki')"
                type="number"
                min="1"
                :placeholder="__('Ievadi maksimālo dalībnieku skaitu')"
            />

            <flux:radio.group wire:model.live="schedule_type" :label="__('Grafika veids')" variant="segmented">
                <flux:radio value="recurring" :label="__('Regulārs')"/>
                <flux:radio value="specific" :label="__('Vienreizējs')"/>
            </flux:radio.group>

            <div class="flex flex-col sm:flex-row gap-4">
                <div class="flex-1">
                    @if($this->schedule_type === 'recurring')
                        <flux:select wire:model="day_of_week" :label="__('Nedēļas diena')">
                            <flux:select.option value="">{{ __('Izvēlies dienu') }}</flux:select.option>
                            @foreach($this->dayOptions as $day)
                                <flux:select.option :value="$day['value']">{{ $day['label'] }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    @else
                        <flux:date-picker min="today" placeholder="Izvēlies datumu" :label="__('Datums')"
                                          wire:model="date"/>
                    @endif
                </div>
                <div class="flex-1">
                    <flux:time-picker min="06:00" max="22:00" wire:model="start_time" interval="15"
                                      placeholder="Izvēlies laiku"
                                      :label="__('Sākuma laiks')"/>
                </div>
            </div>


            <flux:switch wire:model="is_active" :label="__('Aktīvs')"/>

            <div class="flex items-center justify-end gap-4">
                <flux:button href="{{ route('admin.schedules.index') }}" wire:navigate variant="ghost">
                    {{ __('Atcelt') }}
                </flux:button>
                <flux:button type="submit" variant="primary">
                    {{ __('Saglabāt') }}
                </flux:button>
            </div>
        </form>
    </div>
</div>
