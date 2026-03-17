{{-- SLOGAN AND NUMBER --}}
<div id="slogan" class="container mx-auto px-4 py-12">
    <div class="py-12 px-4 border-8 md:border-12 border-blue rounded-4xl text-center">
        <flux:heading level="2" class="px-8 md:px-0 text-center">{{ __('Kustība sākas tieši šeit!') }}</flux:heading>
        <div class="flex items-center justify-center">
            <flux:link class="flex!" href="{{ site('contact', 'google_maps_url', 'https://maps.app.goo.gl/UdGP64Acxz2RVPJe7') }}" target="_blank">
                <flux:icon.map-pin />
                {{ site('contact', 'address', __('Strēlnieku iela 20 A, Sigulda')) }}
            </flux:link>
        </div>
    </div>
</div>