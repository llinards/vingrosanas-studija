@props([
'section',
])

@switch($section)
    @case('about')
        <flux:heading level="3">{{ __('Kas ir vingrošanas studija?') }}</flux:heading>
        <flux:text>
            {{ __('“Vingrošanas studija” ir moderna sporta zāle
            studijas formātā, kas radīta dažāda vecuma
            cilvēkiem, kuri vēlas uzlabot veselību, atgūt
            kustību brīvību un stiprināt sportisko formu
            nepiespiestā un atbalstošā vidē.') }}
        </flux:text>

        <flux:heading level="3">{{ __('Ko vingrošanas studija piedāvā') }}</flux:heading>
        <ul class="list-disc pl-4">
            <li>{{ __('Grupas un individuālās nodarbības') }}</li>
            <li>{{ __('Funkcionālo trenažieru zonu') }}</li>
            <li>{{ __('Fizioterapeita un masāžas pakalpojumus') }}</li>
        </ul>

        <flux:text>
            {{ __('Mūsu nodarbības ir piemērotas ikvienam – gan
            iesācējiem vai tiem, kas atsāk pēc pārtraukuma,
            gan tiem, kas vēlas paaugstināt fizisko
            sagatavotību un kustību kvalitāti ikdienā.') }}
        </flux:text>

        <flux:heading level="3">{{ __('Mūsu pieeja') }}</flux:heading>
        <flux:text>
            {{ __('Uzsvaru liekam uz kustību brīvību, stabilitāti,
            mobilitāti un vispārējo labsajūtu. Mēs palīdzam
            stiprināt veselību, samazināt traumu risku un
            radīt vairāk enerģijas ikdienas dzīvei.') }}
        </flux:text>
        @break

    @case('goal')
        <flux:text>
            {{ __('Mūsu mērķis ir palīdzēt cilvēkiem stiprināt veselību, uzlabot kustību kvalitāti un mazināt traumu un slimību
            risku, veicinot aktīvu, apzinātu un sabalansētu dzīvesveidu. Mēs ticam, ka regulāra, pareizi vadīta kustība
            ir viens no nozīmīgākajiem faktoriem ilgtermiņa labsajūtas nodrošināšanā.') }}
        </flux:text>
        <flux:text>
            {{ __('Mēs radām vidi, kurā ikviens – neatkarīgi no vecuma, pieredzes vai fiziskās sagatavotības līmeņa – jūtas
            droši, atbalstīti un motivēti. Mūsu mērķis nav tikai fizisks progress, bet arī pārliecības un pozitīvas
            attieksmes veidošana pret kustību kā ikdienas sastāvdaļu.') }}
        </flux:text>
        @break

    @case('vision')
        <flux:text>
            {{ __('Mūsu vīzija ir kļūt par iedvesmojošu un inovatīvu vingrošanas studiju, kur kustība kļūst par dabisku
            dzīvesveida daļu. Mēs vēlamies būt vieta, kur cilvēki ne tikai uzlabo savu fizisko sagatavotību, bet arī
            attīsta izpratni par ķermeni, tā iespējām un nepieciešamībām.') }}
        </flux:text>
        <flux:text>
            {{ __('Mēs tiecamies radīt vidi, kur profesionāla pieeja apvienojas ar cilvēcīgu attieksmi – vietu, kur klients
            jūtas sadzirdēts, novērtēts un iedrošināts. Ilgtermiņā mūsu mērķis ir veidot kopienu, kur veselība, kustība
            un labsajūta kļūst par vērtību, kas ietekmē dzīves kvalitāti arī ārpus studijas telpām.') }}
        </flux:text>
        @break

    @case('mission')
        <flux:text>
            {{ __('Mūsu misija ir nodrošināt profesionālu, sistemātisku un individuāli pielāgotu pieeju katram klientam. Mēs
            strādājam rūpīgi un pētnieciskā veidā, izvērtējot katra cilvēka fizisko stāvokli, vajadzības un mērķus, lai
            nodrošinātu drošu un efektīvu progresu.') }}
        </flux:text>
        <flux:text>
            {{ __('Mēs palīdzam stiprināt ķermeni, attīstīt stabilitāti, mobilitāti un kustību brīvību, vienlaikus mazinot
            traumu risku un veicinot vispārējo labsajūtu. Mūsu darbā svarīga ir uzticēšanās, sadarbība un ilgtermiņa
            rezultāts – nevis ātrs, bet ilgtspējīgs progress.') }}
        </flux:text>
        @break
@endswitch
