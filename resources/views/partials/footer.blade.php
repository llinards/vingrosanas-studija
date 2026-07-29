<footer class="bg-surface pt-12">
    <div class="container mx-auto px-4">
        <div
            class="flex flex-col space-y-2 md:space-y-0 md:flex-row text-center md:text-right items-center justify-between border-y border-black py-3">
            <div class="w-26">
                <img src="{{ asset('images/vingrosanas_studija_logo.svg') }}" alt="">
            </div>
            <flux:heading
                level="2">{{ site('hero', 'heading', __('VINGROŠANAS STUDIJA veselīgam dzīvesveidam')) }}</flux:heading>
        </div>

        <div class="pt-12 pb-12 md:flex md:flex-row space-y-6 md:space-y-0 md:gap-x-12 lg:gap-x-24">
            <ul>
                <li class="list-heading">{{ __('Menu') }}</li>
                <li>
                    <flux:link href="/#aboutUs">{{ __('Par mums') }}</flux:link>
                </li>
                <li>
                    <flux:link href="/#coaches">{{ __('Treneri') }}</flux:link>
                </li>
                <li>
                    <flux:link href="/#services">{{ __('Pakalpojumi') }}</flux:link>
                </li>
                <li>
                    <flux:link href="/#kontakti">{{ __('Kontakti') }}</flux:link>
                </li>
            </ul>
            <ul>
                <li class="list-heading">{{ __('Informācija') }}</li>
                <li>
                    <flux:link href="{{ route('privacy-policy') }}">
                        {{ __('Privātuma politika') }}</flux:link>
                </li>
            </ul>
            <ul>
                <li class="list-heading">{{ __('Kontakti') }}</li>
                @if (filled(site('contact', 'phone')))
                    <li>
                        <flux:link
                            href="tel:{{ site('contact', 'phone') }}">{{ str_replace('+371', '+371 ', site('contact', 'phone')) }}</flux:link>
                    </li>
                @endif
                <li>
                    <flux:link
                        href="mailto:{{ site('contact', 'email', 'info@vingrosanasstudija.lv') }}">{{ site('contact', 'email', 'info@vingrosanasstudija.lv') }}</flux:link>
                </li>
                <li>
                    <flux:link
                        href="{{ site('contact', 'google_maps_url', 'https://maps.app.goo.gl/UdGP64Acxz2RVPJe7') }}"
                        target="_blank">{{ site('contact', 'address', __('Strēlnieku iela 20 A, Sigulda')) }}
                    </flux:link>
                </li>
            </ul>
            <div class="md:w-full flex flex-col md:items-end">
                <ul class="list-heading md:text-end">
                    <li>{{ __('Pieseko') }}</li>
                    <li class="flex items-end md:justify-end mt-1 md:mt-2 gap-x-4">
                        <flux:link
                            href="{{ site('contact', 'instagram_url', 'https://www.instagram.com/vingrosanas.studija') }}"
                            target="_blank"
                            rel="noopener noreferrer">
                            <flux:icon.instagram/>
                        </flux:link>

                        <flux:link href="{{ site('contact', 'facebook_url', 'https://www.facebook.com/vs.sigulda') }}"
                                   target="_blank" rel="noopener noreferrer">
                            <flux:icon.facebook/>
                        </flux:link>
                    </li>
                </ul>
            </div>
        </div>
    </div>
    <div id="copyright" class="bg-blue py-6">
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
