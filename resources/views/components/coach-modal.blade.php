<flux:modal class="bg-beige mx-4 lg:mx-auto p-6 md:p-12" name="{{ $coachModalName }}">
    <div class="flex flex-col md:grid lg:grid-cols-2 gap-x-12">
        <img class="mt-8 md:mt-4 lg:mt-0 max-h-156 lg:max-h-none lg:h-full object-cover w-full pb-3 md:pb-6 lg:pb-0"
            src="{{ $coachModalImg }}" alt="">
        <div class="space-y-6 md:space-y-12 flex flex-col justify-center">
            <div>
                <flux:heading class="pb-0!" level="2">{{ $coachName }}</flux:heading>
                <flux:text>{{ $coachTitle }}</flux:text>
            </div>
            <div>
                <flux:heading class="pb-3 md:pb-6" level="3">{{__('Profesionālais profils') }}</flux:heading>
                <flux:text>{{ $coachProfile }}</flux:text>
            </div>
            <div>
                <flux:heading class="pb-3 md:pb-6" level="3">{{__('Specializācija un pakalpojumi') }}</flux:heading>
                <flux:text>
                    {{ $coachServices }}
                </flux:text>
            </div>
            <div>
                <flux:heading class="pb-3 md:pb-6" level="3">{{__('Vērtības un darba pieeja') }}</flux:heading>
                <flux:text>
                    {{ $coachValues }}
                </flux:text>
            </div>
        </div>
    </div>
</flux:modal>