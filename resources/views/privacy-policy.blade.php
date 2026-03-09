<x-layouts.main :title="__('Privātuma politika')">
    <div class="container mx-auto pt-36 pb-12 px-4 space-y-6">
        <flux:heading level="1">{{ __('Privātuma politika') }}</flux:heading>
        <flux:heading level="3">@lang('1. Personas datu apstrāde')</flux:heading>
        <flux:text>
            @lang('Mēs apkopojam un apstrādājam jūsu personas datus tikai tad, ja tas ir nepieciešams, lai nodrošinātu mūsu mājaslapas darbību, uzlabotu lietotāja pieredzi un sniegtu jums mūsu pakalpojumus. Mēs veicam šo datu apstrādi, pamatojoties uz likumīgām interesēm, līguma izpildi, tiesisko pienākumu izpildi vai jūsu piekrišanu.')
        </flux:text>

        <flux:heading level="3">@lang('2. Sīkdatnes un to izmantošana')</flux:heading>
        <flux:text>
            @lang('Mūsu mājaslapa izmanto sīkdatnes (cookies), lai nodrošinātu labāku lietotāja pieredzi, analizētu vietnes apmeklējumu un veiktu uzlabojumus. Sīkdatnes ir nelielas teksta datnes, kas tiek saglabātas jūsu ierīcē.')
        </flux:text>

        <flux:heading level="3">@lang('2.1. Izmantotās sīkdatnes')</flux:heading>
        <flux:table class="w-full">
            <flux:table.columns>
                <flux:table.column>
                    <flux:heading level="4">@lang('Sīkdatne')</flux:heading>
                </flux:table.column>
                <flux:table.column>
                    <flux:heading level="4">@lang('Mērķis')</flux:heading>
                </flux:table.column>
                <flux:table.column align="end">
                    <flux:heading level="4">@lang('Derīguma termiņš')</flux:heading>
                </flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach (Whitecube\LaravelCookieConsent\Facades\Cookies::getCategories() as $category)
                    @foreach ($category->getCookies() as $cookie)
                        <flux:table.row>
                            <flux:table.cell class="whitespace-normal">
                                <flux:text>{{ $cookie->name }}</flux:text>
                            </flux:table.cell>
                            <flux:table.cell class="whitespace-normal">
                                @if($cookie->description)
                                    <flux:text>{{ $cookie->description }}</flux:text>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:text>
                                    {{ \Carbon\CarbonInterval::minutes($cookie->duration)->cascade() }}
                                </flux:text>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                @endforeach
            </flux:table.rows>
        </flux:table>

        <flux:heading level="3">@lang('3. Trešo pušu sīkdatnes')</flux:heading>
        <flux:text>
            @lang('Papildus mūsu izmantotajām sīkdatnēm mūsu mājaslapa var saturēt arī trešo pušu sīkdatnes, piemēram, analītikas nolūkos vai sociālo tīklu integrācijai. Šādas sīkdatnes var tikt iestatītas, piemēram, Google Analytics vai Facebook.')
        </flux:text>

        <flux:heading level="3">@lang('4. Kā kontrolēt un dzēst sīkdatnes?')</flux:heading>
        <flux:text>
            @lang('Jūs varat mainīt savus sīkdatņu iestatījumus, izmantojot pārlūka iestatījumus vai mūsu sīkdatņu pārvaldības paneli. Tomēr, ja jūs izslēdzat noteiktas sīkdatnes, dažas funkcijas mūsu mājaslapā var nedarboties pareizi.')
        </flux:text>

        <flux:heading level="3">@lang('5. Jūsu tiesības saistībā ar personas datiem')</flux:heading>
        <flux:text>@lang('Jums ir tiesības:')</flux:text>
        <ul class="list-disc pl-5">
            <li>@lang('Piekļūt saviem datiem un saņemt informāciju par to apstrādi')</li>
            <li>@lang('Pieprasīt labot neprecīzus vai nepilnīgus datus')</li>
            <li>@lang('Pieprasīt dzēst savus personas datus, ja tie vairs nav nepieciešami')</li>
            <li>@lang('Ierobežot savu datu apstrādi noteiktos gadījumos')</li>
            <li>@lang('Saņemt savus personas datus strukturētā formātā un nodot tos citam pakalpojumu sniedzējam')</li>
            <li>@lang('Iebilst pret datu apstrādi, ja tā tiek veikta uz mūsu leģitīmajām interesēm')</li>
        </ul>

        <flux:heading level="3">@lang('6. Politikas izmaiņas')</flux:heading>
        <flux:text>
            @lang('Šī privātuma politika var tikt mainīta bez iepriekšēja brīdinājuma. Jaunākā privātuma politikas versija, kas ir publicēta vietnē, aizstāj visas iepriekšējās privātuma politikas versijas.')
        </flux:text>
    </div>
</x-layouts.main>
