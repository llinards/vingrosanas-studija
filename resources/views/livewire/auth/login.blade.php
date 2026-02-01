<x-layouts.auth :title="__('Pieteikties')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Pieteikties savā kontā')"
                       :description="__('Ievadiet savu e-pastu un paroli, lai pieteiktos')"/>

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')"/>

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('E-pasta adrese')"
                :value="old('email')"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="email@example.com"
            />

            <!-- Password -->
            <div class="relative">
                <flux:input
                    name="password"
                    :label="__('Parole')"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Parole')"
                    viewable
                />
            </div>

            <!-- Remember Me -->
            <flux:checkbox name="remember" :label="__('Atcerēties mani')" :checked="old('remember')"/>

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                    {{ __('Pieteikties') }}
                </flux:button>
            </div>
        </form>

        @if (Route::has('password.request'))
            <div class="space-x-1 text-sm text-center rtl:space-x-reverse text-zinc-600">
                <flux:link :href="route('password.request')" wire:navigate>
                    {{ __('Aizmirsāt paroli?') }}
                </flux:link>
            </div>
        @endif

        @if (Route::has('register'))
            <div class="space-x-1 text-sm text-center rtl:space-x-reverse text-zinc-600">
                <span>{{ __('Nav konta?') }}</span>
                <flux:link :href="route('register')" wire:navigate>{{ __('Reģistrēties') }}</flux:link>
            </div>
        @endif
    </div>
</x-layouts.auth>
