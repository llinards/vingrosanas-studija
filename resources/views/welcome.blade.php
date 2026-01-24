<x-layouts.main :title="__('Sākums')">

    {{-- NAVIGATION --}}
    <x-nav />

    {{-- HEADER --}}
    <x-header />

    {{-- ACCORDION WRAPPER --}}

    <div class="mx-auto max-w-7xl px-4 pb-12">
        <flux:heading level="2" class="py-12">Pakalpojumi un cenas</flux:heading>
        <flux:accordion transition>
            <x-services-price-table />
        </flux:accordion>
    </div>


    {{-- FORM --}}
    <div class="pb-12 mx-auto max-w-7xl px-4 grid lg:grid-cols-2 gap-y-12">
        <div class="flex flex-col text-center lg:text-start space-y-6 lg:space-y-9">
            <ul class="md:text-xl lg:text-3xl">
                <li class="list-heading">Tālrunis</li>
                <li>
                    <flux:link href="tel:+37126620757">+371 26620757</flux:link>
                </li>
            </ul>
            <ul class="md:text-xl lg:text-3xl">
                <li class="list-heading">E-pasts</li>
                <li>
                    <flux:link href="mailto:vingrosanas@studija.lv">vingrosanas@studija.lv</flux:link>
                </li>
            </ul>
            <ul class="md:text-xl lg:text-3xl">
                <li class="list-heading">Adrese</li>
                <li>
                    <flux:link href="https://maps.app.goo.gl/UdGP64Acxz2RVPJe7" target="_blank">Strēlnieku iela 20
                        A, Sigulda</flux:link>
                </li>
            </ul>
            <ul class="md:text-xl lg:text-3xl">
                <li class="list-heading">Pieseko</li>
                <li class="mt-1 lg:mt-2 flex justify-center lg:justify-start">
                    <flux:link href="https://www.instagram.com/vingrosanas.studija" target="_blank"
                        rel="noopener noreferrer">
                        <flux:icon.instagram />
                    </flux:link>
                </li>
            </ul>
        </div>
        <form action="#" method="POST" class="flex flex-col items-center justify-center w-full">
            <div class="w-full border-gray border bg-beige rounded-md shadow-2xl p-6 md:p-8 lg:p-12 space-y-6 mb-6 md:mb-12">
                <flux:input type="test" label="Vārds*" required />
                <flux:input type="test" label="Uzvārds*" required />
                <flux:input type="email" label="E-pasts*" required />
                <flux:textarea rows="4" label="Jautājums*" resize="none" required />
            </div>
            <flux:button type="submit" class="btn-primary self-center">Nosūtīt</flux:button>
        </form>
    </div>


    {{-- FOOTER --}}
    <x-footer />

</x-layouts.main>