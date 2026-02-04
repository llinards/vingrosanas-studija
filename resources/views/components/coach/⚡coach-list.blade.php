<?php

use App\Models\Coach;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    #[Computed]
    public function coaches(): Collection
    {
        return Coach::all();
    }

    public function destroy(Coach $coach): void
    {
        try {
            $imagePath = str_replace('storage/', '', $coach->image);

            if ($imagePath && Storage::disk('public')->exists($imagePath)) {
                Storage::disk('public')->delete($imagePath);
            }

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
                            <div
                                class="flex size-full items-center justify-center bg-linear-to-br from-zinc-200 to-zinc-300">
                                <flux:icon.user class="size-24 text-zinc-400"/>
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
                            {{ __('Rediģēt') }}
                        </flux:button>
                        <flux:modal.trigger :name="'delete-coach-'.$coach->id">
                            <flux:button variant="danger" class="flex-1">
                                {{ __('Dzēst') }}
                            </flux:button>
                        </flux:modal.trigger>
                    </div>

                    <flux:modal :name="'delete-coach-'.$coach->id" class="min-w-[22rem]">
                        <div class="space-y-6">
                            <div>
                                <flux:heading level="2" size="lg">{{ __('Dzēst treneri?') }}</flux:heading>
                                <flux:text class="mt-2">
                                    <p>{{ __('Tu vēlies dzēst treneri - ') }} <strong>{{ $coach->name }}</strong>.</p>
                                    <p>{{ __('Šo darbību nevar atcelt.') }}</p>
                                </flux:text>
                            </div>
                            <div class="flex gap-2">
                                <flux:spacer/>
                                <flux:modal.close>
                                    <flux:button variant="ghost">{{ __('Atcelt') }}</flux:button>
                                </flux:modal.close>
                                <flux:button variant="danger" wire:click="destroy({{ $coach->id }})">
                                    {{ __('Dzēst treneri') }}
                                </flux:button>
                            </div>
                        </div>
                    </flux:modal>
                </flux:card>
            @endforeach
        </div>
    @endif
</div>
