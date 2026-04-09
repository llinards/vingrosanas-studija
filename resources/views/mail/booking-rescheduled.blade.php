<x-mail::message>
# Rezervācijas laika izmaiņas

Jūsu rezervācijas laiks ir mainīts.

**Iepriekšējā rezervācijas informācija:**

- **Datums:** {{ $originalDate->format('d.m.Y') }}
- **Laiks:** {{ substr($originalTime, 0, 5) }}

**Jaunā rezervācijas informācija:**

- **Pakalpojums:** {{ $booking->schedule->service->name }}
- **Treneris:** {{ $booking->schedule->service->coach->name }}
- **Datums:** {{ $booking->booking_date->format('d.m.Y') }}
- **Laiks:** {{ substr($booking->schedule->start_time, 0, 5) }}

Ja Jums ir jautājumi, lūdzu, sazinieties ar mums.

Paldies,<br>
{{ config('app.name') }}
</x-mail::message>
