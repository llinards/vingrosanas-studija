<x-layouts.main :title="__('Sākums')">

    {{-- NAVIGATION --}}
    <x-nav />

    {{-- HEADER --}}
    <x-header />

    {{-- WORKOUT CAROUSEL --}}
    <div>
        <div class="f-carousel" id="workoutCarousel">
            <x-workout-carousel-slides />
            <x-workout-carousel-slides />
            <x-workout-carousel-slides />
            <x-workout-carousel-slides />
            <x-workout-carousel-slides />
            <x-workout-carousel-slides />
        </div>
    </div>

    {{-- IMAGES WITH QUOTE --}}
    <x-image-quote />

    {{-- ACCORDION WRAPPER --}}
    <div class="mx-auto max-w-7xl px-4 pb-12">
        <flux:heading level="2" class="py-b">{{ __('Pakalpojumi un cenas') }}</flux:heading>
        <flux:accordion transition>
            <x-services-price-table />
        </flux:accordion>
    </div>

    {{-- VIDEO BANNER --}}
    <x-video-banner />

    {{-- CTA --}}
    <x-banner-with-cta />

    {{-- COACHES --}}

    {{-- BANNER ROW WITH CTA BUTTON --}}
    <x-banner-with-cta />

    {{-- GALLERY CAROUSEL --}}
    <x-gallery-carousel />

    {{-- NEW PREMISES --}}
    <x-new-premises />

    {{-- BANNER ROW WITH CTA BUTTON --}}
    <x-banner-with-cta />

    {{-- FORM --}}
    <x-form />

    {{-- FOOTER --}}
    <x-footer />

</x-layouts.main>