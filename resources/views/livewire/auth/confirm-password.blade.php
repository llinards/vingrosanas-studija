<x-layouts.auth>
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="__('Apstiprināt paroli')"
            :description="__('Šī ir drošā lietotnes sadaļa. Lūdzu, apstipriniet savu paroli, lai turpinātu.')"
        />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.confirm.store') }}" class="flex flex-col gap-6">
            @csrf

            <flux:input
                name="password"
                :label="__('Parole')"
                type="password"
                required
                autocomplete="current-password"
                :placeholder="__('Parole')"
                viewable
            />

            <flux:button variant="primary" type="submit" class="w-full" data-test="confirm-password-button">
                {{ __('Apstiprināt') }}
            </flux:button>
        </form>
    </div>
</x-layouts.auth>
