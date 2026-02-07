{{-- SLOGAN AND NUMBER --}}
<div class="container mx-auto px-4">
    <div id="counter" class="mb-12 pb-6 md:pb-0 px-4 border-12 border-blue rounded-4xl text-center">
        <flux:heading level="2" class="text-center">{{ __('Kustība sākas tieši šeit!') }}</flux:heading>
        <div class="flex items-center justify-center">
            <flux:link class="flex!" href="https://maps.app.goo.gl/UdGP64Acxz2RVPJe7" target="_blank">
                <flux:icon.map-pin />
                {{ __('Strēlnieku iela 20 A, Sigulda') }}
            </flux:link>
        </div>
        <div
            class="text-center flex flex-col md:flex-row items-center justify-evenly my-6 md:mb-12 space-y-6 md:space-y-0">
            <div class="space-y-3">
                <flux:text id="yearsOfExperience" class="counter-number">6</flux:text>
                <flux:text>{{ __('Gadu pieredze') }}</flux:text>
            </div>
            <div class="space-y-3">
                <flux:text id="totalCalories" class="counter-number" data-suffix="K">120</flux:text>
                <flux:text>{{ __('Patērētās kalorijas') }}</flux:text>
            </div>
            <div class="space-y-3">
                <flux:text id="trainingCoaches" class="counter-number">3</flux:text>
                <flux:text>{{ __('Profesionāli treneri') }}</flux:text>
            </div>
            <div class="space-y-3">
                <flux:text id="totalClients" class="counter-number" data-suffix="+">100</flux:text>
                <flux:text>{{ __('Apmierināti klienti') }}</flux:text>
            </div>
        </div>
    </div>
</div>