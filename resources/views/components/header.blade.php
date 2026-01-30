<header class="h-screen relative bg-cover bg-position-[35%_center] md:bg-center"
    style="background-image: linear-gradient(rgba(0, 0, 0, 0.3), rgba(0, 0, 0, 0.3)), url('{{ asset('images/header_image.jpg') }}');"
    x-data="{ open: false }"
    x-effect="document.body.style.overflow = open ? 'hidden' : ''">

    <div class="container mx-auto px-4 pt-6 h-full flex flex-col relative">

        {{-- LOGO --}}
        <nav class="relative flex justify-center items-center gap-4">
            <div class="w-28">
                <a href="{{ route('home') }}">
                    <x-app-logo-icon />
                </a>
            </div>

            {{-- NAV OPEN/CLOSE CONTROL --}}
            <div class="absolute right-0 top-1/2 -translate-y-1/2" x-cloak>
                <flux:button variant="ghost" @click="open = !open">
                    <flux:icon.bars-3 />
                </flux:button>
            </div>
            <div class="absolute right-0 top-1/2 -translate-y-1/2 z-30" x-cloak>
                <flux:button x-show="open" variant="ghost" @click="open = !open">
                    <flux:icon.x-mark />
                </flux:button>
            </div>
        </nav>

        {{-- HEADING AND CTA --}}
        <div class="mt-auto pb-12 md:pb-36 md:max-w-1/5 space-y-4 md:space-y-6 flex flex-col items-start">
            <flux:heading level="1">{{ __('VINGROŠANAS STUDIJA veselīgam dzīvesveidam') }}</flux:heading>
            <flux:button class="button primary">{{ __('Pieteikties') }}</flux:button>
        </div>

        {{-- CONTACT/SOCIAL MEDIA ICONS --}}
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
            <a href="https://www.instagram.com/vingrosanas.studija" target="_blank" rel="noopener noreferrer">
                <flux:icon.facebook />
            </a>
        </div>
    </div>

    {{-- NAV LINKS --}}
    <nav>
        <div x-cloak x-show="open" x-transition:enter="transition-opacity duration-300 ease-out"
                x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                x-transition:leave="transition-opacity duration-300 ease-in" x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0" @click="open = false" class="fixed inset-0 bg-black/50 z-10">
            </div>
        <div x-cloak x-show="open" x-transition:enter="transition-all duration-300 ease-out"
            x-transition:enter-start="translate-x-full opacity-0" x-transition:enter-end="translate-x-0 opacity-100"
            x-transition:leave="transition-all duration-300 ease-in"
            x-transition:leave-start="translate-x-0 opacity-100" x-transition:leave-end="translate-x-full opacity-0"
            class="bg-beige w-full md:w-1/2 lg:w-1/3 fixed inset-y-0 right-0 h-screen z-20
                                        flex flex-col items-center justify-center
                                        overflow-y-auto overscroll-contain space-y-4 md:space-y-4 lg:space-y-6">

            <flux:navlist>
                <flux:navlist.item href="#apply">{{ __('Pieteikties') }}</flux:navlist.item>
            </flux:navlist>
            <flux:navlist>
                <flux:navlist.item href="#about-us">{{ __('Par mums') }}</flux:navlist.item>
            </flux:navlist>
            <flux:navlist>
                <flux:navlist.item href="#coaches">{{ __('Treneri') }}</flux:navlist.item>
            </flux:navlist>
            <flux:navlist>
                <flux:navlist.item href="#services">{{ __('Pakalpojumi') }}</flux:navlist.item>
            </flux:navlist>
            <flux:navlist>
                <flux:navlist.item href="#contacts">{{ __('Kontakti') }}</flux:navlist.item>
            </flux:navlist>
            <flux:navlist>
                <flux:navlist.item href="#services">{{ __('Pakalpojumi') }}</flux:navlist.item>
            </flux:navlist>
            <flux:navlist class="nav-icons flex flex-row gap-x-4 absolute bottom-16">
                <flux:navlist.item href="https://www.instagram.com/vingrosanas.studija" target="_blank"
                    rel="noopener noreferrer">
                    <flux:icon.facebook />
                </flux:navlist.item>
                <flux:navlist.item href="https://www.instagram.com/vingrosanas.studija" target="_blank"
                    rel="noopener noreferrer">
                    <flux:icon.instagram />
                </flux:navlist.item>
            </flux:navlist>
        </div>
    </nav>
</header>