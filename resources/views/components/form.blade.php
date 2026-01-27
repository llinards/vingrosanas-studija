<div class="pb-12 mx-auto max-w-7xl px-4 grid lg:grid-cols-2 gap-x-6 gap-y-12">

    {{-- LEFT PANEL: CONTACT DETAILS --}}
    <div class="flex flex-col text-center lg:text-start space-y-6 lg:space-y-9">
        <ul class="md:text-xl lg:text-3xl">
            <li class="list-heading">{{ __('Tālrunis') }}</li>
            <li>
                <flux:link href="tel:+37126620757">+371 26620757</flux:link>
            </li>
        </ul>
        <ul class="md:text-xl lg:text-3xl">
            <li class="list-heading">{{ __('E-pasts') }}</li>
            <li>
                <flux:link href="mailto:vingrosanas@studija.lv">vingrosanas@studija.lv</flux:link>
            </li>
        </ul>
        <ul class="md:text-xl lg:text-3xl">
            <li class="list-heading">{{ __('Adrese') }}</li>
            <li>
                <flux:link href="https://maps.app.goo.gl/UdGP64Acxz2RVPJe7" target="_blank">Strēlnieku iela 20
                    A, Sigulda</flux:link>
            </li>
        </ul>
        <ul class="md:text-xl lg:text-3xl">
            <li class="list-heading">{{ __('Pieseko') }}</li>
            <li class="mt-1 lg:mt-2 flex justify-center lg:justify-start">
                <flux:link href="https://www.instagram.com/vingrosanas.studija" target="_blank"
                    rel="noopener noreferrer">
                    <flux:icon.instagram />
                </flux:link>
            </li>
        </ul>
    </div>

    {{-- RIGHT PANEL: FORM --}}
    <form action="#" method="POST" class="flex flex-col items-center justify-center w-full">
        <div
            class="w-full border-gray border bg-beige rounded-md shadow-2xl p-6 md:p-8 lg:p-12 space-y-6 mb-6 md:mb-12">
            <flux:input type="test" :label="__('Vārds*')" required />
            <flux:input type="test" :label="__('Uzvārds*')" required />
            <flux:input type="email" :label="__('E-pasts*')" required />
            <flux:textarea rows="4" :label="__('Jautājums*')" resize="none" required />
        </div>
        <flux:button type="submit" class="primary self-center">{{ __('Nosūtīt') }}</flux:button>
    </form>
</div>