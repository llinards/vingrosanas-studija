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

    {{-- BANNER ROW WITH BUTTON --}}
    <div class="bg-blue py-8 md:py-6 mb-12 flex justify-center">
        <flux:button class="btn-secondary">Pieteikties</flux:button>
    </div>


    {{-- FORM --}}
    <x-form/>


    {{-- FOOTER --}}
    <x-footer />

</x-layouts.main>