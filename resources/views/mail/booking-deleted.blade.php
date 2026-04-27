<x-mail::message>
# Rezervācija atcelta

Informējam, ka Jūsu rezervācija ir atcelta.

**Rezervācijas informācija:**

- **Pakalpojums:** {{ $booking->schedule->service->name }}
- **Treneris:** {{ $booking->schedule->service->coach->name }}
- **Datums:** {{ $booking->booking_date->format('d.m.Y') }}
- **Laiks:** {{ substr($booking->schedule->start_time, 0, 5) }}

Ja par šo rezervāciju ir veikta apmaksa, lūdzu, sazinieties ar mums, lai vienotos par atmaksu.

Paldies,<br>
{{ config('app.name') }}
</x-mail::message>
