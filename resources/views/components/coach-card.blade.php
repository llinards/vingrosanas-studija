<flux:modal.trigger name="{{ $coachModalName }}">
    <div class="group w-full flex flex-col">
        <img class="shrink-0 object-cover w-full h-104 sm:h-120 md:h-136 lg:h-196 xl:h-224 lg:grayscale group-hover:grayscale-0 transition-all duration-300 cursor-pointer"
            src="{{ $coachCardImg }}" alt="">
        <div class="shrink-0 px-4 pt-6 lg:px-8 xl:px-12 flex justify-between items-start gap-4">
            <div class="min-w-0">
                <flux:heading level="3">{{ $coachName }}</flux:heading>
                <flux:text>{{ $coachTitle }}</flux:text>
            </div>
            <flux:icon.arrow-right />
        </div>
    </div>
</flux:modal.trigger>