@props([
    'days',
    'week',
    'weekStart',
    'initialDay',
    'isFirstWeek',
    'isLastWeek',
    'cardFor',
])

{{--
    A week of classes inside a Livewire component that uses App\Livewire\Concerns\ShowsWeekTimetable:
    week arrows, seven columns on large screens, a day strip and sideways swiping on small screens.

    $cardFor(array $entry) describes each class card:
    array{key: string, tone: string, lines: list<?string>, button: ?array{label: string, action: string, variant: string, icon?: string, disabled?: bool}, status: ?string}
    The button's action is a Livewire expression for wire:click; without a button the status is shown.
--}}
<div class="space-y-8">
    {{-- WEEK NAVIGATION --}}
    <div class="flex items-center justify-between gap-4 border-y border-black py-3">
        <flux:button variant="ghost" icon="chevron-left" wire:click="previousWeek" :disabled="$isFirstWeek"
                     :aria-label="__('Iepriekšējā nedēļa')"/>
        <flux:heading level="3">
            {{ $weekStart->format('d.m.') }} – {{ $weekStart->copy()->addDays(6)->format('d.m.Y') }}
        </flux:heading>
        <flux:button variant="ghost" icon="chevron-right" wire:click="nextWeek" :disabled="$isLastWeek"
                     :aria-label="__('Nākamā nedēļa')"/>
    </div>

    @if(collect($days)->contains(fn (array $day) => $day['entries'] !== []))
        <div wire:key="week-{{ $week }}" class="space-y-4"
             x-data="{
                 day: '{{ $initialDay }}',
                 dates: @js(array_column($days, 'date')),
                 isFirstWeek: @js($isFirstWeek),
                 isLastWeek: @js($isLastWeek),
                 touchStart: null,
                 startSwipe(event) {
                     const touch = event.changedTouches[0];
                     {{-- Swipes from the screen edge belong to the phone's own back / forward gesture. --}}
                     const isFromScreenEdge = touch.clientX < 24 || touch.clientX > window.innerWidth - 24;
                     this.touchStart = isFromScreenEdge ? null : { x: touch.clientX, y: touch.clientY };
                 },
                 endSwipe(event) {
                     if (this.touchStart === null || window.matchMedia('(min-width: 1024px)').matches) {
                         return;
                     }
                     const touch = event.changedTouches[0];
                     const distanceX = touch.clientX - this.touchStart.x;
                     const distanceY = touch.clientY - this.touchStart.y;
                     this.touchStart = null;
                     {{-- Only a clear sideways swipe counts, so scrolling the page never changes the day. --}}
                     if (Math.abs(distanceX) < 50 || Math.abs(distanceX) < Math.abs(distanceY) * 1.5) {
                         return;
                     }
                     this.moveDay(distanceX < 0 ? 1 : -1);
                 },
                 moveDay(direction) {
                     const index = this.dates.indexOf(this.day) + direction;
                     if (index >= 0 && index < this.dates.length) {
                         this.day = this.dates[index];
                     } else if (direction > 0 && ! this.isLastWeek) {
                         $wire.nextWeek();
                     } else if (direction < 0 && ! this.isFirstWeek) {
                         $wire.previousWeek(true);
                     }
                 },
             }">
            {{-- DAY PICKER (SMALL SCREENS) --}}
            <div class="grid grid-cols-7 gap-1 lg:hidden">
                @foreach($days as $day)
                    <button type="button" wire:key="tab-{{ $day['date'] }}" @click="day = '{{ $day['date'] }}'"
                            :class="day === '{{ $day['date'] }}' ? 'bg-blue border-blue text-white' : 'border-zinc-200'"
                            class="rounded-lg border py-2 text-center transition-colors">
                        <span class="block text-xs uppercase">{{ $day['weekday_short'] }}</span>
                        <span class="block font-semibold">{{ $day['day_of_month'] }}</span>
                    </button>
                @endforeach
            </div>

            {{-- DAYS (swipe sideways on small screens to change the day) --}}
            <div class="grid gap-3 lg:grid-cols-7" @touchstart.passive="startSwipe($event)" @touchend.passive="endSwipe($event)">
                @foreach($days as $day)
                    <section wire:key="day-{{ $day['date'] }}" class="space-y-3"
                             :class="day === '{{ $day['date'] }}' ? '' : 'max-lg:hidden'">
                        <header class="border-b border-zinc-200 pb-2">
                            <div class="font-heading text-lg font-medium uppercase">{{ $day['weekday'] }}</div>
                            <flux:text size="sm">{{ $day['short_date'] }}</flux:text>
                        </header>

                        @forelse($day['entries'] as $entry)
                            @php($card = $cardFor($entry))
                            <x-timetable.class-card wire:key="{{ $card['key'] }}" :entry="$entry" :tone="$card['tone']"
                                                    :lines="$card['lines']">
                                <x-slot:action>
                                    @if($card['button'])
                                        <flux:button :variant="$card['button']['variant']" size="sm" class="lg:w-full"
                                                     :icon="$card['button']['icon'] ?? null"
                                                     :disabled="$card['button']['disabled'] ?? false"
                                                     wire:click="{{ $card['button']['action'] }}">
                                            {{ $card['button']['label'] }}
                                        </flux:button>
                                    @else
                                        <span class="text-sm font-medium uppercase">{{ $card['status'] }}</span>
                                    @endif
                                </x-slot:action>
                            </x-timetable.class-card>
                        @empty
                            <flux:text size="sm">{{ __('Nodarbību nav') }}</flux:text>
                        @endforelse
                    </section>
                @endforeach
            </div>
        </div>
    @else
        <flux:text>{{ __('Šajā nedēļā nodarbību nav.') }}</flux:text>
    @endif
</div>
