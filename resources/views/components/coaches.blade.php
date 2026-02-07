<div id="coaches" class="container mx-auto ">

    {{-- HIDDEN HEADING TO ENSURE PROPER HTML MARKUP AS THIS SECTION HAS NO HEADING --}}
    <flux:heading class="hidden" level="2">{{ __('Treneri') }}</flux:heading>

    {{-- COACH CARD WRAPPER - DESKTOP --}}
    <div class="hidden py-12 lg:grid grid-cols-3 divide-x">

        {{-- anete-platkevica --}}
        <div class="h-full">
            <x-coach-card>
                <x-slot name="coachModalName">anete-platkevica</x-slot>
                <x-slot name="coachCardImg">{{ asset('images/anete_platkevica_10.jpg') }}</x-slot>
                <x-slot name="coachName">Anete Platkeviča</x-slot>
                <x-slot name="coachTitle">{{__('“Vingrošanas studija” dibinātāja,
                    veselības vingrošanas trerenere') }}</x-slot>
            </x-coach-card>
        </div>

        {{-- aiva-skutele --}}
        <div class="h-full">
            <x-coach-card>
                <x-slot name="coachModalName">aiva-skutele</x-slot>
                <x-slot name="coachCardImg">{{ asset('images/aiva_skutele_1.jpg') }}</x-slot>
                <x-slot name="coachName">Aiva Skutele</x-slot>
                <x-slot name="coachTitle">{{__('Bērnu fizioterapeite') }}</x-slot>
            </x-coach-card>
        </div>

        {{-- elina-nagle --}}
        <div class="h-full">
            <x-coach-card>
                <x-slot name="coachModalName">elina-nagle</x-slot>
                <x-slot name="coachCardImg">{{ asset('images/elina_nagle_2.jpg') }}</x-slot>
                <x-slot name="coachName">Elīna Nagle</x-slot>
                <x-slot name="coachTitle">{{__('Jogas pasniedzēja') }}</x-slot>
            </x-coach-card>
        </div>
    </div>

    {{-- COACH CARD WRAPPER - MOBILE --}}
    <div class="px-4 py-12 lg:hidden">
        <div class="f-carousel " id="coachCarousel">
            <div class="f-carousel__slide ">
                <x-coach-card>
                    <x-slot name="coachModalName">anete-platkevica</x-slot>
                    <x-slot name="coachCardImg">{{ asset('images/anete_platkevica_10.jpg') }}</x-slot>
                    <x-slot name="coachName">Anete Platkeviča</x-slot>
                    <x-slot name="coachTitle">{{__('“Vingrošanas studija” dibinātāja,
                        veselības vingrošanas trerenere') }}</x-slot>
                </x-coach-card>
            </div>
            <div class="f-carousel__slide">
                <x-coach-card>
                    <x-slot name="coachModalName">aiva-skutele</x-slot>
                    <x-slot name="coachCardImg">{{ asset('images/aiva_skutele_1.jpg') }}</x-slot>
                    <x-slot name="coachName">Aiva Skutele</x-slot>
                    <x-slot name="coachTitle">{{__('Bērnu fizioterapeite') }}</x-slot>
                </x-coach-card>
            </div>
            <div class="f-carousel__slide">
                <x-coach-card>
                    <x-slot name="coachModalName">elina-nagle</x-slot>
                    <x-slot name="coachCardImg">{{ asset('images/elina_nagle_2.jpg') }}</x-slot>
                    <x-slot name="coachName">Elīna Nagle</x-slot>
                    <x-slot name="coachTitle">{{__('Jogas pasniedzēja') }}</x-slot>
                </x-coach-card>
            </div>
        </div>
    </div>

    {{-- COACH MODALS --}}

    {{-- anete-platkevica --}}
    <x-coach-modal>
        <x-slot name="coachModalName">anete-platkevica</x-slot>
        <x-slot name="coachModalImg">{{ asset('images/anete_platkevica_13.jpg') }}</x-slot>

        <x-slot name="coachName">Anete Platkeviča</x-slot>
        <x-slot name="coachTitle">{{__('“Vingrošanas studija” dibinātāja,
            veselības vingrošanas trerenere') }}</x-slot>

        <x-slot name="coachProfile">
            {{__('Anete Platkēviča – veselības vingrošanas trenere ar bakalaura grādu veselības
            sportā, specializējusies veselības
            vingrošanā.
            Man ir svarīgi palīdzēt cilvēkiem izprast savu ķermeni – kā tas darbojas, kāpēc konkrēta
            kustība ir nepieciešama un kā
            no tās gūt maksimālu ieguvumu. Ticu, ka tieši izpratne rada motivāciju un palīdz sasniegt
            ilgtermiņa rezultātus.') }}
        </x-slot>

        <x-slot name="coachServices">
            {{__('Vadu veselības vingrošanas nodarbības ar uzsvaru uz kustību precizitāti un
            ķermeņa funkcionalitāti, īpašu uzmanību
            pievēršot:') }}
            <ul class="list-disc pl-4">
                <li>{{__('stājas stiprināšanai;') }}</li>
                <li>{{__('ikdienas kustību kvalitātes uzlabošanai;') }}</li>
                <li>{{__('locītavu mobilitātei un ķermeņa līdzsvaram.') }}</li>
            </ul>
        </x-slot>

        <x-slot name="coachValues">
            {{__('Mans mērķis ir palīdzēt cilvēkiem attīstīt funkcionālu, harmonisku un brīvi
            kustīgu ķermeni.
            Nodarbībās veidoju vidi, kurā dalībnieki jūtas droši, atbalstīti un iedvesmoti izzināt savu
            ķermeni, veicinot gan
            fizisku, gan mentālu labsajūtu.') }}
        </x-slot>
    </x-coach-modal>

    {{-- aiva-skutele --}}
    <x-coach-modal>
        <x-slot name="coachModalName">aiva-skutele</x-slot>
        <x-slot name="coachModalImg">{{ asset('images/aiva_skutele_2.jpg') }}</x-slot>

        <x-slot name="coachName">Aiva Skutele</x-slot>
        <x-slot name="coachTitle">{{__('Bērnu fizioterapeite') }}</x-slot>

        <x-slot name="coachProfile">
            {{__('Aiva Skutele – divu meitu mamma un speciāliste, kura strādā ar bērniem no dzimšanas līdz 18 gadu
            vecumam.
            2018. gadā ieguvu profesionālo bakalaura grādu fizioterapijā un kļuvu par sertificētu fizioterapeiti. Kopš
            tā laika
            regulāri papildinu zināšanas dažādos tālākizglītības kursos.
            Atgriežoties darbā pēc bērnu kopšanas atvaļinājuma, sapratu, ka tieši darbs ar bērniem ir mans patiesais
            aicinājums.') }}
        </x-slot>

        <x-slot name="coachServices">
            <ul class="list-disc pl-4">
                <li>{{__('Fizioterapeita konsultācijas bērniem') }}</li>
                <li>{{__('Ārstnieciskā vingrošana – individuāli un grupās') }}</li>
                <li>{{__('Kinezioloģiskā teipošana') }}</li>
                <li>{{__('Bobata metodes vingrošana zīdaiņiem') }}</li>
                <li>{{__('Hendlinga apmācības jaunajiem vecākiem') }}</li>
            </ul>
        </x-slot>

        <x-slot name="coachValues">
            {{__('Manas galvenās vērtības ir cieņa, attīstība un izaugsme – lai sniegtu bērniem un vecākiem profesionālu
            palīdzību,
            iedrošinājumu un ticību, ka katrs mazais solis ved uz lieliem panākumiem.
            Ar savu darbu vēlos radīt drošu un atbalstošu vidi, kur bērni un viņu vecāki jūtas sadzirdēti un saprasti.
            Man ir
            svarīgi, lai katrs bērns saņemtu individuālu pieeju, kas palīdz viņam justies spēcīgākam un pārliecinātākam
            par sevi.') }}
        </x-slot>
    </x-coach-modal>

    {{-- elina-nagle --}}
    <x-coach-modal>
        <x-slot name="coachModalName">elina-nagle</x-slot>
        <x-slot name="coachModalImg">{{ asset('images/elina_nagle_2.jpg') }}</x-slot>

        <x-slot name="coachName">Elīna Nagle</x-slot>
        <x-slot name="coachTitle">{{__('Jogas pasniedzēja') }}</x-slot>

        <x-slot name="coachProfile">
            {{__('Elīna Nagle jogu apguvusi Indijā, Rišikešā – pasaules jogas galvaspilsētā. Vairāku gadu garumā viņa ir
            praktizējusi un
            pasniegusi jogu Āzijā, pilnveidojot zināšanas un padziļinot izpratni par ķermeni un kustību.
            Elīna ir arī sertificēta Pilates Mat 1 pasniedzēja, kas savās nodarbībās apvieno jogas plūdumu ar ķermeņa
            stabilizējošiem vingrinājumiem.') }}
        </x-slot>

        <x-slot name="coachServices">
            {{__('Elīnai sirdij tuvas ir dinamiskas un spēcīgas jogas prakses, kā arī plūsmas joga, kas palīdz attīstīt
            spēku, elastību un
            iekšēju līdzsvaru.
            Viņa vada nodarbības gan latviešu, gan angļu valodā, pielāgojot praksi dažādu līmeņu dalībniekiem un
            uzsverot kustības
            dabiskumu un prieku.') }}
        </x-slot>

        <x-slot name="coachValues">
            {{__('Jogas pozas nav mērķis – mērķis ir sajūta pēc nodarbības.
            Vadot savas prakses, viņa iedrošina kustēties nepiespiesti, ieklausīties sevī un izbaudīt procesu. Elīnu
            raksturo
            dzīvesprieks un aktivitāte – viņa mīl skriet, slēpot un ceļot kopā ar ģimeni, iedvesmojot arī citus dzīvot
            kustībā un
            harmonijā.') }}
        </x-slot>
    </x-coach-modal>
</div>