<x-layouts.auth>
    <div class="mt-4 flex flex-col gap-6">
        <flux:text class="text-center">
            {{ __('Lūdzu, verificējiet savu e-pasta adresi, noklikšķinot uz saites, ko tikko nosūtījām uz jūsu e-pastu.') }}
        </flux:text>

        @if (session('status') == 'verification-link-sent')
            <flux:text class="text-center font-medium !text-green-600">
                {{ __('Jauna verificēšanas saite ir nosūtīta uz e-pasta adresi, ko norādījāt reģistrējoties.') }}
            </flux:text>
        @endif

        <div class="flex flex-col items-center justify-between space-y-3">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <flux:button type="submit" variant="primary" class="w-full">
                    {{ __('Atkārtoti nosūtīt verificēšanas e-pastu') }}
                </flux:button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
               <flux:button variant="ghost" type="submit" class="text-sm cursor-pointer" data-test="logout-button">
                    {{ __('Iziet') }}
                </flux:button>
            </form>
        </div>
    </div>
</x-layouts.auth>
