<div id="contactForm" class="py-12 mx-auto max-w-7xl px-4 grid lg:grid-cols-2 gap-x-6 gap-y-12">

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
    <form id="contactForm" action="#" method="POST" class="flex flex-col items-center justify-center w-full">
        <div class="w-full border-6 border-blue rounded-4xl bg-white shadow-2xl p-8 lg:p-12 space-y-6 mb-6 md:mb-12">
            <flux:input type="test" :label="__('Vārds*')" required />
            <flux:input type="test" :label="__('Uzvārds*')" required />
            <flux:input type="email" :label="__('E-pasts*')" required />
            <flux:textarea rows="4" :label="__('Jautājums*')" resize="none" required />
            <flux:field variant="inline">
                <flux:checkbox wire:model="terms" required />
                <flux:label>{{ __('Es piekrītu manu personas datu apstrādei saziņas nolūkos saskaņā ar Privātuma
                    politiku.*') }}</flux:label>
                <flux:error name="terms" />
            </flux:field>
        </div>
        <flux:button type="submit" class="button large primary self-center">{{ __('Nosūtīt') }}</flux:button>
    </form>

    <flux:modal id="confirmModal"
        class="p-6 md:p-12 lg:p-24 flex flex-col items-center justify-center text-center space-y-6" name="confirm">
        <flux:icon.check class="check mt-12 md:mt-0" />
        <flux:heading level="2">{{ __('Tava ziņa ir veiksmīgi nosūtīta!') }}</flux:heading>
        <flux:text>{{ __('Mēs drīz ar tevi sazināsimies — paldies, ka esi ceļā uz kustību kopā ar mums!') }}</flux:text>
    </flux:modal>
</div>