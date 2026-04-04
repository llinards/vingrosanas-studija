<div class="absolute inset-x-0 top-0 z-50" x-data="{ open: false }" x-effect="
    const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
    document.body.style.overflow = open ? 'hidden' : '';
    document.body.style.paddingRight = open ? scrollbarWidth + 'px' : '';
">
    <div class="container mx-auto px-4 pt-6">
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
        </nav>

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
                class="bg-surface w-full md:w-1/2 lg:w-1/3 fixed inset-y-0 right-0 h-screen z-20
                                                            flex flex-col items-center justify-center
                                                            overflow-y-auto overscroll-contain space-y-4 md:space-y-4 lg:space-y-6">

                <div class="absolute right-7 top-12 close-button" x-cloak>
                    <flux:button x-show="open" variant="ghost" @click="open = !open">
                        <flux:icon.x-mark />
                    </flux:button>
                </div>

                <flux:navlist>
                    <flux:navlist>
                        <flux:modal.trigger name="booking-modal">
                            <flux:navlist.item @click="open = false">{{ __('Pieteikties') }}</flux:navlist.item>
                        </flux:modal.trigger>
                    </flux:navlist>
                </flux:navlist>
                <flux:navlist>
                    <flux:navlist.item href="/#aboutUs" @click="open = false">{{ __('Par mums') }}</flux:navlist.item>
                </flux:navlist>
                <flux:navlist>
                    <flux:navlist.item href="/#coaches" @click="open = false">{{ __('Treneri') }}</flux:navlist.item>
                </flux:navlist>
                <flux:navlist>
                    <flux:navlist.item href="/#services" @click="open = false">{{ __('Pakalpojumi') }}
                    </flux:navlist.item>
                </flux:navlist>
                <flux:navlist>
                    <flux:navlist.item href="/#kontakti" @click="open = false">{{ __('Kontakti') }}
                    </flux:navlist.item>
                </flux:navlist>
                <flux:navlist class="nav-icons flex flex-row gap-x-4 absolute bottom-16">
                    <flux:navlist.item href="{{ site('contact', 'facebook_url', 'https://www.facebook.com/vs.sigulda') }}" target="_blank"
                        rel="noopener noreferrer">
                        <flux:icon.facebook />
                    </flux:navlist.item>
                    <flux:navlist.item href="{{ site('contact', 'instagram_url', 'https://www.instagram.com/vingrosanas.studija') }}" target="_blank"
                        rel="noopener noreferrer">
                        <flux:icon.instagram />
                    </flux:navlist.item>
                </flux:navlist>
            </div>
        </nav>
    </div>
</div>