<x-layouts.main :title="__('Abonements veiksmīgs')">
    <div class="container mx-auto pt-36 pb-12 px-4 space-y-6">
        @if($membership->payment_status->value === 'paid')
            <div class="md:max-w-1/3 mx-auto">
                <div class="py-8 px-6 md:px-8 border-8 md:border-12 border-blue rounded-4xl space-y-4">
                    <flux:icon.check class="size-12 text-blue mx-auto mb-4"/>
                    <flux:heading level="3"
                                  class="text-center">{{ __('Abonements aktivizēts!') }}</flux:heading>
                    <flux:text class="text-center pb-4">
                        {{ __('Paldies par abonementa iegādi! Apstiprinājums tiks nosūtīts uz Jūsu e-pastu.') }}
                    </flux:text>

                    <div class="space-y-4">
                        <div class="flex justify-between items-center py-2 border-b border-gray-200">
                            <flux:text>{{ __('Abonements') }}</flux:text>
                            <flux:text>{{ $membership->tierLabel() }}</flux:text>
                        </div>

                        <div class="flex justify-between items-center py-2 border-b border-gray-200">
                            <flux:text>{{ __('Periods') }}</flux:text>
                            <flux:text>{{ $membership->period_start->format('d.m.Y') }}
                                — {{ $membership->period_end->format('d.m.Y') }}</flux:text>
                        </div>

                        <div class="flex justify-between items-center py-2 border-b border-gray-200">
                            <flux:text>{{ __('Nodarbības') }}</flux:text>
                            <flux:text>{{ $membership->bookings->count() }}
                                /{{ $membership->sessions_total }}</flux:text>
                        </div>
                    </div>
                </div>

                <div class="mt-8 text-center space-y-4">
                    <flux:button class="button large primary"
                                 href="{{ route('membership.manage', ['membership' => $membership, 'session_id' => $membership->stripe_checkout_session_id]) }}">
                        {{ __('Pārvaldīt abonementu') }}
                    </flux:button>
                    <div>
                        <flux:link href="{{ route('home') }}"
                                   icon="arrow-uturn-left">
                            {{ __('Atgriezties uz sākumu') }}
                        </flux:link>
                    </div>
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
                    <flux:button href="{{ route('home') }}" class="button large primary">
                        {{ __('Atgriezties uz sākumu') }}
                    </flux:button>
                </div>
            </div>
        @endif
    </div>
</x-layouts.main>
