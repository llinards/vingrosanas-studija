@props(['heading'])

<div class="flex justify-center">
    <div class="w-full max-w-2xl">
        <flux:heading level="1" size="xl" class="mb-6">{{ $heading }}</flux:heading>

        <form wire:submit="save" class="flex flex-col gap-6">
            <div class="flex flex-col sm:flex-row gap-4">
                <div class="sm:w-1/3">
                    <flux:select wire:model.live="coach_id" :label="__('Treneris')">
                        <flux:select.option value="">{{ __('Izvēlies treneri') }}</flux:select.option>
                        @foreach($this->coaches as $coach)
                            <flux:select.option wire:key="coach-{{ $coach->id }}" :value="$coach->id">{{ $coach->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
                <div class="flex-1">
                    <flux:select wire:model.live="schedule_id" :label="__('Nodarbība')">
                        <flux:select.option value="">
                            {{ $this->coach_id ? __('Izvēlies nodarbību') : __('Vispirms izvēlies treneri') }}
                        </flux:select.option>
                        @foreach($this->classesOfCoach as $class)
                            <flux:select.option wire:key="class-{{ $class['id'] }}" :value="$class['id']">{{ $class['label'] }}</flux:select.option>
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
                    <flux:input type="number" wire:model.blur="participant_count" :label="__('Dalībnieku skaits')"
                                min="1"/>
                    @if($this->schedule_id && $this->booking_date && $this->selectedSchedule && !$this->isExclusiveService)
                        <flux:text class="mt-2 text-sm text-zinc-500">
                            {{ __('Pieteikušies') }}: {{ $this->signedUpParticipants }} {{ __('no') }} {{ $this->selectedSchedule->max_capacity }} · {{ __('Brīvas vietas') }}: {{ max(0, $this->selectedSchedule->max_capacity - $this->signedUpParticipants) }}
                        </flux:text>
                    @endif
                </div>
            </div>

            @foreach($this->slotWarnings as $warning)
                <flux:callout wire:key="slot-warning-{{ $loop->index }}" variant="warning" icon="exclamation-triangle">
                    <flux:callout.heading>{{ $warning }}</flux:callout.heading>
                </flux:callout>
            @endforeach

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
                    <flux:select wire:model.live="payment_status" :label="__('Maksājuma statuss')"
                                 :disabled="$this->isPaymentStatusLocked">
                        @foreach($this->paymentStatusOptions as $option)
                            <flux:select.option :value="$option['value']">{{ $option['label'] }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    @if($this->isPaymentStatusLocked)
                        <flux:text class="mt-2 text-sm text-zinc-500">
                            {{ __('Atmaksāts caur Stripe — statusu vairs nevar mainīt.') }}
                        </flux:text>
                    @elseif($this->willRefundThroughStripe)
                        <flux:text class="mt-2 text-sm text-amber-600">
                            {{ __('Saglabājot klientam caur Stripe tiks atmaksāta pilna summa.') }}
                        </flux:text>
                    @endif
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
