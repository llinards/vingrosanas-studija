<div id="newPremises" class="py-12 grid lg:grid-cols-2 gap-x-6 lg:relative">
    <div class="flex lg:justify-end">
        <div class="lg:h-156 lg:max-w-157 flex flex-col justify-center pb-12 lg:pb-0 px-4">
            <flux:heading level="2">{{ site('new_premises', 'heading', __('Jaunas telpas, jaunas iespējas')) }}</flux:heading>
            <flux:text class="pb-6">{{ site('new_premises', 'description', '') }}</flux:text>
            <ul class="list-disc pl-4">
                @foreach (json_decode(site('new_premises', 'bullet_points', '[]'), true) ?: [] as $point)
                    <li>{{ $point }}</li>
                @endforeach
            </ul>
        </div>
    </div>

    <div class="lg:absolute lg:w-3/7 md:inset-y-0 md:right-0 mt-12">
        <div class="f-carousel" id="ownerCarousel">
            @foreach (json_decode(site('new_premises', 'images', '[]'), true) ?: [] as $image)
                <div class="f-carousel__slide">
                    <img src="{{ asset($image) }}" alt="">
                </div>
            @endforeach
        </div>
    </div>
</div>