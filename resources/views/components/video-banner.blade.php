<div id="videoBanner" class="full-width-banner relative pb-12" x-data="{ playing: false }">
    {{-- Thumbnail --}}
    <div x-show="!playing" @click="playing = true" class="cursor-pointer relative h-full w-full">
        <img class="h-full w-full object-cover" src="{{ asset('images/anete_platkevica_9.jpg') }}" alt="">
        <flux:icon.play-btn class="centered-icon" />
    </div>

    {{-- Video --}}
    <video x-show="playing" x-cloak class="w-full h-full object-cover" controls autoplay x-ref="video"
        @click.away="playing = false; $refs.video.pause()">
        <source src="{{ asset('videos/your-video.mp4') }}" type="video/mp4">
        {{ __('Diemžēl video nav iespējams atskaņot') }}
    </video>
</div>