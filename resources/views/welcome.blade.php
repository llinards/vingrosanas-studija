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
    <div id="quote" class="relative flex flex-col md:grid grid-cols-2 overflow-hidden">
        <div class="h-124 md:h-206">
            <img class="h-full w-full object-cover" src="{{ asset('images/anete_platkevica_7.jpg') }}" alt="">
        </div>
        <div class="h-124 md:h-206 relative flex justify-center items-center">
            <img class="h-full w-full absolute blur-sm object-cover" src="{{ asset('images/anete_platkevica_8.jpg') }}"
                alt="">
            <div class="absolute inset-0 bg-black/30"></div>


            <div
                class="h-full relative flex flex-col justify-center items-center text-center space-y-6 md:space-y-12 px-4 sm:px-6 md:px-8 lg:px-12 xl:px-24">
                <flux:icon.quotes />
                <flux:text>{{__('"Kustība ir dzīvesveids, veselība - ieguvums."') }}</flux:text>
                <hr class="border-blue border-t w-18">
                <flux:text>{{__('Vingrošanas studijas dibinātāja') }}</flux:text>
                <flux:text>{{__('Anete Platkēviča') }}</flux:text>
            </div>

        </div>
        <flux:icon.arrow-down class="md:hidden image-arrow" />
        <flux:icon.arrow-right class="hidden md:flex image-arrow" />
    </div>

    {{-- ACCORDION WRAPPER --}}
    <div class="mx-auto max-w-7xl px-4 pb-12">
        <flux:heading level="2" class="py-b">{{ __('Pakalpojumi un cenas') }}</flux:heading>
        <flux:accordion transition>
            <x-services-price-table />
        </flux:accordion>
    </div>

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