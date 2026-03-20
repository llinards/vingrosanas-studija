<x-layouts.main :title="__('Rezervācija veiksmīga')">
    <div class="container mx-auto pt-36 pb-12 px-4 space-y-6">
        @if($booking->payment_status->value === 'paid')
            <div class="md:max-w-1/3 mx-auto">
                <div class="py-8 px-6 md:px-8 border-8 md:border-12 border-blue rounded-4xl space-y-4">
                    <flux:icon.check class="size-12 text-blue mx-auto mb-4"/>
                    <flux:heading level="3"
                                  class="text-center">{{ __('Rezervācija veiksmīga!') }}</flux:heading>
                    <flux:text class="text-center pb-4">
                        {{ __('Paldies par Jūsu rezervāciju! Apstiprinājums tiks nosūtīts uz Jūsu e-pastu.') }}
                    </flux:text>

                    <div class="space-y-4">
                        <div class="flex justify-between items-center py-2 border-b border-gray-200">
                            <flux:text>{{ __('Treniņš') }}</flux:text>
                            <flux:text>{{ $booking->schedule->service->name }}</flux:text>
                        </div>

                        <div class="flex justify-between items-center py-2 border-b border-gray-200">
                            <flux:text>{{ __('Treneris') }}</flux:text>
                            <flux:text>{{ $booking->schedule->service->coach->name }}</flux:text>
                        </div>

                        <div class="flex justify-between items-center py-2 border-b border-gray-200">
                            <flux:text>{{ __('Datums') }}</flux:text>
                            <flux:text>{{ $booking->booking_date->format('d.m.Y') }}</flux:text>
                        </div>

                        <div class="flex justify-between items-center py-2 border-b border-gray-200">
                            <flux:text>{{ __('Laiks') }}</flux:text>
                            <flux:text
                            >{{ substr($booking->schedule->start_time, 0, 5) }}</flux:text>
                        </div>
                    </div>
                </div>

                <div class="mt-8 text-center">
                    <flux:button href="{{ route('home') }}" class="btn btn-lg btn-primary">
                        {{ __('Atgriezties uz sākumu') }}
                    </flux:button>
                </div>

                <livewire:booking.booking-cancel :booking="$booking" />
            </div>
        @elseif($booking->payment_status->value === 'failed')
            <div class="md:max-w-1/3 mx-auto">
                <div class="py-8 px-6 md:px-8 border-8 md:border-12 border-blue rounded-4xl space-y-4">
                    <flux:icon.exclamation-triangle class="size-12 text-blue mx-auto mb-4"/>
                    <flux:heading level="3" class="text-center">{{ __('Ir notikusi kļūda!') }}</flux:heading>
                    <flux:text class="text-center mt-2">
                        {{ __('Lūdzu, sazinies ar mums.') }}
                    </flux:text>
                </div>

                <div class="mt-8 text-center">
                    <flux:button href="{{ route('home') }}" class="btn btn-lg btn-primary">
                        {{ __('Atgriezties uz sākumu') }}
                    </flux:button>
                </div>
            </div>
        @elseif($booking->payment_status->value === 'refunded')
            <div class="md:max-w-1/3 mx-auto">
                <div class="py-8 px-6 md:px-8 border-8 md:border-12 border-blue rounded-4xl space-y-4">
                    <flux:icon.receipt-refund class="size-12 text-blue mx-auto mb-4"/>
                    <flux:heading level="3"
                                  class="text-center">{{ __('Rezervācija ir atcelta un tās apmaksa tiks atgriezta!') }}</flux:heading>
                </div>

                <div class="mt-8 text-center">
                    <flux:button href="{{ route('home') }}" class="btn btn-lg btn-primary">
                        {{ __('Atgriezties uz sākumu') }}
                    </flux:button>
                </div>
            </div>
        @else
            <div class="md:max-w-1/3 mx-auto">
                <div class="py-8 px-6 md:px-8 border-8 md:border-12 border-blue rounded-4xl space-y-4">
                    <flux:icon.clock class="size-12 text-blue mx-auto mb-4"/>
                    <flux:heading level="3" class="text-center">{{ __('Maksājums tiek apstrādāts') }}</flux:heading>
                    <flux:text class="text-center mt-2">
                        {{ __('Lūdzu, uzgaidiet apstiprinājuma e-pastu.') }}
                    </flux:text>
                </div>

                <div class="mt-8 text-center">
                    <flux:button href="{{ route('home') }}" class="btn btn-lg btn-primary">
                        {{ __('Atgriezties uz sākumu') }}
                    </flux:button>
                </div>
            </div>
        @endif
    </div>
</x-layouts.main>
