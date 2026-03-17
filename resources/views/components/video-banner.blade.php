<div class="video-banner full-width-banner relative" x-data="{ playing: false }">
    {{-- Thumbnail --}}
    <div x-show="!playing" @click="playing = true; $refs.video.play()" class="cursor-pointer relative h-full w-full">
        <img class="h-full w-full object-cover" src="{{ asset(site('media', 'video_thumbnail', 'images/anete_platkevica_9.jpg')) }}" alt="">
        <flux:icon.play-btn class="centered-icon" />
    </div>

    {{-- Video --}}
    <video x-show="playing" x-cloak class="w-full h-full object-cover" controls x-ref="video"
        @ended="playing = false; $refs.video.currentTime = 0">
        <source src="{{ asset(site('media', 'video_file', 'videos/rick-astley-never-gonna-give-you-up.mp4')) }}" type="video/mp4">
        {{ __('Diemžēl video nav iespējams atskaņot') }}
    </video>
</div>