<x-layouts.main :title="__('Sākums')">

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
        <flux:heading level="2" class="">{{ __('Pakalpojumi un cenas') }}</flux:heading>
        <flux:accordion transition>
            <livewire:services-price-table />
        </flux:accordion>
    </div>

    {{-- VIDEO BANNER --}}
    <x-video-banner />


    {{-- SLOGAN AND ACHIEVEMENT COUNTER --}}
    <x-achievement />

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

    {{-- BOOKING MODAL --}}
    <livewire:booking-modal />

</x-layouts.main>