<x-layouts.main :title="__('Sākums')">
    <div class="h-screen bg-cover bg-position-[35%_center] md:bg-center"
        style="background-image: linear-gradient(rgba(0, 0, 0, 0.3), rgba(0, 0, 0, 0.3)), url('{{ asset('images/header_image.jpg') }}');">

        <div class="container mx-auto px-4 pt-6 h-full flex flex-col">
            <div class="flex justify-center">
                <div class="w-28 h-28">
                    <img src="{{ asset('images/vingrosanas_studija_logo.svg') }}" alt="">
                </div>
            </div>

            <div class="mt-auto pb-12 md:pb-36 md:max-w-1/5 space-y-4 md:space-y-6 flex flex-col items-start">
                <flux:heading level="1">VINGROŠANAS
                    STUDIJA
                    veselīgam
                    dzīvesveidam</flux:heading>
                <flux:button class="btn-primary">Pieteikties</flux:button>
            </div>
        </div>
    </div>
</x-layouts.main>