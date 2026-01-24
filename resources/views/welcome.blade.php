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

    <div class="mx-auto max-w-7xl px-4 pb-12 grid lg:grid-cols-2">
        <div class="container mx-auto justify-center space-y-6">
            <flux:heading level="2">Jaunas telpas, jaunas iespējas</flux:heading>
            <flux:text>Mūsu vingrošanas studija tagad ir plašāka un jaudīgāka nekā jebkad - vieta, kur kustība kļūst par
                labsajūtu.</flux:text>
            <ul class="list-disc pl-4">
                <li>Personalizēta treniņu programma vai treneru konsultācijas</li>
                <li>Grupas nodarbības (joga, pilates, body balance, HIIT, u.c.)</li>
                <li>Privātās treniņu sesijas</li>
                <li>Vingrošana nelielās grupās</li>
                <li>Pārģērbšanās un dušas telpas ar skapīšiem</li>
                <li>Dzeramais ūdens</li>
            </ul>
        </div>
        <div></div>
    </div>

    {{-- BANNER ROW WITH BUTTON --}}
    <div class="bg-blue py-8 md:py-6 mb-12 flex justify-center">
        <flux:button class="secondary">Pieteikties</flux:button>
    </div>


    {{-- FORM --}}
    <x-form />


    {{-- FOOTER --}}
    <x-footer />

</x-layouts.main>