<x-layouts.auth>
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Izveidot kontu')" :description="__('Ievadiet savus datus, lai izveidotu kontu')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Name -->
            <flux:input
                name="name"
                :label="__('Vārds')"
                :value="old('name')"
                type="text"
                required
                autofocus
                autocomplete="name"
                :placeholder="__('Pilns vārds')"
            />

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('E-pasta adrese')"
                :value="old('email')"
                type="email"
                required
                autocomplete="email"
                placeholder="email@example.com"
            />

            <!-- Password -->
            <flux:input
                name="password"
                :label="__('Parole')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Parole')"
                viewable
            />

            <!-- Confirm Password -->
            <flux:input
                name="password_confirmation"
                :label="__('Apstipriniet paroli')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Apstipriniet paroli')"
                viewable
            />

            <div class="flex items-center justify-end">
                <flux:button type="submit" variant="primary" class="w-full">
                    {{ __('Izveidot kontu') }}
                </flux:button>
            </div>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600">
            <span>{{ __('Jau ir konts?') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('Pieteikties') }}</flux:link>
        </div>
    </div>
</x-layouts.auth>
