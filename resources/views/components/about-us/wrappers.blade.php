<div id="aboutUs" class="mx-auto max-w-7xl px-4 py-12">
    <flux:accordion class="lg:hidden" transition>
        <flux:accordion.item>
            <flux:accordion.heading>
                <flux:heading level="2">{{ __('Par mums') }}</flux:heading>
            </flux:accordion.heading>
            <flux:accordion.content class="space-y-6 pb-6 pt-6">
                <x-about-us.content section="about" />
            </flux:accordion.content>
        </flux:accordion.item>
        <flux:accordion.item>
            <flux:accordion.heading>
                <flux:heading level="2">{{ __('Mērķis') }}</flux:heading>
            </flux:accordion.heading>
            <flux:accordion.content class="space-y-6 pb-6 pt-6">
                <x-about-us.content section="goal" />
            </flux:accordion.content>
        </flux:accordion.item>
        <flux:accordion.item>
            <flux:accordion.heading>
                <flux:heading level="2">{{ __('Vīzija') }}</flux:heading>
            </flux:accordion.heading>
            <flux:accordion.content class="space-y-6 pb-6 pt-6">
                <x-about-us.content section="vision" />
            </flux:accordion.content>
        </flux:accordion.item>
        <flux:accordion.item>
            <flux:accordion.heading>
                <flux:heading level="2">{{ __('Misija') }}</flux:heading>
            </flux:accordion.heading>
            <flux:accordion.content class="space-y-6 pb-6 pt-6">
                <x-about-us.content section="mission" />
            </flux:accordion.content>
        </flux:accordion.item>
    </flux:accordion>

    <div class="hidden lg:grid md:grid-cols-2 md:gap-x-6" x-data="{ active: 'about' }">
        <div class="flex flex-col w-fit space-y-12">
            <button type="button" @mouseenter="active = 'about'" @focus="active = 'about'">
                <flux:heading level="2">{{ __('Par mums') }}</flux:heading>
            </button>

            <button type="button" @mouseenter="active = 'goal'" @focus="active = 'goal'">
                <flux:heading level="2">{{ __('Mērķis') }}</flux:heading>
            </button>

            <button type="button" @mouseenter="active = 'vision'" @focus="active = 'vision'">
                <flux:heading level="2">{{ __('Vīzija') }}</flux:heading>
            </button>

            <button type="button" @mouseenter="active = 'mission'" @focus="active = 'mission'">
                <flux:heading level="2">{{ __('Misija') }}</flux:heading>
            </button>
        </div>

        <div class="pt-6 md:pt-0">
            <template x-if="active === 'about'">
                <div x-data="{ shown: false }" x-init="requestAnimationFrame(() => { shown = true })"
                    :class="shown ? 'opacity-100' : 'opacity-0'" class="transition-opacity duration-300 space-y-6 pb-6">
                    <x-about-us.content section="about" />
                </div>
            </template>

            <template x-if="active === 'goal'">
                <div x-data="{ shown: false }" x-init="requestAnimationFrame(() => { shown = true })"
                    :class="shown ? 'opacity-100' : 'opacity-0'" class="transition-opacity duration-300 space-y-6 pb-6">
                    <x-about-us.content section="goal" />
                </div>
            </template>

            <template x-if="active === 'vision'">
                <div x-data="{ shown: false }" x-init="requestAnimationFrame(() => { shown = true })"
                    :class="shown ? 'opacity-100' : 'opacity-0'" class="transition-opacity duration-300 space-y-6 pb-6">
                    <x-about-us.content section="vision" />
                </div>
            </template>

            <template x-if="active === 'mission'">
                <div x-data="{ shown: false }" x-init="requestAnimationFrame(() => { shown = true })"
                    :class="shown ? 'opacity-100' : 'opacity-0'" class="transition-opacity duration-300 space-y-6 pb-6">
                    <x-about-us.content section="mission" />
                </div>
            </template>
        </div>
    </div>
</div>