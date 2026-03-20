<flux:modal name="booking-and-consultation-modal">
    <div class="video-banner max-h-196 w-full flex flex-col md:flex-row items-center overflow-hidden"
        x-data="{ playing: false }"
        x-on:modal-close.document="if (!$event.detail?.name || $event.detail.name === 'booking-and-consultation-modal') { playing = false; if ($refs.video) { $refs.video.pause(); $refs.video.currentTime = 0; } }">
        {{-- Thumbnail --}}
        <div x-show="!playing" @click="playing = true; $refs.video.play()"
            class="cursor-pointer w-full h-full flex items-center">
            <img class="object-cover object-center h-108 md:h-full w-full"
                src="{{ asset('images/anete_platkevica_9.jpg') }}" alt="">
            <flux:icon.play-btn class="centered-icon" />
        </div>

        {{-- Video --}}
        <video x-show="playing" x-cloak class="h-108 md:h-196 w-full object-cover" controls x-ref="video"
            @click.away="playing = false; $refs.video.pause(); $refs.video.currentTime = 0">
            <source src="{{ asset('videos/rick-astley-never-gonna-give-you-up.mp4') }}" type="video/mp4">
            {{ __('Diemžēl video nav iespējams atskaņot') }}
        </video>

        <div class="md:absolute md:bottom-10 md:left-1/2 md:-translate-x-1/2 z-10 flex flex-col md:flex-row gap-4 w-full bg-blue px-4 md:px-0 py-4 justify-center"
            x-on:click.stop>
            <flux:button href="#contactForm" class="btn btn-lg btn-secondary"
                x-on:click="$flux.modal('booking-and-consultation-modal').close()">{{ __('Pieteikties konsultācijai') }}
            </flux:button>
            <flux:modal.trigger name="booking-modal">
                <flux:button x-on:click="$flux.modal('booking-and-consultation-modal').close()"
                    class="btn btn-lg btn-secondary">{{ __('Pieteikties apmeklējumam') }}
                </flux:button>
            </flux:modal.trigger>
        </div>
    </div>
</flux:modal>