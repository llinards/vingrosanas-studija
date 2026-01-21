<x-layouts.main :title="__('Sākums')">
    {{--
    <flux:sidebar collapsible dir="rtl" class="bg-zinc-900 w-full md:w-lg data-flux-sidebar-collapsed-desktop:hidden">
        <flux:sidebar.toggle icon="x-mark" class="cursor-pointer" />
        <flux:sidebar.nav class="text-center items-center">
            <flux:sidebar.item href="#">Home</flux:sidebar.item>
            <flux:sidebar.item href="#">Inbox</flux:sidebar.item>
            <flux:sidebar.item href="#">Documents</flux:sidebar.item>
            <flux:sidebar.item href="#">Calendar</flux:sidebar.item>
            <flux:sidebar.item href="#">Calendar</flux:sidebar.item>
        </flux:sidebar.nav>
    </flux:sidebar>

    <flux:header class="bg-zinc-900 flex justify-end">
        <flux:sidebar.toggle icon="bars-3" inset="left" class="cursor-pointer" />
    </flux:header> --}}

    {{-- <flux:main> --}}
        {{--
    </flux:main> --}}


    <div class="h-screen relative bg-cover bg-position-[35%_center] md:bg-center"
        style="background-image: linear-gradient(rgba(0, 0, 0, 0.3), rgba(0, 0, 0, 0.3)), url('{{ asset('images/header_image.jpg') }}');">

        <div class="container mx-auto px-4 pt-6 h-full flex flex-col relative">
            <div class="flex justify-center items-center gap-4">
                <div class="w-28 h-28">
                    <img src="{{ asset('images/vingrosanas_studija_logo.svg') }}" alt="">
                </div>
            </div>

            <div class="mt-auto pb-12 md:pb-36 md:max-w-1/5 space-y-4 md:space-y-6 flex flex-col items-start">
                <flux:heading level="1">VINGROŠANAS
                    STUDIJA
                    veselīgam
                    dzīvesveidam</flux:heading>
                <flux:button class="btn-primary">Pieteikties</flux:button>
            </div>
            <div class="absolute right-4 md:right-8 top-1/2 -translate-y-1/2 z-10 flex flex-col space-y-6">
                <a href="tel:+37126620757">
                    <flux:icon.phone />
                </a>
                <a href="mailto:info@vingrosanasstudija.lv">
                    <flux:icon.envelope />
                </a>
                <a href="https://www.instagram.com/vingrosanas.studija" target="_blank" rel="noopener noreferrer">
                    <flux:icon.instagram />
                </a>
            </div>
        </div>
    </div>
</x-layouts.main>