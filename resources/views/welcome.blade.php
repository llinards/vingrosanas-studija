<x-layouts.main :title="__('Sākums')">

    {{-- NAVIGATION --}}
    <x-nav />

    {{-- HEADER --}}
    <x-header />

    {{-- ACCORDION WRAPPER --}}
    <div class="mx-auto max-w-7xl px-4 pb-12">
        <flux:heading level="2" class="py-12">{{ __('Pakalpojumi un cenas') }}</flux:heading>
        <flux:accordion transition>
            <x-services-price-table />
        </flux:accordion>
    </div>

    {{-- CTA --}}
    <x-banner-with-cta />

    {{-- COACHES --}}

    {{-- BANNER ROW WITH CTA BUTTON --}}
    <x-banner-with-cta />

    {{-- CAROUSEL --}}
    <div class="pb-12">
        <div class="f-carousel" id="fitnessCarousel">
            <div class="f-carousel__slide">
                <img src="{{ asset('images/anete_platkevica_1.jpg') }}" alt="">
            </div>
            <div class="f-carousel__slide">
                <img src="{{ asset('images/anete_platkevica_2.jpg') }}" alt="">
            </div>
            <div class="f-carousel__slide">
                <img src="{{ asset('images/anete_platkevica_3.jpg') }}" alt="">
            </div>
        </div>
    </div>

    {{-- NEW PREMISES --}}
    <x-new-premises />

    {{-- BANNER ROW WITH CTA BUTTON --}}
    <x-banner-with-cta />

    {{-- FORM --}}
    <x-form />


    {{-- FOOTER --}}
    <x-footer />

</x-layouts.main>