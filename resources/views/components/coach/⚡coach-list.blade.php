<?php

use App\Models\Coach;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    #[Computed]
    public function coaches(): Collection
    {
        return Coach::query()->orderBy('position')->get();
    }

    public function updatePosition(int $id, int $position): void
    {
        $coach   = Coach::findOrFail($id);
        $coaches = Coach::query()->orderBy('position')->get();
        $coaches = $coaches->reject(fn($c) => $c->id === $id);
        $coaches->splice($position, 0, [$coach]);
        $coaches->each(function ($coach, $index) {
            $coach->update(['position' => $index]);
        });

        unset($this->coaches);

        Flux::toast(
            text: __('Treneru secība veiksmīgi atjaunināta!'),
            variant: 'success',
        );
    }

    public function destroy(Coach $coach): void
    {
        try {
            $coach->deleteImage();
            $coach->delete();

            unset($this->coaches);

            Flux::toast(
                text: __('Treneris veiksmīgi dzēsts!'),
                variant: 'success',
            );
        } catch (\Exception $e) {
            Log::error($e);

            Flux::toast(
                text: __('Neizdevās dzēst treneri. Lūdzu, mēģini vēlreiz.'),
                heading: __('Kļūda!'),
                variant: 'danger',
            );
        }
    }
};
?>

<div>
    @if($this->coaches->isEmpty())
        <div class="flex flex-col items-center">
            <flux:heading class="mb-2" level="2" size="xl">Šobrīd nav neviena aktīva trenera!</flux:heading>
            <flux:button href="{{route('coach-create')}}" wire:navigate class="mb-4">Pievienot jaunu treneri
            </flux:button>
        </div>
    @else
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4" wire:sort="updatePosition">
            @foreach($this->coaches as $coach)
                <flux:card wire:key="coach-{{ $coach->id }}" wire:sort:item="{{ $coach->id }}"
                           class="flex flex-col p-4 cursor-move">
                    <div wire:sort:handle class="mb-2 flex justify-center">
                        <flux:icon.bars-3 class="size-6 text-zinc-400 hover:text-zinc-600"/>
                    </div>

                    <div class="relative mb-4 aspect-square overflow-hidden rounded-lg bg-zinc-100" wire:sort:ignore>
                        @if($coach->image_url)
                            <img
                                src="{{ $coach->image_url }}"
                                alt="{{ $coach->name }}"
                                class="size-full object-cover"
                            >
                        @else
                            <div
                                class="flex size-full items-center justify-center bg-linear-to-br from-zinc-200 to-zinc-300">
                                <flux:icon.user class="size-24 text-zinc-400"/>
                            </div>
                        @endif
                    </div>

                    <div class="flex-1" wire:sort:ignore>
                        <flux:badge :color="$coach->is_active ? 'green' : 'red'" size="sm">
                            {{ $coach->is_active ? 'Aktīvs' : 'Neaktīvs' }}
                        </flux:badge>
                        <flux:heading level="3" size="lg" class="my-1">
                            {{ $coach->name }}
                        </flux:heading>
                    </div>

                    <div class="mt-4 flex gap-2" wire:sort:ignore>
                        <flux:button href="{{ route('coach.edit', $coach) }}" variant="primary" class="flex-1">
                            {{ __('Rediģēt') }}
                        </flux:button>
                        <flux:button wire:confirm="{{__('Vai tiešām vēlies dzēst treneri?')}}" class="flex-1"
                                     variant="danger"
                                     wire:click="destroy({{ $coach->id }})">
                            {{ __('Dzēst treneri') }}
                        </flux:button>
                    </div>
                </flux:card>
            @endforeach
        </div>
    @endif
</div>
