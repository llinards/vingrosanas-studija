<x-layouts.main :title="__('Rezervācija veiksmīga')">

    <div class="bg-blue">
        <div class="container mx-auto px-4 text-center text-white space-y-4">
            <flux:heading level="1" class="text-white!">{{ __('Rezervācija veiksmīga!') }}</flux:heading>
{{--            <flux:text class="text-white/80 text-lg">--}}
{{--                {{ __('Paldies par Jūsu rezervāciju. Apstiprinājums tiks nosūtīts uz Jūsu e-pastu.') }}--}}
{{--            </flux:text>--}}
        </div>
    </div>

    {{-- BOOKING DETAILS --}}
    <div class="container mx-auto px-4 py-12 md:py-16">
        @if($booking->payment_status->value === 'paid')
            <div class="max-w-lg mx-auto">
                <div class="py-8 px-6 md:px-8 border-8 md:border-12 border-blue rounded-4xl space-y-4">
                    <flux:heading level="2" class="text-center mb-6">{{ __('Rezervācijas informācija') }}</flux:heading>

                    <div class="space-y-4">
                        <div class="flex justify-between items-center py-2 border-b border-gray-200">
                            <flux:text class="text-gray-500">{{ __('Treniņš') }}</flux:text>
                            <flux:text class="font-medium">{{ $booking->schedule->service->name }}</flux:text>
                        </div>

                        <div class="flex justify-between items-center py-2 border-b border-gray-200">
                            <flux:text class="text-gray-500">{{ __('Treneris') }}</flux:text>
                            <flux:text class="font-medium">{{ $booking->schedule->service->coach->name }}</flux:text>
                        </div>

                        <div class="flex justify-between items-center py-2 border-b border-gray-200">
                            <flux:text class="text-gray-500">{{ __('Datums') }}</flux:text>
                            <flux:text class="font-medium">{{ $booking->booking_date->format('d.m.Y') }}</flux:text>
                        </div>

                        <div class="flex justify-between items-center py-2 border-b border-gray-200">
                            <flux:text class="text-gray-500">{{ __('Laiks') }}</flux:text>
                            <flux:text class="font-medium">{{ substr($booking->schedule->start_time, 0, 5) }}</flux:text>
                        </div>

                        <div class="flex justify-between items-center py-2">
                            <flux:text class="text-gray-500">{{ __('Dalībnieki') }}</flux:text>
                            <flux:text class="font-medium">{{ $booking->participant_count }} {{ $booking->participant_count === 1 ? __('persona') : __('personas') }}</flux:text>
                        </div>
                    </div>
                </div>

                <div class="mt-8 text-center">
                    <flux:button href="{{ route('home') }}" class="button large primary">
                        {{ __('Atgriezties uz sākumu') }}
                    </flux:button>
                </div>
            </div>
        @else
            <div class="max-w-lg mx-auto text-center">
                <div class="py-8 px-6 md:px-8 bg-yellow-50 border-2 border-yellow-200 rounded-2xl">
                    <flux:icon.clock class="size-12 text-yellow-600 mx-auto mb-4" />
                    <flux:heading level="3">{{ __('Maksājums tiek apstrādāts') }}</flux:heading>
                    <flux:text class="text-yellow-800 mt-2">
                        {{ __('Lūdzu, uzgaidiet apstiprinājuma e-pastu.') }}
                    </flux:text>
                </div>

                <div class="mt-8">
                    <flux:button href="{{ route('home') }}" class="button large primary">
                        {{ __('Atgriezties uz sākumu') }}
                    </flux:button>
                </div>
            </div>
        @endif
    </div>
</x-layouts.main>
