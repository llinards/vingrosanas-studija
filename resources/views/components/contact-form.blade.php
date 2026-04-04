<div id="kontakti" class="py-12 mx-auto max-w-7xl px-4 grid lg:grid-cols-2 gap-x-6 gap-y-12">

    {{-- LEFT PANEL: CONTACT DETAILS --}}
    <div id="contactDetails" class="flex flex-col text-center lg:text-start space-y-6 lg:space-y-9">
        <ul>
            <li class="list-heading">{{ __('Tālrunis') }}</li>
            <li>
                <flux:link href="tel:+37126620757">+371 26620757</flux:link>
            </li>
        </ul>
        <ul>
            <li class="list-heading">{{ __('E-pasts') }}</li>
            <li>
                <flux:link href="mailto:vingrosanas@studija.lv">vingrosanas@studija.lv</flux:link>
            </li>
        </ul>
        <ul>
            <li class="list-heading">{{ __('Adrese') }}</li>
            <li>
                <flux:link href="https://maps.app.goo.gl/UdGP64Acxz2RVPJe7" target="_blank">Strēlnieku iela 20
                    A, Sigulda
                </flux:link>
            </li>
        </ul>
        <ul>
            <li class="list-heading">{{ __('Pieseko') }}</li>
            <li class="mt-1 lg:mt-2 flex justify-center lg:justify-start gap-x-4">
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

    {{-- RIGHT PANEL: FORM --}}
    <livewire:contact-form />
</div>
