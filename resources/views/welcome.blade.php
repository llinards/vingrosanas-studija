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

    <div class="mx-auto max-w-7xl px-4 pb-12 grid lg:grid-cols-2 gap-x-6">
        <div class="container mx-auto justify-center space-y-6">
            <flux:heading level="2">{{ __('Jaunas telpas, jaunas iespējas') }}</flux:heading>
            <flux:text>{{ __('Mūsu vingrošanas studija tagad ir plašāka un jaudīgāka nekā jebkad - vieta, kur kustība kļūst par labsajūtu.') }}</flux:text>
            <ul class="list-disc pl-4">
                <li>{{ __('Personalizēta treniņu programma vai treneru konsultācijas') }}</li>
                <li>{{ __('Grupas nodarbības (joga, pilates, body balance, HIIT, u.c.)') }}</li>
                <li>{{ __('Privātās treniņu sesijas') }}</li>
                <li>{{ __('Vingrošana nelielās grupās') }}</li>
                <li>{{ __('Pārģērbšanās un dušas telpas ar skapīšiem') }}</li>
                <li>{{ __('Dzeramais ūdens') }}</li>
            </ul>
        </div>
        <div>
            <style>
                #myCarousel {
                    --f-carousel-gap: 10px;
                    --f-carousel-slide-width: 100%;
                    --f-carousel-slide-padding: 50px;
                    --f-carousel-slide-bg: #eee;
                }
            </style>
            <div class="f-carousel" id="ownerCarousel">
                <div class="f-carousel__slide">1</div>
                <div class="f-carousel__slide">2</div>
                <div class="f-carousel__slide">3</div>
            </div>
        </div>

    </div>

    {{-- BANNER ROW WITH BUTTON --}}
    <div class="bg-blue py-8 md:py-6 mb-12 flex justify-center">
        <flux:button class="secondary">{{ __('Pieteikties') }}</flux:button>
    </div>


    {{-- FORM --}}
    <x-form />


    {{-- FOOTER --}}
    <x-footer />

</x-layouts.main>