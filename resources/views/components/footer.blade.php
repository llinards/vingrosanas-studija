<footer class="bg-beige pt-6">
    <div class="container mx-auto px-4">
        <div
            class="flex flex-col space-y-2 md:space-y-0 md:flex-row text-center md:text-right items-center justify-between border-y border-black py-3">
            <div class="w-36">
                <img src="{{ asset('images/vingrosanas_studija_logo.svg') }}" alt="">
            </div>
            <flux:heading level="2">{{ __('VINGROŠANAS STUDIJA veselīgam dzīvesveidam') }}</flux:heading>
        </div>

        <div class="pt-12 pb-12 md:flex md:flex-row space-y-6 md:space-y-0 gap-x-24">
            <ul>
                <li class="list-heading">{{ __('Menu') }}</li>
                <li>
                    <flux:link href="#about-us">{{ __('Par mums') }}</flux:link>
                </li>
                <li>
                    <flux:link href="#coaches">{{ __('Treneri') }}</flux:link>
                </li>
                <li>
                    <flux:link href="#services">{{ __('Pakalpojumi') }}</flux:link>
                </li>
                <li>
                    <flux:link href="#contactForm">{{ __('Kontakti') }}</flux:link>
                </li>
            </ul>
            <ul>
                <li class="list-heading">{{ __('Informācija') }}</li>
                <li>
                    <flux:link href="#privacy-policy">{{ __('Privātuma politika') }}</flux:link>
                </li>
            </ul>
            <ul>
                <li class="list-heading">{{ __('Kontakti') }}</li>
                <li>
                    <flux:link href="tel:+37126620757">+371 26620757</flux:link>
                </li>
                <li>
                    <flux:link href="mailto:info@vingrosanasstudija.lv">info@vingrosanasstudija.lv</flux:link>
                </li>
                <li>
                    <flux:link href="https://maps.app.goo.gl/UdGP64Acxz2RVPJe7" target="_blank">{{ __('Strēlnieku iela
                        20 A') }}<br />
                        {{ __('Sigulda') }}
                    </flux:link>
                </li>
            </ul>
            <div class="md:w-full flex flex-col md:items-end">
                <ul class="list-heading">
                    <li>{{ __('Pieseko') }}</li>
                    <li class="flex items-end md:justify-end mt-1 md:mt-2 gap-x-2">
                        <flux:link href="https://www.instagram.com/vingrosanas.studija" target="_blank"
                            rel="noopener noreferrer">
                            <flux:icon.instagram />
                        </flux:link>

                        <flux:link href="https://www.facebook.com/vs.sigulda" target="_blank" rel="noopener noreferrer">
                            <flux:icon.facebook />
                        </flux:link>
                    </li>
                </ul>
            </div>
        </div>
    </div>
    <div class="bg-black py-6">
        <div class="container mx-auto space-y-2 text-center">
            <flux:text>{{ __('© VINGROŠANAS STUDIJA :year | Visas tiesības rezervētas.', ['year' => now()->year]) }}
            </flux:text>
            <flux:text>{{ __('Dizains:') }}
                <flux:link href="https://www.simpledesign.lv" class="text-white decoration-white" target="_black"
                    rel="noopener noreferrer">
                    SIMPLE DESIGN
                </flux:link>
            </flux:text>
            <flux:text>
                {{ __('Izstrāde:') }}
                <flux:link href="https://www.slmedia.lv" class="text-white decoration-white" target="_black"
                    rel="noopener noreferrer">
                    S&L
                    Media
                </flux:link>
            </flux:text>
        </div>
    </div>
</footer>