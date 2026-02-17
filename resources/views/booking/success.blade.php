<x-layouts.main :title="__('Rezervācija veiksmīga')">

    <x-header>
        <x-nav />
    </x-header>

    <div class="min-h-[60vh] flex items-center justify-center px-4 py-16">
        <div class="max-w-md w-full text-center space-y-6">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-green-100">
                <flux:icon.check-circle class="size-10 text-green-600" />
            </div>

            <flux:heading size="xl">{{ __('Rezervācija veiksmīga!') }}</flux:heading>

            <flux:text class="text-zinc-600">
                {{ __('Paldies par Jūsu rezervāciju. Apstiprinājums tiks nosūtīts uz Jūsu e-pastu.') }}
            </flux:text>

            @if($booking->payment_status->value === 'paid')
                <div class="rounded-lg border border-zinc-200 p-6 text-left space-y-3">
                    <div class="flex justify-between">
                        <flux:text class="text-zinc-500">{{ __('Treniņš') }}</flux:text>
                        <flux:text class="font-medium">{{ $booking->schedule->service->name }}</flux:text>
                    </div>
                    <flux:separator />
                    <div class="flex justify-between">
                        <flux:text class="text-zinc-500">{{ __('Datums') }}</flux:text>
                        <flux:text class="font-medium">{{ $booking->booking_date->format('d.m.Y') }}</flux:text>
                    </div>
                    <flux:separator />
                    <div class="flex justify-between">
                        <flux:text class="text-zinc-500">{{ __('Laiks') }}</flux:text>
                        <flux:text class="font-medium">{{ substr($booking->schedule->start_time, 0, 5) }}</flux:text>
                    </div>
                    <flux:separator />
                    <div class="flex justify-between">
                        <flux:text class="text-zinc-500">{{ __('Dalībnieki') }}</flux:text>
                        <flux:text class="font-medium">{{ $booking->participant_count }} {{ $booking->participant_count === 1 ? __('persona') : __('personas') }}</flux:text>
                    </div>
                </div>
            @else
                <div class="rounded-lg border border-yellow-200 bg-yellow-50 p-4">
                    <flux:text class="text-yellow-800">
                        {{ __('Jūsu maksājums tiek apstrādāts. Lūdzu, uzgaidiet apstiprinājuma e-pastu.') }}
                    </flux:text>
                </div>
            @endif

            <div class="pt-4">
                <flux:button href="{{ route('home') }}" variant="primary">
                    {{ __('Atgriezties uz sākumu') }}
                </flux:button>
            </div>
        </div>
    </div>

    <x-footer />

</x-layouts.main>
