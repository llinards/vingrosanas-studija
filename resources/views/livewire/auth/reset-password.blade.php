<x-layouts.auth>
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Atiestatīt paroli')" :description="__('Lūdzu, ievadiet savu jauno paroli')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-6">
            @csrf
            <!-- Token -->
            <input type="hidden" name="token" value="{{ request()->route('token') }}">

            <!-- Email Address -->
            <flux:input
                name="email"
                value="{{ request('email') }}"
                :label="__('E-pasts')"
                type="email"
                required
                autocomplete="email"
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
                <flux:button type="submit" variant="primary" class="w-full" data-test="reset-password-button">
                    {{ __('Atiestatīt paroli') }}
                </flux:button>
            </div>
        </form>
    </div>
</x-layouts.auth>
