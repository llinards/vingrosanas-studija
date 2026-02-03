<?php

use App\Models\Coach;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    /**
     * Get all coaches.
     *
     * @return Collection<int, Coach>
     */
    #[Computed]
    public function coaches(): Collection
    {
        return Coach::all();
    }
};
?>

<div>
    @if($this->coaches->isEmpty())
        <div class="flex flex-col items-center">
            <flux:heading class="mb-2" level="2" size="xl">Šobrīd nav neviena aktīva trenera!</flux:heading>
            <flux:button>Pievienot jaunu treneri</flux:button>
        </div>
    @else
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
            @foreach($this->coaches as $coach)
                <flux:card wire:key="coach-{{ $coach->id }}" class="flex flex-col p-4">
                    <div class="relative mb-4 aspect-square overflow-hidden rounded-lg bg-zinc-100">
                        @if($coach->image_url)
                            <img
                                src="{{ $coach->image_url }}"
                                alt="{{ $coach->name }}"
                                class="size-full object-cover"
                            >
                        @else
                            <div class="flex size-full items-center justify-center bg-linear-to-br from-zinc-200 to-zinc-300">
                                <flux:icon.user class="size-24 text-zinc-400" />
                            </div>
                        @endif

                        <div class="absolute right-3 top-3">
                            <flux:badge :color="$coach->is_active ? 'green' : 'red'" size="sm">
                                {{ $coach->is_active ? 'Aktīvs' : 'Neaktīvs' }}
                            </flux:badge>
                        </div>
                    </div>

                    <div class="flex-1">
                        <flux:heading level="3" size="lg" class="mb-1">
                            {{ $coach->name }}
                        </flux:heading>
                        <flux:subheading class="mb-3">
                            {{ $coach->title }}
                        </flux:subheading>
                    </div>

                    <div class="mt-4 flex gap-2">
                        <flux:button variant="primary" class="flex-1">
                            Rediģēt
                        </flux:button>
                        <flux:button variant="danger" class="flex-1">
                            Dzēst
                        </flux:button>
                    </div>
                </flux:card>
            @endforeach
        </div>
    @endif
</div>
