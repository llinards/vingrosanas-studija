<?php

use App\Models\Coach;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    #[Computed]
    public function coaches(): Collection
    {
        return Coach::query()
                    ->where('is_active', true)
                    ->orderBy('position')
                    ->get();
    }
};
?>

<div id="coaches" class="container mx-auto ">
    @if($this->coaches->isNotEmpty())
    <flux:heading class="hidden" level="2">{{ __('Treneri') }}</flux:heading>
    {{-- DESKTOP--}}
    <div class="hidden pb-12 lg:grid grid-cols-3 divide-x">
        @foreach($this->coaches as $coach)
        <div class="h-full">
            <x-coach.coach-card>
                <x-slot name="coachModalName">{{ Str::slug($coach->name) }}</x-slot>
                <x-slot name="coachCardImg">{{ $coach->image_url ?? 'https://placehold.co/640x800.png' }}</x-slot>
                <x-slot name="coachName">{{ $coach->name }}</x-slot>
                <x-slot name="coachTitle">{{ $coach->title }}</x-slot>
            </x-coach.coach-card>
        </div>
        @endforeach
    </div>

    {{-- MOBILE --}}
    <div class="px-4 pb-12 lg:hidden">
        <div class="f-carousel" id="coachCarousel">
            @foreach($this->coaches as $coach)
            <div class="f-carousel__slide">
                <x-coach.coach-card>
                    <x-slot name="coachModalName">{{ Str::slug($coach->name) }}</x-slot>
                    <x-slot name="coachCardImg">{{ $coach->image_url ?? 'https://placehold.co/640x800.png' }}</x-slot>
                    <x-slot name="coachName">{{ $coach->name }}</x-slot>
                    <x-slot name="coachTitle">{{ $coach->title }}</x-slot>
                </x-coach.coach-card>
            </div>
            @endforeach
        </div>
    </div>

    {{-- COACH MODALS --}}
    @foreach($this->coaches as $coach)
    <x-coach.coach-modal>
        <x-slot name="coachModalName">{{ Str::slug($coach->name) }}</x-slot>
        <x-slot name="coachModalImg">{{ $coach->image_url ?? 'https://placehold.co/640x800.png' }}</x-slot>
        <x-slot name="coachName">{{ $coach->name }}</x-slot>
        <x-slot name="coachTitle">{{ $coach->title }}</x-slot>
        <x-slot name="coachBio">{!! $coach->bio !!}</x-slot>
    </x-coach.coach-modal>
    @endforeach
    @endif
</div>