<x-mail::message>
# Rezervācija netika apstiprināta

Jūsu maksājums tika saņemts pēc tam, kad rezervācijas apmaksas termiņš bija beidzies, tāpēc vieta nodarbībā Jums netika rezervēta.

**Atmaksājamā summa:** {{ number_format($amountInCents / 100, 2) }} EUR

Maksājums tiek atgriezts pilnā apmērā un tiks ieskaitīts Jūsu kontā dažu darba dienu laikā.

Lai pieteiktos nodarbībai, lūdzu, izvēlieties laiku nodarbību sarakstā vēlreiz.

<x-mail::button :url="route('timetable')">
Nodarbību saraksts
</x-mail::button>

Atvainojamies par sagādātajām neērtībām.

Paldies,<br>
{{ config('app.name') }}
</x-mail::message>
