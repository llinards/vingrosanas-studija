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
    <div></div>

    {{-- FOOTER --}}
    <x-footer />

</x-layouts.main>