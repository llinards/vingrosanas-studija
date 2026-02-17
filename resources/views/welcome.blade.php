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
    <livewire:services-price-table />

    {{-- VIDEO BANNER --}}
    <x-video-banner />

    {{-- SLOGAN --}}
    <x-slogan />

    {{-- COACHES --}}
    <livewire:coaches />

    {{-- BANNER ROW WITH CTA BUTTON --}}
    <x-banner-with-cta />

    {{-- ABOUT US --}}
    <x-about-us.wrappers />

    {{-- GALLERY CAROUSEL --}}
    <x-gallery-carousel />

    {{-- NEW PREMISES --}}
    <x-new-premises />

    {{-- BANNER ROW WITH CTA BUTTON --}}
    <x-banner-with-cta />

    {{-- FORM --}}
    <x-contact-form />

    {{-- BOOKING MODAL --}}
    <livewire:booking-modal />

    <x-EXTRA-booking-modal />

</x-layouts.main>